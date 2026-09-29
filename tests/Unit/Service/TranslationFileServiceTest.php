<?php

namespace Wexample\SymfonyTranslations\Tests\Unit\Service;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Yaml\Yaml;
use Wexample\SymfonyTranslations\Interface\TextTranslatorInterface;
use Wexample\SymfonyTranslations\Service\PendingTextTranslator;
use Wexample\SymfonyTranslations\Service\TextTranslationService;
use Wexample\SymfonyTranslations\Service\TranslationFileService;
use Wexample\SymfonyTranslations\Translation\Translator;

class TranslationFileServiceTest extends TestCase
{
    private string $dir;

    protected function setUp(): void
    {
        $this->dir = sys_get_temp_dir().'/translation-file-service-'.uniqid().'/';
        mkdir($this->dir.'pages', 0777, true);
    }

    protected function tearDown(): void
    {
        exec('rm -rf '.escapeshellarg($this->dir));
    }

    public function testTranslatesWordingAndKeepsWhatIsNot(): void
    {
        $this->writeSource([
            'title' => 'Hello %name%',
            'button' => ['save' => 'Save <b>{count}</b> items'],
            'ref' => '@page::title',
            'same' => '%',
            'number' => 12,
            'dash' => '—',
        ]);

        $this->createService($this->createEngine('fake'))->translateFiles('en', 'fr');

        $this->assertSame([
            'title' => 'FR(HELLO %name%)',
            'button' => ['save' => 'FR(SAVE <b>{count}</b> ITEMS)'],
            'ref' => '@page::title',
            'same' => '%',
            'number' => 12,
            'dash' => '—',
        ], $this->readTarget());
    }

    public function testKeepsWordingWrittenByHandAndRetranslatesChangedSource(): void
    {
        $this->writeSource(['a' => 'One', 'b' => 'Two']);
        file_put_contents($this->dir.'pages/index.fr.yml', Yaml::dump(['a' => 'Un, à la main']));

        $service = $this->createService($this->createEngine('fake'));
        $service->translateFiles('en', 'fr');

        $this->assertSame(['a' => 'Un, à la main', 'b' => 'FR(TWO)'], $this->readTarget());

        $this->writeSource(['a' => 'One changed', 'b' => 'Two changed', 'c' => 'Three']);
        $stats = $service->translateFiles('en', 'fr');

        $this->assertSame(
            ['a' => 'Un, à la main', 'b' => 'FR(TWO CHANGED)', 'c' => 'FR(THREE)'],
            $this->readTarget()
        );
        $this->assertSame(2, $stats['translated']);
        $this->assertSame(0, $service->translateFiles('en', 'fr')['written']);
    }

    public function testRealEngineReplacesWhatThePendingOneCopied(): void
    {
        $this->writeSource(['a' => 'One']);

        $this->createService($this->createEngine(PendingTextTranslator::ENGINE_NAME, false))->translateFiles('en', 'fr');
        $this->assertSame(['a' => 'One'], $this->readTarget());

        $this->createService($this->createEngine('fake'))->translateFiles('en', 'fr');
        $this->assertSame(['a' => 'FR(ONE)'], $this->readTarget());
    }

    public function testLeavesBundlesAloneUnlessAsked(): void
    {
        $bundleDir = sys_get_temp_dir().'/translation-file-service-bundle-'.uniqid().'/';
        mkdir($bundleDir);
        file_put_contents($bundleDir.'index.en.yml', Yaml::dump(['a' => 'One']));
        $this->writeSource(['a' => 'One']);

        $translator = $this->createStub(Translator::class);
        $translator->method('getTranslationPaths')->willReturn([$this->dir, '@SomeBundle' => $bundleDir]);
        $service = new TranslationFileService($translator, new TextTranslationService($this->createEngine('fake')), $this->dir);

        $service->translateFiles('en', 'fr');
        $this->assertFileExists($this->dir.'pages/index.fr.yml');
        $this->assertFileDoesNotExist($bundleDir.'index.fr.yml');

        $service->translateFiles('en', 'fr', includeBundles: true);
        $this->assertFileExists($bundleDir.'index.fr.yml');

        exec('rm -rf '.escapeshellarg($bundleDir));
    }

    public function testDryRunWritesNothing(): void
    {
        $this->writeSource(['a' => 'One']);

        $stats = $this->createService($this->createEngine('fake'))->translateFiles('en', 'fr', dryRun: true);

        $this->assertSame(1, $stats['written']);
        $this->assertFileDoesNotExist($this->dir.'pages/index.fr.yml');
        $this->assertFileDoesNotExist($this->dir.TranslationFileService::LOCK_FILE_NAME);
    }

    public function testRemovesATranslatedFileWhoseSourceIsGone(): void
    {
        $this->writeSource(['a' => 'One']);
        $service = $this->createService($this->createEngine('fake'));
        $service->translateFiles('en', 'fr');

        unlink($this->dir.'pages/index.en.yml');
        $stats = $service->translateFiles('en', 'fr');

        $this->assertSame(1, $stats['removed']);
        $this->assertFileDoesNotExist($this->dir.'pages/index.fr.yml');
        $this->assertArrayNotHasKey('pages/index.fr.yml', $this->readLock());
    }

    public function testKeepsOrphansWhenAsked(): void
    {
        $this->writeSource(['a' => 'One']);
        $service = $this->createService($this->createEngine('fake'));
        $service->translateFiles('en', 'fr');

        unlink($this->dir.'pages/index.en.yml');
        $stats = $service->translateFiles('en', 'fr', keepOrphans: true);

        $this->assertSame(0, $stats['removed']);
        $this->assertFileExists($this->dir.'pages/index.fr.yml');
    }

