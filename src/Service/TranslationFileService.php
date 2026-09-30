<?php

namespace Wexample\SymfonyTranslations\Service;

use Symfony\Component\Yaml\Yaml;
use Wexample\PhpYaml\YamlIncludeResolver;
use Wexample\SymfonyTranslations\Helper\TransFileHelper;
use Wexample\SymfonyTranslations\Translation\Translator;

/**
 * Translates every element the application reads: the `.<target>.yml` next to
 * its `.<source>.yml`, or the target block of its `.trans.yml`. The element
 * decides: one holding a `.trans.yml` is read from and written into it, the
 * blocks of the other locales left as they are.
 *
 * Only the application's own files by default: a bundle's live in its package,
 * and writing there from an application is a change to a dependency — wanted
 * when developing the bundles through symlinks, never by surprise.
 *
 * A key is translated when the target file lacks it, or when its source changed
 * since it was translated. What the target holds without a trace in the lock —
 * a wording written by hand — is kept as it is. The lock sits at the root of
 * each translations directory and records, per element, locale and key, the
 * hash of the source the translation was made from and the engine that made
 * it. Its entries are named after the per-locale file whichever way the element
 * is stored, so that they survive a conversion. It is written after each file,
 * so that an interrupted run leaves no translation looking as if it were
 * written by hand.
 *
 * A target file the lock knows, whose source is gone, is removed with its
 * entry, unless orphans are kept. One the lock does not know was not written
 * here, and is never touched.
 *
 * A key the engine left out gets no lock entry: the target keeps its previous
 * translation, or goes without the key — read in the fallback locale — and the
 * next run asks for it again. A dry run never reaches the engine: it counts
 * what would be sent.
 */
class TranslationFileService
{
    final public const string LOCK_FILE_NAME = '.translations.lock.json';

    final public const string KEYS_SEPARATOR = '.';

    public function __construct(
        private readonly Translator $translator,
        private readonly TextTranslationService $textTranslationService,
        private readonly string $projectDir,
    ) {
    }

