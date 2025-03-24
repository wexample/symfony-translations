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
        $catalogue = new MessageCatalogue('test');
        $catalogue->add([
            'test_key' => 'Test Value',
            'simple_key' => 'Simple value',
            'group.nested_key' => 'Nested translation value',
            'welcome_message' => 'Hello %name%, welcome to our application!'
        ], 'messages');

        // Mock the Symfony translator to return our test catalogue
        $translator->translator->method('getCatalogue')
            ->willReturn($catalogue);
        
        // Mock the Symfony translator's trans method
        $translator->translator->method('trans')
            ->willReturnCallback(function($id, $parameters, $domain, $locale) use ($catalogue) {
                if ($catalogue->has($id, $domain)) {
                    return $catalogue->get($id, $domain);
                }
                return $id;
            });

        // Test basic translation
        $this->assertEquals(
            'Test Value',
            $translator->trans('test_key', [], 'messages'),
            'Basic translation should work correctly'
        );

        // Test simple key translation
        $this->assertEquals(
            'Simple value',
            $translator->trans('simple_key', [], 'messages'),
            'Simple key translation should work correctly'
        );

        // Test with parameters
        $this->assertEquals(
            'Hello John, welcome to our application!',
            $translator->trans('welcome_message', ['%name%' => 'John'], 'messages'),
            'Translation with parameters should work correctly'
        );
    }

    /**
     * Test domain resolution in translations
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
        
        // Mock the Symfony translator to return our test catalogue
        $translator->translator->method('getCatalogue')
            ->willReturn($catalogue);
        
        // Test domain resolution with explicit domain
        $this->assertEquals(
            'Welcome to our site',
            $translator->trans('page_title', [], 'app.pages.home'),
            'Translation with explicit domain should work correctly'
        );
        
        // Test domain resolution with domain in the key
        $this->assertEquals(
            'Welcome to our site',
            $translator->trans('app.pages.home::page_title'),
            'Translation with domain in the key should work correctly'
        );
        
        // Test domain resolution with bundle
        $this->assertEquals(
            'Bundle Title',
            $translator->trans('TestBundle.pages.index::bundle_title'),
            'Translation with bundle domain should work correctly'
        );
        
        // Test fallback behavior when translation is not found
        $this->assertEquals(
            'app.pages.home::nonexistent_key',
            $translator->trans('app.pages.home::nonexistent_key'),
            'Fallback should return the full domain and key when translation is not found'
        );
    }

    /**
     * Test domain stack functionality
     */
    public function testDomainStack(): void
    {
        /** @var Translator $translator */
        $translator = $this->translator;
        
        // Create a test catalogue with domain-specific translations
        $catalogue = new MessageCatalogue('test');
        $catalogue->add(['title' => 'Home Page Title'], 'app.pages.home');
        $catalogue->add(['title' => 'About Page Title'], 'app.pages.about');
        
        // Mock the Symfony translator to return our test catalogue
        $translator->translator->method('getCatalogue')
            ->willReturn($catalogue);
        
        // Set up domain stack
        $translator->setDomain('page', 'app.pages.home');
        
        // Test domain resolution with domain alias
        $this->assertEquals(
            'Home Page Title',
            $translator->trans('@page::title'),
            'Translation with domain alias should work correctly'
        );
        
        // Change the domain in the stack
        $translator->setDomain('page', 'app.pages.about');
        
        // Test that the new domain is used
        $this->assertEquals(
            'About Page Title',
            $translator->trans('@page::title'),
            'Translation should use the updated domain from the stack'
        );
        
        // Revert the domain
        $translator->revertDomain('page');
        
        // Test that the domain stack is empty after revert
        $this->assertNull(
            $translator->getDomain('page'),
            'Domain should be null after revert'
        );
    }
}