    public function testNeverRemovesAFileItDidNotWrite(): void
    {
        file_put_contents($this->dir.'pages/other.fr.yml', Yaml::dump(['a' => 'Écrit à la main']));

        $stats = $this->createService($this->createEngine('fake'))->translateFiles('en', 'fr');

        $this->assertSame(0, $stats['removed']);
        $this->assertFileExists($this->dir.'pages/other.fr.yml');
    }

    public function testLocksEachFileBeforeTheNextOne(): void
    {
        file_put_contents($this->dir.'pages/a.en.yml', Yaml::dump(['a' => 'One']));
        file_put_contents($this->dir.'pages/b.en.yml', Yaml::dump(['b' => 'Two']));

        $engine = new class implements TextTranslatorInterface {
            public function translate(
                array $texts,
                string $sourceLocale,
                string $targetLocale
            ): array {
                if (isset($texts['b'])) {
                    throw new \RuntimeException('Engine down');
                }

                return array_map(static fn (string $text): string => 'FR('.$text.')', $texts);
            }

            public function getEngineName(): string
            {
                return 'fake';
            }
        };

        try {
            $this->createService($engine)->translateFiles('en', 'fr');
            $this->fail('The engine failure should stop the run.');
        } catch (\RuntimeException) {
        }

        $this->assertSame('fake', $this->readLock()['pages/a.fr.yml']['a']['engine']);
    }

    public function testDryRunNeverReachesTheEngine(): void
    {
        $this->writeSource(['a' => 'One', 'b' => 'Two']);

        $stats = $this->createService($this->createFailingEngine())->translateFiles('en', 'fr', dryRun: true);

        $this->assertSame(2, $stats['translated']);
    }

    public function testAsksAgainNextRunForWhatTheEngineLeftOut(): void
    {
        $this->writeSource(['a' => 'One', 'b' => 'Two']);

        $stats = $this->createService($this->createForgetfulEngine('b'))->translateFiles('en', 'fr');

        $this->assertSame(['a' => 'FR(One)'], $this->readTarget());
        $this->assertSame(1, $stats['untranslated']);
        $this->assertArrayNotHasKey('b', $this->readLock()['pages/index.fr.yml']);

        $stats = $this->createService($this->createEngine('fake'))->translateFiles('en', 'fr');

        $this->assertSame(['a' => 'FR(One)', 'b' => 'FR(TWO)'], $this->readTarget());
        $this->assertSame(1, $stats['translated']);
    }

    public function testKeepsThePreviousTranslationOfAChangedKeyLeftOut(): void
    {
        $this->writeSource(['a' => 'One']);
        $this->createService($this->createEngine('fake'))->translateFiles('en', 'fr');

        $this->writeSource(['a' => 'One changed']);
        $this->createService($this->createForgetfulEngine('a'))->translateFiles('en', 'fr');

        $this->assertSame(['a' => 'FR(ONE)'], $this->readTarget());

        $this->createService($this->createEngine('fake'))->translateFiles('en', 'fr');
        $this->assertSame(['a' => 'FR(ONE CHANGED)'], $this->readTarget());
    }

    private function createFailingEngine(): TextTranslatorInterface
    {
        return new class implements TextTranslatorInterface {
            public function translate(
                array $texts,
                string $sourceLocale,
                string $targetLocale
            ): array {
                throw new \LogicException('The engine was reached.');
            }

            public function getEngineName(): string
            {
                return 'fake';
            }
        };
    }

    /**
     * Translates every text but the one under $forgottenKey, as a model skipping a line.
     */
    private function createForgetfulEngine(string $forgottenKey): TextTranslatorInterface
    {
        return new class($forgottenKey) implements TextTranslatorInterface {
            public function __construct(
                private readonly string $forgottenKey,
            ) {
            }

            public function translate(
                array $texts,
                string $sourceLocale,
                string $targetLocale
            ): array {
                unset($texts[$this->forgottenKey]);

                return array_map(static fn (string $text): string => 'FR('.$text.')', $texts);
            }

            public function getEngineName(): string
            {
                return 'fake';
            }
        };
    }

    private function readLock(): array
    {
        return json_decode(file_get_contents($this->dir.TranslationFileService::LOCK_FILE_NAME), true);
    }

    private function writeSource(array $data): void
    {
        file_put_contents($this->dir.'pages/index.en.yml', Yaml::dump($data));
    }

    private function readTarget(): array
    {
        return Yaml::parseFile($this->dir.'pages/index.fr.yml');
    }

    private function createService(TextTranslatorInterface $engine): TranslationFileService
    {
        $translator = $this->createStub(Translator::class);
        $translator->method('getTranslationPaths')->willReturn([$this->dir]);

        return new TranslationFileService($translator, new TextTranslationService($engine), $this->dir);
    }

    /**
     * Upper-cases and wraps, so a translated text is told from a copied one;
     * fails the test if a placeholder reaches it unmasked.
     */
    private function createEngine(
        string $name,
        bool $translates = true
    ): TextTranslatorInterface {
        return new class($name, $translates) implements TextTranslatorInterface {
            public function __construct(
                private readonly string $name,
                private readonly bool $translates,
            ) {
            }

            public function translate(
                array $texts,
                string $sourceLocale,
                string $targetLocale
            ): array {
                return array_map(function (string $text) use ($targetLocale): string {
                    if (preg_match('/%\w+%|\{\w+\}|<b>/', $text)) {
                        throw new \LogicException('Placeholder reached the engine: '.$text);
                    }

                    return $this->translates
                        ? strtoupper($targetLocale).'('.strtoupper($text).')'
                        : $text;
                }, $texts);
            }

            public function getEngineName(): string
            {
                return $this->name;
            }
        };
    }
}
