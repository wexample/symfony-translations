<?php

namespace Wexample\SymfonyTranslations\Develop;

use Wexample\SymfonyLoader\Interface\DevelopTabInterface;

class DevelopTranslationsTab implements DevelopTabInterface
{
    public function getId(): string
    {
        return 'translations';
    }

    public function getLabel(): string
    {
        return 'Translations';
    }

    public function getComponentPath(): string
    {
        return '@WexampleSymfonyTranslationsBundle/components/develop-translations';
    }
}
