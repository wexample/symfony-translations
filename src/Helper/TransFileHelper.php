<?php

namespace Wexample\SymfonyTranslations\Helper;

use Symfony\Component\Yaml\Yaml;

/**
 * Reads and writes `<element>.trans.yml`, the file holding every language of an
 * element, one top-level block per locale:
 *
 *     en:
 *       title: Hello
 *     fr:
 *       title: Bonjour
 *
 * A block is changed as text, the others left byte for byte as they are: the
 * source block is written by hand, with its comments and layout.
 */
class TransFileHelper
{
    final public const string SUFFIX = '.trans.yml';

    final public const int DUMP_INLINE = 20;

    final public const int DUMP_INDENT = 2;

    /**
     * @return array<string, string> The text of each block by locale, in file order;
     *                               what comes before the first block under ''
     */
    public static function splitBlocks(string $content): array
    {
        $blocks = ['' => ''];
        $locale = '';

        foreach (preg_split('/(?<=\n)/', $content, -1, PREG_SPLIT_NO_EMPTY) as $line) {
            if (preg_match('/^([\'"]?)([A-Za-z][\w-]*)\1\s*:/', $line, $matches)) {
                $locale = $matches[2];
                $blocks[$locale] = '';
            }

            $blocks[$locale] .= $line;
        }

        return $blocks;
    }

    /**
     * @param array|null $tree Null removes the block
     */
    public static function writeBlock(
        string $content,
        string $locale,
        ?array $tree
    ): string {
        return self::writeBlockText($content, $locale, null === $tree ? null : self::dump($locale, $tree));
    }

    /**
     * @param string|null $text The block, its `<locale>:` line included; null removes it
     */
    public static function writeBlockText(
        string $content,
        string $locale,
        ?string $text
    ): string {
        $blocks = self::splitBlocks($content);

        if (null === $text) {
            unset($blocks[$locale]);

            return implode('', $blocks);
        }

        if (isset($blocks[$locale])) {
            // The blank lines separating it from the next block stay.
            $body = rtrim($blocks[$locale]);
            $blocks[$locale] = rtrim($text).(substr($blocks[$locale], strlen($body)) ?: "\n");
        } else {
            $last = array_key_last($blocks);
            if ('' !== $blocks[$last] && ! str_ends_with($blocks[$last], "\n\n")) {
                $blocks[$last] = rtrim($blocks[$last])."\n\n";
            }

            $blocks[$locale] = $text;
        }

        return implode('', $blocks);
    }

    /**
     * A per-locale file as a block, its text indented under the locale so that
     * comments and layout survive, or dumped when indenting would change its data.
     */
    public static function fileToBlock(
        string $locale,
        string $fileContent
    ): string {
        $data = Yaml::parse($fileContent) ?? [];
        $lines = preg_split('/(?<=\n)/', rtrim($fileContent)."\n", -1, PREG_SPLIT_NO_EMPTY);
        $text = $locale.":\n".implode('', array_map(
            static fn (string $line): string => '' === trim($line) ? "\n" : str_repeat(' ', self::DUMP_INDENT).$line,
            $lines
        ));

        return (Yaml::parse($text)[$locale] ?? []) == $data && [] !== $data ? $text : self::dump($locale, $data);
    }

    /**
     * A block as a per-locale file: the reverse of fileToBlock().
     */
    public static function blockToFile(
        string $locale,
        string $blockText
    ): string {
        $data = Yaml::parse($blockText)[$locale] ?? [];
        $lines = array_slice(preg_split('/(?<=\n)/', rtrim($blockText)."\n", -1, PREG_SPLIT_NO_EMPTY), 1);
        $indent = null;

        foreach ($lines as $line) {
            if ('' !== trim($line)) {
                $indent = min($indent ?? PHP_INT_MAX, strlen($line) - strlen(ltrim($line, ' ')));
            }
        }

        $text = implode('', array_map(
            static fn (string $line): string => '' === trim($line) ? "\n" : substr($line, min($indent, strlen($line) - strlen(ltrim($line, ' ')))),
            $lines
        ));

        return (Yaml::parse($text) ?? []) == $data && [] !== $data
            ? $text
            : Yaml::dump($data, self::DUMP_INLINE, self::DUMP_INDENT, Yaml::DUMP_MULTI_LINE_LITERAL_BLOCK);
    }

    private static function dump(
        string $locale,
        array $tree
    ): string {
        return Yaml::dump([$locale => $tree], self::DUMP_INLINE, self::DUMP_INDENT, Yaml::DUMP_MULTI_LINE_LITERAL_BLOCK);
    }
}
