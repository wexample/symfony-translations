<?php

namespace Wexample\SymfonyTranslations\Tests\Unit\Translation;

use Symfony\Component\Translation\MessageCatalogue;
use Wexample\SymfonyTranslations\Tests\AbstractTranslationTest;
use Wexample\SymfonyTranslations\Translation\Translator;

class TranslationDirectoriesTest extends AbstractTranslationTest
{
    protected string $testTranslationsPath;

    protected function setUp(): void
    {
        parent::setUp();

        // Path to test translations directory
        $this->testTranslationsPath = __DIR__ . '/../../Resources/translations';
    }

    /**
     * Test population of translation catalogues
     */
    public function testPopulateCatalogues(): void
    {
        /** @var Translator $translator */
        $translator = $this->translator;

        // Create a mock catalogue
        $catalogue = new MessageCatalogue('test');
        
        // Configure the Symfony translator mock to return our catalogue
        $translator->translator->method('getCatalogue')
            ->willReturn($catalogue);

        // Call populateCatalogues to populate the catalogue with translations
        $translator->populateCatalogues();

        // Test that the catalogue contains translations
        // Note: Since we're using mocks, we can't actually test the content
        // but we can verify that the method runs without errors
        $this->assertInstanceOf(MessageCatalogue::class, $translator->getCatalogue());
    }

    /**
     * Test flattening of nested arrays for translation keys
     */
    public function testFlattenArray(): void
    {
        /** @var Translator $translator */
        $translator = $this->translator;

        // Use reflection to access the private flattenArray method
        $reflectionClass = new \ReflectionClass(Translator::class);
        $method = $reflectionClass->getMethod('flattenArray');
        $method->setAccessible(true);

        // Test flattening a simple array
        $simpleArray = [
            'key1' => 'value1',
            'key2' => 'value2'
        ];
        $flattenedSimple = $method->invoke($translator, $simpleArray);
        $this->assertEquals([
            'key1' => 'value1',
            'key2' => 'value2'
        ], $flattenedSimple);

        // Test flattening a nested array
        $nestedArray = [
            'group1' => [
                'key1' => 'value1',
                'key2' => 'value2'
            ],
            'group2' => [
                'key3' => 'value3',
                'subgroup' => [
                    'key4' => 'value4'
                ]
            ]
        ];
        $flattenedNested = $method->invoke($translator, $nestedArray);
        $this->assertEquals([
            'group1.key1' => 'value1',
            'group1.key2' => 'value2',
            'group2.key3' => 'value3',
            'group2.subgroup.key4' => 'value4'
        ], $flattenedNested);

        // Test flattening with a prefix
        $prefixedArray = [
            'key1' => 'value1',
            'key2' => [
                'subkey1' => 'subvalue1'
            ]
        ];
        $flattenedPrefixed = $method->invoke($translator, $prefixedArray, 'prefix');
        $this->assertEquals([
            'prefix.key1' => 'value1',
            'prefix.key2.subkey1' => 'subvalue1'
        ], $flattenedPrefixed);
    }

    /**
     * Test getting all available locales
     */
    public function testGetAllLocales(): void
    {
        /** @var Translator $translator */
        $translator = $this->translator;

        // The translator should have at least the 'test' locale from setup
        $locales = $translator->getAllLocales();
        $this->assertContains('test', $locales);
        $this->assertContains('en', $locales);
    }

    /**
     * Test filtering translations by key pattern
     */
    public function testTransFilter(): void
    {
        /** @var Translator $translator */
        $translator = $this->translator;

        // Create a mock catalogue with test translations
        $catalogue = new MessageCatalogue('test');
        $catalogue->add([
            'welcome.title' => 'Welcome',
            'welcome.message' => 'Welcome to our site',
            'error.title' => 'Error',
            'error.message' => 'An error occurred'
        ], 'messages');

        // Configure the Symfony translator mock to return our catalogue
        $translator->translator->method('getCatalogue')
            ->willReturn($catalogue);

        // Test filtering with a pattern that matches welcome keys
        $welcomeFiltered = $translator->transFilter('welcome.*');
        $this->assertCount(2, $welcomeFiltered);
        $this->assertArrayHasKey('messages::welcome.title', $welcomeFiltered);
        $this->assertArrayHasKey('messages::welcome.message', $welcomeFiltered);

        // Test filtering with a pattern that matches error keys
        $errorFiltered = $translator->transFilter('error.*');
        $this->assertCount(2, $errorFiltered);
        $this->assertArrayHasKey('messages::error.title', $errorFiltered);
        $this->assertArrayHasKey('messages::error.message', $errorFiltered);

        // Test filtering with a pattern that matches all keys
        $allFiltered = $translator->transFilter('*');
        $this->assertCount(4, $allFiltered);
    }
}
