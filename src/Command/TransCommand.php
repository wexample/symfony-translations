<?php

namespace Wexample\SymfonyTranslations\Command;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

class TransCommand extends AbstractTranslationCommand
{
    protected static $defaultDescription = 'Translate a key in a specific locale with optional parameters and domain';

    protected function configure(): void
    {
        parent::configure();

        $this
            ->addArgument('locale', InputArgument::REQUIRED, 'The locale to use for translation')
            ->addArgument('key', InputArgument::REQUIRED, 'The translation key to translate')
            ->addOption('domain', 'd', InputOption::VALUE_OPTIONAL, 'The domain to use for translation', 'messages')
            ->addOption('parameters', 'p', InputOption::VALUE_OPTIONAL, 'JSON encoded parameters for translation placeholders', '{}');
    }

    protected function execute(
        InputInterface $input,
        OutputInterface $output
    ): int {
        $io = new SymfonyStyle($input, $output);
        $key = $input->getArgument('key');
        $locale = $input->getArgument('locale');
        $domain = $input->getOption('domain');
        $parametersJson = $input->getOption('parameters');

        try {
            $parameters = json_decode($parametersJson, true, 512, JSON_THROW_ON_ERROR);
            if (! is_array($parameters)) {
                $parameters = [];
            }
        } catch (\JsonException $e) {
            $io->error('Invalid JSON for parameters: ' . $e->getMessage());

            return Command::FAILURE;
        }

        // Check if the locale exists
        $availableLocales = $this->translator->getAllLocales();
        if (! in_array($locale, $availableLocales)) {
            $io->error(sprintf('Locale "%s" not found. Available locales: %s', $locale, implode(', ', $availableLocales)));

            return Command::FAILURE;
        }

        // Translate the key
        $translation = $this->translator->trans($key, $parameters, $domain, $locale);

        // If the translation is the same as the key, it might not be translated
        if ($translation === $key) {
            $io->note(sprintf('No translation found for key "%s" in domain "%s" for locale "%s"', $key, $domain, $locale));
        }

        $io->title(sprintf('Translation for key "%s" in locale "%s"', $key, $locale));
        $io->section('Domain: ' . $domain);
        $io->writeln($translation);

        return Command::SUCCESS;
    }
}
