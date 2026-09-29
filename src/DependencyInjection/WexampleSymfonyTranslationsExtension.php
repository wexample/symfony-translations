<?php

namespace Wexample\SymfonyTranslations\DependencyInjection;

use Symfony\Component\DependencyInjection\ContainerBuilder;
use Wexample\SymfonyHelpers\DependencyInjection\AbstractWexampleSymfonyExtension;

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
            'wexample_symfony_translations.locale_routing.enabled',
            $config['locale_routing']['enabled']
        );
        $container->setParameter(
            'wexample_symfony_translations.locale_routing.excluded_paths',
            $config['locale_routing']['excluded_paths']
        );
    }
}
