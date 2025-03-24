<?php

namespace Wexample\SymfonyTranslations\Tests;

use Wexample\SymfonyTesting\Tests\AbstractApplicationTestCase;
use Wexample\SymfonyTranslations\Translation\Translator;

abstract class AbstractTranslationTest extends AbstractApplicationTestCase
{
    protected ?object $translator = null;

    protected function setUp(): void
    {
        parent::setUp();

        /* @var Translator $translator */
        $this->translator = self::getContainer()->get(Translator::class);
    }
}
