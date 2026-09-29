<?php

namespace Wexample\SymfonyTranslations\Service;

use Wexample\SymfonyTranslations\Helper\TranslationPlaceholderHelper;
use Wexample\SymfonyTranslations\Interface\TextTranslatorInterface;

/**
 * The one way in to the translation engine, for interface files and entity
 * content alike: placeholders are masked on the way out and restored on the way
 * back, and texts with no words in them never reach the engine.
 */
class TextTranslationService
{
    public function __construct(
        private readonly TextTranslatorInterface $textTranslator,
    ) {
    }

    /**
     * @param array<array-key, string> $texts
     * @return array<array-key, string> Under the keys of $texts
     */
    public function translate(
        array $texts,
        string $sourceLocale,
        string $targetLocale
    ): array {
        $translations = $texts;
        $masked = [];
        $placeholders = [];

        foreach ($texts as $key => $text) {
            if (! preg_match('/\p{L}/u', $text)) {
                continue;
            }

            [$masked[$key], $placeholders[$key]] = TranslationPlaceholderHelper::mask($text);
        }

        if (empty($masked) || $sourceLocale === $targetLocale) {
            return $translations;
        }

        foreach ($this->textTranslator->translate($masked, $sourceLocale, $targetLocale) as $key => $translation) {
            $translations[$key] = TranslationPlaceholderHelper::unmask($translation, $placeholders[$key]);
        }

        return $translations;
    }

    public function getEngineName(): string
    {
        return $this->textTranslator->getEngineName();
    }

    /**
     * What a translation is checked against: the source it was made from.
     */
    public static function hashSource(string $source): string
    {
        return hash('xxh128', $source);
    }
}
