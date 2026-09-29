<?php

namespace Wexample\SymfonyTranslations\Service;

use Wexample\SymfonyTranslations\Interface\TextTranslatorInterface;

/**
 * Stands in until a real engine is plugged: every text comes back as it went in.
 *
 * The whole mechanism runs through it — files written, rows stored, sources
 * hashed — and what it produced is marked with its engine name, so the first run
 * with a real engine replaces it without having to be forced.
 */
class PendingTextTranslator implements TextTranslatorInterface
{
    final public const string ENGINE_NAME = 'pending';

    public function translate(
        array $texts,
        string $sourceLocale,
        string $targetLocale
    ): array {
        // A real engine replaces this one by aliasing the interface: syrtis/symfony-engine
        // does it when its translation is enabled.
        return $texts;
    }

    public function getEngineName(): string
    {
        return self::ENGINE_NAME;
    }
}
