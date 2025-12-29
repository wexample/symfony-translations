<?php

namespace Wexample\SymfonyTranslations\Translation;

use Exception;
use InvalidArgumentException;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Component\HttpKernel\KernelInterface;
use Symfony\Component\Translation\MessageCatalogueInterface;
use Symfony\Component\Translation\TranslatorBagInterface;
use Symfony\Contracts\Translation\LocaleAwareInterface;
use Symfony\Contracts\Translation\TranslatorInterface;
use Wexample\Helpers\Helper\ArrayHelper;
use Wexample\Helpers\Helper\ClassHelper;
use Wexample\Helpers\Helper\FileHelper;
use Wexample\Helpers\Helper\VariableSpecialHelper;
use Wexample\PhpYaml\YamlIncludeResolver;
use Wexample\SymfonyTemplate\Helper\TemplateHelper;
use Wexample\SymfonyHelpers\Helper\VariableHelper;
use function array_merge;
use function array_pop;
use function array_values;
use function current;
use function dirname;
use function explode;
use function implode;
use function pathinfo;
use function preg_match;
use function str_replace;

class Translator implements TranslatorInterface, TranslatorBagInterface, LocaleAwareInterface
{
    final public const string DOMAIN_PREFIX = '@';

    final public const string DOMAIN_SEPARATOR = ClassHelper::METHOD_SEPARATOR;

    final public const string KEYS_SEPARATOR = FileHelper::EXTENSION_SEPARATOR;

    final public const string DOMAIN_TYPE_COMPONENT = VariableHelper::COMPONENT;

    final public const string DOMAIN_TYPE_FORM = VariableHelper::FORM;

    final public const string DOMAIN_TYPE_LAYOUT = VariableHelper::LAYOUT;

    final public const string DOMAIN_TYPE_PAGE = VariableHelper::PAGE;

    final public const string DOMAIN_TYPE_PDF = \Wexample\SymfonyHelpers\Helper\FileHelper::FILE_EXTENSION_PDF;

    final public const string DOMAIN_TYPE_VUE = FileHelper::FILE_EXTENSION_VUE;

    /**
     * Stack of domain contexts, organized by name
     */
    protected array $domainsStack = [];

    /**
     * Available locales
     */
    private array $locales = [];

    /**
     * Translation paths
     */
    private array $translationPaths = [];

    /**
     * YAML resolvers for handling includes and references, one per locale
     * @var array<YamlIncludeResolver> $yamlResolvers
     */
    private array $yamlResolvers = [];

    /**
     * Tracks which locales have had their catalogue populated for this request lifecycle.
     */
    private array $cataloguesPopulated = [];

    /**
     * @throws InvalidArgumentException|Exception
     */
    public function __construct(
        public \Symfony\Bundle\FrameworkBundle\Translation\Translator $translator,
        KernelInterface $kernel,
        private readonly ParameterBagInterface $parameterBag
    )
    {
        // Initialize locales from the Symfony translator
        $this->addLocale($this->getLocale());

        foreach ($this->translator->getFallbackLocales() as $fallbackLocale) {
            $this->addLocale($fallbackLocale);
        }

        // Load translation paths
        $this->loadTranslationPaths($kernel->getProjectDir());

        // Load translation files
        $this->loadTranslationFiles();

        // Populate catalogues
        $this->populateCatalogues();
    }

    /**
     * Load translation paths from parameter bag and project directory
     */
    private function loadTranslationPaths(string $pathProject): void
    {
        // Add default translations directory
        $this->translationPaths = FileHelper::filterNonExisting(
            array_merge(
                $this->parameterBag->get('translations_paths') ?? [],
                [
                    $pathProject . '/translations/'
                ]
            )
        );
    }

    /**
     * Load translation files for all locales
     */
    private function loadTranslationFiles(): void
    {
        foreach ($this->getAllLocales() as $locale) {
            $this->loadTranslationFilesForLocale($locale);
        }
    }

    /**
     * Load translation files for a specific locale
     */
    private function loadTranslationFilesForLocale(string $locale): void
    {
        // Create a resolver for this locale if it doesn't exist
        if (!isset($this->yamlResolvers[$locale])) {
            $this->yamlResolvers[$locale] = new YamlIncludeResolver();
        }

        $resolver = $this->yamlResolvers[$locale];

        foreach ($this->translationPaths as $key => $basePath) {
            if (!is_dir($basePath)) {
                continue; // Skip non-existent directories
            }

            FileHelper::scanDirectoryForFiles(
                directoryPath: $basePath,
                extension: FileHelper::FILE_EXTENSION_YML,
                fileProcessor: function (
                    \SplFileInfo $file
                ) use
                (
                    $locale,
                    $basePath,
                    $key,
                    $resolver
                ) {
                    $filename = $file->getFilename();
                    $expectedSuffix = FileHelper::EXTENSION_SEPARATOR . $locale . FileHelper::EXTENSION_SEPARATOR . FileHelper::FILE_EXTENSION_YML;

                    if (str_ends_with($filename, $expectedSuffix)) {
                        $filePath = $file->getPathname();

                        $domain = $this->buildDomainFromPath($filePath, $basePath, is_string($key) ? $key : null);

                        if (!empty($domain)) {
                            $resolver->registerFile($domain, $filePath);
                        }
                    }
                });
        }
    }

