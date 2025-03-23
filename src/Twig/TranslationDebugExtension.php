<?php

namespace Wexample\SymfonyTranslations\Twig;

use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

class TranslationDebugExtension extends AbstractExtension
{
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
     * @return array Empty array for now, will be enhanced later
     */
    public function dumpTranslations(): array
    {
        return [];
    }
}