    /**
     * @param string|null $pathFilter Only the source files whose path contains it
     * @param bool $force Translate again what an engine already translated, keeping what was written by hand
     * @param bool $includeBundles Also write into the bundles' own translation directories
     * @param bool $keepOrphans Keep the target files whose source is gone
     * @param callable(string $targetPath, int $translatedCount): void|null $onFile
     * @param callable(string $targetPath): void|null $onOrphan
     * @param callable(string $targetPath, string[] $keys): void|null $onUntranslated Keys the engine left out
     * @return array{files: int, written: int, translated: int, untranslated: int, removed: int}
     */
    public function translateFiles(
        string $sourceLocale,
        string $targetLocale,
        ?string $pathFilter = null,
        bool $force = false,
        bool $dryRun = false,
        bool $includeBundles = false,
        bool $keepOrphans = false,
        ?callable $onFile = null,
        ?callable $onOrphan = null,
        ?callable $onUntranslated = null,
    ): array {
        $stats = ['files' => 0, 'written' => 0, 'translated' => 0, 'untranslated' => 0, 'removed' => 0];

        foreach ($this->getBasePaths($includeBundles) as $basePath) {
            $lockPath = $basePath.self::LOCK_FILE_NAME;
            $lock = is_file($lockPath) ? json_decode(file_get_contents($lockPath), true, flags: JSON_THROW_ON_ERROR) : [];

            foreach ($this->findElements($basePath, $sourceLocale) as $element) {
                if (null !== $pathFilter && ! str_contains($element, $pathFilter)) {
                    continue;
                }

                // A .trans.yml holding other locales only.
                if (null === $source = $this->readLocale($element, $sourceLocale)) {
                    continue;
                }

                $targetPath = $this->getTargetPath($element, $targetLocale);
                $targetKey = substr($element, strlen($basePath)).'.'.$targetLocale.'.yml';
                $fileLock = $lock[$targetKey] ?? [];

                $existingTarget = $this->readLocale($element, $targetLocale);

                [$target, $translatedCount, $untranslatedKeys] = $this->translateTree(
                    $source,
                    $existingTarget ?? [],
                    $fileLock,
                    $sourceLocale,
                    $targetLocale,
                    $force,
                    $dryRun
                );

                $stats['files']++;
                $stats['translated'] += $translatedCount;
                $stats['untranslated'] += count($untranslatedKeys);

                // Compared as data: a file written by hand is not rewritten for its layout alone,
                // and a file the engine left empty is not created.
                $changed = null === $existingTarget ? [] !== $target : $target != $existingTarget;

                if ($changed) {
                    $stats['written']++;

                    if (! $dryRun) {
                        $this->writeLocale($element, $targetLocale, $target);
                    }
                }

                if ($fileLock !== ($lock[$targetKey] ?? [])) {
                    $lock[$targetKey] = $fileLock;

                    if (! $dryRun) {
                        $this->updateLock($lockPath, $targetKey, $fileLock);
                    }
                }

                if ($onFile && $changed) {
                    $onFile($targetPath, $translatedCount);
                }

                if ($onUntranslated && [] !== $untranslatedKeys) {
                    $onUntranslated($targetPath, $untranslatedKeys);
                }
            }

            if ($keepOrphans) {
                continue;
            }

            $targetSuffix = '.'.$targetLocale.'.yml';

            foreach (array_keys($lock) as $targetKey) {
                if (! str_ends_with($targetKey, $targetSuffix)) {
                    continue;
                }

                $element = $basePath.substr($targetKey, 0, -strlen($targetSuffix));
                $targetPath = $this->getTargetPath($element, $targetLocale);

                if ((null !== $pathFilter && ! str_contains($targetPath, $pathFilter))
                    || null !== $this->readLocale($element, $sourceLocale)) {
                    continue;
                }

                $stats['removed']++;
                unset($lock[$targetKey]);

                if (! $dryRun) {
                    $this->writeLocale($element, $targetLocale, null);
                    $this->updateLock($lockPath, $targetKey, null);
                }

                if ($onOrphan) {
                    $onOrphan($targetPath);
                }
            }
        }

        return $stats;
    }

    /**
     * One locale of an element, from its `.trans.yml` if it has one, else from
     * its per-locale file.
     *
     * @param string $element The path of its files, without their suffix
     * @return array|null Null when the element does not have the locale
     */
    public function readLocale(
        string $element,
        string $locale
    ): ?array {
        if (is_file($element.TransFileHelper::SUFFIX)) {
            $content = Yaml::parseFile($element.TransFileHelper::SUFFIX)[$locale] ?? null;

            if (null !== $content) {
                return (array) $content;
            }
        }

        $path = $element.'.'.$locale.'.yml';

        return is_file($path) ? (Yaml::parseFile($path) ?? []) : null;
    }

    /**
     * Into the element's `.trans.yml` if it has one, leaving the other blocks as
     * they are, else into its per-locale file.
     *
     * @param array|null $tree Null removes the locale, and the `.trans.yml` left without any
     */
    private function writeLocale(
        string $element,
        string $locale,
        ?array $tree
    ): void {
        $transPath = $element.TransFileHelper::SUFFIX;

        if (! is_file($transPath)) {
            $path = $element.'.'.$locale.'.yml';

            if (null !== $tree) {
                file_put_contents($path, Yaml::dump($tree, TransFileHelper::DUMP_INLINE, TransFileHelper::DUMP_INDENT, Yaml::DUMP_MULTI_LINE_LITERAL_BLOCK));
            } elseif (is_file($path)) {
                unlink($path);
            }

            return;
        }

        $content = TransFileHelper::writeBlock(file_get_contents($transPath), $locale, $tree);

        if ('' === trim($content)) {
            unlink($transPath);
        } else {
            file_put_contents($transPath, $content);
        }
    }

