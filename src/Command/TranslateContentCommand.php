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
use Wexample\SymfonyTranslations\Service\PendingTextTranslator;
use Wexample\SymfonyTranslations\Service\TextTranslationService;
use Wexample\SymfonyTranslations\Translation\Translator;

/**
 * Translates ahead of time what readers would otherwise wait for: every
 * #[Translatable] field of every entity of a class, or of every class.
 */
class TranslateContentCommand extends AbstractTranslationCommand
{
    protected static $defaultDescription = 'Translate the #[Translatable] fields of stored entities into a locale';

    public function __construct(
        Translator $translator,
        BundleService $bundleService,
        private readonly ContentTranslationService $contentTranslationService,
        private readonly TextTranslationService $textTranslationService,
        private readonly EntityManagerInterface $entityManager,
    ) {
        parent::__construct($translator, $bundleService);
    }

    protected function configure(): void
    {
        parent::configure();

        $this
            ->addArgument('to', InputArgument::REQUIRED, 'The locale to translate into')
            ->addArgument('entity', InputArgument::OPTIONAL, 'The entity class, every class having #[Translatable] fields if omitted')
            ->addOption('force', null, InputOption::VALUE_NONE, 'Translate again what an engine already translated; values written by hand are kept')
            ->addOption('batch-size', null, InputOption::VALUE_REQUIRED, 'Entities loaded at once', '50');
    }

    protected function execute(
        InputInterface $input,
        OutputInterface $output
    ): int {
        $io = new SymfonyStyle($input, $output);
        $to = $input->getArgument('to');
        $force = (bool) $input->getOption('force');
        $batchSize = max(1, (int) $input->getOption('batch-size'));
        $translatableClasses = $this->contentTranslationService->getTranslatableClasses();
        $classes = $input->getArgument('entity') ? [$input->getArgument('entity')] : $translatableClasses;

        if ($to === $this->contentTranslationService->getSourceLocale()) {
            $io->error('Content is written in "'.$to.'" already.');

            return Command::FAILURE;
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

        foreach ($classes as $class) {
            $io->section($class.' → '.$to);

            $query = $this->entityManager->createQueryBuilder()
                ->select('e')
                ->from($class, 'e')
                ->getQuery();

            $count = 0;
            $progress = $io->createProgressBar();

            foreach ($query->toIterable() as $entity) {
                $this->contentTranslationService->translateEntity($entity, $to, $force);
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

        return Command::SUCCESS;
    }
}
