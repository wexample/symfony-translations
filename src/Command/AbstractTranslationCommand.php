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

    protected function displayTranslationsTable(
        SymfonyStyle $io,
        array $translations
    ): void
    {
        $rows = [];
        $index = 1;

        foreach ($translations as $key => $value) {
            $rows[] = [
                $index++,
                $key,
                is_array($value) ? json_encode($value) : $value,
            ];
        }

        if (empty($rows)) {
            $io->writeln('No translations found');
            return;
        }

        $table = new Table($io);
        $table->setHeaders(['#', 'Key', 'Value']);
        $table->setRows($rows);
        $table->render();
    }

    public static function getBundleClassName(): string
    {
        return WexampleSymfonyTranslationsBundle::class;
    }
}
