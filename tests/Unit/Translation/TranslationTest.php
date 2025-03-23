<?php

namespace Wexample\SymfonyTranslations\Tests\Unit\Translation;

use Wexample\SymfonyHelpers\Helper\BundleHelper;
use Wexample\SymfonyTesting\Tests\AbstractApplicationTestCase;
use Wexample\SymfonyTranslations\Translation\Translator;
use Wexample\SymfonyTranslations\WexampleSymfonyTranslationsBundle;

class TranslationTest extends AbstractApplicationTestCase
{
    protected ?object $translator = null;

    protected function setUp(): void
    {
        parent::setUp();

        /* @var Translator $translator */
        $this->translator = self::getContainer()->get(Translator::class);
    }

    public function testBasicTranslation()
    {
        /** @var Translator $translator */
        $translator = $this->translator;

        $this->assertNotNull($translator);

        // Add the test translations directory
        $translator->addTranslationDirectory(
            BundleHelper::getBundleRootPath(
                WexampleSymfonyTranslationsBundle::class,
                self::$kernel
            )
            .'tests/Resources/translations/'
        );

        // Resolve the catalog to load translations
        $translator->resolveCatalog();

        // Set the locale for testing
        $translator->setLocale('test');

        // Create a simple test translation
        $key = 'test_key';
        $value = 'Test Value';
        
        // Add the translation directly to the Symfony translator
        $translator->getCatalogue()->set($key, $value, 'messages');

        // Test that the translation works
        $this->assertEquals(
            $value,
            $translator->trans($key, [], 'messages'),
            'Basic translation should work correctly'
        );
    }

    /**
     * Test domain stack management
     */
    public function testDomainStack()
    {
        /** @var Translator $translator */
        $translator = $this->translator;

        // Set up test domains
        $translator->setDomain('context', '@test.domain.one');
        $translator->setDomain('page', '@test.domain.two');

        // Test getting domains from stack
        $this->assertEquals('@test.domain.one', $translator->getDomain('context'));
        $this->assertEquals('@test.domain.two', $translator->getDomain('page'));

        // Test domain resolution with resolveDomain
        $this->assertEquals('context', $translator->resolveDomain('context'));
        $this->assertEquals('@test.domain.one', $translator->resolveDomain('@context'));

        // Test setting domain from path
        $path = 'test/path/to/file.html.twig';
        $domainFromPath = $translator->setDomainFromPath('path_domain', $path);

        // Verify the domain was set correctly
        $this->assertNotNull($translator->getDomain('path_domain'));
        $this->assertEquals($domainFromPath, $translator->getDomain('path_domain'));

        // Test domain reversion
        $translator->setDomain('temp_domain', '@test.domain.three');
        $this->assertEquals('@test.domain.three', $translator->getDomain('temp_domain'));
        $translator->revertDomain('temp_domain');
        $this->assertNull($translator->getDomain('temp_domain'));
    }
}
