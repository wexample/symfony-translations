<?php

namespace Wexample\SymfonyTranslations\Translation;

use Exception;
use Psr\Cache\InvalidArgumentException;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Component\Finder\SplFileInfo;
use Symfony\Component\HttpKernel\KernelInterface;
use Symfony\Component\Translation\MessageCatalogueInterface;
use Symfony\Component\Translation\TranslatorBagInterface;
use Symfony\Contracts\Translation\LocaleAwareInterface;
use Symfony\Contracts\Translation\TranslatorInterface;
use Wexample\SymfonyHelpers\Helper\ClassHelper;
use Wexample\SymfonyHelpers\Helper\FileHelper;
use Wexample\SymfonyHelpers\Helper\VariableHelper;
use function array_filter;
use function array_merge;
use function array_pop;
use function current;
use function end;
use function explode;
use function file_exists;
use function implode;
use function is_array;
use function is_null;
use function pathinfo;
use function str_replace;
use function str_starts_with;
use function strlen;
use function strpos;
use function substr;

class Translator implements TranslatorInterface, TranslatorBagInterface, LocaleAwareInterface
{
    public const DOMAIN_SEPARATOR = ClassHelper::METHOD_SEPARATOR;

    public const DOMAIN_PREFIX = '@';

    public const KEYS_SEPARATOR = FileHelper::EXTENSION_SEPARATOR;

    public const DOMAIN_SAME_KEY_WILDCARD = '%';

    public const DOMAIN_TYPE_COMPONENT = VariableHelper::COMPONENT;

    public const DOMAIN_TYPE_FORM = VariableHelper::FORM;

    public const DOMAIN_TYPE_LAYOUT = VariableHelper::LAYOUT;

    public const DOMAIN_TYPE_PAGE = VariableHelper::PAGE;

    public const DOMAIN_TYPE_PDF = FileHelper::FILE_EXTENSION_PDF;

    public const DOMAIN_TYPE_VUE = FileHelper::FILE_EXTENSION_VUE;

    public const DOMAINS_DEFAULT = [
        self::DOMAIN_TYPE_COMPONENT,
        self::DOMAIN_TYPE_FORM,
        self::DOMAIN_TYPE_LAYOUT,
        self::DOMAIN_TYPE_PAGE,
        self::DOMAIN_TYPE_PDF,
        self::DOMAIN_TYPE_VUE,
    ];

    public const FILE_EXTENDS = '~extends';

    protected array $domainsStack = [];

    private array $locales = [];

    /**
     * @throws InvalidArgumentException|Exception
     */
    public function __construct(
        public \Symfony\Bundle\FrameworkBundle\Translation\Translator $translator,
        private readonly array $parameters,
        KernelInterface $kernel,
        ParameterBagInterface $parameterBag,
    ) {
        $pathProject = $kernel->getProjectDir();

        // Merge all existing locales.
        foreach (array_merge($this->translator->getFallbackLocales(), [$this->getLocale()]) as $locale) {
            $this->addLocale($locale);
        }

        // Search into "translation" folder for sub folders.
        // Allow notation : path.to.folder::translation.key
        $pathTranslationsAll = $parameterBag->get('translations_paths');

        // Add root translations
        $pathTranslationsAll[] = $pathProject.'/translations';

        foreach ($pathTranslationsAll as $pathTranslations) {
            if (file_exists($pathTranslations)) {
                $this->addTranslationDirectory(
                    $pathTranslations,
                );
            }
        }

        $this->resolveCatalog();
    }

    public function addTranslationDirectory(
        string $pathTranslations
    ) {
        $it = new RecursiveDirectoryIterator(
            $pathTranslations
        );

        /** @var SplFileInfo $file */
        foreach (new RecursiveIteratorIterator($it) as $file) {
            $info = (object) pathinfo($file);

            if (FileHelper::FILE_EXTENSION_YML === $info->extension) {
                $exp = explode(FileHelper::EXTENSION_SEPARATOR, $info->filename);

                $subDir = FileHelper::buildRelativePath(
                    $info->dirname,
                    $pathTranslations
                );

                $domain = [];
                // There is a subdirectory
                // (allow translation files at dir root)
                if (VariableHelper::_EMPTY_STRING !== $subDir) {
                    $domain = explode(
                        '/',
                        $subDir
                    );
                }

                // Append file name
                $domain[] = $exp[0];
                $domain = implode(self::KEYS_SEPARATOR, $domain);

                $this->addLocale($exp[1]);

                $this->translator->addResource(
                    $info->extension,
                    $file,
                    $exp[1],
                    $domain
                );
            }
        }
    }

