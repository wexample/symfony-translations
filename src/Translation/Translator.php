<?php

namespace Wexample\SymfonyTranslations\Translation;

use Exception;
use InvalidArgumentException;
use SplFileInfo;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Component\HttpKernel\KernelInterface;
use Symfony\Component\Translation\MessageCatalogueInterface;
use Symfony\Component\Translation\TranslatorBagInterface;
use Symfony\Contracts\Translation\LocaleAwareInterface;
use Symfony\Contracts\Translation\TranslatorInterface;
use Wexample\Helpers\Helper\ClassHelper;
use Wexample\PhpYaml\YamlIncludeResolver;
use Wexample\SymfonyHelpers\Helper\FileHelper;
use Wexample\SymfonyHelpers\Helper\VariableHelper;
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
use function substr;

class Translator implements TranslatorInterface, TranslatorBagInterface, LocaleAwareInterface
{
    final public const string DOMAIN_SEPARATOR = ClassHelper::METHOD_SEPARATOR;

    final public const string DOMAIN_PREFIX = '@';

    final public const string KEYS_SEPARATOR = FileHelper::EXTENSION_SEPARATOR;

    final public const string DOMAIN_SAME_KEY_WILDCARD = '%';

    final public const string DOMAIN_TYPE_COMPONENT = VariableHelper::COMPONENT;

    final public const string DOMAIN_TYPE_FORM = VariableHelper::FORM;

    final public const string DOMAIN_TYPE_LAYOUT = VariableHelper::LAYOUT;

    final public const string DOMAIN_TYPE_PAGE = VariableHelper::PAGE;

    final public const string DOMAIN_TYPE_PDF = FileHelper::FILE_EXTENSION_PDF;

    final public const string DOMAIN_TYPE_VUE = FileHelper::FILE_EXTENSION_VUE;

    final public const array DOMAINS_DEFAULT = [
        self::DOMAIN_TYPE_COMPONENT,
        self::DOMAIN_TYPE_FORM,
        self::DOMAIN_TYPE_LAYOUT,
        self::DOMAIN_TYPE_PAGE,
        self::DOMAIN_TYPE_PDF,
        self::DOMAIN_TYPE_VUE,
    ];

    protected array $domainsStack = [];

    private array $locales = [];

    /**
     * YAML Include Resolver instance
     */
    private YamlIncludeResolver $yamlResolver;

    /**
     * @throws InvalidArgumentException|Exception
     */
    public function __construct(
        public \Symfony\Bundle\FrameworkBundle\Translation\Translator $translator,
        private readonly array $parameters,
        KernelInterface $kernel,
        ParameterBagInterface $parameterBag,
    )
    {
        // Initialize the YAML Include Resolver
        $this->yamlResolver = new YamlIncludeResolver();

        $pathProject = $kernel->getProjectDir();

        // Merge all existing locales, fallbacks at end.
        foreach (array_merge([$this->getLocale()], $this->translator->getFallbackLocales()) as $locale) {
            $this->addLocale($locale);
        }

        // Search into "translation" folder for sub folders.
        // Allow notation : path.to.folder::translation.key
        $pathTranslationsAll = $parameterBag->get('translations_paths') ?? [];

        // Add root translations
        $pathTranslationsAll[] = $pathProject . '/translations';

        $this->addTranslationDirectories($pathTranslationsAll);

        $this->resolveCatalog();
    }

    public function addTranslationDirectories(
        array $directories
    )
    {
        foreach ($directories as $pathTranslations) {
            if (file_exists($pathTranslations)) {
                $this->addTranslationDirectory(
                    $pathTranslations
                );
            }
        }
    }

    public function addTranslationDirectory(
        string $directory
    )
    {
        // Use the YamlIncludeResolver to scan the directory and register files
        $registeredFiles = $this->yamlResolver->scanDirectory($directory);

        // Add the registered files as resources to the Symfony translator
        /** @var SplFileInfo $fileInfo */
        foreach ($registeredFiles as $fileInfo) {
            $exp = explode(FileHelper::EXTENSION_SEPARATOR, $fileInfo->getFilename());

            $locale = $exp[1];
            // Add the locale to our list of available locales
            $this->addLocale($locale);

            // Add the file as a resource to the Symfony translator
            $this->translator->addResource(
                $fileInfo->getExtension(),
                $fileInfo->getRealPath(),
                $locale,
                $this->yamlResolver->buildDomainFromFile($fileInfo, $directory)
            );
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
    ): array
    {
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

    public function trimDomain(string $domain): string
    {
        return substr($domain, 1);
    }

    function isTranslationLink(string $string): bool
    {
        return preg_match(
                '/^' . self::DOMAIN_PREFIX . '[a-zA-Z_\-\.]+::([a-zA-Z_\-\.]+|' . self::DOMAIN_SAME_KEY_WILDCARD . ')+$/',
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
    ): array
    {
        $catalogue = $this->translator->getCatalogue($locale);
        $output = [];

        if ($this->isTranslationLink($value)) {
            // Use the YAML resolver to get the value
            $resolvedValue = $this->yamlResolver->getValue($value);

            // If the resolved value is the same as the original value, it means the reference wasn't found
            if ($resolvedValue === $value) {
                $output[$key] = $value;
            } else {
                $output[$key] = $resolvedValue;
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
    ): string
    {
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
    ): string
    {
        $parameters = $this->updateParameters($parameters);
        $default = $id;

        // Use the YAML resolver to get the value if it's a reference
        if ($this->isTranslationLink($id)) {
            $resolvedValue = $this->yamlResolver->getValue($id);
            if ($resolvedValue !== $id) {
                return $resolvedValue;
            }
        }

        // Extract domain from the ID if not provided explicitly
        if (is_null($domain) && $domain = $this->yamlResolver->splitDomain($id)) {
            $id = $this->yamlResolver->splitKey(key: $id);

            if ($domain) {
                $default = $domain . static::DOMAIN_SEPARATOR . $id;
            }
        }

        $catalogue = $this->translator->getCatalogue($locale);
        $all = $catalogue->all();

        if ($domain) {
            if (isset($all[$domain][$id])) {
                $value = $all[$domain][$id];

                // If the value is a translation link, resolve it using the YAML resolver
                if ($this->isTranslationLink($value)) {
                    $resolvedValue = $this->yamlResolver->getValue($value);
                    if ($resolvedValue !== $value) {
                        return $resolvedValue;
                    }
                    return $value;
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
    ): string
    {
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
                . self::KEYS_SEPARATOR
                . current(
                    explode(self::KEYS_SEPARATOR, $info->basename)
                );
        } else {
            return $path;
        }
    }

    public function setDomain(
        string $name,
        string $value
    ): void
    {
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
                    $filtered[$domain . self::DOMAIN_SEPARATOR . $id] = $translation;
                }
            }
        }

        return $filtered;
    }

    public function buildRegexForFilterKey(string $key): string
    {
        return '/^' . str_replace('*', '.*', $key) . '$/';
    }
}
