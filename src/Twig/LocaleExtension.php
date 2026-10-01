<?php

namespace Wexample\SymfonyTranslations\Twig;

use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Twig\TwigFunction;
use Wexample\SymfonyHelpers\Twig\AbstractExtension;
use Wexample\SymfonyTranslations\Service\LocaleService;

class LocaleExtension extends AbstractExtension
{
    public function __construct(
        private readonly LocaleService $localeService,
        private readonly RequestStack $requestStack,
        private readonly UrlGeneratorInterface $urlGenerator,
    ) {
    }

    public function getFunctions(): array
    {
        return [
            new TwigFunction(
                'locales',
                [
                    $this,
                    'locales',
                ]
            ),
            new TwigFunction(
                'locale_direction',
                [
                    $this->localeService,
                    'getDirection',
                ]
            ),
        ];
    }

    /**
     * Every locale the application speaks, each with the url of the current
     * page in that language.
     *
     * @return array<int, array{code: string, name: string, dir: string, url: ?string, current: bool}>
     */
    public function locales(): array
    {
        $request = $this->requestStack->getMainRequest();
        $currentLocale = $request?->getLocale() ?? $this->localeService->getDefaultLocale();
        $locales = [];

        foreach ($this->localeService->getLocales() as $locale) {
            $locales[] = [
                'code' => $locale,
                'name' => $this->localeService->getLocaleName($locale),
                // Each name is written its own way, whatever the page's direction.
                'dir' => $this->localeService->getDirection($locale),
                'url' => $request ? $this->buildLocaleUrl($locale) : null,
                'current' => $locale === $currentLocale,
            ];
        }

        return $locales;
    }

    /**
     * Null when the current url carries no locale: an excluded path has no
     * other version to go to.
     */
    private function buildLocaleUrl(string $locale): ?string
    {
        $request = $this->requestStack->getMainRequest();

        // Without a prefix, the same page asked in that language.
        if ($this->localeService->isCookieEnabled()) {
            return $request->getBaseUrl().$request->getPathInfo().'?'.http_build_query(
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
            [LocaleService::LOCALE_ATTRIBUTE => $locale] + $routeParams
        ).(null !== $query ? '?'.$query : '');
    }
}
