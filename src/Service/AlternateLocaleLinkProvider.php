<?php

namespace Wexample\SymfonyTranslations\Service;

use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Wexample\SymfonyHelpers\Interface\HeadLinkProviderInterface;

/**
 * Tells search engines the page exists in every language the application
 * speaks — `<link rel="alternate" hreflang>` — so each language is listed
 * for those who read it, not as copies of one another. `x-default` names the
 * version for a reader whose language is none of them.
 *
 * Only where the language is in the url: switched by a cookie, every version
 * shares one address, and there is nothing to point to.
 */
class AlternateLocaleLinkProvider implements HeadLinkProviderInterface
{
    public function __construct(
        private readonly LocaleService $localeService,
        private readonly LocaleUrlService $localeUrlService,
    ) {
    }

    public function getHeadLinks(): array
    {
        if ($this->localeService->isCookieEnabled()) {
            return [];
        }

        $links = [];

        foreach ($this->localeService->getLocales() as $locale) {
            $url = $this->localeUrlService->getLocaleUrl($locale, UrlGeneratorInterface::ABSOLUTE_URL);

            // Not a page that has versions: none of the locales will have one.
            if (null === $url) {
                return [];
            }

            $links[] = [
                'rel' => 'alternate',
                'hreflang' => $this->localeService->getLanguageTag($locale),
                'href' => $url,
            ];
        }

        $default = $this->localeUrlService->getLocaleUrl(
            $this->localeService->getDefaultLocale(),
            UrlGeneratorInterface::ABSOLUTE_URL
        );

        if (null !== $default) {
            $links[] = ['rel' => 'alternate', 'hreflang' => 'x-default', 'href' => $default];
        }

        return $links;
    }
}
