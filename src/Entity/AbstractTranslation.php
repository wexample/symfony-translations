<?php

namespace Wexample\SymfonyTranslations\Entity;

use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Wexample\SymfonyHelpers\Entity\AbstractEntity;

/**
 * Concrete class must declare the locale property with ManyToOne:
 *
 *   #[ORM\ManyToOne(targetEntity: YourLocale::class)]
 *   #[ORM\JoinColumn(nullable: false)]
 *   protected YourLocale $locale;
 *
 * and a unique constraint on (locale, key):
 *
 *   #[ORM\UniqueConstraint(columns: ['locale_id', 'key'])]
 */
abstract class AbstractTranslation extends AbstractEntity
{
    #[ORM\Column(type: Types::STRING, length: 512)]
    protected string $key;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    protected ?string $value = null;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    protected \DateTimeImmutable $updatedAt;

    public function __construct()
    {
        $this->updatedAt = new \DateTimeImmutable();
    }

    abstract public function getLocale(): AbstractLocale;

    abstract public function setLocale(AbstractLocale $locale): static;

    public function getKey(): string
    {
        return $this->key;
    }

    public function setKey(string $key): static
    {
        $this->key = $key;

        return $this;
    }

    public function getValue(): ?string
    {
        return $this->value;
    }

    public function setValue(?string $value): static
    {
        $this->value = $value;
        $this->updatedAt = new \DateTimeImmutable();

        return $this;
    }

    public function getUpdatedAt(): \DateTimeImmutable
    {
        return $this->updatedAt;
    }
}