    /**
     * Populate translation catalogues with resolved values from YAML files
     */
    /**
     * Populate translation catalogues with resolved values from YAML files
     *
     * @throws Exception If there's an error resolving values
     */
    public function populateCatalogues(): void
    {
        // Process each locale
        foreach ($this->yamlResolvers as $locale => $resolver) {
            $this->populateCatalogueForLocale($locale);
        }
    }

    /**
     * Populate a single translation catalogue with resolved values from YAML files.
     *
     * @throws Exception If there's an error resolving values
     */
    private function populateCatalogueForLocale(string $locale): void
    {
        if (!isset($this->yamlResolvers[$locale])) {
            return;
        }

        $resolver = $this->yamlResolvers[$locale];

        // Get the catalogue for this locale
        $catalogue = $this->translator->getCatalogue($locale);

        // Get all domains from the resolver
        if ($domainsContent = $resolver->getAllDomainsContent()) {
            $domains = array_keys($domainsContent);

            // For each domain, resolve all values and add them to the catalogue
            foreach ($domains as $domain) {
                $values = $domainsContent[$domain] ?? [];

                if (!empty($values)) {
                    $resolvedValues = $resolver->resolveValues($values, $domain);
                    $flattenedValues = ArrayHelper::flattenArray($resolvedValues);

                    foreach ($flattenedValues as $key => $value) {
                        if (is_string($value)) {
                            $catalogue->add([$key => $value], $domain);
                        }
                    }
                }
            }
        }
    }

    /**
     * Build a domain identifier from a translation file path
     *
     * @param string $filePath The full path to the translation file
     * @param string $basePath The base directory containing translations
     * @param string|null $bundleName Optional prefix for the domain (e.g. bundle name)
     * @return string The domain identifier or empty string if invalid file
     * @throws InvalidArgumentException If file path is invalid
     */
    public function buildDomainFromPath(
        string $filePath,
        string $basePath,
        ?string $bundleName = null
    ): string
    {
        $info = (object) pathinfo($filePath);

        // Remove prefix from bundles keys.
        if ($bundleName && str_starts_with($bundleName, '@')) {
            $bundleName = substr($bundleName, strlen('@'));
        }

        if (!isset($info->extension) || FileHelper::FILE_EXTENSION_YML !== $info->extension) {
            return '';
        }

        // Split filename to get base name and locale
        // example: messages.fr.yml -> [messages, fr]
        $filenameParts = explode(FileHelper::EXTENSION_SEPARATOR, $info->filename);

        // Get relative path from base directory
        // example: /var/www/translations/admin/messages.fr.yml with base /var/www/translations
        // gives: admin
        $subDir = FileHelper::buildRelativePath(
            $info->dirname,
            dirname($basePath)
        );

        $domainParts = [];

        // Add subdirectory parts to domain if they exist
        if (VariableSpecialHelper::EMPTY_STRING !== $subDir) {
            $domainParts = explode('/', $subDir);

            if ($bundleName && !empty($domainParts) && $domainParts[0] === 'assets') {
                array_shift($domainParts);
            }
        }

        // Add filename (without locale) to domain parts
        $domainParts[] = $filenameParts[0];

        // Join domain parts with separator
        $domain = implode(self::KEYS_SEPARATOR, $domainParts);

        // Add prefix if provided (for bundles or specific namespaces)
        if (is_string($bundleName)) {
            $domain = $bundleName . '.' . $domain;
        }

        return $domain;
    }

    /**
     * Add a locale to the available locales list
     */
    public function addLocale(string $locale): void
    {
        $this->locales[$locale] = $locale;
    }

    /**
     * Set a domain based on a template path
     */
    public function setDomainFromTemplatePath(
        string $name,
        string $path
    ): string
    {
        $domain = self::buildDomainFromTemplatePath(
            TemplateHelper::trimPathPrefix($path)
        );

        $this->setDomain($name, $domain);
        return $domain;
    }

    /**
     * Build a domain identifier from a template path
     */
    public static function buildDomainFromTemplatePath(string $path): string
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

    /**
     * Push a domain value onto the stack for a given name
     */
    public function setDomain(
        string $name,
        string $value
    ): void
    {
        $this->domainsStack[$name][] = $value;
    }

