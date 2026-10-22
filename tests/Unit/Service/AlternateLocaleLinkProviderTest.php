<?php

namespace Wexample\SymfonyTranslations\Tests\Unit\Service;

use PHPUnit\Framework\TestCase;
use Wexample\SymfonyTranslations\Service\AlternateLocaleLinkProvider;
use Wexample\SymfonyTranslations\Service\LocaleService;
use Wexample\SymfonyTranslations\Service\LocaleUrlService;

class AlternateLocaleLinkProviderTest extends TestCase
{
    private function provider(bool $cookie, ?string $pathPrefix = 'https://site.test/'): AlternateLocaleLinkProvider
    {
        $locales = $this->createStub(LocaleService::class);
        $locales->method('isCookieEnabled')->willReturn($cookie);
        $locales->method('getLocales')->willReturn(['en', 'fr', 'pt_BR']);
        $locales->method('getDefaultLocale')->willReturn('en');
        $locales->method('getLanguageTag')->willReturnCallback(fn (string $locale) => str_replace('_', '-', $locale));

        $urls = $this->createStub(LocaleUrlService::class);
        $urls->method('getLocaleUrl')->willReturnCallback(
            fn (string $locale) => null === $pathPrefix ? null : $pathPrefix.$locale.'/'
        );

        return new AlternateLocaleLinkProvider($locales, $urls);
    }

    public function testEveryLanguageIsLinkedWithADefault(): void
    {
        $this->assertSame([
            ['rel' => 'alternate', 'hreflang' => 'en', 'href' => 'https://site.test/en/'],
            ['rel' => 'alternate', 'hreflang' => 'fr', 'href' => 'https://site.test/fr/'],
            ['rel' => 'alternate', 'hreflang' => 'pt-BR', 'href' => 'https://site.test/pt_BR/'],
            ['rel' => 'alternate', 'hreflang' => 'x-default', 'href' => 'https://site.test/en/'],
        ], $this->provider(false)->getHeadLinks());
    }

    public function testNothingWhereTheLanguageIsNotInTheUrl(): void
    {
        $this->assertSame([], $this->provider(true)->getHeadLinks());
    }

    public function testNothingOnAPageWithoutVersions(): void
    {
        $this->assertSame([], $this->provider(false, null)->getHeadLinks());
    }
}
