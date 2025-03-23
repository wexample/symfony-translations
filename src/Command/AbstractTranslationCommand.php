<?php

namespace Wexample\SymfonyTranslations\Command;

use Symfony\Component\Console\Helper\Table;
use Symfony\Component\Console\Style\SymfonyStyle;
use Wexample\SymfonyHelpers\Command\AbstractBundleCommand;
use Wexample\SymfonyHelpers\Service\BundleService;
use Wexample\SymfonyTranslations\Translation\Translator;
use Wexample\SymfonyTranslations\WexampleSymfonyTranslationsBundle;

abstract class AbstractTranslationCommand extends AbstractBundleCommand
{
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
