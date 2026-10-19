<?php

namespace Wexample\SymfonyTranslations\Service;

use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

/**
 * The current page in another language: what the language menu links to,
 * and what a search engine is told the page also exists as.
 */
class LocaleUrlService
{
    public function __construct(
        private readonly LocaleService $localeService,
        private readonly RequestStack $requestStack,
        private readonly UrlGeneratorInterface $urlGenerator,
    ) {
    }

    /**
     * Null when there is no request, or when the current url carries no
     * locale: an excluded path has no other version to go to.
     *
     * @param int $referenceType one of UrlGeneratorInterface's
     */
    public function getLocaleUrl(
        string $locale,
        int $referenceType = UrlGeneratorInterface::ABSOLUTE_PATH,
    ): ?string {
        $request = $this->requestStack->getMainRequest();

        if (! $request) {
            return null;
        }

        // Without a prefix, the same page asked in that language.
        if ($this->localeService->isCookieEnabled()) {
            $base = UrlGeneratorInterface::ABSOLUTE_URL === $referenceType
                ? $request->getSchemeAndHttpHost()
                : '';

            return $base.$request->getBaseUrl().$request->getPathInfo().'?'.http_build_query(
                [LocaleService::LOCALE_ATTRIBUTE => $locale] + $request->query->all()
            );
        }

        $routeParams = $request->attributes->get('_route_params', []);

        if (! array_key_exists(LocaleService::LOCALE_ATTRIBUTE, $routeParams)) {
            return null;
        }

        $query = $request->getQueryString();

        return $this->urlGenerator->generate(
            $request->attributes->get('_route'),
            [LocaleService::LOCALE_ATTRIBUTE => $locale] + $routeParams,
            $referenceType
        ).(null !== $query ? '?'.$query : '');
    }
}
