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
use Wexample\Helpers\Helper\ClassHelper;
use Wexample\SymfonyHelpers\Helper\FileHelper;
use Wexample\Helpers\Helper\TextHelper;
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
    final public const DOMAIN_SEPARATOR = ClassHelper::METHOD_SEPARATOR;

    final public const DOMAIN_PREFIX = '@';

    final public const KEYS_SEPARATOR = FileHelper::EXTENSION_SEPARATOR;

    final public const DOMAIN_SAME_KEY_WILDCARD = '%';

    final public const DOMAIN_TYPE_COMPONENT = VariableHelper::COMPONENT;

    final public const DOMAIN_TYPE_FORM = VariableHelper::FORM;

    final public const DOMAIN_TYPE_LAYOUT = VariableHelper::LAYOUT;

    final public const DOMAIN_TYPE_PAGE = VariableHelper::PAGE;

    final public const DOMAIN_TYPE_PDF = FileHelper::FILE_EXTENSION_PDF;

    final public const DOMAIN_TYPE_VUE = FileHelper::FILE_EXTENSION_VUE;

    final public const DOMAINS_DEFAULT = [
        self::DOMAIN_TYPE_COMPONENT,
        self::DOMAIN_TYPE_FORM,
        self::DOMAIN_TYPE_LAYOUT,
        self::DOMAIN_TYPE_PAGE,
        self::DOMAIN_TYPE_PDF,
        self::DOMAIN_TYPE_VUE,
    ];

    final public const FILE_EXTENDS = '~extends';

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

        // Merge all existing locales, fallbacks at end.
        foreach (array_merge([$this->getLocale()], $this->translator->getFallbackLocales()) as $locale) {
            $this->addLocale($locale);
        }

        // Search into "translation" folder for sub folders.
        // Allow notation : path.to.folder::translation.key
        $pathTranslationsAll = $parameterBag->get('translations_paths') ?? [];

        // Add root translations
        $pathTranslationsAll[] = $pathProject.'/translations';

        foreach ($pathTranslationsAll as $aliasPrefix => $pathTranslations) {
            if (file_exists($pathTranslations)) {
                $this->addTranslationDirectory(
                    $pathTranslations,
                    str_starts_with($aliasPrefix, '@') ? $aliasPrefix : null
                );
            }
        }

        $this->resolveCatalog();
    }

    public function addTranslationDirectory(
        string $pathTranslations,
        ?string $aliasPrefix = null
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
                    dirname($pathTranslations)
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
                $domain = $aliasPrefix ? $aliasPrefix.'.'.$domain : self::DOMAIN_PREFIX . $domain;

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
            $locale,
            $domain
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
        string $locale,
        string $currentDomain = null
    ): array {
        $catalogue = $this->translator->getCatalogue($locale);
        $all = $catalogue->all();

        if (isset($translations[static::FILE_EXTENDS])) {
            $extendsDomainRaw = $this->trimDomain($translations[static::FILE_EXTENDS]);
            unset($translations[static::FILE_EXTENDS]);

            $extendsDomain = $this->findMatchingDomainVariant($extendsDomainRaw, $all);

            if ($extendsDomain) {
                return $translations + $this->resolveExtend($all[$extendsDomain], $locale, $extendsDomain);
            }

            throw new Exception('Unable to extend translations. Domain does not exists : '.$extendsDomainRaw
                .'. Existing domains are: '.TextHelper::toList(array_keys($all)));
        }

        return $translations;
    }

    public function trimDomain(string $domain): string
    {
        return substr($domain, 1);
    }

    function isIncludeReference(string $string): bool
    {
        return preg_match(
                '/^'.self::DOMAIN_PREFIX.'[a-zA-Z_\-\.]+::([a-zA-Z_\-\.]+|'.self::DOMAIN_SAME_KEY_WILDCARD.')+$/',
                $string
            ) === 1;
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

        if ($this->isIncludeReference($value)) {
            $refDomain = $this->trimDomain($this->splitDomain($value));
            $refKey = $this->splitId($value);
            $shortNotation = self::DOMAIN_SAME_KEY_WILDCARD === $refKey;

            if ($shortNotation) {
                $refKey = $key;
            }

            $items = [];

            $foundDomain = $this->findMatchingDomainVariant($refDomain, $all);
            // If no matching domain found, use the original reference domain
            // This will likely fail later, but it's consistent with the original behavior
            if (!$foundDomain) {
                $foundDomain = $refDomain;
            }

            // Found the exact referenced key.
            if ($foundDomain && isset($all[$foundDomain][$refKey])) {
                $items = $this->resolveCatalogItem(
                    $refKey,
                    $all[$foundDomain][$refKey],
                    $foundDomain,
                    $locale
                );
            } else {
                $subTranslations = array_filter(
                    $all[$foundDomain] ?? [],
                    fn($key): bool => str_starts_with($key, $refKey.self::KEYS_SEPARATOR),
                    ARRAY_FILTER_USE_KEY
                );

                $items += $this->resolveCatalogTranslations(
                    $subTranslations,
                    $foundDomain,
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

        // Extract domain from the ID if not provided explicitly
        if (is_null($domain) && $domain = $this->splitDomain($id)) {
            $id = $this->splitId($id);
            $domain = $this->resolveDomain($domain);

            if ($domain) {
                $default = $domain.static::DOMAIN_SEPARATOR.$id;
            }
        }

        $catalogue = $this->translator->getCatalogue($locale);
        $all = $catalogue->all();
        
        // Try to find the domain in the catalogue
        $foundDomain = $this->findMatchingDomainVariant($domain, $all);

        if ($foundDomain) {
            if (isset($all[$foundDomain][$id])) {
                $value = $all[$foundDomain][$id];
                
                // If the value is a translation link, resolve it
                if ($this->isIncludeReference($value)) {
                    $refDomain = $this->trimDomain($this->splitDomain($value));
                    $refKey = $this->splitId($value);
                    
                    // Check if the referenced key exists in any domain variant
                    $refExists = false;
                    $refDomainVariants = $this->generateDomainVariants($refDomain);
                    
                    foreach ($refDomainVariants as $refVariant) {
                        if (isset($all[$refVariant]) && isset($all[$refVariant][$refKey])) {
                            $refExists = true;
                            break;
                        }
                    }
                    
                    // If the referenced key doesn't exist, return the original link
                    if (!$refExists) {
                        return $value;
                    }
                    
                    // Otherwise, recursively translate the referenced key
                    return $this->trans($refKey, $parameters, $refDomain, $locale);
                }
                
                return $value;
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
                    $domain ?: 'messages',
                    $locale
                )
            : $default;
    }

    public function updateParameters(array $parameters = []): array
    {
        return array_merge($this->parameters, $parameters);
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
        $catalogue = $this->translator->getCatalogue();
        $messages = $catalogue->all();
        $regex = $this->buildRegexForFilterKey($key);
        $filtered = [];

        foreach ($messages as $domain => $translations) {
            foreach ($translations as $id => $translation) {
                if (preg_match($regex, $id)) {
                    $filtered[$domain.self::DOMAIN_SEPARATOR.$id] = $translation;
                }
            }
        }

        return $filtered;
    }

    public function buildRegexForFilterKey(string $key): string
    {
        return '/^'.str_replace('*', '.*', $key).'$/';
    }

    /**
     * Generate domain variants and find the first matching one in the catalogue.
     * 
     * @param string|null $domain Base domain name
     * @param array $all All domains from the catalogue
     * @return string|null Found domain variant or null if not found
     */
    private function findMatchingDomainVariant(?string $domain, array $all): ?string
    {
        if ($domain === null) {
            // If domain is null, try to use 'messages' (Symfony default domain)
            return isset($all['messages']) ? 'messages' : null;
        }
        
        // Try different variants of domain name to find the right one
        $domainVariants = $this->generateDomainVariants($domain);
        
        foreach ($domainVariants as $variant) {
            if (isset($all[$variant])) {
                return $variant;
            }
        }
        
        return null;
    }

    /**
     * Generate domain variants for a given domain.
     * 
     * @param string|null $domain Base domain name
     * @return array Array of domain variants
     */
    private function generateDomainVariants(?string $domain): array
    {
        if ($domain === null) {
            return ['messages']; // Symfony default domain
        }
        
        return [
            $domain,                      // test.domain.one
            '@' . $domain,                // @test.domain.one
            '@translations.' . $domain     // @translations.test.domain.one
        ];
    }
}