    private function getTargetPath(
        string $element,
        string $locale
    ): string {
        return is_file($element.TransFileHelper::SUFFIX) ? $element.TransFileHelper::SUFFIX.'#'.$locale : $element.'.'.$locale.'.yml';
    }

    /**
     * Changes the entry of one target file, re-reading the lock under an
     * exclusive lock first: runs for other locales may be writing the same
     * file at the same time, and must not erase each other's entries.
     *
     * @param array<string, array{hash: string, engine: string}>|null $fileLock Null removes the entry
     */
    private function updateLock(
        string $lockPath,
        string $targetKey,
        ?array $fileLock
    ): void {
        $handle = fopen($lockPath, 'c+');
        flock($handle, LOCK_EX);

        $content = stream_get_contents($handle);
        $lock = '' === $content ? [] : json_decode($content, true, flags: JSON_THROW_ON_ERROR);

        if (null === $fileLock) {
            unset($lock[$targetKey]);
        } else {
            $lock[$targetKey] = $fileLock;
        }

        ksort($lock);
        ftruncate($handle, 0);
        rewind($handle);
        fwrite($handle, json_encode($lock, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)."\n");
        fflush($handle);
        flock($handle, LOCK_UN);
        fclose($handle);
    }

    /**
     * The target mirrors the source: same keys in the same order, a key gone
     * from the source is gone from the target.
     *
     * @param array<string, array{hash: string, engine: string}> $fileLock Updated in place
     * @return array{0: array, 1: int, 2: string[]} The target tree, the number of texts translated
     *                                                — or to translate, on a dry run — and the keys the engine left out
     */
    private function translateTree(
        array $source,
        array $existingTarget,
        array &$fileLock,
        string $sourceLocale,
        string $targetLocale,
        bool $force,
        bool $dryRun
    ): array {
        $leaves = [];
        $this->collectLeaves($source, [], $leaves);

        $engine = $this->textTranslationService->getEngineName();
        $target = [];
        $toTranslate = [];
        $keptLock = [];

        foreach ($leaves as $key => [$path, $value]) {
            $existing = $this->getAt($existingTarget, $path);
            $entry = $fileLock[$key] ?? null;

            if (! is_string($value) || $this->isVerbatim($path, $value)) {
                $this->setAt($target, $path, $value);
                continue;
            }

            $hash = TextTranslationService::hashSource($value);

            if (null !== $existing && null === $entry) {
                // Written by hand: never overwritten.
                $this->setAt($target, $path, $existing);
            } elseif (null === $existing
                || $force
                || $entry['hash'] !== $hash
                || (PendingTextTranslator::ENGINE_NAME === $entry['engine'] && PendingTextTranslator::ENGINE_NAME !== $engine)) {
                $toTranslate[$key] = $value;
                $this->setAt($target, $path, $value);
            } else {
                $this->setAt($target, $path, $existing);
                $keptLock[$key] = $entry;
            }
        }

        if (empty($toTranslate) || $dryRun) {
            ksort($keptLock);
            $fileLock = $keptLock;

            return [$target, count($toTranslate), []];
        }

        $translations = $this->textTranslationService->translate($toTranslate, $sourceLocale, $targetLocale);

        foreach ($translations as $key => $translation) {
            $this->setAt($target, $leaves[$key][0], $translation);
            $keptLock[$key] = [
                'hash' => TextTranslationService::hashSource($toTranslate[$key]),
                'engine' => $engine,
            ];
        }

        $untranslated = array_diff_key($toTranslate, $translations);

        foreach (array_keys($untranslated) as $key) {
            $path = $leaves[$key][0];
            $existing = $this->getAt($existingTarget, $path);

            if (null === $existing) {
                $this->unsetAt($target, $path);
            } else {
                // Its old entry, if any, no longer matches the source: asked again next run.
                $this->setAt($target, $path, $existing);
                if (isset($fileLock[$key])) {
                    $keptLock[$key] = $fileLock[$key];
                }
            }
        }

        ksort($keptLock);
        $fileLock = $keptLock;

        return [$target, count($translations), array_map('strval', array_keys($untranslated))];
    }

