<?php

namespace Wexample\SymfonyTranslations\Tests\Unit\Translation;

use Wexample\SymfonyTranslations\Test\AbstractTranslationTest;
use Wexample\SymfonyTranslations\Translation\Translator;


class TranslationTest extends AbstractTranslationTest
{
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
}
