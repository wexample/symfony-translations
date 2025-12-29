<?php

namespace Wexample\SymfonyTranslations\Tests\Integration;

use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Twig\Environment;
use Wexample\SymfonyTesting\Tests\Traits\GlobalHandlersSnapshotTrait;
use Wexample\SymfonyTesting\Tests\Traits\RunInSeparateProcessTestTrait;
use Wexample\SymfonyTranslations\Tests\Fixtures\App\AppKernel;

class TranslationColdCacheTest extends KernelTestCase
{
    use GlobalHandlersSnapshotTrait;
    use RunInSeparateProcessTestTrait;

    protected static function getKernelClass(): string
    {
        return AppKernel::class;
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this->snapshotGlobalHandlers();
    }

    protected function tearDown(): void
    {
        self::ensureKernelShutdown();
        $this->restoreGlobalHandlers();
        parent::tearDown();
    }

    protected function runIsolatedTest(): void
    {
        $kernel = new AppKernel('test', true);
        $cacheDir = $kernel->getCacheDir();

        $this->rmDir($cacheDir);

        self::bootKernel();

        /** @var Environment $twig */
        $twig = self::getContainer()->get(Environment::class);
        self::assertSame('Bonjour', trim($twig->render('page.html.twig')));
    }

    private function rmDir(string $dir): void
    {
        if (!is_dir($dir)) {
            return;
        }

        $items = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($dir, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::CHILD_FIRST
        );

        foreach ($items as $item) {
            if ($item->isDir()) {
                @rmdir($item->getPathname());
            } else {
                @unlink($item->getPathname());
            }
        }

        @rmdir($dir);
    }

}
