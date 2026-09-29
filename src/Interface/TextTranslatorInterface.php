<?php

namespace Wexample\SymfonyTranslations\Interface;

/**
 * What turns a text from one language into another: interface wording read from
 * the yml files as well as entity content.
 *
 * Texts come in batches, as an engine answers a list faster than the same list
 * one call at a time. Placeholders are already masked by the caller, so an engine
 * only has to leave the `[#n]` tokens untouched.
 */
interface TextTranslatorInterface
{
    /**
     * @param array<array-key, string> $texts
     * @return array<array-key, string> The translations, under the keys of $texts
     */
    public function translate(
        array $texts,
        string $sourceLocale,
        string $targetLocale
    ): array;

    /**
     * Recorded next to each translation, so that the ones made by an engine
     * since replaced can be told apart and redone.
     */
    public function getEngineName(): string;
}
