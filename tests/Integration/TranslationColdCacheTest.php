<?php

namespace Wexample\SymfonyTranslations\Tests\Integration;

use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Twig\Environment;
use Wexample\SymfonyTranslations\Tests\Fixtures\App\AppKernel;

class TranslationColdCacheTest extends KernelTestCase
{
    private mixed $initialExceptionHandler = null;
    private mixed $initialErrorHandler = null;

    protected static function getKernelClass(): string
    {
        return AppKernel::class;
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this->initialExceptionHandler = $this->getCurrentExceptionHandler();
        $this->initialErrorHandler = $this->getCurrentErrorHandler();
    }

    protected function tearDown(): void
    {
        self::ensureKernelShutdown();
        $this->restoreHandlers();
        parent::tearDown();
    }

    #[\PHPUnit\Framework\Attributes\RunInSeparateProcess]
    public function testTranslationsAvailableOnFirstRequestAfterColdCache(): void
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

    private function restoreHandlers(): void
    {
        for ($i = 0; $i < 50; $i++) {
            $current = $this->getCurrentExceptionHandler();
            if ($current === $this->initialExceptionHandler) {
                break;
            }
            if (!restore_exception_handler()) {
                break;
            }
        }

        for ($i = 0; $i < 50; $i++) {
            $current = $this->getCurrentErrorHandler();
            if ($current === $this->initialErrorHandler) {
                break;
            }
            if (!restore_error_handler()) {
                break;
            }
        }
    }

    private function getCurrentExceptionHandler(): mixed
    {
        $temporary = static function (): void {
        };

        $previous = set_exception_handler($temporary);
        restore_exception_handler();

        return $previous;
    }

    private function getCurrentErrorHandler(): mixed
    {
        $temporary = static function (): void {
        };

        $previous = set_error_handler($temporary);
        restore_error_handler();

        return $previous;
    }
}
