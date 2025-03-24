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
use Wexample\Helpers\Helper\ClassHelper;
use Wexample\Helpers\Helper\FileHelper;
use Wexample\Helpers\Helper\VariableSpecialHelper;
use Wexample\PhpYaml\YamlIncludeResolver;
use Wexample\SymfonyDesignSystem\Helper\TemplateHelper;
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
    final public const string DOMAIN_SEPARATOR = ClassHelper::METHOD_SEPARATOR;

    final public const string KEYS_SEPARATOR = FileHelper::EXTENSION_SEPARATOR;

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
     */
    private array $yamlResolvers = [];

    /**
     * @throws InvalidArgumentException|Exception
     */
    public function __construct(
        public \Symfony\Bundle\FrameworkBundle\Translation\Translator $translator,
        KernelInterface $kernel,
        private readonly ParameterBagInterface $parameterBag,
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
                    if (str_ends_with(
                        $file->getFilename(),
                        FileHelper::EXTENSION_SEPARATOR . $locale . FileHelper::EXTENSION_SEPARATOR . FileHelper::FILE_EXTENSION_YML
                    )) {
                        $filePath = $file->getPathname();

                        $domain = $this->buildDomainFromPath($filePath, $basePath, $key);

                        $resolver->registerFile($domain, $filePath);
                    }
                });
        }
    }
    
    /**
     * Populate translation catalogues with resolved values from YAML files
     */
    public function populateCatalogues(): void
    {
        // Process each locale
        foreach ($this->yamlResolvers as $locale => $resolver) {
            $debug[$locale] = [];
            
            // Get the catalogue for this locale
            $catalogue = $this->translator->getCatalogue($locale);
            
            // Get all domains from the resolver
            $domains = array_keys($resolver->getAllDomainsContent());

            // For each domain, resolve all values and add them to the catalogue
            foreach ($domains as $domain) {
                $values = $resolver->getAllDomainsContent()[$domain] ?? [];
                
                // Add all values to the catalogue
                foreach ($values as $key => $value) {
                    if (is_string($value)) {
                        $catalogue->set($key, $value, $domain);
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
     * @param string|int|null $aliasPrefix Optional prefix for the domain (e.g. bundle name)
     * @return string The domain identifier
     */
    public function buildDomainFromPath(
        string $filePath,
        string $basePath,
        string|int $aliasPrefix = null
    ): string
    {
        $info = (object) pathinfo($filePath);

        if (FileHelper::FILE_EXTENSION_YML !== $info->extension) {
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
        }

        // Add filename (without locale) to domain parts
        $domainParts[] = $filenameParts[0];

        // Join domain parts with separator
        $domain = implode(self::KEYS_SEPARATOR, $domainParts);

        // Add prefix if provided (for bundles or specific namespaces)
        if (is_string($aliasPrefix)) {
            $domain = $aliasPrefix . '.' . $domain;
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
        array_pop($this->domainsStack[$name]);
    }

    /**
     * Get the current domains stack
     */
    public function getDomainsStack(): array
    {
        return $this->domainsStack;
    }

    /**
     * Filter translations by a key pattern
     */
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
     */
    public function trans(
        string $id,
        array $parameters = [],
        string $domain = null,
        string $locale = null
    ): string
    {
        return $this->translator->trans(
            id: $id,
            parameters: $parameters,
            domain: $domain,
            locale: $locale
        );
    }
}
