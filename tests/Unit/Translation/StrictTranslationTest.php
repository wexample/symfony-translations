<?php

namespace Wexample\SymfonyTranslations\Tests\Unit\Translation;

use PHPUnit\Framework\TestCase;
use Symfony\Bundle\FrameworkBundle\Translation\Translator as SymfonyTranslator;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBag;
use Symfony\Component\HttpKernel\KernelInterface;
use Symfony\Component\Translation\MessageCatalogue;
use Wexample\SymfonyTranslations\Exception\MissingTranslationException;
use Wexample\SymfonyTranslations\Translation\Translator;

class StrictTranslationTest extends TestCase
{
    public function testADefinedKeyIsTranslated(): void
    {
        $this->assertSame('Bonjour', $this->translator(true)->trans('front.home::hello'));
    }

    public function testAMissingKeyThrowsWithItsDomainAndLocales(): void
    {
        $this->expectException(MissingTranslationException::class);
        $this->expectExceptionMessage('Translation "goodbye" is missing from domain "front.home" in fr, en.');

        $this->translator(true)->trans('front.home::goodbye');
    }

    public function testAnUnsetDomainAliasThrows(): void
    {
        $this->expectException(MissingTranslationException::class);

        $this->translator(true)->trans('@page::title');
    }

    public function testPlainTextComesBackUnchanged(): void
    {
        $this->assertSame('Save', $this->translator(true)->trans('Save'));
    }

    public function testAMissingKeyIsReturnedAsIsWhenNotStrict(): void
    {
        $this->assertSame('front.home::goodbye', $this->translator(false)->trans('front.home::goodbye'));
    }

    private function translator(bool $strict): Translator
    {
        $catalogues = [
            'fr' => new MessageCatalogue('fr', ['front.home' => ['hello' => 'Bonjour']]),
            'en' => new MessageCatalogue('en'),
        ];

        $symfonyTranslator = $this->createStub(SymfonyTranslator::class);
        $symfonyTranslator->method('getLocale')->willReturn('fr');
        $symfonyTranslator->method('getFallbackLocales')->willReturn(['en']);
        $symfonyTranslator->method('getCatalogue')
            ->willReturnCallback(fn (?string $locale = null) => $catalogues[$locale ?? 'fr']);
        $symfonyTranslator->method('trans')
            ->willReturnCallback(fn (string $id, array $parameters, string $domain, string $locale) => $catalogues[$locale]->get($id, $domain));

        $kernel = $this->createStub(KernelInterface::class);
        $kernel->method('getProjectDir')->willReturn(sys_get_temp_dir().'/strict-translation-test');
        $kernel->method('getCacheDir')->willReturn(sys_get_temp_dir().'/strict-translation-test/cache');

        return new Translator(
            $symfonyTranslator,
            $kernel,
            new ParameterBag(['translations_paths' => []]),
            $strict
        );
    }
}
