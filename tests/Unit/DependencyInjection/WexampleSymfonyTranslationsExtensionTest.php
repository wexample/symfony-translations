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

    private function load(array $config): ContainerBuilder
    {
        $container = new ContainerBuilder();
        (new WexampleSymfonyTranslationsExtension())->load([$config], $container);

        return $container;
    }
}
