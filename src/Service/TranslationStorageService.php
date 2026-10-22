<?php

namespace Wexample\SymfonyTranslations\Service;

use Symfony\Component\Intl\Locales;
use Wexample\SymfonyTranslations\Helper\TransFileHelper;

/**
 * Moves elements between the two ways of storing their translations: one
 * `.<locale>.yml` per language, or every language in one `.trans.yml`.
 *
 * Nothing is translated nor lost: the text of each file becomes a block, or the
 * reverse, keeping its comments and layout. When an element has both, its
 * `.trans.yml` wins, as it is the one read. The lock needs no change, its
 * entries being named after the per-locale files either way.
 */
class TranslationStorageService
{
    final public const string STORAGE_LOCALE = 'locale';

    final public const string STORAGE_TRANS = 'trans';

    final public const array STORAGES = [self::STORAGE_LOCALE, self::STORAGE_TRANS];

    public function __construct(
        private readonly TranslationFileService $translationFileService,
    ) {
    }

    /**
     * @param string $storage One of STORAGES
     * @param string|null $firstLocale The block written first into a new `.trans.yml`, the source one
     * @param callable(string $element, string[] $locales): void|null $onElement
     * @return int The elements converted, or to convert on a dry run
     */
    public function convert(
        string $storage,
        ?string $firstLocale = null,
        ?string $pathFilter = null,
        bool $includeBundles = false,
        bool $dryRun = false,
        ?callable $onElement = null,
    ): int {
        $count = 0;

        foreach ($this->translationFileService->getBasePaths($includeBundles) as $basePath) {
            foreach ($this->findElements($basePath) as $element => $localeFiles) {
                if (null !== $pathFilter && ! str_contains($element, $pathFilter)) {
                    continue;
                }

                $locales = self::STORAGE_TRANS === $storage
                    ? $this->convertToTrans($element, $localeFiles, $firstLocale, $dryRun)
                    : $this->convertToLocaleFiles($element, $dryRun);

                if ([] !== $locales) {
                    $count++;

                    if ($onElement) {
                        $onElement($element, $locales);
                    }
                }
            }
        }

        return $count;
    }

    /**
     * @param array<string, string> $localeFiles
     * @return string[] The locales moved into the `.trans.yml`
     */
    private function convertToTrans(
        string $element,
        array $localeFiles,
        ?string $firstLocale,
        bool $dryRun
    ): array {
        if ([] === $localeFiles) {
            return [];
        }

        $transPath = $element.TransFileHelper::SUFFIX;
        $content = is_file($transPath) ? file_get_contents($transPath) : '';
        $blocks = TransFileHelper::splitBlocks($content);

        if (null !== $firstLocale && isset($localeFiles[$firstLocale])) {
            $localeFiles = [$firstLocale => $localeFiles[$firstLocale]] + $localeFiles;
        }

        foreach ($localeFiles as $locale => $path) {
            if (! isset($blocks[$locale])) {
                $content = TransFileHelper::writeBlockText($content, $locale, TransFileHelper::fileToBlock($locale, file_get_contents($path)));
            }
        }

        if (! $dryRun) {
            file_put_contents($transPath, $content);
            array_map('unlink', $localeFiles);
        }

        return array_keys($localeFiles);
    }

    /**
     * @return string[] The locales moved out of the `.trans.yml`
     */
    private function convertToLocaleFiles(
        string $element,
        bool $dryRun
    ): array {
        $transPath = $element.TransFileHelper::SUFFIX;

        if (! is_file($transPath)) {
            return [];
        }

        $blocks = TransFileHelper::splitBlocks(file_get_contents($transPath));
        unset($blocks['']);

        if (! $dryRun) {
            foreach ($blocks as $locale => $text) {
                file_put_contents($element.'.'.$locale.'.yml', TransFileHelper::blockToFile($locale, $text));
            }

            unlink($transPath);
        }

        return array_keys($blocks);
    }

    /**
     * Every element having translation files, with its per-locale ones. A
     * `.<name>.yml` counts only when the name is a locale: `.ai.yml` and the
     * like belong to other tools.
     *
     * @return array<string, array<string, string>> The per-locale files by locale, by element
     */
    private function findElements(string $basePath): array
    {
        $elements = [];

        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($basePath, \FilesystemIterator::SKIP_DOTS)
        );

        foreach ($iterator as $file) {
            $path = $file->getPathname();

            if (str_ends_with($path, TransFileHelper::SUFFIX)) {
                $elements[substr($path, 0, -strlen(TransFileHelper::SUFFIX))] ??= [];
            } elseif (preg_match('/^(.+)\.([A-Za-z]{2,3}(?:_[A-Za-z0-9]+)*)\.yml$/', $path, $matches)
                && Locales::exists($matches[2])) {
                $elements[$matches[1]][$matches[2]] = $path;
            }
        }

        ksort($elements);

        return array_map(static function (array $files): array {
            ksort($files);

            return $files;
        }, $elements);
    }
}
