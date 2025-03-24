<?php

namespace Wexample\SymfonyTranslations\Tests\Unit\Translation;

use Symfony\Component\Translation\MessageCatalogue;
use Wexample\SymfonyTranslations\Tests\AbstractTranslationTest;
use Wexample\SymfonyTranslations\Translation\Translator;

class TranslationTest extends AbstractTranslationTest
{
    /**
     * Test basic translation functionality
     */
    public function testBasicTranslation(): void
    {
        /** @var Translator $translator */
        $translator = $this->translator;

        // Create a test catalogue with some translations
        $catalogue = $this->createMock(\Symfony\Component\Translation\MessageCatalogueInterface::class);
        
        // Configure the has method to return true for our test keys
        $catalogue->method('has')
            ->willReturnCallback(function($id, $domain) {
                return $domain === 'messages' && in_array($id, ['test_key', 'simple_key', 'group.nested_key', 'welcome_message']);
            });

        // Configure the get method to return our translations
        $catalogue->method('get')
            ->willReturnCallback(function($id, $domain) {
                $translations = [
                    'test_key' => 'Test Value',
                    'simple_key' => 'Simple value',
                    'group.nested_key' => 'Nested translation value',
                    'welcome_message' => 'Hello %name%, welcome to our application!'
                ];
                
                return $translations[$id] ?? $id;
            });

        // Configure the translator mock to return our catalogue
        $translator->translator->method('getCatalogue')
            ->willReturn($catalogue);

        // Configure the translator mock to return our test translations
        $translator->translator->method('trans')
            ->willReturnCallback(function($id, $parameters, $domain, $locale) use ($catalogue) {
                $translation = $catalogue->get($id, $domain);
                
                foreach ($parameters as $key => $value) {
                    $translation = str_replace($key, $value, $translation);
                }
                
                return $translation;
            });

        // Test basic translation with forceTranslate
        $this->assertEquals(
            'Test Value',
            $translator->trans('test_key', [], 'messages', null, true),
            'Basic translation should work correctly'
        );

        // Test simple key translation with forceTranslate
        $this->assertEquals(
            'Simple value',
            $translator->trans('simple_key', [], 'messages', null, true),
            'Simple key translation should work correctly'
        );

        // Test with parameters and forceTranslate
        $this->assertEquals(
            'Hello John, welcome to our application!',
            $translator->trans('welcome_message', ['%name%' => 'John'], 'messages', null, true),
            'Translation with parameters should work correctly'
        );
    }

    /**
     * Test domain resolution functionality
     */
    public function testDomainResolution(): void
    {
        /** @var Translator $translator */
        $translator = $this->translator;

        // Create a test catalogue with domain-specific translations
        $catalogue = new MessageCatalogue('test');
        $catalogue->add(['page_title' => 'Welcome to our site'], 'app.pages.home');
        $catalogue->add(['header' => 'Main Header'], 'app.components.header');
        
        // Add a bundle translation to test the assets handling
        $catalogue->add(['bundle_title' => 'Bundle Title'], 'TestBundle.pages.index');
        
        // Create a mock catalogue that will respond correctly to has() method
        $mockCatalogue = $this->createMock(\Symfony\Component\Translation\MessageCatalogueInterface::class);
        
        // Configure the has method to return true for our test keys
        $mockCatalogue->method('has')
            ->willReturnCallback(function($id, $domain) {
                return ($domain === 'app.pages.home' && $id === 'page_title') ||
                       ($domain === 'app.components.header' && $id === 'header') ||
                       ($domain === 'TestBundle.pages.index' && $id === 'bundle_title');
            });

        // Configure the translator mock to return our mock catalogue
        $translator->translator->method('getCatalogue')
            ->willReturn($mockCatalogue);

        // Configure the translator mock to return our test translations
        $translator->translator->method('trans')
            ->willReturnCallback(function($id, $parameters, $domain, $locale) {
                $translations = [
                    'app.pages.home' => [
                        'page_title' => 'Welcome to our site'
                    ],
                    'app.components.header' => [
                        'header' => 'Main Header'
                    ],
                    'TestBundle.pages.index' => [
                        'bundle_title' => 'Bundle Title'
                    ]
                ];
                
                if (isset($translations[$domain][$id])) {
                    return $translations[$domain][$id];
                }
                
                return $id;
            });
        
        // Test domain resolution with explicit domain
        $this->assertEquals(
            'Welcome to our site',
            $translator->trans('page_title', [], 'app.pages.home', null, true),
            'Translation with explicit domain should work correctly'
        );
        
        // Test domain resolution with domain in the key
        $this->assertEquals(
            'Welcome to our site',
            $translator->trans('app.pages.home::page_title', [], null, null, true),
            'Translation with domain in the key should work correctly'
        );
        
        // Test bundle domain resolution
        $this->assertEquals(
            'Bundle Title',
            $translator->trans('bundle_title', [], 'TestBundle.pages.index', null, true),
            'Translation with bundle domain should work correctly'
        );
    }

    /**
     * Test domain stack functionality
     */
    public function testDomainStack(): void
    {
        /** @var Translator $translator */
        $translator = $this->translator;
        
        // Create a mock catalogue that will respond correctly to has() method
        $mockCatalogue = $this->createMock(\Symfony\Component\Translation\MessageCatalogueInterface::class);
        
        // Configure the has method to return true for our test keys
        $mockCatalogue->method('has')
            ->willReturnCallback(function($id, $domain) {
                return ($domain === 'app.pages.home' && $id === 'title') ||
                       ($domain === 'app.pages.about' && $id === 'title');
            });

        // Configure the translator mock to return our mock catalogue
        $translator->translator->method('getCatalogue')
            ->willReturn($mockCatalogue);

        // Configure the translator mock to return our test translations
        $translator->translator->method('trans')
            ->willReturnCallback(function($id, $parameters, $domain, $locale) {
                $translations = [
                    'app.pages.home' => [
                        'title' => 'Home Page Title'
                    ],
                    'app.pages.about' => [
                        'title' => 'About Page Title'
                    ]
                ];
                
                if (isset($translations[$domain][$id])) {
                    return $translations[$domain][$id];
                }
                
                return $id;
            });
        
        // Set up domain stack
        $translator->setDomain('page', 'app.pages.home');
        
        // Test domain resolution with domain alias
        $this->assertEquals(
            'Home Page Title',
            $translator->trans('@page::title', [], null, null, true),
            'Translation with domain alias should work correctly'
        );
        
        // Change the domain in the stack
        $translator->setDomain('page', 'app.pages.about');
        
        // Test domain resolution with updated domain alias
        $this->assertEquals(
            'About Page Title',
            $translator->trans('@page::title', [], null, null, true),
            'Translation with updated domain alias should work correctly'
        );
    }
}