    /**
     * Allows :
     *   - Include key: "@other.translation.file::other.key"
     *   - Include same key: "@other.translation.file::%".
     *
     * @throws Exception
     */
    public function resolveCatalog()
    {
        $locales = $this->getAllLocales();

        foreach ($locales as $locale) {
            $catalogue = $this->translator->getCatalogue($locale);
            $all = $catalogue->all();

            foreach ($all as $domain => $translations) {
                $this->resolveCatalogTranslations(
                    $translations,
                    $domain,
                    $locale
                );
            }
        }
    }

    public function addLocale(string $locale): void
    {
        $this->locales[$locale] = $locale;
    }

    public function getAllLocales(): array
    {
        return array_values($this->locales);
    }

    public function getLocale(): string
    {
        return $this->translator->getLocale();
    }

    public function getCatalogue($locale = null): MessageCatalogueInterface
    {
        return $this->translator->getCatalogue($locale);
    }

    /**
     * @throws Exception
     */
    public function resolveCatalogTranslations(
        array $translations,
        string $domain,
        string $locale
    ): array {
        $translations = $this->resolveExtend(
            $translations,
            $locale
        );
        $resolved = [];

        foreach ($translations as $key => $value) {
            $resolved += $this->resolveCatalogItem(
                $key,
                $value,
                $domain,
                $locale
            );
        }

        return $resolved;
    }

    /**
     * @throws Exception
     */
    public function resolveExtend(
        array $translations,
        string $locale
    ): array {
        $catalogue = $this->translator->getCatalogue($locale);
        $all = $catalogue->all();

        if (isset($translations[static::FILE_EXTENDS])) {
            $extendsDomain = $this->trimDomain($translations[static::FILE_EXTENDS]);
            unset($translations[static::FILE_EXTENDS]);

            if (isset($all[$extendsDomain])) {
                return $translations + $this->resolveExtend($all[$extendsDomain], $locale);
            }

            throw new Exception('Unable to extend translations. Domain does not exists : '.$extendsDomain);
        }

        return $translations;
    }

    public function trimDomain(string $domain): string
    {
        return substr($domain, 1);
    }

    /**
     * @throws Exception
     */
    public function resolveCatalogItem(
        string $key,
        string $value,
        string $domain,
        string $locale
    ): array {
        $catalogue = $this->translator->getCatalogue($locale);
        $all = $catalogue->all();
        $output = [];

        if (self::DOMAIN_PREFIX === $value[0]) {
            $refDomain = $this->trimDomain($this->splitDomain($value));
            $refKey = $this->splitId($value);
            $shortNotation = self::DOMAIN_SAME_KEY_WILDCARD === $refKey;

            if ($shortNotation) {
                $refKey = $key;
            }

            $items = [];

            // Found the exact referenced key.
            if (isset($all[$refDomain][$refKey])) {
                $items = $this->resolveCatalogItem(
                    $refKey,
                    $all[$refDomain][$refKey],
                    $refDomain,
                    $locale
                );
            } else {
                $subTranslations = array_filter(
                    $all[$refDomain],
                    function(
                        $key
                    ) use
                    (
                        $refKey
                    ): bool {
                        return str_starts_with($key, $refKey.self::KEYS_SEPARATOR);
                    },
                    ARRAY_FILTER_USE_KEY
                );

                $items += $this->resolveCatalogTranslations(
                    $subTranslations,
                    $refDomain,
                    $locale
                );
            }

            foreach ($items as $outputKey => $outputValue) {
                $keyDiff = $key;
                $prefix = $refKey.self::KEYS_SEPARATOR;

                if (str_starts_with($outputKey, $prefix)) {
                    $keyDiff = $key.self::KEYS_SEPARATOR
                        .substr(
                            $outputKey,
                            strlen($prefix)
                        );
                }

                $output[$keyDiff]
                    = $outputValue;
            }
        } else {
            $output[$key] = $value;
        }

        foreach ($output as $outputKey => $outputValue) {
            $catalogue->set(
                $outputKey,
                $outputValue,
                $domain
            );
        }

        return $output;
    }

