<?php

namespace Wexample\SymfonyTranslations\Tests\Unit\Translation;

use Wexample\PhpYaml\YamlIncludeResolver;
use Wexample\SymfonyTranslations\Tests\AbstractTranslationTest;
use Wexample\SymfonyTranslations\Translation\Translator;

class TranslationDomainTest extends AbstractTranslationTest
{
    /**
     * Test domain stack management and resolution
     */
    public function testDomainStackAndResolution(): void
    {
        /** @var Translator $translator */
        $translator = $this->translator;

        // Set up test domains
        $translator->setDomain('context', 'translations.test.domain.one');
        $translator->setDomain('page', 'translations.test.domain.two');

        // Test getting domains from stack
        $this->assertEquals('translations.test.domain.one', $translator->getDomain('context'));
        $this->assertEquals('translations.test.domain.two', $translator->getDomain('page'));

        // Test domain resolution with resolveDomain
        $this->assertEquals('context', $translator->resolveDomain('context'));
        $this->assertEquals('translations.test.domain.one', $translator->resolveDomain('@context'));

        // Test multiple domains in stack
        $translator->setDomain('context', 'translations.test.domain.three');
        $this->assertEquals('translations.test.domain.three', $translator->getDomain('context'));
        
        // Test domain reversion
        $translator->revertDomain('context');
        $this->assertEquals('translations.test.domain.one', $translator->getDomain('context'));
        
        // Test reversion of all domains in stack
        $translator->revertDomain('context');
        $this->assertNull($translator->getDomain('context'));
    }

    /**
     * Test setting domain from template path
     */
    public function testDomainFromTemplatePath(): void
    {
        /** @var Translator $translator */
        $translator = $this->translator;

        // Test with a simple path
        $path = 'pages/home/index.html.twig';
        $domainFromPath = $translator->setDomainFromTemplatePath('path_domain', $path);
        
        $this->assertEquals('pages.home.index', $domainFromPath);
        $this->assertEquals($domainFromPath, $translator->getDomain('path_domain'));

        // Test with a more complex path
        $path = 'components/header/navigation.html.twig';
        $domainFromPath = $translator->setDomainFromTemplatePath('component_domain', $path);
        
        $this->assertEquals('components.header.navigation', $domainFromPath);
        $this->assertEquals($domainFromPath, $translator->getDomain('component_domain'));
    }

    /**
     * Test building domain from path
     */
    public function testBuildDomainFromPath(): void
    {
        /** @var Translator $translator */
        $translator = $this->translator;

        // Test with a simple file path
        $filePath = __DIR__ . '/../../Resources/translations/test/messages/messages.test.yml';
        $basePath = __DIR__ . '/../../Resources/translations';
        
        $domain = $translator->buildDomainFromPath($filePath, $basePath);
        $this->assertEquals('translations.test.messages.messages', $domain);

        // Test with bundle name
        $domain = $translator->buildDomainFromPath($filePath, $basePath, 'TestBundle');
        $this->assertEquals('TestBundle.test.messages.messages', $domain);

        // Test with assets directory and bundle name
        $filePath = __DIR__ . '/../../Resources/translations/assets/test/messages/messages.test.yml';
        $domain = $translator->buildDomainFromPath($filePath, $basePath, 'TestBundle');
        $this->assertEquals('TestBundle.test.messages.messages', $domain, 'Assets directory should be removed when bundle name is provided');
    }
}
