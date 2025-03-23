<?php

namespace Wexample\SymfonyTranslations\Twig;

use Twig\TwigFunction;
use Wexample\SymfonyHelpers\Twig\AbstractExtension;
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
            new TwigFunction(
                'dump_trans',
                [$this, 'dumpTranslations'],
                [self::FUNCTION_OPTION_HTML]
            ),
            new TwigFunction(
                'dump_trans_locales',
                [$this, 'dumpTranslationLocales'],
                [self::FUNCTION_OPTION_HTML]
            ),
            new TwigFunction(
                'dump_trans_domains',
                [$this, 'dumpTranslationDomains'],
                [self::FUNCTION_OPTION_HTML]
            ),
        ];
    }

    /**
     * Dumps translation information for debugging purposes.
     */
    public function dumpTranslations()
    {
        dump($this->translator->getCatalogues());
    }

    /**
     * Dumps available locales for debugging purposes.
     */
    public function dumpTranslationLocales()
    {
        dump([
            'current_locale' => $this->translator->getLocale(),
            'available_locales' => $this->translator->getAllLocales(),
        ]);
    }

    /**
     * Dumps domain stack for debugging purposes.
     */
    public function dumpTranslationDomains()
    {
        dump($this->translator->getDomainsStack());
    }
}
