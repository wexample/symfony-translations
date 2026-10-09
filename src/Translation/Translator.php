<?php

namespace Wexample\SymfonyTranslations\Translation;

use function array_merge;
use function array_pop;
use function array_values;
use function current;
use function dirname;

use Exception;

use function explode;
use function implode;

use InvalidArgumentException;

use function pathinfo;
use function preg_match;
use function str_replace;

use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Component\HttpKernel\KernelInterface;
use Symfony\Component\Translation\MessageCatalogueInterface;
use Symfony\Component\Translation\TranslatorBagInterface;
use Symfony\Component\Yaml\Yaml;
use Symfony\Contracts\Translation\LocaleAwareInterface;
use Symfony\Contracts\Translation\TranslatorInterface;
use Wexample\Helpers\Helper\ArrayHelper;
use Wexample\Helpers\Helper\ClassHelper;
use Wexample\Helpers\Helper\FileHelper;
use Wexample\Helpers\Helper\VariableSpecialHelper;
use Wexample\PhpYaml\YamlIncludeResolver;
use Wexample\SymfonyHelpers\Helper\VariableHelper;
use Wexample\SymfonyTemplate\Helper\TemplateHelper;
use Wexample\SymfonyTranslations\Exception\MissingTranslationException;
use Wexample\SymfonyTranslations\Helper\TransFileHelper;

class Translator implements TranslatorInterface, TranslatorBagInterface, LocaleAwareInterface
{
    final public const string DOMAIN_PREFIX = '@';

    final public const string DOMAIN_SEPARATOR = ClassHelper::METHOD_SEPARATOR;

    final public const string KEYS_SEPARATOR = FileHelper::EXTENSION_SEPARATOR;

    final public const string DOMAIN_TYPE_COMPONENT = VariableHelper::COMPONENT;

    final public const string DOMAIN_TYPE_ENTITY = VariableHelper::ENTITY;

    final public const string DOMAIN_TYPE_FORM = VariableHelper::FORM;

    final public const string DOMAIN_TYPE_LAYOUT = VariableHelper::LAYOUT;

    /**
     * The mail being rendered: its template addresses its own file as
     * `@mail::`, the way a page does with `@page::`.
     */
    final public const string DOMAIN_TYPE_MAIL = VariableHelper::MAIL;

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
     * @var array<YamlIncludeResolver>
     */
    private array $yamlResolvers = [];

    /**
     * Tracks which locales have had their catalogue populated for this request lifecycle.
     */
    private array $cataloguesPopulated = [];

    /**
     * Every locale of the `.trans.yml` files, by path.
     */
    private array $transFiles = [];

    /**
     * The files a locale is read from, found by a scan that opens none of
     * them: the catalogue cache is checked against their times and sizes.
     *
     * @var array<string, list<array{string, string, string}>> locale → [kind, path, domain]
     */
    private array $localeFiles = [];

    private string $transFilesCacheDir;

    /**
     * Whether the files may change while the kernel runs: in debug, every
     * request checks them; otherwise only a deployment changes them, and it
     * clears the kernel cache.
     */
    private bool $watchFiles;

    /**
     * @throws InvalidArgumentException|Exception
     */
    public function __construct(
        public \Symfony\Bundle\FrameworkBundle\Translation\Translator $translator,
        KernelInterface $kernel,
        private readonly ParameterBagInterface $parameterBag,
        // A missing `domain::key` throws instead of being returned as is.
        private readonly bool $strict = false,
    ) {
        $this->transFilesCacheDir = $kernel->getCacheDir() . '/wexample_translations/trans_files';
        $this->watchFiles = $kernel->isDebug();

        // Initialize locales from the Symfony translator
        $this->addLocale($this->getLocale());

        foreach ($this->translator->getFallbackLocales() as $fallbackLocale) {
            $this->addLocale($fallbackLocale);
        }

        // Load translation paths
        $this->loadTranslationPaths($kernel->getProjectDir());

        // Populate catalogues: their files are listed only where needed
        $this->populateCatalogues();

        // Build @entity.X aliases from all entity.X domains in the catalogue
        $this->buildEntityAliases();
    }

