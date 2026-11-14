<?php

declare(strict_types=1);

namespace Wexample\SymfonyTranslations\Tests\Unit\Translation;

use PHPUnit\Framework\TestCase;
use Symfony\Bundle\FrameworkBundle\Translation\Translator as SymfonyTranslator;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Component\HttpKernel\KernelInterface;
use Symfony\Component\Translation\MessageCatalogue;
use Wexample\SymfonyTranslations\Translation\Translator;

/**
 * The yml messages are added to a catalogue once per process: every request
 * sets its locale and translates, and neither populates it again. Only in
 * debug does the next request, after `reset()`, check the files once more.
 */
class CataloguePopulationTest extends TestCase
{
    private string $cacheDir;

    /**
     * Counts the messages added to it, a population adding one batch per
     * domain.
     */
    private MessageCatalogue $catalogue;

    protected function setUp(): void
    {
        $this->cacheDir = sys_get_temp_dir() . '/wexample-translations-population-' . uniqid();

        $this->catalogue = new class ('test') extends MessageCatalogue {
            public int $adds = 0;

            public function add(array $messages, string $domain = 'messages'): void
            {
                ++$this->adds;
                parent::add($messages, $domain);
            }
        };
    }

    protected function tearDown(): void
    {
        foreach (glob($this->cacheDir . '/wexample_translations/catalogues/*') ?: [] as $file) {
            unlink($file);
        }

        foreach ([
            $this->cacheDir . '/wexample_translations/catalogues',
            $this->cacheDir . '/wexample_translations',
            $this->cacheDir,
        ] as $directory) {
            if (is_dir($directory)) {
                rmdir($directory);
            }
        }
    }

    public function testACatalogueIsPopulatedOnceAcrossSetLocaleAndTrans(): void
    {
        $translator = $this->createTranslator(debug: false);
        $populated = $this->catalogue->adds;
        $this->assertGreaterThan(0, $populated, 'The constructor populates the catalogue.');

        // What every request does, twice: neither populates it again.
        foreach ([1, 2] as $request) {
            $translator->setLocale('test');
            $translator->trans('@test.messages::test_key');
            $translator->has('@test.messages::simple_key');
            $translator->reset();
        }

        $this->assertSame($populated, $this->catalogue->adds);
    }

    public function testInDebugTheNextRequestChecksTheFilesAgain(): void
    {
        $translator = $this->createTranslator(debug: true);
        $populated = $this->catalogue->adds;

        // The request the translator was built in: populated already.
        $translator->setLocale('test');
        $translator->trans('@test.messages::test_key');
        $this->assertSame($populated, $this->catalogue->adds);

        // The next one compares the files to the cache, once.
        $translator->reset();
        $translator->setLocale('test');
        $translator->trans('@test.messages::test_key');
        $translator->trans('@test.messages::simple_key');
        $this->assertSame(2 * $populated, $this->catalogue->adds);
    }

    public function testACatalogueSymfonyBuiltAgainIsPopulatedAgain(): void
    {
        $translator = $this->createTranslator(debug: false);
        $previous = $this->catalogue;

        // What adding a resource does, on a cold cache among others: the
        // catalogue populated is dropped, another one is built in its place.
        $this->catalogue = new MessageCatalogue('test');
        $this->assertSame([], $this->catalogue->all());

        $translator->trans('@test.messages::test_key');

        $this->assertNotSame($previous, $this->catalogue);
        $this->assertNotSame([], $this->catalogue->all());
    }

    private function createTranslator(bool $debug): Translator
    {
        $symfonyTranslator = $this->createStub(SymfonyTranslator::class);
        $symfonyTranslator->method('getLocale')->willReturn('test');
        $symfonyTranslator->method('getFallbackLocales')->willReturn([]);
        // Whichever catalogue the test holds at the time of the call.
        $symfonyTranslator->method('getCatalogue')->willReturnCallback(fn () => $this->catalogue);

        $kernel = $this->createStub(KernelInterface::class);
        $kernel->method('getProjectDir')->willReturn(__DIR__);
        $kernel->method('getCacheDir')->willReturn($this->cacheDir);
        $kernel->method('isDebug')->willReturn($debug);

        $parameterBag = $this->createStub(ParameterBagInterface::class);
        $parameterBag->method('get')
            ->willReturnMap([['translations_paths', [__DIR__ . '/../../Resources/translations/']]]);

        return new Translator($symfonyTranslator, $kernel, $parameterBag);
    }
}
