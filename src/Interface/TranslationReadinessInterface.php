<?php

namespace Wexample\SymfonyTranslations\Interface;

/**
 * Something whose words are not worth an engine's work yet: a draft, a record
 * waiting to be sorted. Read in another language while it is not ready, it
 * shows what was written by hand and what was already translated and still
 * holds, its own words for the rest — and nothing is sent to the engine, by a
 * page, the api or `translations:translate-content` alike. The first read once
 * it is ready translates what is missing.
 */
interface TranslationReadinessInterface
{
    public function isReadyForTranslation(): bool;
}
