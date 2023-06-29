<?php

namespace Wexample\SymfonyTranslations\DependencyInjection;

use Symfony\Component\DependencyInjection\ContainerBuilder;
use Wexample\SymfonyHelpers\DependencyInjection\AbstractWexampleSymfonyExtension;

class WexampleSymfonyTranslationsExtension extends AbstractWexampleSymfonyExtension
{
    public function load(
        array $configs,
        ContainerBuilder $container
    ) {
        $configuration = new Configuration();
        $paths = $this->processConfiguration($configuration, $configs);
        $container->setParameter('translations_paths', $paths['translations_paths']);
    }
}
