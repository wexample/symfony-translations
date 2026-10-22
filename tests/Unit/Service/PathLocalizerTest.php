<?php

namespace Wexample\SymfonyTranslations\Tests\Unit\Service;

use PHPUnit\Framework\TestCase;
use Wexample\SymfonyTranslations\Service\LocaleService;
use Wexample\SymfonyTranslations\Service\PathLocalizer;

class PathLocalizerTest extends TestCase
{
    private function localizer(bool $routing = true, bool $cookie = false): PathLocalizer
    {
        $locales = $this->createStub(LocaleService::class);
        $locales->method('isRoutingEnabled')->willReturn($routing);
        $locales->method('isCookieEnabled')->willReturn($cookie);
        $locales->method('getLocales')->willReturn(['en', 'fr']);
        $locales->method('isPathExcluded')->willReturnCallback(
            static fn (string $path): bool => str_starts_with($path, '/_')
        );

        return new PathLocalizer($locales);
    }

    public function testAPathIsServedUnderEachLanguage(): void
    {
        $this->assertSame(['/en/cart/checkout/', '/fr/cart/checkout/'], $this->localizer()->localizePath('/cart/checkout/'));
    }

    public function testAnExcludedPathStaysAsWritten(): void
    {
        $this->assertSame(['/_system/'], $this->localizer()->localizePath('/_system/'));
    }

    public function testWithoutPrefixedUrlsEveryPathStaysAsWritten(): void
    {
        $this->assertSame(['/cart/'], $this->localizer(routing: false)->localizePath('/cart/'));
        $this->assertSame(['/cart/'], $this->localizer(cookie: true)->localizePath('/cart/'));
    }
}
