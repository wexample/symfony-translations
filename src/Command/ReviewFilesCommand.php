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
use Wexample\SymfonyTranslations\Service\TranslationFileService;
use Wexample\SymfonyTranslations\Translation\Translator;

/**
 * What of the machine translations was read over in its context, after the
 * engine: per locale, the texts read and those left — a later pass, by an
 * agent or by hand, starts from what is left. With `--mark`, records that the
 * texts named were read (`--by`): the lock keeps it until their source changes
 * and they are translated again. Mark only what was actually read, in its
 * context: a key read for one term, not for its whole sentence, is not.
 */
class ReviewFilesCommand extends AbstractTranslationCommand
{
    protected static $defaultDescription = 'Report which machine translations were read over in their context, or mark some as read';

    public function __construct(
        Translator $translator,
        BundleService $bundleService,
        private readonly TranslationFileService $translationFileService,
        private readonly LocaleService $localeService,
    ) {
        parent::__construct($translator, $bundleService);
    }

    protected function configure(): void
    {
        parent::configure();

        $this
            ->addArgument('to', InputArgument::IS_ARRAY, 'The locales, every enabled locale but the source one if omitted')
            ->addOption('from', null, InputOption::VALUE_REQUIRED, 'The source locale, the default locale if omitted')
            ->addOption('path', null, InputOption::VALUE_REQUIRED, 'Only the files whose path contains this string')
            ->addOption('key', null, InputOption::VALUE_REQUIRED | InputOption::VALUE_IS_ARRAY, 'With --mark, only these keys and those under them')
            ->addOption('mark', null, InputOption::VALUE_NONE, 'Mark the texts as read over, rather than report')
            ->addOption('by', null, InputOption::VALUE_REQUIRED, 'Who read them over: '.implode(' or ', TranslationFileService::REVIEWERS), 'agent')
            ->addOption('include-bundles', null, InputOption::VALUE_NONE, 'Also the translation directories of the bundles, which belong to their packages')
            ->addOption('dry-run', null, InputOption::VALUE_NONE, 'With --mark, count what would be marked, write nothing');
    }

    protected function execute(
        InputInterface $input,
        OutputInterface $output
    ): int {
        $io = new SymfonyStyle($input, $output);
        $from = $input->getOption('from') ?? $this->localeService->getDefaultLocale();
        $locales = array_values(array_unique($input->getArgument('to')))
            ?: array_values(array_diff($this->localeService->getEnabledLocales(), [$from]));
        $path = $input->getOption('path');
        $includeBundles = (bool) $input->getOption('include-bundles');

        if ($input->getOption('mark')) {
            $by = (string) $input->getOption('by');

            if (! in_array($by, TranslationFileService::REVIEWERS, true)) {
                $io->error(sprintf('--by is one of %s.', implode(', ', TranslationFileService::REVIEWERS)));

                return Command::INVALID;
            }

            foreach ($locales as $to) {
                $marked = $this->translationFileService->markReviewed(
                    $from,
                    $to,
                    $by,
                    $path,
                    $input->getOption('key'),
                    $includeBundles,
                    (bool) $input->getOption('dry-run')
                );
                $io->writeln(sprintf(' %s <comment>%d</comment> texts %s read over by %s', $to, $marked, $input->getOption('dry-run') ? 'to mark' : 'marked', $by));
            }

            return Command::SUCCESS;
        }

        $rows = [];

        foreach ($locales as $to) {
            $texts = 0;
            $reviewed = 0;

            foreach ($this->translationFileService->reviewStatus($from, $to, $path, $includeBundles) as $file) {
                $texts += $file['texts'];
                $reviewed += $file['reviewed'];

                if ($output->isVerbose() && $file['reviewed'] < $file['texts']) {
                    $io->writeln(sprintf(' %s <comment>%d</comment> left  %s', $to, $file['texts'] - $file['reviewed'], $file['path']));
                }
            }

            $rows[] = [$to, $reviewed, $texts - $reviewed, $texts];
        }

        $io->table(['Locale', 'Read over', 'Left', 'Texts'], $rows);

        return Command::SUCCESS;
    }
}
