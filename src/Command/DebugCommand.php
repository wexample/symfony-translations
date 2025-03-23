<?php

namespace Wexample\SymfonyTranslations\Command;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Helper\Table;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
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

    protected function configure(): void
    {
        $this
            ->addArgument('locale', InputArgument::REQUIRED, 'The locale to debug')
            ->addOption('domain', 'd', InputOption::VALUE_OPTIONAL, 'The domain to filter by')
            ->addOption('format', 'f', InputOption::VALUE_OPTIONAL, 'Output format (table, json)', 'table');
    }

    protected function execute(
        InputInterface $input,
        OutputInterface $output
    ): int
    {
        $io = new SymfonyStyle($input, $output);
        $locale = $input->getArgument('locale');
        $domain = $input->getOption('domain');
        $format = $input->getOption('format');

        return $this->displayTranslations($io, $locale, $domain, $format);
    }

    private function displayTranslations(
        SymfonyStyle $io,
        string $locale,
        ?string $domain,
        string $format
    ): int
    {
        $catalogue = $this->translator->getCatalogue($locale);

        if (!$catalogue) {
            $io->error(sprintf('Catalogue for locale "%s" not found', $locale));
            return Command::FAILURE;
        }

        $allDomains = $domain ? [$domain => $catalogue->all($domain)] : $catalogue->all();

        if (empty($allDomains)) {
            $io->warning(sprintf('No translations found for locale "%s"%s', $locale, $domain ? sprintf(' and domain "%s"', $domain) : ''));
            return Command::SUCCESS;
        }

        if ($format === 'json') {
            $io->writeln(json_encode($allDomains, JSON_PRETTY_PRINT));
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

    private function displayTranslationsTable(
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
