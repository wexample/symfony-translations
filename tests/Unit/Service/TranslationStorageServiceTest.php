<?php

namespace Wexample\SymfonyTranslations\Tests\Unit\Service;

use PHPUnit\Framework\TestCase;
use Wexample\SymfonyTranslations\Service\PendingTextTranslator;
use Wexample\SymfonyTranslations\Service\TextTranslationService;
use Wexample\SymfonyTranslations\Service\TranslationFileService;
use Wexample\SymfonyTranslations\Service\TranslationStorageService;
use Wexample\SymfonyTranslations\Translation\Translator;

class TranslationStorageServiceTest extends TestCase
{
    private string $dir;

    protected function setUp(): void
    {
        $this->dir = sys_get_temp_dir().'/translation-storage-service-'.uniqid().'/';
        mkdir($this->dir.'components', 0777, true);
    }

    protected function tearDown(): void
    {
        exec('rm -rf '.escapeshellarg($this->dir));
    }

    public function testConvertsBackAndForthKeepingTheTextAsWritten(): void
    {
        $en = "# The button.\ntitle: Hello   # greeting\n\nlong: |\n  Line one\n  Line two\n";
        $fr = "title: Bonjour\nlong: |\n  Ligne un\n";
        $ai = "description: Not a translation\n";
        file_put_contents($this->dir.'components/button.en.yml', $en);
        file_put_contents($this->dir.'components/button.fr.yml', $fr);
        file_put_contents($this->dir.'components/button.ai.yml', $ai);
        $service = $this->createService();

        $this->assertSame(1, $service->convert(TranslationStorageService::STORAGE_TRANS, 'en'));
        $this->assertFileDoesNotExist($this->dir.'components/button.en.yml');
        $this->assertSame(
            "en:\n  # The button.\n  title: Hello   # greeting\n\n  long: |\n    Line one\n    Line two\n\nfr:\n  title: Bonjour\n  long: |\n    Ligne un\n",
            file_get_contents($this->dir.'components/button.trans.yml')
        );
        $this->assertSame($ai, file_get_contents($this->dir.'components/button.ai.yml'));

        $this->assertSame(1, $service->convert(TranslationStorageService::STORAGE_LOCALE));
        $this->assertFileDoesNotExist($this->dir.'components/button.trans.yml');
        $this->assertSame($en, file_get_contents($this->dir.'components/button.en.yml'));
        $this->assertSame($fr, file_get_contents($this->dir.'components/button.fr.yml'));
    }

    public function testDryRunWritesNothing(): void
    {
        file_put_contents($this->dir.'components/button.en.yml', "title: Hello\n");

        $this->assertSame(1, $this->createService()->convert(TranslationStorageService::STORAGE_TRANS, dryRun: true));
        $this->assertFileExists($this->dir.'components/button.en.yml');
        $this->assertFileDoesNotExist($this->dir.'components/button.trans.yml');
    }

    private function createService(): TranslationStorageService
    {
        $translator = $this->createStub(Translator::class);
        $translator->method('getTranslationPaths')->willReturn([$this->dir]);

        return new TranslationStorageService(new TranslationFileService(
            $translator,
            new TextTranslationService(new PendingTextTranslator()),
            $this->dir
        ));
    }
}
