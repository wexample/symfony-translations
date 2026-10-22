<?php

namespace Wexample\SymfonyTranslations\Entity\Traits;

use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

/**
 * The language of an entity implementing HasLocaleInterface, as a locale
 * code: `fr`, `en`, `pt_BR`.
 */
trait HasLocaleTrait
{
    #[ORM\Column(type: Types::STRING, length: 12, nullable: true)]
    protected ?string $locale = null;

    public function getLocale(): ?string
    {
        return $this->locale;
    }

    public function setLocale(?string $locale): static
    {
        $this->locale = $locale;

        return $this;
    }
}