    private function buildEntityAliases(): void
    {
        foreach (array_keys($this->translator->getCatalogue()->all()) as $domain) {
            // Match any "<prefix>.entity.<name>" domain, wherever it comes from:
            //   "front.entity.metric"                        -> alias "entity.metric"
            //   "WexampleSymfonyMoneyBundle.entity.currency" -> alias "entity.currency"
            if (preg_match('/(?:^|\.)' . self::DOMAIN_TYPE_ENTITY . '\.(.+)$/', $domain, $matches)) {
                $alias = self::DOMAIN_TYPE_ENTITY . self::KEYS_SEPARATOR . $matches[1];

                // First registered wins: app paths are loaded before bundle ones,
                // so an app-level entity file overrides the bundle one.
                if (! isset($this->domainsStack[$alias])) {
                    $this->domainsStack[$alias] = [$domain];
                }
            }
        }
    }

    /**
     * Get all entity domain aliases (entity.X), e.g. for client-side alias resolution.
     */
    public function getEntityAliases(): array
    {
        $aliases = [];

        foreach ($this->domainsStack as $alias => $stack) {
            if (str_starts_with($alias, self::DOMAIN_TYPE_ENTITY . self::KEYS_SEPARATOR)) {
                $aliases[$alias] = end($stack);
            }
        }

        return $aliases;
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
                    $pathProject . '/translations/',
                ]
            )
        );
    }

    /**
     * Every directory translation files are read from, keyed like translations_paths.
     */
    public function getTranslationPaths(): array
    {
        return $this->translationPaths;
    }

    /**
     * Load translation files for all locales
     */

    /**
     * The files of a locale: per-locale `.yml` files first, then the
     * `.trans.yml` files holding every language of an element. Only listed
     * here; read when the catalogue cannot come from the cache.
     */
    private function loadTranslationFilesForLocale(string $locale): void
    {
        $files = [];

        foreach ($this->translationPaths as $key => $basePath) {
            if (! is_dir($basePath)) {
                continue; // Skip non-existent directories
            }

            $transFiles = [];

            FileHelper::scanDirectoryForFiles(
                directoryPath: $basePath,
                extension: FileHelper::FILE_EXTENSION_YML,
                fileProcessor: function (
                    \SplFileInfo $file
                ) use (
                    $locale,
                    $basePath,
                    $key,
                    &$files,
                    &$transFiles
                ) {
                    $filename = $file->getFilename();
                    $expectedSuffix = FileHelper::EXTENSION_SEPARATOR . $locale . FileHelper::EXTENSION_SEPARATOR . FileHelper::FILE_EXTENSION_YML;

                    if (str_ends_with($filename, $expectedSuffix)) {
                        $filePath = $file->getPathname();
                        $domain = $this->buildDomainFromPath($filePath, $basePath, is_string($key) ? $key : null);

                        if (! empty($domain)) {
                            $files[] = ['file', $filePath, $domain];
                        }
                    } elseif (str_ends_with($filename, TransFileHelper::SUFFIX)) {
                        $transFiles[] = $file->getPathname();
                    }
                }
            );

            // After the per-locale files: an element holding a .trans.yml is read from it.
            foreach ($transFiles as $filePath) {
                $domain = $this->buildDomainFromPath($filePath, $basePath, is_string($key) ? $key : null);

                if (! empty($domain)) {
                    $files[] = ['trans', $filePath, $domain];
                }
            }
        }

        $this->localeFiles[$locale] = $files;
        unset($this->yamlResolvers[$locale]);
    }

    /**
     * The files of a locale, read into its resolver.
     */
    private function getResolver(string $locale): YamlIncludeResolver
    {
        if (isset($this->yamlResolvers[$locale])) {
            return $this->yamlResolvers[$locale];
        }

        $resolver = $this->yamlResolvers[$locale] = new YamlIncludeResolver();

        foreach ($this->localeFiles[$locale] ?? [] as [$kind, $filePath, $domain]) {
            if ('file' === $kind) {
                $resolver->registerFile($domain, $filePath);
            } elseif (is_array($content = $this->parseTransFile($filePath)[$locale] ?? null)) {
                $resolver->registerContent($domain, $content);
            }
        }

        return $resolver;
    }

    /**
     * Parsed once, read for every locale.
     */
    private function parseTransFile(string $filePath): array
    {
        return $this->transFiles[$filePath] ??= $this->readTransFile($filePath);
    }

    /**
     * A .trans.yml holds every language of an element, and the translator is
     * built on every request: parsed each time, the demo's files alone cost
     * seconds a page. The parsed content is kept as php in the kernel cache,
     * stamped with the file's time and size, and parsed again only once the
     * file has changed.
     */
    private function readTransFile(string $filePath): array
    {
        $stamp = filemtime($filePath) . '-' . filesize($filePath);
        $cacheFile = $this->transFilesCacheDir . '/' . hash('xxh128', $filePath) . '.php';

        if (is_file($cacheFile)) {
            $cached = include $cacheFile;

            if (is_array($cached) && ($cached['stamp'] ?? null) === $stamp) {
                return $cached['content'];
            }
        }

        $content = Yaml::parseFile($filePath) ?? [];

        $this->writeTransFileCache($cacheFile, $stamp, $content);

        return $content;
    }

    // Written aside then moved, so that a request reading it meanwhile finds
    // the previous file whole or none, never half of one. A cache that cannot
    // be written only means parsing again next time.
    private function writeTransFileCache(string $cacheFile, string $stamp, array $content): void
    {
        $directory = dirname($cacheFile);

        if (! is_dir($directory) && ! @mkdir($directory, 0777, true) && ! is_dir($directory)) {
            return;
        }

        $temporary = @tempnam($directory, 'trans');

        if (false === $temporary) {
            return;
        }

        $code = '<?php return ' . var_export(['stamp' => $stamp, 'content' => $content], true) . ';' . PHP_EOL;

        if (false === @file_put_contents($temporary, $code) || ! @rename($temporary, $cacheFile)) {
            @unlink($temporary);

            return;
        }

        @chmod($cacheFile, 0666);

        if (function_exists('opcache_invalidate')) {
            opcache_invalidate($cacheFile, true);
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
        foreach ($this->getAllLocales() as $locale) {
            $this->populateCatalogueForLocale($locale);
        }
    }

    /**
     * Populate a single translation catalogue with resolved values from YAML
     * files. Resolving includes and references through every file costs most
     * of a request: the resolved catalogue is kept in the kernel cache,
     * stamped with the list of its files and their times and sizes, and
     * resolved again only once one of them is added, removed or changed.
     * Out of debug the files are not even listed: the cache is trusted until
     * the next deployment clears it.
     *
     * @throws Exception If there's an error resolving values
     */
    private function populateCatalogueForLocale(string $locale): void
    {
        $cacheFile = dirname($this->transFilesCacheDir) . '/catalogues/' . $locale . '.php';
        $cached = is_file($cacheFile) ? include $cacheFile : null;

        if (! is_array($cached) || $this->watchFiles) {
            if (! isset($this->localeFiles[$locale])) {
                $this->loadTranslationFilesForLocale($locale);
            }

            $stamp = $this->stampFiles($this->localeFiles[$locale]);

            if (($cached['stamp'] ?? null) !== $stamp) {
                $cached = ['stamp' => $stamp, 'content' => $this->resolveMessages($locale)];
                $this->writeTransFileCache($cacheFile, $stamp, $cached['content']);
            }
        }

        $catalogue = $this->translator->getCatalogue($locale);

        foreach ($cached['content'] as $domain => $values) {
            $catalogue->add($values, $domain);
        }
    }

    /**
     * Every message of a locale, by domain, includes and references resolved.
     *
     * @return array<string, array<string, string>>
     */
    private function resolveMessages(string $locale): array
    {
        $resolver = $this->getResolver($locale);
        $messages = [];

        foreach ($resolver->getAllDomainsContent() ?: [] as $domain => $values) {
            if (empty($values)) {
                continue;
            }

            $flattened = ArrayHelper::flattenArray($resolver->resolveValues($values, $domain));
            $messages[$domain] = array_filter($flattened, 'is_string');
        }

        return array_filter($messages);
    }

    /**
     * @param list<array{string, string, string}> $files
     */
    private function stampFiles(array $files): string
    {
        $parts = [];

        foreach ($files as [$kind, $filePath, $domain]) {
            $parts[] = $kind . '|' . $filePath . '|' . $domain . '|' . @filemtime($filePath) . '|' . @filesize($filePath);
        }

        return hash('xxh128', implode("\n", $parts));
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
    ): string {
        $info = (object) pathinfo($filePath);

        // Remove prefix from bundles keys.
        if ($bundleName && str_starts_with($bundleName, '@')) {
            $bundleName = substr($bundleName, strlen('@'));
        }

        if (! isset($info->extension) || FileHelper::FILE_EXTENSION_YML !== $info->extension) {
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

            if ($bundleName && ! empty($domainParts) && $domainParts[0] === 'assets') {
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
    ): string {
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
    ): void {
        $this->domainsStack[$name][] = $value;
    }

    /**
     * Remove the most recent domain value from the stack for a given name
     */
    public function revertDomain(string $name): void
    {
        if (isset($this->domainsStack[$name]) && ! empty($this->domainsStack[$name])) {
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
        if (! isset($this->domainsStack[$name]) || empty($this->domainsStack[$name])) {
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
        $filtered = [];

        $regex = $this->buildRegexForFilterKey(YamlIncludeResolver::splitKey($key));

        // Fallbacks first, so the locale asked for overwrites what it translates.
        foreach (array_reverse($this->getLocaleChain($this->getLocale())) as $locale) {
            $this->ensureCataloguePopulated($locale);

            foreach ($this->translator->getCatalogue($locale)->all($keyDomain) as $id => $translation) {
                if (preg_match($regex, $id)) {
                    $filtered[$keyDomain . self::DOMAIN_SEPARATOR . $id] = $translation;
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

        $locale = (string) $locale;
        unset($this->cataloguesPopulated[$locale]);
        if (! isset($this->locales[$locale])) {
            $this->addLocale($locale);
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
    ): string {
        $locale ??= $this->getLocale();
        $this->ensureCataloguePopulated($locale);

        $default = $id;
        $keyed = false;

        // Handle domain resolution from the ID if no domain is provided
        if (null === $domain && ($domain = YamlIncludeResolver::splitDomain($id))) {
            $keyed = true;
            $id = YamlIncludeResolver::splitKey($id);
            $domain = $this->resolveDomain($domain);

            if ($domain) {
                $default = $domain . static::DOMAIN_SEPARATOR . $id;
            }
        }

        if ($forceTranslate) {
            return $this->translator->trans(
                $id,
                $parameters,
                $domain,
                $locale
            );
        }

        // Return the translation if it exists, otherwise return the default value
        if ($domain && null !== $definingLocale = $this->findLocaleDefining($id, $domain, $locale)) {
            return $this->translator->trans(
                $id,
                $parameters,
                $domain,
                $definingLocale
            );
        }

        // Only a `domain::key`: plain text goes through trans() too, and is
        // meant to come back unchanged.
        if ($keyed && $this->strict) {
            throw MissingTranslationException::create($id, $domain, $this->getLocaleChain($locale));
        }

        return $default;
    }

    /**
     * The first of the locale and its fallbacks whose own catalogue holds the
     * key: a page in a language translated in part shows the rest in the
     * fallback one rather than raw keys. Symfony's fallback catalogues are
     * copies taken before the yml messages are added, so they cannot do it.
     */
    private function findLocaleDefining(
        string $id,
        string $domain,
        string $locale
    ): ?string {
        foreach ($this->getLocaleChain($locale) as $candidate) {
            $this->ensureCataloguePopulated($candidate);

            if ($this->translator->getCatalogue($candidate)->defines($id, $domain)) {
                return $candidate;
            }
        }

        return null;
    }

    /**
     * @return string[] The locale, then its fallbacks
     */
    private function getLocaleChain(string $locale): array
    {
        return array_values(array_unique([
            $locale,
            ...$this->translator->getFallbackLocales(),
        ]));
    }

    private function ensureCataloguePopulated(?string $locale = null): void
    {
        $locale = $locale ?? $this->getLocale();

        if (isset($this->cataloguesPopulated[$locale])) {
            return;
        }

        if (! isset($this->locales[$locale])) {
            $this->addLocale($locale);
        }

        $this->populateCatalogueForLocale($locale);
        $this->cataloguesPopulated[$locale] = true;
    }
}
