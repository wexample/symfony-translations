<?php

namespace Wexample\SymfonyTranslations\Tests\Unit\Service;

use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Mapping\ClassMetadata;
use PHPUnit\Framework\TestCase;
use Wexample\SymfonyTranslations\Attribute\Translatable;
use Wexample\SymfonyTranslations\Interface\TextTranslatorInterface;
use Wexample\SymfonyTranslations\Interface\TranslationReadinessInterface;
use Wexample\SymfonyTranslations\Repository\ContentTranslationRepository;
use Wexample\SymfonyTranslations\Service\ContentTranslationService;
use Wexample\SymfonyTranslations\Service\LocaleService;
use Wexample\SymfonyTranslations\Service\TextTranslationService;
use Wexample\SymfonyTranslations\Translation\Translator;

class ContentTranslationServiceTest extends TestCase
{
    private bool $engineFails = false;

    /** @var array<string, array<string, array>> Rows by locale, by field */
    private array $rows = [];

    /** @var string[] Each engine call, as "from>to:text" */
    private array $calls = [];

    public function testTranslatesFromTheEntityText(): void
    {
        $article = new ContentTranslationServiceTestArticle('Hello');

        $this->assertSame(['title' => 'KO(Hello)'], $this->createService()->translateEntity($article, 'ko'));
        $this->assertSame(['en>ko:Hello'], $this->calls);
        $this->assertNull($this->rows['ko']['title']['source_locale']);
    }

    public function testTranslatesFromAnotherLocaleAndKeepsItOnRead(): void
    {
        $article = new ContentTranslationServiceTestArticle('Hello');

        $this->createService()->translateEntity($article, 'ko', sourceLocale: 'ja');

        $this->assertSame(['en>ja:Hello', 'ja>ko:JA(Hello)'], $this->calls);
        $this->assertSame('ja', $this->rows['ko']['title']['source_locale']);

        // Read later, as a page does: nothing is translated again.
        $this->calls = [];
        $this->assertSame(['title' => 'KO(JA(Hello))'], $this->createService()->translateEntity($article, 'ko'));
        $this->assertSame([], $this->calls);

        // Its source changes: made again from the same locale.
        $article->title = 'Hi';
        $this->createService()->translateEntity($article, 'ko');
        $this->assertSame(['en>ja:Hi', 'ja>ko:JA(Hi)'], $this->calls);
    }

    public function testKeepsWordingWrittenByHand(): void
    {
        $article = new ContentTranslationServiceTestArticle('Hello');
        $this->rows['ko']['title'] = ['field' => 'title', 'value' => '안녕', 'source_hash' => 'x', 'source_locale' => null, 'engine' => null];

        $this->assertSame(['title' => '안녕'], $this->createService()->translateEntity($article, 'ko', true, 'ja'));
        $this->assertSame([], $this->calls);
    }

    public function testSendsNothingForWhatIsNotReady(): void
    {
        $draft = new ContentTranslationServiceTestDraft('Hello', 'World');
        $this->rows['ko']['title'] = ['field' => 'title', 'value' => '안녕', 'source_hash' => 'x', 'source_locale' => null, 'engine' => null];

        // Its wording written by hand, its own words for the rest.
        $this->assertSame(['title' => '안녕', 'summary' => 'World'], $this->createService(ContentTranslationServiceTestDraft::class)->translateEntity($draft, 'ko'));
        $this->assertSame([], $this->calls);
        $this->assertArrayNotHasKey('summary', $this->rows['ko']);

        // Ready, the next read translates what is missing.
        $draft->ready = true;
        $this->assertSame(['title' => '안녕', 'summary' => 'KO(World)'], $this->createService(ContentTranslationServiceTestDraft::class)->translateEntity($draft, 'ko'));
        $this->assertSame(['en>ko:World'], $this->calls);
    }

    public function testAFailingEngineLeavesTheTextInItsOwnWordsAndStoresNothing(): void
    {
        $this->engineFails = true;
        $article = new ContentTranslationServiceTestArticle('Hello');

        $this->assertSame(['title' => 'Hello'], $this->createService()->translateEntity($article, 'ko'));
        $this->assertArrayNotHasKey('ko', $this->rows);

        $this->engineFails = false;
        $this->assertSame(['title' => 'KO(Hello)'], $this->createService()->translateEntity($article, 'ko'));
    }

    private function createService(string $class = ContentTranslationServiceTestArticle::class): ContentTranslationService
    {
        $metadata = $this->createStub(ClassMetadata::class);
        $metadata->method('getName')->willReturn($class);
        $metadata->method('getFieldValue')->willReturnCallback(static fn (object $entity, string $field) => $entity->$field);
        $metadata->method('getIdentifierValues')->willReturn(['id' => 1]);

        $entityManager = $this->createStub(EntityManagerInterface::class);
        $entityManager->method('getClassMetadata')->willReturn($metadata);

        $repository = $this->createStub(ContentTranslationRepository::class);
        $repository->method('findRowsForEntity')->willReturnCallback(
            fn (string $class, string $id, string $locale): array => $this->rows[$locale] ?? []
        );
        $repository->method('saveValue')->willReturnCallback(
            function (string $class, string $id, string $field, string $locale, ?string $value, string $hash, ?string $engine, ?string $sourceLocale = null): void {
                $this->rows[$locale][$field] = ['field' => $field, 'value' => $value, 'source_hash' => $hash, 'source_locale' => $sourceLocale, 'engine' => $engine];
            }
        );

        $engine = $this->createStub(TextTranslatorInterface::class);
        $engine->method('getEngineName')->willReturn('fake');
        $engine->method('translate')->willReturnCallback(function (array $texts, string $from, string $to): array {
            if ($this->engineFails) {
                throw new \RuntimeException('API responded with HTTP 404');
            }

            foreach ($texts as $text) {
                $this->calls[] = $from.'>'.$to.':'.$text;
            }

            return array_map(static fn (string $text): string => strtoupper($to).'('.$text.')', $texts);
        });

        return new ContentTranslationService(
            $entityManager,
            $repository,
            new TextTranslationService($engine),
            new LocaleService('en', ['en', 'ja', 'ko'], false, []),
            $this->createStub(Translator::class),
        );
    }
}

class ContentTranslationServiceTestArticle
{
    public function __construct(
        #[Translatable]
        public string $title,
    ) {
    }
}

class ContentTranslationServiceTestDraft implements TranslationReadinessInterface
{
    public bool $ready = false;

    public function __construct(
        #[Translatable]
        public string $title,
        #[Translatable]
        public string $summary,
    ) {
    }

    public function isReadyForTranslation(): bool
    {
        return $this->ready;
    }
}