    /**
     * Remove the most recent domain value from the stack for a given name
     */
    public function revertDomain(string $name): void
    {
        if (isset($this->domainsStack[$name]) && !empty($this->domainsStack[$name])) {
            array_pop($this->domainsStack[$name]);
        }
    }

    /**
     * Get the current domains stack
     */
    public function getDomainsStack(): array
    {
        return $this->domainsStack;
    }

    public function getDomain(string $name): ?string
    {
        if (!isset($this->domainsStack[$name]) || empty($this->domainsStack[$name])) {
            return null;
        }

        return end($this->domainsStack[$name]);
    }

    public function resolveDomain(string $domain): ?string
    {
        if (str_starts_with($domain, YamlIncludeResolver::DOMAIN_PREFIX)) {
            $domainPart = YamlIncludeResolver::trimDomainPrefix($domain);
            if (isset($this->domainsStack[$domainPart])) {
                return $this->getDomain($domainPart);
            }
        }

        return $domain;
    }

    /**
     * Filter translations by a key pattern
     */
    public function transFilter(string $key): array
    {
        if (null === $keyDomain = YamlIncludeResolver::splitDomain($key)) {
            return [];
        }

        $keyDomain = $this->resolveDomain($keyDomain);
        $catalogue = $this->translator->getCatalogue();
        $messages = $catalogue->all();
        $filtered = [];

        $regex = $this->buildRegexForFilterKey(YamlIncludeResolver::splitKey($key));

        foreach (($messages[$keyDomain] ?? []) as $id => $translation) {
            if (preg_match($regex, $id)) {
                $filtered[$keyDomain . self::DOMAIN_SEPARATOR . $id] = $translation;
            }
        }

        return $filtered;
    }

    /**
     * Build a regex pattern for filtering translation keys
     */
    public function buildRegexForFilterKey(string $key): string
    {
        return '/^' . str_replace('*', '.*', $key) . '$/';
    }

    /**
     * {@inheritdoc}
     */
    public function getCatalogues(): array
    {
        return $this->translator->getCatalogues();
    }

    /**
     * Get all available locales
     */
    public function getAllLocales(): array
    {
        return array_values($this->locales);
    }

    /**
     * {@inheritdoc}
     */
    public function setLocale($locale): void
    {
        $this->translator->setLocale($locale);

        $locale = (string) $locale;
        unset($this->cataloguesPopulated[$locale]);
        if (!isset($this->locales[$locale])) {
            $this->addLocale($locale);
            $this->loadTranslationFilesForLocale($locale);
            $this->populateCatalogueForLocale($locale);
        }
    }

    /**
     * {@inheritdoc}
     */
    public function getLocale(): string
    {
        return $this->translator->getLocale();
    }

    /**
     * {@inheritdoc}
     */
    public function getCatalogue($locale = null): MessageCatalogueInterface
    {
        return $this->translator->getCatalogue($locale);
    }

    /**
     * {@inheritdoc}
     *
     * @param string $id The message id (may also contain the domain with a separator)
     * @param array $parameters An array of parameters for the message
     * @param string|null $domain The domain for the message or null to use the default
     * @param string|null $locale The locale or null to use the default
     * @param bool $forceTranslate Whether to force translation even if the key doesn't exist
     * @return string The translated string
     */
    public function trans(
        string $id,
        array $parameters = [],
        ?string $domain = null,
        ?string $locale = null,
        bool $forceTranslate = false
    ): string
    {
        $this->ensureCataloguePopulated($locale);

        $default = $id;

        // Handle domain resolution from the ID if no domain is provided
        if (null === $domain && ($domain = YamlIncludeResolver::splitDomain($id))) {
            $id = YamlIncludeResolver::splitKey($id);
            $domain = $this->resolveDomain($domain);

            if ($domain) {
                $default = $domain . static::DOMAIN_SEPARATOR . $id;
            }
        }

        // Check if the translation exists in the catalogue or if we're forcing translation
        $catalogue = $this->translator->getCatalogue();
        // Return the translation if it exists, otherwise return the default value
        if ($forceTranslate || ($domain && $catalogue->has($id, $domain))) {
            return $this->translator->trans(
                $id,
                $parameters,
                $domain,
                $locale
            );
        }

        return $default;
    }

    private function ensureCataloguePopulated(?string $locale = null): void
    {
        $locale = $locale ?? $this->getLocale();

        if (isset($this->cataloguesPopulated[$locale])) {
            return;
        }

        if (!isset($this->locales[$locale])) {
            $this->addLocale($locale);
            $this->loadTranslationFilesForLocale($locale);
        }

        $this->populateCatalogueForLocale($locale);
        $this->cataloguesPopulated[$locale] = true;
    }
}
