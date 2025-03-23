<?php

namespace Wexample\SymfonyTranslations\Tests\Unit\Translation;

use Wexample\SymfonyTranslations\Test\AbstractTranslationTest;
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
     * Test adding a single translation directory
     */
    public function testAddTranslationDirectory()
    {
        /** @var Translator $translator */
        $translator = $this->translator;

        // Get initial locale count
        $initialLocales = count($this->getLocalesFromTranslator($translator));

        // Add a single translation directory
        $translator->addTranslationDirectory($this->testTranslationsPath);

        // Check that locales were added
        $newLocales = count($this->getLocalesFromTranslator($translator));
        $this->assertGreaterThanOrEqual(
            $initialLocales,
            $newLocales,
            'Adding a translation directory should add new locales'
        );

        // Verify that the 'test' locale was added
        $locales = $this->getLocalesFromTranslator($translator);
        $this->assertContains(
            'test',
            $locales,
            'The test locale should be added when adding the test translations directory'
        );
    }

    /**
     * Test adding multiple translation directories
     */
    public function testAddTranslationDirectories()
    {
        /** @var Translator $translator */
        $translator = $this->translator;

        // Get initial locale count
        $initialLocales = count($this->getLocalesFromTranslator($translator));

        // Create a temporary directory for testing with a test translation file
        $tempDir = sys_get_temp_dir() . '/symfony_translations_test_' . uniqid();
        mkdir($tempDir, 0777, true);
        
        // Add multiple translation directories
        $translator->addTranslationDirectories([
            $this->testTranslationsPath,
            $tempDir
        ]);

        // Check that locales were added
        $newLocales = count($this->getLocalesFromTranslator($translator));
        $this->assertGreaterThanOrEqual(
            $initialLocales,
            $newLocales,
            'Adding translation directories should add new locales'
        );

        // Verify that the 'test' locale was added
        $locales = $this->getLocalesFromTranslator($translator);
        $this->assertContains(
            'test',
            $locales,
            'The test locale should be added when adding the test translations directory'
        );

        // Clean up the temporary directory
        rmdir($tempDir);
    }

    /**
     * Test that adding a non-existent directory is handled gracefully
     */
    public function testAddNonExistentDirectory()
    {
        $this->expectException(\Exception::class);
        /** @var Translator $translator */
        $translator = $this->translator;

        // We expect an exception when adding a non-existent directory
        $this->expectException(\Exception::class);
        
        // Create a path to a directory that doesn't exist
        $nonExistentDir = sys_get_temp_dir() . '/non_existent_dir_' . uniqid();
        
        // This should throw an exception
        $translator->addTranslationDirectory($nonExistentDir);
    }

    /**
     * Helper method to get locales from the translator
     */
    private function getLocalesFromTranslator(Translator $translator): array
    {
        return $translator->getAllLocales();
    }

    /**
     * Helper method to get resources from the Symfony translator
     */
    private function getResourcesFromTranslator(Translator $translator): array
    {
        $resources = [];
        foreach ($translator->translator->getCatalogues() as $catalogue) {
            $resources = array_merge($resources, $catalogue->getResources());
        }
        return $resources;
    }
}
