<?php

namespace Wexample\SymfonyTranslations\Command;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Helper\Table;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

class LocalesCommand extends AbstractTranslationCommand
{
    protected static $defaultDescription = 'List all available locales configured in the application';

    protected function execute(
        InputInterface $input,
        OutputInterface $output
    ): int
    {
        $io = new SymfonyStyle($input, $output);

        $locales = $this->translator->getAllLocales();

        if (empty($locales)) {
            $io->warning('No locales found');
            return Command::SUCCESS;
        }

        $io->title('Available Locales');

        $rows = [];
        $index = 1;

        foreach ($locales as $locale) {
            $rows[] = [
                $index++,
                $locale
            ];
        }

        $table = new Table($io);
        $table->setHeaders(['#', 'Locale']);
        $table->setRows($rows);
        $table->render();

        return Command::SUCCESS;
    }
}
