<?php

namespace Wexample\SymfonyTranslations\DependencyInjection;

use Symfony\Component\Config\Definition\Builder\TreeBuilder;
use Symfony\Component\Config\Definition\ConfigurationInterface;
use Wexample\SymfonyTranslations\Service\TranslationStorageService;

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

    /** Seconds a translation request may take: a batch is answered in one model call. */
    final public const int DEFAULT_SYRTIS_TIMEOUT = 180;

    final public const int DEFAULT_SYRTIS_CONNECT_TIMEOUT = 10;

    public function getConfigTreeBuilder(): TreeBuilder
    {
        $treeBuilder = new TreeBuilder('wexample_symfony_translations');

        $treeBuilder->getRootNode()
            ->children()
                ->arrayNode('translations_paths')
                    ->scalarPrototype()->end()
                ->end()
                // Languages entity content may be read in beyond framework.enabled_locales,
                // those having an interface: through the api, or translated() given a locale.
                ->arrayNode('content_locales')
                    ->scalarPrototype()->end()
                    ->defaultValue([])
                ->end()
                // How the interface files are stored: `locale`, one .<locale>.yml per language,
                // or `trans`, every language of an element in its .trans.yml. translations:translate-files
                // converts the elements it writes into it; left unset, each element keeps its own.
                ->scalarNode('storage')
                    ->defaultNull()
                    ->validate()
                        ->ifNotInArray([null, ...TranslationStorageService::STORAGES])
                        ->thenInvalid('Expected '.implode(' or ', TranslationStorageService::STORAGES).', got %s.')
                    ->end()
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
                // Without a prefix: a page asked with ?_locale= switches the
                // language, a cookie keeps it, the browser's says it otherwise.
                ->arrayNode('locale_cookie')
                    ->canBeEnabled()
                ->end()
                // A `domain::key` no locale defines throws a MissingTranslationException
                // instead of being printed as is. Left unset, on in the test environment:
                // every page a test renders is checked.
                ->booleanNode('strict')
                    ->defaultNull()
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
                        // A request left unanswered fails after it instead of holding the run forever.
                        ->integerNode('timeout')
                            ->defaultValue(self::DEFAULT_SYRTIS_TIMEOUT)
                            ->min(1)
                        ->end()
                        ->integerNode('connect_timeout')
                            ->defaultValue(self::DEFAULT_SYRTIS_CONNECT_TIMEOUT)
                            ->min(1)
                        ->end()
                    ->end()
                ->end()
            ->end();

        return $treeBuilder;
    }
}
