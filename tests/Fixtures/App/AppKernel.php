<?php

namespace Wexample\SymfonyTranslations\Tests\Fixtures\App;

use Wexample\SymfonyTesting\Tests\Fixtures\AbstractFixtureKernel;

class AppKernel extends AbstractFixtureKernel
{
    protected function getFixtureDir(): string
    {
        return __DIR__;
    }

    protected function getExtraBundles(): iterable
    {
        return [
            new \Wexample\SymfonyTranslations\WexampleSymfonyTranslationsBundle(),
        ];
    }

    protected function getConfigFiles(): array
    {
        return [
            __DIR__ . '/config/config.yaml',
        ];
    }

    protected function getRoutesControllersDir(): ?string
    {
        return __DIR__ . '/Controller/';
    }
}

