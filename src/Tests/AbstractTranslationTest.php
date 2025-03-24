<?php

namespace Wexample\SymfonyTranslations\Tests;

use Symfony\Bundle\FrameworkBundle\Translation\Translator as SymfonyTranslator;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Component\HttpKernel\KernelInterface;
use Wexample\SymfonyTesting\Tests\AbstractApplicationTestCase;
use Wexample\SymfonyTranslations\Translation\Translator;

abstract class AbstractTranslationTest extends AbstractApplicationTestCase
{
    protected ?object $translator = null;

    protected function setUp(): void
    {
        parent::setUp();

        // Create mock objects
        $symTranslator = $this->createMock(SymfonyTranslator::class);
        $kernel = $this->createMock(KernelInterface::class);
        $parameterBag = $this->createMock(ParameterBagInterface::class);

        // Configure mocks
        $kernel->method('getProjectDir')
            ->willReturn(__DIR__);

        $parameterBag->method('get')
            ->with('translations_paths')
            ->willReturn([__DIR__ . '/Resources/translations']);

        // Create a stub for the Symfony translator's getCatalogue method
        $catalogue = new \Symfony\Component\Translation\MessageCatalogue('test');
        $symTranslator->method('getCatalogue')
            ->willReturn($catalogue);

        $symTranslator->method('getFallbackLocales')
            ->willReturn(['en']);

        $symTranslator->method('getLocale')
            ->willReturn('test');

        // Create the translator instance
        $this->translator = new Translator($symTranslator, $kernel, $parameterBag);
    }

    protected function tearDown(): void
    {
        $this->translator = null;
        parent::tearDown();
    }
}
