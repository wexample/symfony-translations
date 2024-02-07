<?php

namespace Wexample\SymfonyTranslations\Twig;

use Twig\TwigFunction;
use Wexample\SymfonyHelpers\Twig\AbstractExtension;
use Wexample\SymfonyTranslations\Translation\Translator;

class TranslationExtension extends AbstractExtension
{
    public function __construct(
        private readonly Translator $translator
    ) {
    }

    public function getFunctions(): array
    {
        return [
            new TwigFunction(
                'translation_build_domain_from_path',
                [
                    $this,
                    'translationBuildDomainFromPath',
                ]
            ),
            new TwigFunction(
                'translation_debug_info',
                [
                    $this,
                    'translationDebugInfo',
                ]
            ),
        ];
    }

    public function translationBuildDomainFromPath(string $path): string
    {
        return Translator::buildDomainFromPath(
            $path
        );
    }

    public function translationDebugInfo(): array
    {
        return $this->translator->getCatalogues();
    }
}
