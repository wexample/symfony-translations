<?php

namespace Wexample\SymfonyTranslations\Tests\Integration;

use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Twig\Environment;
use Wexample\SymfonyTranslations\Tests\Fixtures\App\AppKernel;

/**
 * The resolved catalogue of a locale is read from the kernel cache while
 * none of its files changes, and resolved again once one does.
 */
class CatalogueCacheTest extends KernelTestCase
{
    private const string MESSAGES = __DIR__ . '/../Fixtures/App/translations/test/messages.fr.yml';

    protected static function getKernelClass(): string
    {
        return AppKernel::class;
    }

    public function testTheResolvedCatalogueIsKeptUntilAFileChanges(): void
    {
        $this->assertSame('Bonjour', $this->render());

        $cacheFile = self::$kernel->getCacheDir() . '/wexample_translations/catalogues/fr.php';
        $this->assertFileExists($cacheFile);

        // Nothing changed: the cached catalogue answers, even a doctored one.
        $cached = include $cacheFile;
        $cached['content']['translations.test.messages']['hello'] = 'Bonjour du cache';
        file_put_contents($cacheFile, '<?php return ' . var_export($cached, true) . ';');
        if (function_exists('opcache_invalidate')) {
            opcache_invalidate($cacheFile, true);
        }
        $this->assertSame('Bonjour du cache', $this->render());

        // A file touched: resolved again from the files.
        clearstatcache();
        touch(self::MESSAGES, filemtime(self::MESSAGES) + 1);
        $this->assertSame('Bonjour', $this->render());
    }

    private function render(): string
    {
        self::ensureKernelShutdown();
        self::bootKernel();

        return trim(self::getContainer()->get(Environment::class)->render('page.html.twig'));
    }
}
