<?php

namespace Wexample\SymfonyTranslations\Tests\Unit\Translation;

use Wexample\SymfonyTranslations\Tests\AbstractTranslationTest;
use Wexample\SymfonyTranslations\Translation\Translator;

class TranslationDomainTest extends AbstractTranslationTest
{
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
