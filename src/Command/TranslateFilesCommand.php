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
use Wexample\SymfonyTranslations\Service\TranslationStorageService;
use Wexample\SymfonyTranslations\Translation\Translator;

/**
 * Translates the interface: every element of the application gets its `<to>`
 * locale — a `.<to>.yml`, or a block of its `.trans.yml` — for each target
 * locale, and the target locales are enabled. Without any target named, every
 * enabled locale but the source one: what keeps an application translated as
 * its wording grows, a new locale being named once to be enabled for good. The
 * bundles' files only when asked for, as they belong to their packages. With a `storage` configured, the
 * elements are converted to it first.
 */
class TranslateFilesCommand extends AbstractTranslationCommand
{
    protected static $defaultDescription = 'Translate every interface translation file into one or several locales, and enable them';

    public function __construct(
        Translator $translator,
        BundleService $bundleService,
        private readonly TranslationFileService $translationFileService,
        private readonly TextTranslationService $textTranslationService,
        private readonly LocaleConfigService $localeConfigService,
        private readonly LocaleService $localeService,
        private readonly TranslationStorageService $translationStorageService,
        private readonly string $projectDir,
        private readonly ?string $storage = null,
    ) {
        parent::__construct($translator, $bundleService);
    }

    protected function configure(): void
    {
        parent::configure();

        $this
            ->addArgument('to', InputArgument::IS_ARRAY, 'The locales to translate into, every enabled locale but the source one if omitted')
            ->addOption('from', null, InputOption::VALUE_REQUIRED, 'The locale to translate from, the default locale if omitted')
            ->addOption('path', null, InputOption::VALUE_REQUIRED, 'Only the files whose path contains this string')
            ->addOption('include-bundles', null, InputOption::VALUE_NONE, 'Also write into the translation directories of the bundles, which belong to their packages')
            ->addOption('force', null, InputOption::VALUE_NONE, 'Translate again what an engine already translated; wording written by hand is kept')
            ->addOption('keep-orphans', null, InputOption::VALUE_NONE, 'Keep the translated files whose source file is gone, removed otherwise')
            ->addOption('dry-run', null, InputOption::VALUE_NONE, 'Report what would be written, write nothing');
    }

    protected function execute(
        InputInterface $input,
        OutputInterface $output
    ): int {
        $io = new SymfonyStyle($input, $output);
        $from = $input->getOption('from') ?? $this->localeService->getDefaultLocale();
        $locales = array_values(array_unique($input->getArgument('to')))
            ?: array_values(array_diff($this->localeService->getEnabledLocales(), [$from]));
        $dryRun = (bool) $input->getOption('dry-run');

        if ([] === $locales) {
            $io->error('No locale to translate into: name one, or enable some in framework.enabled_locales.');

            return Command::FAILURE;
        }

        if (in_array($from, $locales, true)) {
            $io->error('The source locale is among the target ones: '.$from);

            return Command::FAILURE;
        }

        if (PendingTextTranslator::ENGINE_NAME === $this->textTranslationService->getEngineName()) {
            $io->warning('No translation engine is configured yet: texts are copied untranslated, and will be translated on the first run with a real engine.');
        }

        if (null !== $this->storage) {
            $converted = $this->translationStorageService->convert(
                $this->storage,
                $from,
                $input->getOption('path'),
                (bool) $input->getOption('include-bundles'),
                $dryRun
            );

            if ($converted > 0) {
                $io->note(sprintf('%d elements %s to the "%s" storage.', $converted, $dryRun ? 'to convert' : 'converted', $this->storage));
            }
        }

        foreach ($locales as $to) {
            // Two runs on one locale would pay the engine twice for the same
            // texts and write the same files: the second leaves it to the first.
            $runLock = fopen(sys_get_temp_dir().'/translate-files-'.md5($this->projectDir).'-'.$to.'.lock', 'c');

            if (! flock($runLock, LOCK_EX | LOCK_NB)) {
                $io->warning(sprintf('Another run is translating "%s" in this application: skipped.', $to));
                fclose($runLock);

                continue;
            }

            $this->translateLocale($io, $input, $from, $to, $dryRun);
            fclose($runLock);
        }

        return Command::SUCCESS;
    }

    private function translateLocale(
        SymfonyStyle $io,
        InputInterface $input,
        string $from,
        string $to,
        bool $dryRun
    ): void {
        $io->title(sprintf('Translating interface files from "%s" to "%s"', $from, $to));

        $stats = $this->translationFileService->translateFiles(
            $from,
            $to,
            $input->getOption('path'),
            (bool) $input->getOption('force'),
            $dryRun,
            (bool) $input->getOption('include-bundles'),
            (bool) $input->getOption('keep-orphans'),
            static fn (string $targetPath, int $translatedCount) => $io->writeln(
                sprintf(' %s <comment>%d</comment>  %s', $dryRun ? '~' : '✓', $translatedCount, $targetPath)
            ),
            static fn (string $targetPath) => $io->writeln(
                sprintf(' %s <comment>source gone</comment>  %s', $dryRun ? '~' : '✗', $targetPath)
            ),
            static fn (string $targetPath, array $keys) => $io->writeln(
                sprintf(' <error>!</error> <comment>left out</comment>  %s: %s', $targetPath, implode(', ', $keys))
            ),
        );

        if (! $dryRun && $this->localeConfigService->enableLocale($to)) {
            $io->note(sprintf('"%s" added to framework.enabled_locales in %s.', $to, LocaleConfigService::CONFIG_FILE));
        }

        $io->success(sprintf(
            '%s: %d source files, %d target files %s, %d texts %s, %d orphan files %s.',
            $to,
            $stats['files'],
            $stats['written'],
            $dryRun ? 'to write' : 'written',
            $stats['translated'],
            $dryRun ? 'to translate' : 'translated',
            $stats['removed'],
            $dryRun ? 'to remove' : 'removed'
        ));

        if ($stats['untranslated'] > 0) {
            $io->warning(sprintf(
                '%s: %d texts left out by the engine, read in the fallback locale meanwhile. Run the command again to ask for them.',
                $to,
                $stats['untranslated']
            ));
        }
    }
}
