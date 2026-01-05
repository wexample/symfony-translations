<?php

namespace Wexample\SymfonyTranslations\Tests;

use Symfony\Bundle\FrameworkBundle\Translation\Translator as SymfonyTranslator;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Component\HttpKernel\KernelInterface;
use Symfony\Component\Translation\MessageCatalogueInterface;
use Wexample\SymfonyTesting\Tests\AbstractApplicationTestCase;
use Wexample\SymfonyTranslations\Translation\Translator;

abstract class AbstractTranslationTest extends AbstractApplicationTestCase
{
    protected ?object $translator = null;
    protected SymfonyTranslator $symTranslator;

    protected function setUp(): void
    {
        parent::setUp();

        // Create mock objects
        $this->symTranslator = $this->createStub(SymfonyTranslator::class);
        $kernel = $this->createStub(KernelInterface::class);
        $parameterBag = $this->createStub(ParameterBagInterface::class);

        // Configure mocks
        $kernel->method('getProjectDir')
            ->willReturn(__DIR__);

        $parameterBag->method('get')
            ->with('translations_paths')
            ->willReturn([__DIR__ . '/Resources/translations']);

        // Create a stub for the Symfony translator's getCatalogue method
        $catalogue = new \Symfony\Component\Translation\MessageCatalogue('test');
        $this->symTranslator->method('getCatalogue')
            ->willReturn($catalogue);

        $this->symTranslator->method('getFallbackLocales')
            ->willReturn(['en']);

        $this->symTranslator->method('getLocale')
            ->willReturn('test');

        // Create the translator instance
        $this->translator = new Translator($this->symTranslator, $kernel, $parameterBag);
    }

    /**
     * Configure the translator mock with the given translations
     */
    protected function configureTranslatorMock(array $translations): void
    {
        // Create a mock catalogue that will respond correctly to has() method
        $mockCatalogue = $this->createStub(MessageCatalogueInterface::class);

        // Configure the has method to return true for our test keys
        $mockCatalogue->method('has')
            ->willReturnCallback(function ($id, $domain) use ($translations) {
                return isset($translations[$domain][$id]);
            });

        // Configure the get method to return our translations
        $mockCatalogue->method('get')
            ->willReturnCallback(function ($id, $domain) use ($translations) {
                return $translations[$domain][$id] ?? $id;
            });

        // Configure the translator mock to return our catalogue
        $this->symTranslator->method('getCatalogue')
            ->willReturn($mockCatalogue);

        // Configure the translator mock to return our test translations
        $this->symTranslator->method('trans')
            ->willReturnCallback(function ($id, $parameters, $domain, $locale) use ($translations) {
                $translation = $translations[$domain][$id] ?? $id;

                foreach ($parameters as $key => $value) {
                    $translation = str_replace($key, $value, $translation);
                }

                return $translation;
            });
    }

    protected function tearDown(): void
    {
        $this->translator = null;
        parent::tearDown();
    }
}
