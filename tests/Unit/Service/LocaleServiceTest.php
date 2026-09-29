<?php

namespace Wexample\SymfonyTranslations\Tests\Unit\Service;

use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Wexample\SymfonyTranslations\Service\LocaleService;

class LocaleServiceTest extends TestCase
{
    public function testTakesTheDirectionFromTheLocaleData(): void
    {
        $service = $this->createService();

        foreach (['ar', 'he', 'fa', 'ur', 'ar_EG'] as $locale) {
            $this->assertSame(LocaleService::DIRECTION_RTL, $service->getDirection($locale), $locale);
        }

        foreach (['en', 'fr', 'zh', 'ja', 'en_GB'] as $locale) {
            $this->assertSame(LocaleService::DIRECTION_LTR, $service->getDirection($locale), $locale);
        }
    }

    public function testReadsContentInMoreLocalesThanTheInterface(): void
    {
        $service = $this->createService(contentLocales: ['it', 'fr']);

        $this->assertSame(['en', 'fr', 'ar'], $service->getLocales());
        $this->assertSame(['en', 'fr', 'ar', 'it'], $service->getContentLocales());
        $this->assertFalse($service->hasLocale('it'));
        $this->assertTrue($service->hasContentLocale('it'));
    }

    public function testGuessesAnApiAnswerAmongTheContentLocales(): void
    {
        $service = $this->createService(contentLocales: ['it']);
        $request = Request::create('/api/article');
        $request->headers->set('Accept-Language', 'it-IT,it;q=0.9');

        $this->assertSame('en', $service->guessLocale($request));
        $this->assertSame('it', $service->guessLocale($request, true));
    }

    public function testWritesLanguageTagsForHtml(): void
    {
        $this->assertSame('en-GB', $this->createService()->getLanguageTag('en_GB'));
    }

    private function createService(array $contentLocales = []): LocaleService
    {
        return new LocaleService('en', ['en', 'fr', 'ar'], true, [], $contentLocales);
    }
}