    /**
     * What is not wording: a reference to another key, the same-key wildcard,
     * the `~extends` directive, and whatever lives under it.
     */
    private function isVerbatim(
        array $path,
        string $value
    ): bool {
        return str_starts_with((string) $path[0], '~')
            || YamlIncludeResolver::DOMAIN_SAME_KEY_WILDCARD === $value
            || (str_starts_with($value, YamlIncludeResolver::DOMAIN_PREFIX)
                && str_contains($value, YamlIncludeResolver::DOMAIN_SEPARATOR));
    }

    /**
     * @param array<string, array{0: array, 1: mixed}> $leaves Flat key => [path, value], in source order
     */
    private function collectLeaves(
        array $tree,
        array $path,
        array &$leaves
    ): void {
        foreach ($tree as $key => $value) {
            $childPath = [...$path, $key];

            if (is_array($value) && ! empty($value)) {
                $this->collectLeaves($value, $childPath, $leaves);
            } else {
                $leaves[implode(self::KEYS_SEPARATOR, $childPath)] = [$childPath, $value];
            }
        }
    }

    private function getAt(
        array $tree,
        array $path
    ): mixed {
        foreach ($path as $key) {
            if (! is_array($tree) || ! array_key_exists($key, $tree)) {
                return null;
            }

            $tree = $tree[$key];
        }

        return $tree;
    }

    /**
     * Removes a leaf, and the branches it leaves empty.
     */
    private function unsetAt(
        array &$tree,
        array $path
    ): void {
        $key = array_shift($path);

        if ([] === $path) {
            unset($tree[$key]);

            return;
        }

        $this->unsetAt($tree[$key], $path);

        if ([] === $tree[$key]) {
            unset($tree[$key]);
        }
    }

    private function setAt(
        array &$tree,
        array $path,
        mixed $value
    ): void {
        $node = &$tree;

        foreach ($path as $key) {
            $node[$key] ??= [];
            $node = &$node[$key];
        }

        $node = $value;
    }

    /**
     * @return string[] With a trailing slash, each directory once
     */
    public function getBasePaths(bool $includeBundles): array
    {
        $basePaths = [];

        foreach ($this->translator->getTranslationPaths() as $path) {
            if (($realPath = realpath($path)) && ($includeBundles || $this->isApplicationPath($realPath))) {
                $basePaths[$realPath] = $realPath.DIRECTORY_SEPARATOR;
            }
        }

        return array_values($basePaths);
    }

    /**
     * Inside the project and out of its vendor directory. Resolved paths, so a
     * bundle symlinked from elsewhere falls outside the project as well.
     */
    private function isApplicationPath(string $realPath): bool
    {
        $projectDir = realpath($this->projectDir).DIRECTORY_SEPARATOR;
        $path = $realPath.DIRECTORY_SEPARATOR;

        return str_starts_with($path, $projectDir)
            && ! str_starts_with($path, $projectDir.'vendor'.DIRECTORY_SEPARATOR);
    }

    /**
     * The elements having a `.<source>.yml` or a `.trans.yml`, each once.
     *
     * @return string[] The path of their files, without their suffix
     */
    private function findElements(
        string $basePath,
        string $sourceLocale
    ): array {
        $suffixes = ['.'.$sourceLocale.'.yml', TransFileHelper::SUFFIX];
        $elements = [];

        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($basePath, \FilesystemIterator::SKIP_DOTS)
        );

        foreach ($iterator as $file) {
            foreach ($suffixes as $suffix) {
                if (str_ends_with($file->getFilename(), $suffix)) {
                    $element = substr($file->getPathname(), 0, -strlen($suffix));
                    $elements[$element] = $element;
                }
            }
        }

        sort($elements);

        return array_values($elements);
    }
}
