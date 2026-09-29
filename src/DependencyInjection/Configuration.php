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

    final public const string DEFAULT_SYRTIS_HOST = 'https://api.syrtis.ai';

    /**
     * Characters of texts sent to Syrtis in one request: the model answers
     * them in one go, so a batch must fit its output.
     */
    final public const int DEFAULT_SYRTIS_MAX_BATCH_LENGTH = 6000;

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
                // Translates through a session on a Syrtis translation scenario.
                ->arrayNode('syrtis')
                    ->canBeEnabled()
                    ->children()
                        // The Syrtis API, or a local Syrtis answering the same routes.
                        ->scalarNode('host')
                            ->defaultValue(self::DEFAULT_SYRTIS_HOST)
                            ->cannotBeEmpty()
                        ->end()
                        ->scalarNode('api_key')
                            ->isRequired()
                            ->cannotBeEmpty()
                        ->end()
                        ->scalarNode('session_secure_id')
                            ->isRequired()
                            ->cannotBeEmpty()
                        ->end()
                        ->integerNode('max_batch_length')
                            ->defaultValue(self::DEFAULT_SYRTIS_MAX_BATCH_LENGTH)
                            ->min(1)
                        ->end()
                    ->end()
                ->end()
            ->end();

        return $treeBuilder;
    }
}
