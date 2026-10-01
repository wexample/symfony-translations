<?php

namespace Wexample\SymfonyTranslations\DependencyInjection;

use GuzzleHttp\Client as GuzzleClient;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;
use SyrtisClient\Common\SyrtisClient;
use Wexample\SymfonyHelpers\DependencyInjection\AbstractWexampleSymfonyExtension;
use Wexample\SymfonyTranslations\Interface\TextTranslatorInterface;
use Wexample\SymfonyTranslations\Translation\SyrtisTextTranslator;

class WexampleSymfonyTranslationsExtension extends AbstractWexampleSymfonyExtension
{
    public function load(
        array $configs,
        ContainerBuilder $container
    ): void {
        $this->loadConfig(
            __DIR__,
            $container
        );

        $configuration = new Configuration();
        $config = $this->processConfiguration($configuration, $configs);
        $existing = $container->hasParameter('translations_paths')
            ? (array) $container->getParameter('translations_paths')
            : [];
        $container->setParameter('translations_paths', array_merge($existing, $config['translations_paths']));

        $container->setParameter(
            'wexample_symfony_translations.content_locales',
            $config['content_locales']
        );
        $container->setParameter(
            'wexample_symfony_translations.storage',
            $config['storage']
        );
        $container->setParameter(
            'wexample_symfony_translations.locale_routing.enabled',
            $config['locale_routing']['enabled']
        );
        $container->setParameter(
            'wexample_symfony_translations.locale_routing.excluded_paths',
            $config['locale_routing']['excluded_paths']
        );
        $container->setParameter(
            'wexample_symfony_translations.locale_cookie.enabled',
            $config['locale_cookie']['enabled']
        );

        if ($config['syrtis']['enabled']) {
            $this->registerSyrtisTextTranslator($container, $config['syrtis']);
        }
    }

    /**
     * Syrtis replaces the pending engine. Its client is the translator's own,
     * so that an application's Syrtis client, if any, keeps its configuration.
     */
    private function registerSyrtisTextTranslator(
        ContainerBuilder $container,
        array $config
    ): void {
        $container
            ->setDefinition(SyrtisTextTranslator::class, new Definition(SyrtisTextTranslator::class))
            ->setArguments([
                '$client' => (new Definition(SyrtisClient::class))->setArguments([
                    '$host' => $config['host'],
                    '$apiKey' => $config['api_key'],
                    // The Syrtis client waits indefinitely by default, and gives no
                    // way to say otherwise: its http client is given ready-made,
                    // with the base uri the Syrtis client would have given its own.
                    '$httpClient' => new Definition(GuzzleClient::class, [[
                        'base_uri' => rtrim($config['host'], '/').'/api/'.SyrtisClient::API_VERSION_DEFAULT.'/',
                        'timeout' => $config['timeout'],
                        'connect_timeout' => $config['connect_timeout'],
                    ]]),
                ]),
                '$sessionSecureId' => $config['session_secure_id'],
                '$maxBatchLength' => $config['max_batch_length'],
            ]);

        $container->setAlias(TextTranslatorInterface::class, SyrtisTextTranslator::class);
    }
}