    public function splitDomain(?string $id): ?string
    {
        if (strpos($id, self::DOMAIN_SEPARATOR)) {
            return current(explode(self::DOMAIN_SEPARATOR, $id));
        }

        return null;
    }

    public function splitId(string $id): ?string
    {
        if (strpos($id, self::DOMAIN_SEPARATOR)) {
            $exp = explode(self::DOMAIN_SEPARATOR, $id);

            return end($exp);
        }

        return null;
    }

    public function transArray(
        array|string $id,
        array $parameters = [],
        string $separator = '',
        string $domain = null,
        string $locale = null
    ): string {
        if (is_array($id)) {
            $output = [];

            foreach ($id as $idPart) {
                $output[] = $this->trans(
                    $idPart,
                    $parameters,
                    $domain,
                    $locale
                );
            }

            return implode($separator, $output);
        }

        return $id;
    }

    public function trans(
        string $id,
        array $parameters = [],
        string $domain = null,
        string $locale = null
    ): string {
        $parameters = $this->updateParameters($parameters);
        $default = $id;

        if (is_null($domain) && $domain = $this->splitDomain($id)) {
            $id = $this->splitId($id);
            $domain = $this->resolveDomain($domain);

            if ($domain) {
                $default = $domain.static::DOMAIN_SEPARATOR.$id;
            }
        }

        // If not found, return the full id to ease fixing.
        return $this
            ->translator
            ->getCatalogue()
            ->has($id, $domain ?: 'messages')

            ? $this
                ->translator
                ->trans(
                    $id,
                    $parameters,
                    $domain,
                    $locale
                )
            : $default;
    }

    protected function updateParameters(array $parameters = []): array
    {
        return array_merge($this->parameters, $parameters);
    }

    public function resolveDomain(string $domain): ?string
    {
        if (str_starts_with($domain, self::DOMAIN_PREFIX)) {
            $domainPart = $this->trimDomain($domain);
            if (isset($this->domainsStack[$domainPart])) {
                return $this->getDomain($domainPart);
            }
        }

        return $domain;
    }

    public function getDomain(string $name): ?string
    {
        return empty($this->domainsStack[$name]) ?
            null :
            end($this->domainsStack[$name]);
    }

    public function setDomainFromPath(
        string $name,
        string $path
    ): string {
        $domain = Translator::buildDomainFromPath($path);

        $this->setDomain($name, $domain);

        return $domain;
    }

    public static function buildDomainFromPath(string $path): string
    {
        $info = (object) pathinfo($path);

        // The path format is valid.
        if ('.' !== $info->dirname) {
            return str_replace(
                    '/',
                    self::KEYS_SEPARATOR,
                    $info->dirname
                )
                .self::KEYS_SEPARATOR
                .current(
                    explode(self::KEYS_SEPARATOR, $info->basename)
                );
        } else {
            return $path;
        }
    }

    public function setDomain(
        string $name,
        string $value
    ): void {
        $this->domainsStack[$name][] = $value;
    }

    public function revertDomain(string $name): void
    {
        array_pop($this->domainsStack[$name]);
    }

    public function setLocale($locale)
    {
        $this->translator->setLocale($locale);
    }

    public function getCatalogues(): array
    {
        return $this->translator->getCatalogues();
    }

    public function transFilter(string $key): array
    {
        $output = [];

        if (str_contains($key, '*')) {
            $keyRegex = $this->buildRegexForFilterKey($key);

            $domainAlias = $this->splitDomain($key);
            $domainResolved = $this
                ->resolveDomain(
                    $domainAlias
                );

            $allDomainTranslations = $this->translator->getCatalogue()->all($domainResolved);

            foreach ($allDomainTranslations as $translationCandidateKey => $value) {
                if (preg_match($keyRegex, $translationCandidateKey)) {
                    $output[$domainAlias.Translator::DOMAIN_SEPARATOR.$translationCandidateKey] = $value;
                }
            }
        } else {
            $output[$key] = $this->trans($key);
        }

        return $output;
    }

    public function buildRegexForFilterKey(string $key): string
    {
        $keyRegex = str_replace('*', '[a-zA-Z0-9]', $this->splitId($key));
        $keyRegex = str_replace('.', '\.', $keyRegex);

        return '/'.$keyRegex.'/';
    }
}
