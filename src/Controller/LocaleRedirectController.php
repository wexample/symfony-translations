<?php

namespace Wexample\SymfonyTranslations\Controller;

use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Wexample\SymfonyTranslations\Service\LocaleService;

/**
 * The site root has no language: it sends to the home page of the one the
 * visitor last chose, or of the one the browser asks for.
 */
final class LocaleRedirectController
{
    public function __construct(
        private readonly LocaleService $localeService,
    ) {
    }

    public function root(Request $request): RedirectResponse
    {
        return new RedirectResponse(
            $request->getBasePath().'/'.$this->localeService->guessLocale($request).'/'
        );
    }
}
