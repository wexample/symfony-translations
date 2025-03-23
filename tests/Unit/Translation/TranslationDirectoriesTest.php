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

        // Get initial resource count
        $initialResources = count($this->getResourcesFromTranslator($translator));

        // Add a single translation directory
        $translator->addTranslationDirectory($this->testTranslationsPath);

        // Check that locales were added
        $newLocales = count($this->getLocalesFromTranslator($translator));
        $this->assertGreaterThan(
            $initialLocales,
            $newLocales,
            'Adding a translation directory should add new locales'
        );

        // Check that resources were added
        $newResources = count($this->getResourcesFromTranslator($translator));
        $this->assertGreaterThan(
            $initialResources,
            $newResources,
            'Adding a translation directory should add new resources'
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

        // Get initial resource count
        $initialResources = count($this->getResourcesFromTranslator($translator));

        // Create a temporary directory for testing
        $tempDir = sys_get_temp_dir() . '/symfony_translations_test_' . uniqid();
        mkdir($tempDir, 0777, true);

        // Create a test directory that doesn't exist
        $nonExistentDir = sys_get_temp_dir() . '/non_existent_dir_' . uniqid();

        // Add multiple translation directories, including one that doesn't exist
        $translator->addTranslationDirectories([
            $this->testTranslationsPath,
            $tempDir,
            $nonExistentDir // This directory doesn't exist and should be skipped
        ]);

        // Check that locales were added
        $newLocales = count($this->getLocalesFromTranslator($translator));
        $this->assertGreaterThan(
            $initialLocales,
            $newLocales,
            'Adding translation directories should add new locales'
        );

        // Check that resources were added
        $newResources = count($this->getResourcesFromTranslator($translator));
        $this->assertGreaterThan(
            $initialResources,
            $newResources,
            'Adding translation directories should add new resources'
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
        /** @var Translator $translator */
        $translator = $this->translator;

        // Get initial resource count
        $initialResources = count($this->getResourcesFromTranslator($translator));

        // Create a path to a directory that doesn't exist
        $nonExistentDir = sys_get_temp_dir() . '/non_existent_dir_' . uniqid();

        // Add a non-existent directory
        $translator->addTranslationDirectory($nonExistentDir);

        // Check that no resources were added
        $newResources = count($this->getResourcesFromTranslator($translator));
        $this->assertEquals(
            $initialResources,
            $newResources,
            'Adding a non-existent directory should not add any resources'
        );
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
