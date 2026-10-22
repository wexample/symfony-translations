<?php

namespace Wexample\SymfonyTranslations\Tests\Unit\DependencyInjection;

use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Wexample\SymfonyTranslations\DependencyInjection\WexampleSymfonyTranslationsExtension;
use Wexample\SymfonyTranslations\Interface\TextTranslatorInterface;
use Wexample\SymfonyTranslations\Service\PendingTextTranslator;
use Wexample\SymfonyTranslations\Translation\SyrtisTextTranslator;

class WexampleSymfonyTranslationsExtensionTest extends TestCase
{
    public function testTextsStayPendingWithoutAnEngine(): void
    {
        $container = $this->load([]);

        $this->assertSame(
            PendingTextTranslator::class,
            (string) $container->getAlias(TextTranslatorInterface::class)
        );
        $this->assertFalse($container->hasDefinition(SyrtisTextTranslator::class));
    }

    public function testSyrtisBecomesTheEngineOnceConfigured(): void
    {
        $container = $this->load([
            'syrtis' => [
                'api_key' => 'test-key',
                'session_secure_id' => 'ses_test',
            ],
        ]);

        $this->assertSame(
            SyrtisTextTranslator::class,
            (string) $container->getAlias(TextTranslatorInterface::class)
        );

        $arguments = $container->getDefinition(SyrtisTextTranslator::class)->getArguments();
        $this->assertSame('ses_test', $arguments['$sessionSecureId']);
        $this->assertSame(6000, $arguments['$maxBatchLength']);
        $this->assertSame('https://api.syrtis.ai', $arguments['$client']->getArgument('$host'));
    }

    public function testStrictInTheTestEnvironmentOnly(): void
    {
        $this->assertTrue($this->load([], 'test')->getParameter('wexample_symfony_translations.strict'));
        $this->assertFalse($this->load([], 'dev')->getParameter('wexample_symfony_translations.strict'));
        $this->assertFalse($this->load(['strict' => false], 'test')->getParameter('wexample_symfony_translations.strict'));
        $this->assertTrue($this->load(['strict' => true], 'prod')->getParameter('wexample_symfony_translations.strict'));
    }

    private function load(
        array $config,
        string $environment = 'test'
    ): ContainerBuilder {
        $container = new ContainerBuilder();
        $container->setParameter('kernel.environment', $environment);
        (new WexampleSymfonyTranslationsExtension())->load([$config], $container);

        return $container;
    }
}
