<?php

namespace Wexample\SymfonyTranslations\DependencyInjection;

use Symfony\Component\Config\Definition\Builder\TreeBuilder;
use Symfony\Component\Config\Definition\ConfigurationInterface;

class Configuration implements ConfigurationInterface
{
    /**
     * Paths reached by a program rather than read by someone: they keep their
     * url whatever the language, and take the locale from the cookie or the
     * Accept-Language header instead.
     */
    final public const array DEFAULT_LOCALE_EXCLUDED_PATHS = [
        '^/_',
        '^/api(/|$)',
        '^/js/routing',
        '^/favicon\.ico$',
        '^/robots\.txt$',
    ];

    public function getConfigTreeBuilder(): TreeBuilder
    {
        $treeBuilder = new TreeBuilder('wexample_symfony_translations');

        $treeBuilder->getRootNode()
            ->children()
                ->arrayNode('translations_paths')
                    ->scalarPrototype()->end()
                ->end()
                // Prefixes every page url with /{_locale}, the locales being framework.enabled_locales.
                ->arrayNode('locale_routing')
                    ->canBeEnabled()
                    ->children()
                        ->arrayNode('excluded_paths')
                            ->scalarPrototype()->end()
                            ->defaultValue(self::DEFAULT_LOCALE_EXCLUDED_PATHS)
                        ->end()
                    ->end()
                ->end()
            ->end();

        return $treeBuilder;
    }
}
