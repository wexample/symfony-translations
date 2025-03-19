<?php

namespace Wexample\SymfonyTranslations\Tests\Unit\Translation;

use Wexample\SymfonyTranslations\WexampleSymfonyTranslationsBundle;
use Wexample\SymfonyHelpers\Helper\BundleHelper;
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

    public function testTranslation()
    {
        /** @var Translator $translator */
        $translator = $this->translator;

        $this->assertNotNull($translator);

        $translator->addTranslationDirectory(
            BundleHelper::getBundleRootPath(
                WexampleSymfonyTranslationsBundle::class,
                self::$kernel
            )
            .'tests/Resources/translations/'
        );

        $translator->resolveCatalog();

        $translator->setLocale('test');

        $this->_testOne();

        // Missing key fails to be extended.
        $this->assertTranslation(
            'include_missing',
            '@test.domain.two::missing'
        );

        $this->_testOne('test.domain.three');
        
        // Test with null domain
        $this->testNullDomain();
    }
    
    /**
     * Test translation with null domain
     */
    public function testNullDomain()
    {
        /** @var Translator $translator */
        $translator = $this->translator;
        
        // Test with a direct call to the underlying Symfony translator
        // This bypasses our custom domain handling and uses the default 'messages' domain
        $value = 'Direct translation with null domain';
        $key = 'direct_null_domain_key';
        
        // Add the translation directly to the Symfony translator
        $translator->translator->getCatalogue()->set($key, $value, 'messages');
        
        // Test translation with null domain using the direct key
        $this->assertEquals(
            $value,
            $translator->trans($key, [], null)
        );
    }

    protected function _testOne(string $domain = 'test.domain.one')
    {
        // Simple

        $this->assertTranslation(
            'simple_key',
            'Simple value',
            $domain
        );

        $this->assertTranslation(
            'simple_group.simple_group_key',
            'Simple group value',
            $domain
        );

        // Include with the same key name

        $this->assertTranslation(
            'include_key_full_notation',
            'Included value',
            $domain
        );

        $this->assertTranslation(
            'include_key_short_notation',
            'Included string with short notation',
            $domain
        );

        // Include with a different key name

        $this->assertTranslation(
            'include_different_key',
            'Included value with different key',
            $domain
        );

        // Include a group

        $this->assertTranslation(
            'deep_values.deepTwo',
            'Deep two',
            $domain
        );

        $this->assertTranslation(
            'deep_values_2.deeper.deepTwo',
            'Deep two',
            $domain
        );

        $this->assertTranslation(
            'simple_group.include_group_short_notation.sub_group.two',
            'Two',
            $domain
        );

        // Loop

        $this->assertTranslation(
            'include_resolvable_loop.sub_group.two',
            'Two',
            $domain
        );
    }

    protected function assertTranslation(
        string $key,
        string $expectedValue,
        string $domain = 'test.domain.one',
        array $args = []
    ) {
        $this->assertEquals(
            $expectedValue,
            $this->translator->trans(
                $key,
                $args,
                $domain,
            ),
            'Translation '.$domain.'::'.$key.' is translated as "'.$expectedValue.'"'
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
