<?php

namespace Wexample\SymfonyTranslations\Command;

use Wexample\SymfonyHelpers\Command\AbstractBundleCommand;
use Wexample\SymfonyHelpers\Service\BundleService;
use Wexample\SymfonyTranslations\Translation\Translator;
use Wexample\SymfonyTranslations\WexampleSymfonyTranslationsBundle;

class DebugCommand extends AbstractBundleCommand
{
    protected static $defaultDescription = 'Debug translations';

    public function __construct(
        protected readonly Translator $translator,
        BundleService $bundleService,
        string $name = null,
    )
    {
        parent::__construct(
            $bundleService,
            $name
        );
    }


    public static function getBundleClassName(): string
    {
        return WexampleSymfonyTranslationsBundle::class;
    }
}
