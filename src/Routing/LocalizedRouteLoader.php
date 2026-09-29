<?php

namespace Wexample\SymfonyTranslations\Routing;

use Symfony\Component\Config\Loader\LoaderInterface;
use Symfony\Component\Config\Loader\LoaderResolverInterface;
use Symfony\Component\Routing\Route;
use Symfony\Component\Routing\RouteCollection;
use Wexample\SymfonyTranslations\Controller\LocaleRedirectController;
use Wexample\SymfonyTranslations\Service\LocaleService;

/**
 * Puts every page url under /{_locale}, whichever bundle declared it.
 *
 * It decorates the root routing loader, so it sees the whole collection once it
 * is assembled: an application importing its controllers in one resource has no
 * way to prefix the pages and not the api from its own routes file. What stays
 * unprefixed is decided by path, following the rule that a path opening on an
 * underscore, or on /api, is reached by a program.
 */
final class LocalizedRouteLoader implements LoaderInterface
{
    final public const string ROUTE_ROOT_REDIRECT = 'locale_root_redirect';

    public function __construct(
        private readonly LoaderInterface $inner,
        private readonly LocaleService $localeService,
    ) {
    }

    public function load(
        mixed $resource,
        ?string $type = null
    ): mixed {
        $collection = $this->inner->load($resource, $type);

        if ($collection instanceof RouteCollection && $this->localeService->isRoutingEnabled()) {
            $this->localize($collection);
        }

        return $collection;
    }

    private function localize(RouteCollection $collection): void
    {
        $requirement = implode('|', array_map('preg_quote', $this->localeService->getLocales()));

        foreach ($collection->all() as $route) {
            $path = $route->getPath();

            if (str_contains($path, '{'.LocaleService::LOCALE_ATTRIBUTE.'}')
                || $this->localeService->isPathExcluded($path)) {
                continue;
            }

            $route->setPath('/{'.LocaleService::LOCALE_ATTRIBUTE.'}'.('/' === $path ? '/' : $path));
            $route->setRequirement(LocaleService::LOCALE_ATTRIBUTE, $requirement);
            // Lets a url be generated outside any request, from a command or a message handler.
            $route->setDefault(LocaleService::LOCALE_ATTRIBUTE, $this->localeService->getDefaultLocale());
        }

        $collection->add(
            self::ROUTE_ROOT_REDIRECT,
            new Route('/', [
                '_controller' => LocaleRedirectController::class.'::root',
            ], methods: ['GET', 'HEAD'])
        );
    }

    public function supports(
        mixed $resource,
        ?string $type = null
    ): bool {
        return $this->inner->supports($resource, $type);
    }

    public function getResolver(): LoaderResolverInterface
    {
        return $this->inner->getResolver();
    }

    public function setResolver(LoaderResolverInterface $resolver): void
    {
        $this->inner->setResolver($resolver);
    }
}
