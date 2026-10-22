<?php

namespace Wexample\SymfonyTranslations\Command;

use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Wexample\SymfonyHelpers\Service\BundleService;
use Wexample\SymfonyTranslations\Service\ContentTranslationService;
use Wexample\SymfonyTranslations\Service\LocaleService;
use Wexample\SymfonyTranslations\Service\PendingTextTranslator;
use Wexample\SymfonyTranslations\Service\TextTranslationService;
use Wexample\SymfonyTranslations\Translation\Translator;

/**
 * Translates ahead of time what readers would otherwise wait for: every
 * #[Translatable] field of every entity of a class, or of every class, into
 * the locales given or else every content locale. From the entities' own text,
 * or from another locale's translation with `--from`: the values made keep the
 * locale they were made from, and follow its changes.
 */
class TranslateContentCommand extends AbstractTranslationCommand
{
    protected static $defaultDescription = 'Translate the #[Translatable] fields of stored entities into one or several locales';

    public function __construct(
        Translator $translator,
        BundleService $bundleService,
        private readonly ContentTranslationService $contentTranslationService,
        private readonly TextTranslationService $textTranslationService,
        private readonly EntityManagerInterface $entityManager,
        private readonly LocaleService $localeService,
    ) {
        parent::__construct($translator, $bundleService);
    }

    protected function configure(): void
    {
        parent::configure();

        $this
            ->addArgument('to', InputArgument::OPTIONAL | InputArgument::IS_ARRAY, 'The locales to translate into, every content locale if omitted')
            ->addOption('from', null, InputOption::VALUE_REQUIRED, 'Translate from this locale\'s translation rather than from the text of the entities, which is in the default locale')
            ->addOption('entity', null, InputOption::VALUE_REQUIRED, 'The entity class, every class having #[Translatable] fields if omitted')
            ->addOption('force', null, InputOption::VALUE_NONE, 'Translate again what an engine already translated; values written by hand are kept')
            ->addOption('batch-size', null, InputOption::VALUE_REQUIRED, 'Entities loaded at once', '50');
    }

    protected function execute(
        InputInterface $input,
        OutputInterface $output
    ): int {
        $io = new SymfonyStyle($input, $output);
        $sourceLocale = $this->contentTranslationService->getSourceLocale();
        $locales = array_values(array_unique($input->getArgument('to'))) ?: array_values(array_diff(
            $this->localeService->getContentLocales(),
            [$sourceLocale]
        ));
        $force = (bool) $input->getOption('force');
        $batchSize = max(1, (int) $input->getOption('batch-size'));
        $translatableClasses = $this->contentTranslationService->getTranslatableClasses();
        $classes = $input->getOption('entity') ? [$input->getOption('entity')] : $translatableClasses;

        $from = $input->getOption('from');

        if (in_array($sourceLocale, $locales, true)) {
            $io->error('Content is written in "'.$sourceLocale.'" already.');

            return Command::FAILURE;
        }

        if (null !== $from && in_array($from, $locales, true)) {
            $io->error('The source locale is among the target ones: '.$from);

            return Command::FAILURE;
        }

        if (null !== $from && $from !== $sourceLocale && ! $this->localeService->hasContentLocale($from)) {
            $io->error('Not a content locale: "'.$from.'".');

            return Command::FAILURE;
        }

        foreach ($locales as $locale) {
            if (! $this->localeService->hasContentLocale($locale)) {
                $io->error(sprintf(
                    'Not a content locale: "%s". Add it to framework.enabled_locales, or to wexample_symfony_translations.content_locales.',
                    $locale
                ));

                return Command::FAILURE;
            }
        }

        foreach ($classes as $class) {
            if (! in_array($class, $translatableClasses, true)) {
                $io->error('Not an entity with #[Translatable] fields: '.$class);

                return Command::FAILURE;
            }
        }

        if (PendingTextTranslator::ENGINE_NAME === $this->textTranslationService->getEngineName()) {
            $io->warning('No translation engine is configured yet: texts are stored untranslated, and will be translated on the first run with a real engine.');
        }

        foreach ($locales as $to) {
            foreach ($classes as $class) {
                $io->section($class.' '.($from ?? $sourceLocale).' → '.$to);

                $query = $this->entityManager->createQueryBuilder()
                    ->select('e')
                    ->from($class, 'e')
                    ->getQuery();

                $count = 0;
                $progress = $io->createProgressBar();

                foreach ($query->toIterable() as $entity) {
                    $this->contentTranslationService->translateEntity($entity, $to, $force, $from);
                    $progress->advance();

                    if (0 === ++$count % $batchSize) {
                        $this->entityManager->clear();
                    }
                }

                $progress->finish();
                $this->entityManager->clear();
                $io->newLine(2);
                $io->writeln(sprintf(' %d entities, fields: %s', $count, implode(', ', $this->contentTranslationService->getTranslatableFields($class))));
            }

            $io->success('Content translated into "'.$to.'".');
        }

        return Command::SUCCESS;
    }
}
