<?php

namespace Wexample\SymfonyTranslations\Command;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Wexample\SymfonyHelpers\Service\BundleService;
use Wexample\SymfonyTranslations\Service\LocaleService;
use Wexample\SymfonyTranslations\Service\TranslationStorageService;
use Wexample\SymfonyTranslations\Translation\Translator;

/**
 * Moves the interface files to one way of storing them: `trans`, every language
 * of an element in its `.trans.yml`, or `locale`, one `.<locale>.yml` each.
 */
class ConvertFilesCommand extends AbstractTranslationCommand
{
    protected static $defaultDescription = 'Store the interface translations in one .trans.yml per element, or in one .<locale>.yml per language';

    public function __construct(
        Translator $translator,
        BundleService $bundleService,
        private readonly TranslationStorageService $translationStorageService,
        private readonly LocaleService $localeService,
    ) {
        parent::__construct($translator, $bundleService);
    }

    protected function configure(): void
    {
        parent::configure();

        $this
            ->addArgument('storage', InputArgument::REQUIRED, implode(' or ', TranslationStorageService::STORAGES))
            ->addOption('path', null, InputOption::VALUE_REQUIRED, 'Only the elements whose path contains this string')
            ->addOption('include-bundles', null, InputOption::VALUE_NONE, 'Also convert the translation directories of the bundles, which belong to their packages')
            ->addOption('dry-run', null, InputOption::VALUE_NONE, 'Report what would be converted, write nothing');
    }

    protected function execute(
        InputInterface $input,
        OutputInterface $output
    ): int {
        $io = new SymfonyStyle($input, $output);
        $storage = $input->getArgument('storage');
        $dryRun = (bool) $input->getOption('dry-run');

        if (! in_array($storage, TranslationStorageService::STORAGES, true)) {
            $io->error('Not a storage: "'.$storage.'". Expected '.implode(' or ', TranslationStorageService::STORAGES).'.');

            return Command::FAILURE;
        }

        $count = $this->translationStorageService->convert(
            $storage,
            $this->localeService->getDefaultLocale(),
            $input->getOption('path'),
            (bool) $input->getOption('include-bundles'),
            $dryRun,
            static fn (string $element, array $locales) => $io->writeln(
                sprintf(' %s <comment>%s</comment>  %s', $dryRun ? '~' : '✓', implode(' ', $locales), $element)
            ),
        );

        $io->success(sprintf('%d elements %s.', $count, $dryRun ? 'to convert' : 'converted'));

        return Command::SUCCESS;
    }
}
