<?php

namespace Wexample\SymfonyTranslations\Tests\Unit\Twig;

use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Contracts\Translation\TranslatorInterface;
use Wexample\SymfonyTranslations\Service\LocaleService;
use Wexample\SymfonyTranslations\Twig\LocaleExtension;

class LocaleExtensionTest extends TestCase
{
    public function testANumberIsWrittenTheWayTheLocaleWritesIt(): void
    {
        $extension = $this->extension('en');

        $this->assertSame('1,234.5', $extension->formatNumber(1234.5));
        // French groups thousands with a narrow no-break space.
        $this->assertSame("1\u{202F}234,5", $extension->formatNumber(1234.5, locale: 'fr'));
    }

    public function testDecimalsAreFixedWhenGiven(): void
    {
        $extension = $this->extension('en');

        $this->assertSame('1,234.50', $extension->formatNumber('1234.5', 2));
        $this->assertSame('3', $extension->formatNumber(2.6, 0));
    }

    public function testWhatIsNotANumberIsPrintedAsItIs(): void
    {
        $extension = $this->extension('en');

        $this->assertSame('n/a', $extension->formatNumber('n/a'));
        $this->assertSame('', $extension->formatNumber(null));
    }

    private function extension(string $locale): LocaleExtension
    {
        $translator = $this->createStub(TranslatorInterface::class);
        $translator->method('getLocale')->willReturn($locale);

        return new LocaleExtension(
            $this->createStub(LocaleService::class),
            new RequestStack(),
            $this->createStub(UrlGeneratorInterface::class),
            $translator,
        );
    }
}
