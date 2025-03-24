<?php

namespace Wexample\SymfonyTranslations\Translation;

use Exception;
use InvalidArgumentException;
use Symfony\Component\Translation\MessageCatalogueInterface;
use Symfony\Component\Translation\TranslatorBagInterface;
use Symfony\Contracts\Translation\LocaleAwareInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

class Translator implements TranslatorInterface, TranslatorBagInterface, LocaleAwareInterface
{
    private array $locales = [];

    /**
     * @throws InvalidArgumentException|Exception
     */
    public function __construct(
        public \Symfony\Bundle\FrameworkBundle\Translation\Translator $translator,
    )
    {
    }

    public function getCatalogues(): array
    {
        return $this->translator->getCatalogues();
    }

    public function getAllLocales(): array
    {
        return array_values($this->locales);
    }

    public function setLocale($locale): void
    {
        $this->translator->setLocale($locale);
    }

    public function getLocale(): string
    {
        return $this->translator->getLocale();
    }

    public function getCatalogue($locale = null): MessageCatalogueInterface
    {
        return $this->translator->getCatalogue($locale);
    }

    public function trans(
        string $id,
        array $parameters = [],
        string $domain = null,
        string $locale = null
    ): string
    {
        return $this->translator->trans(
            id: $id,
            parameters: $parameters,
            domain: $domain,
            locale: $locale
        );
    }
}
