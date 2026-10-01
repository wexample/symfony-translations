<?php

namespace Wexample\SymfonyTranslations\Interface;

/**
 * Something that speaks a language of its own, known beyond a request: an
 * account whose mails are sent while another user browses, or a worker runs.
 */
interface HasLocaleInterface
{
    /**
     * @return string|null null while unknown: the default locale applies
     */
    public function getLocale(): ?string;
}
