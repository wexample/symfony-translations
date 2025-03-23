<?php

namespace Wexample\SymfonyTranslations\Tests\Unit\Translation;

use Wexample\SymfonyTesting\Tests\AbstractApplicationTestCase;
use Wexample\SymfonyTranslations\Translation\Translator;

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

        // Set the locale for testing
        $translator->setLocale('test');

        // Add translations directly to the Symfony translator
        $translator->translator->getCatalogue()->set('test_key', 'Test Value');
        $translator->translator->getCatalogue()->set('simple_key', 'Simple value');
        $translator->translator->getCatalogue()->set('group.nested_key', 'Nested translation value');
        $translator->translator->getCatalogue()->set('welcome_message', 'Hello %name%, welcome to our application!');

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

        // Test nested translation
        $this->assertEquals(
            'Nested translation value',
            $translator->trans('group.nested_key', [], 'messages'),
            'Nested translation should work correctly'
        );

        // Test translation with parameters
        $this->assertEquals(
            'Hello John, welcome to our application!',
            $translator->translator->trans('welcome_message', ['%name%' => 'John'], 'messages'),
            'Translation with parameters should work correctly'
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
