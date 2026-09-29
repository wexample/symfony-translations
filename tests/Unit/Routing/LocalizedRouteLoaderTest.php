<?php

namespace Wexample\SymfonyTranslations\Tests\Unit\Routing;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Config\Loader\LoaderInterface;
use Symfony\Component\Routing\Route;
use Symfony\Component\Routing\RouteCollection;
use Wexample\SymfonyTranslations\DependencyInjection\Configuration;
use Wexample\SymfonyTranslations\Routing\LocalizedRouteLoader;
use Wexample\SymfonyTranslations\Service\LocaleService;

class LocalizedRouteLoaderTest extends TestCase
{
    public function testPrefixesPagesAndLeavesProgramPathsAlone(): void
    {
        $collection = $this->load(true);

        $this->assertSame('/{_locale}/', $collection->get('home')->getPath());
        $this->assertSame('/{_locale}/design-system/tables', $collection->get('page')->getPath());
        $this->assertSame('en|fr', $collection->get('page')->getRequirement('_locale'));
        $this->assertSame('en', $collection->get('page')->getDefault('_locale'));

        $this->assertSame('/api/article/list', $collection->get('api')->getPath());
        $this->assertSame('/_forms/submit/{name}', $collection->get('system')->getPath());
        $this->assertSame('/robots.txt', $collection->get('robots')->getPath());

        $this->assertSame('/', $collection->get(LocalizedRouteLoader::ROUTE_ROOT_REDIRECT)->getPath());
    }

    public function testChangesNothingWhenDisabled(): void
    {
        $collection = $this->load(false);

        $this->assertSame('/design-system/tables', $collection->get('page')->getPath());
        $this->assertNull($collection->get(LocalizedRouteLoader::ROUTE_ROOT_REDIRECT));
    }

    private function load(bool $enabled): RouteCollection
    {
        $collection = new RouteCollection();
        $collection->add('home', new Route('/'));
        $collection->add('page', new Route('/design-system/tables'));
        $collection->add('api', new Route('/api/article/list'));
        $collection->add('system', new Route('/_forms/submit/{name}'));
        $collection->add('robots', new Route('/robots.txt'));

        $inner = $this->createStub(LoaderInterface::class);
        $inner->method('load')->willReturn($collection);

        $loader = new LocalizedRouteLoader(
            $inner,
            new LocaleService('en', ['en', 'fr'], $enabled, Configuration::DEFAULT_LOCALE_EXCLUDED_PATHS)
        );

        return $loader->load('kernel::loadRoutes', 'service');
    }
}
