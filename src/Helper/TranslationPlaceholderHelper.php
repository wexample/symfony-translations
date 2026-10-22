<?php

namespace Wexample\SymfonyTranslations\Helper;

/**
 * Hides from a translation engine what is not language: parameters and markup.
 *
 * `%name%`, `{count}`, `{{ name }}` and html tags are replaced by `[#n]` tokens
 * before the text leaves, then put back in the translation. An engine that
 * translated `%count%` into `%nombre%` would break the call passing `count`.
 */
class TranslationPlaceholderHelper
{
    final public const string PATTERN = '/%[\w.-]+%|\{\{\s*[\w.]+\s*\}\}|\{[\w.]+\}|<[^>]+>/u';

    /**
     * @return array{0: string, 1: string[]} The masked text, and the placeholders by token number
     */
    public static function mask(string $text): array
    {
        $placeholders = [];

        $masked = preg_replace_callback(
            self::PATTERN,
            static function (array $match) use (&$placeholders): string {
                $placeholders[] = $match[0];

                return '[#'.(count($placeholders) - 1).']';
            },
            $text
        );

        return [$masked, $placeholders];
    }

    /**
     * @param string[] $placeholders
     */
    public static function unmask(
        string $text,
        array $placeholders
    ): string {
        return preg_replace_callback(
            '/\[#(\d+)\]/',
            static fn (array $match): string => $placeholders[(int) $match[1]] ?? $match[0],
            $text
        );
    }
}
