<?php

namespace Wexample\SymfonyTranslations\Tests\Unit\Service;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Yaml\Yaml;
use Wexample\SymfonyTranslations\Service\LocaleConfigService;
use Wexample\SymfonyTranslations\Service\LocaleService;

class LocaleConfigServiceTest extends TestCase
{
    private string $dir;

    protected function setUp(): void
    {
        $this->dir = sys_get_temp_dir().'/locale-config-service-'.uniqid();
        mkdir($this->dir.'/config/packages', 0777, true);
        file_put_contents(
            $this->dir.'/'.LocaleConfigService::CONFIG_FILE,
            "framework:\n    # The site's languages.\n    default_locale: en\n    enabled_locales: [en, fr]\n"
        );
    }

    protected function tearDown(): void
    {
        exec('rm -rf '.escapeshellarg($this->dir));
    }

    public function testKeepsEveryLocaleEnabledInOneRun(): void
    {
        // The container still knows the list it was compiled with.
        $service = new LocaleConfigService($this->dir, new LocaleService('en', ['en', 'fr'], false, []));

        $this->assertTrue($service->enableLocale('zh'));
        $this->assertTrue($service->enableLocale('de'));
        $this->assertFalse($service->enableLocale('zh'));

        $content = file_get_contents($this->dir.'/'.LocaleConfigService::CONFIG_FILE);

        $this->assertSame(['en', 'fr', 'zh', 'de'], Yaml::parse($content)['framework']['enabled_locales']);
        $this->assertStringContainsString("# The site's languages.", $content);
    }
}
