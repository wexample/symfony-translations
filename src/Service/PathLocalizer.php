<?php

namespace Wexample\SymfonyTranslations\Service;

use Wexample\SymfonyHelpers\Interface\PathLocalizerInterface;

/**
 * A path as the localized routing serves it: under each language's prefix,
 * unless it is one of those kept as they are (`/_…`, `/api`, `/robots.txt`)
 * or the language is carried by a cookie rather than the url.
 */
class PathLocalizer implements PathLocalizerInterface
{
    public function __construct(
        private readonly LocaleService $localeService,
    ) {
    }

    public function localizePath(string $path): array
    {
        if (! $this->localeService->isRoutingEnabled()
            || $this->localeService->isCookieEnabled()
            || $this->localeService->isPathExcluded($path)) {
            return [$path];
        }

        return array_map(
            static fn (string $locale): string => '/'.$locale.$path,
            $this->localeService->getLocales()
        );
    }
}
