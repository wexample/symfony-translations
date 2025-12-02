<?php

namespace Wexample\SymfonyTranslations\Command;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Helper\Table;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

class CatalogueCommand extends AbstractTranslationCommand
{
    protected static $defaultDescription = 'Display all translations for a specific locale and optionally filter by domain';

    protected function configure(): void
    {
        parent::configure();

        $this
            ->addArgument('locale', InputArgument::REQUIRED, 'The locale to display translations for')
            ->addOption('domain', 'd', InputOption::VALUE_OPTIONAL, 'The domain to filter by');
    }

    protected function execute(
        InputInterface $input,
        OutputInterface $output
    ): int {
        $io = new SymfonyStyle($input, $output);
        $locale = $input->getArgument('locale');
        $domain = $input->getOption('domain');

        return $this->displayTranslations($io, $locale, $domain);
    }

    private function displayTranslations(
        SymfonyStyle $io,
        string $locale,
        ?string $domain
    ): int {
        $catalogue = $this->translator->getCatalogue($locale);

        if (! $catalogue) {
            $io->error(sprintf('Catalogue for locale "%s" not found', $locale));

            return Command::FAILURE;
        }

        $allDomains = $domain ? [$domain => $catalogue->all($domain)] : $catalogue->all();

        if (empty($allDomains)) {
            $io->warning(sprintf('No translations found for locale "%s"%s', $locale, $domain ? sprintf(' and domain "%s"', $domain) : ''));

            return Command::SUCCESS;
        }

        $io->title(sprintf('Translations for Locale "%s"%s', $locale, $domain ? sprintf(' and Domain "%s"', $domain) : ''));

        foreach ($allDomains as $domainName => $translations) {
            if (empty($translations)) {
                continue;
            }

            $io->section(sprintf('Domain: %s', $domainName));
            $this->displayTranslationsTable($io, $translations);
        }

        return Command::SUCCESS;
    }

    protected function displayTranslationsTable(
        SymfonyStyle $io,
        array $translations
    ): void {
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
}
