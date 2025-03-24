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
use Wexample\SymfonyDesignSystem\Helper\TemplateHelper;
use function array_merge;
use function array_pop;
use function array_values;
use function current;
use function explode;
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
     * YAML resolver for handling includes and references
     */
    private YamlIncludeResolver $yamlResolver;

    /**
     * @throws InvalidArgumentException|Exception
     */
    public function __construct(
        public \Symfony\Bundle\FrameworkBundle\Translation\Translator $translator,
        KernelInterface $kernel,
        private readonly ParameterBagInterface $parameterBag,
    )
    {
        // Initialize the YAML resolver
        $this->yamlResolver = new YamlIncludeResolver();
        
        // Initialize locales from the Symfony translator
        $this->addLocale($this->getLocale());

        foreach ($this->translator->getFallbackLocales() as $fallbackLocale) {
            $this->addLocale($fallbackLocale);
        }

        // Load translation paths
        $this->loadTranslationPaths($kernel->getProjectDir());

        // Load translation files
        $this->loadTranslationFiles();
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
        foreach ($this->translationPaths as $key => $basePath) {
            $finder = new Finder();
            $finder->files()
                ->in($basePath)
                ->name('*.' . $locale . FileHelper::EXTENSION_SEPARATOR . FileHelper::FILE_EXTENSION_YML);

            if (!$finder->hasResults()) {
                continue;
            }

            FileHelper::scanDirectoryForFiles(
                directoryPath: $basePath,
                extension: FileHelper::FILE_EXTENSION_YML,
                fileProcessor: function (
                    \SplFileInfo $file
                ) use
                (
                    &
                    $dd,
                    $locale
                ) {
                    if (str_ends_with(
                        $file->getFilename(),
                        FileHelper::EXTENSION_SEPARATOR . $locale . FileHelper::EXTENSION_SEPARATOR . FileHelper::FILE_EXTENSION_YML
                    )) {
                        $relativePath = $file->getPathname();
                        $dd['domains'][] = $relativePath;
                    }
                });

            foreach ($finder as $fileInfo) {
                // Build domain from file path
                $relativePath = $fileInfo->getPathname();
            }
        }
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
    public function setDomainFromPath(
        string $name,
        string $path
    ): string
    {
        $domain = self::buildDomainFromPath(
            TemplateHelper::trimPathPrefix($path)
        );

        $this->setDomain($name, $domain);
        return $domain;
    }

    /**
     * Build a domain identifier from a file path
     */
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
