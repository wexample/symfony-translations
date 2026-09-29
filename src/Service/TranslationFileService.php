<?php

namespace Wexample\SymfonyTranslations\Service;

use Symfony\Component\Yaml\Yaml;
use Wexample\PhpYaml\YamlIncludeResolver;
use Wexample\SymfonyTranslations\Translation\Translator;

/**
 * Writes the `.<target>.yml` next to every `.<source>.yml` the application reads.
 *
 * Only the application's own files by default: a bundle's live in its package,
 * and writing there from an application is a change to a dependency — wanted
 * when developing the bundles through symlinks, never by surprise.
 *
 * A key is translated when the target file lacks it, or when its source changed
 * since it was translated. What the target holds without a trace in the lock —
 * a wording written by hand — is kept as it is. The lock sits at the root of
 * each translations directory and records, per target file and key, the hash of
 * the source the translation was made from and the engine that made it. It is
 * written after each file, so that an interrupted run leaves no translation
 * looking as if it were written by hand.
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

            foreach ($this->findSourceFiles($basePath, $sourceLocale) as $sourcePath) {
                if (null !== $pathFilter && ! str_contains($sourcePath, $pathFilter)) {
                    continue;
                }

                $targetPath = substr($sourcePath, 0, -strlen('.'.$sourceLocale.'.yml')).'.'.$targetLocale.'.yml';
                $targetKey = substr($targetPath, strlen($basePath));
                $fileLock = $lock[$targetKey] ?? [];

                $existingTarget = is_file($targetPath) ? Yaml::parseFile($targetPath) : null;

                [$target, $translatedCount, $untranslatedKeys] = $this->translateTree(
                    Yaml::parseFile($sourcePath) ?? [],
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
                        file_put_contents($targetPath, Yaml::dump($target, 20, 2, Yaml::DUMP_MULTI_LINE_LITERAL_BLOCK));
                    }
                }

                if ($fileLock !== ($lock[$targetKey] ?? [])) {
                    $lock[$targetKey] = $fileLock;

                    if (! $dryRun) {
                        $this->writeLock($lockPath, $lock);
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
                $targetPath = $basePath.$targetKey;

                if (! str_ends_with($targetKey, $targetSuffix)
                    || (null !== $pathFilter && ! str_contains($targetPath, $pathFilter))
                    || is_file(substr($targetPath, 0, -strlen($targetSuffix)).'.'.$sourceLocale.'.yml')) {
                    continue;
                }

                $stats['removed']++;
                unset($lock[$targetKey]);

                if (! $dryRun) {
                    if (is_file($targetPath)) {
                        unlink($targetPath);
                    }

                    $this->writeLock($lockPath, $lock);
                }

                if ($onOrphan) {
                    $onOrphan($targetPath);
                }
            }
        }

        return $stats;
    }

    private function writeLock(
        string $lockPath,
        array $lock
    ): void {
        ksort($lock);
        file_put_contents(
            $lockPath,
            json_encode($lock, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)."\n"
        );
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
    private function getBasePaths(bool $includeBundles): array
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
     * @return string[]
     */
    private function findSourceFiles(
        string $basePath,
        string $sourceLocale
    ): array {
        $suffix = '.'.$sourceLocale.'.yml';
        $files = [];

        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($basePath, \FilesystemIterator::SKIP_DOTS)
        );

        foreach ($iterator as $file) {
            if (str_ends_with($file->getFilename(), $suffix)) {
                $files[] = $file->getPathname();
            }
        }

        sort($files);

        return $files;
    }
}
