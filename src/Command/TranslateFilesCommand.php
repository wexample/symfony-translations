<?php

namespace Wexample\SymfonyTranslations\Command;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Wexample\SymfonyHelpers\Service\BundleService;
use Wexample\SymfonyTranslations\Service\LocaleConfigService;
use Wexample\SymfonyTranslations\Service\LocaleService;
use Wexample\SymfonyTranslations\Service\PendingTextTranslator;
use Wexample\SymfonyTranslations\Service\TextTranslationService;
use Wexample\SymfonyTranslations\Service\TranslationFileService;
use Wexample\SymfonyTranslations\Translation\Translator;

/**
 * Translates the interface: every `.<from>.yml` of the application gets its
 * `.<to>.yml`, and the target locale is enabled. The bundles' files only when
 * asked for, as they belong to their packages.
 */
class TranslateFilesCommand extends AbstractTranslationCommand
{
    protected static $defaultDescription = 'Translate every interface translation file into a locale, and enable it';

    public function __construct(
        Translator $translator,
        BundleService $bundleService,
        private readonly TranslationFileService $translationFileService,
        private readonly TextTranslationService $textTranslationService,
        private readonly LocaleConfigService $localeConfigService,
        private readonly LocaleService $localeService,
    ) {
        parent::__construct($translator, $bundleService);
    }

    protected function configure(): void
    {
        parent::configure();

        $this
            ->addArgument('to', InputArgument::REQUIRED, 'The locale to translate into')
            ->addOption('from', null, InputOption::VALUE_REQUIRED, 'The locale to translate from, the default locale if omitted')
            ->addOption('path', null, InputOption::VALUE_REQUIRED, 'Only the files whose path contains this string')
            ->addOption('include-bundles', null, InputOption::VALUE_NONE, 'Also write into the translation directories of the bundles, which belong to their packages')
            ->addOption('force', null, InputOption::VALUE_NONE, 'Translate again what an engine already translated; wording written by hand is kept')
            ->addOption('dry-run', null, InputOption::VALUE_NONE, 'Report what would be written, write nothing');
    }

    protected function execute(
        InputInterface $input,
        OutputInterface $output
    ): int {
        $io = new SymfonyStyle($input, $output);
        $to = $input->getArgument('to');
        $from = $input->getOption('from') ?? $this->localeService->getDefaultLocale();
        $dryRun = (bool) $input->getOption('dry-run');

        if ($from === $to) {
            $io->error('The source and target locales are the same: '.$to);

            return Command::FAILURE;
        }

        if (PendingTextTranslator::ENGINE_NAME === $this->textTranslationService->getEngineName()) {
            $io->warning('No translation engine is configured yet: texts are copied untranslated, and will be translated on the first run with a real engine.');
        }

        $io->title(sprintf('Translating interface files from "%s" to "%s"', $from, $to));

        $stats = $this->translationFileService->translateFiles(
            $from,
            $to,
            $input->getOption('path'),
            (bool) $input->getOption('force'),
            $dryRun,
            (bool) $input->getOption('include-bundles'),
            static fn (string $targetPath, int $translatedCount) => $io->writeln(
                sprintf(' %s <comment>%d</comment>  %s', $dryRun ? '~' : '✓', $translatedCount, $targetPath)
            ),
        );

        if (! $dryRun && $this->localeConfigService->enableLocale($to)) {
            $io->note(sprintf('"%s" added to framework.enabled_locales in %s.', $to, LocaleConfigService::CONFIG_FILE));
        }

        $io->success(sprintf(
            '%d source files, %d target files %s, %d texts translated.',
            $stats['files'],
            $stats['written'],
            $dryRun ? 'to write' : 'written',
            $stats['translated']
        ));

        return Command::SUCCESS;
    }
}
