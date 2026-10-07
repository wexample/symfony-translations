<?php

namespace Wexample\SymfonyTranslations\Exception;

use RuntimeException;

/**
 * A `domain::key` that no locale of the chain defines, met while the
 * translator is strict: in tests, where a key printed raw is a bug to stop
 * on, with the key that caused it.
 */
class MissingTranslationException extends RuntimeException
{
    /**
     * @param string[] $locales the locale asked for, then its fallbacks
     */
    public static function create(
        string $id,
        ?string $domain,
        array $locales
    ): self {
        return new self(
            null === $domain
                ? sprintf('Translation "%s": its domain is not set here.', $id)
                : sprintf(
                    'Translation "%s" is missing from domain "%s" in %s.',
                    $id,
                    $domain,
                    implode(', ', $locales)
                )
        );
    }
}
