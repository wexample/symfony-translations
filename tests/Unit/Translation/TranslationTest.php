<?php

namespace Wexample\SymfonyTranslations\Tests\Unit\Translation;

use Symfony\Bundle\FrameworkBundle\Translation\Translator as SymfonyTranslator;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Component\HttpKernel\KernelInterface;
use Wexample\SymfonyTranslations\Tests\AbstractTranslationTest;
use Wexample\SymfonyTranslations\Translation\Translator;

class TranslationTest extends AbstractTranslationTest
{
    protected function setUp(): void
    {
        parent::setUp();

        // Create mock objects
        $this->symTranslator = $this->createMock(SymfonyTranslator::class);
        $kernel = $this->createMock(KernelInterface::class);
        $parameterBag = $this->createMock(ParameterBagInterface::class);

        // Configure mocks
        $kernel->method('getProjectDir')
            ->willReturn(__DIR__);

        $parameterBag->method('get')
            ->with('translations_paths')
            ->willReturn([__DIR__ . '/Resources/translations']);

        // Configure the Symfony translator's getFallbackLocales method
        $this->symTranslator->method('getFallbackLocales')
            ->willReturn(['en']);

        $this->symTranslator->method('getLocale')
            ->willReturn('test');

        // Create the translator instance
        $this->translator = new Translator($this->symTranslator, $kernel, $parameterBag);
    }

    /**
     * Test basic translation functionality
     */
    public function testBasicTranslation(): void
    {
        $translations = [
            'messages' => [
                'test_key' => 'Test Value',
                'simple_key' => 'Simple value',
                'group.nested_key' => 'Nested translation value',
                'welcome_message' => 'Hello %name%, welcome to our application!',
            ],
        ];

        $this->configureTranslatorMock($translations);

        // Test basic translation with forceTranslate
        $this->assertEquals(
            'Test Value',
            $this->translator->trans('test_key', [], 'messages', null, true),
            'Basic translation should work correctly'
        );

        // Test simple key translation with forceTranslate
        $this->assertEquals(
            'Simple value',
            $this->translator->trans('simple_key', [], 'messages', null, true),
            'Simple key translation should work correctly'
        );

        // Test with parameters and forceTranslate
        $this->assertEquals(
            'Hello John, welcome to our application!',
            $this->translator->trans('welcome_message', ['%name%' => 'John'], 'messages', null, true),
            'Translation with parameters should work correctly'
        );
    }

    /**
     * Test domain resolution functionality
     */
    public function testDomainResolution(): void
    {
        $translations = [
            'app.pages.home' => [
                'page_title' => 'Welcome to our site',
            ],
            'app.components.header' => [
                'header' => 'Main Header',
            ],
            'TestBundle.pages.index' => [
                'bundle_title' => 'Bundle Title',
            ],
        ];

        $this->configureTranslatorMock($translations);

        // Test domain resolution with explicit domain
        $this->assertEquals(
            'Welcome to our site',
            $this->translator->trans('page_title', [], 'app.pages.home', null, true),
            'Translation with explicit domain should work correctly'
        );

        // Test domain resolution with domain in the key
        $this->assertEquals(
            'Welcome to our site',
            $this->translator->trans('app.pages.home::page_title', [], null, null, true),
            'Translation with domain in the key should work correctly'
        );

        // Test bundle domain resolution
        $this->assertEquals(
            'Bundle Title',
            $this->translator->trans('bundle_title', [], 'TestBundle.pages.index', null, true),
            'Translation with bundle domain should work correctly'
        );
    }

    /**
     * Test domain stack functionality
     */
    public function testDomainStack(): void
    {
        $translations = [
            'app.pages.home' => [
                'title' => 'Home Page Title',
            ],
            'app.pages.about' => [
                'title' => 'About Page Title',
            ],
        ];

        $this->configureTranslatorMock($translations);

        // Set up domain stack
        $this->translator->setDomain('page', 'app.pages.home');

        // Test domain resolution with domain alias
        $this->assertEquals(
            'Home Page Title',
            $this->translator->trans('@page::title', [], null, null, true),
            'Translation with domain alias should work correctly'
        );

        // Change the domain in the stack
        $this->translator->setDomain('page', 'app.pages.about');

        // Test domain resolution with updated domain alias
        $this->assertEquals(
            'About Page Title',
            $this->translator->trans('@page::title', [], null, null, true),
            'Translation with updated domain alias should work correctly'
        );
    }

    /**
     * Test missing
     */
    public function testMissingTranslations(): void
    {
        $this->configureTranslatorMock([]);
        $this->translator->setDomain('page', 'app.pages.home');

        $this->assertEquals(
            'Missing',
            $this->translator->trans('Missing', [], null, null, true),
            'Missing translation should be returned as it is'
        );

        $this->assertEquals(
            'Missing',
            $this->translator->trans('Missing', [], null, null),
            'Missing translation should be returned as it is'
        );
    }
}
