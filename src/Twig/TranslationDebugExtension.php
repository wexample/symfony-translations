<?php

namespace Wexample\SymfonyTranslations\Twig;

use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;
use Wexample\SymfonyTranslations\Translation\Translator;

class TranslationDebugExtension extends AbstractExtension
{
    public function __construct(
        private readonly Translator $translator
    )
    {

    }

    /**
     * {@inheritdoc}
     */
    public function getFunctions(): array
    {
        return [
            new TwigFunction('dump_trans', [$this, 'dumpTranslations'], ['is_safe' => ['html']]),
        ];
    }

    /**
     * Dumps translation information for debugging purposes.
     *
     * @param string|null $key Optional translation key to debug specifically
     * @return string HTML output with debug information
     */
    public function dumpTranslations(?string $key = null)
    {
        $debugInfo = [
            'test' => 'ok',
        ];

        return '<pre>' . print_r($debugInfo, true) . '</pre>';
    }
}
