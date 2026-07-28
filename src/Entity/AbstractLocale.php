<?php

namespace Wexample\SymfonyTranslations\Entity;

use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Wexample\SymfonyHelpers\Entity\AbstractEntity;
use Wexample\SymfonyHelpers\Entity\Traits\HasNameTrait;

abstract class AbstractLocale extends AbstractEntity
{
    use HasNameTrait;

    #[ORM\Column(type: Types::STRING, length: 10, unique: true)]
    protected string $code;

    #[ORM\Column(type: Types::BOOLEAN)]
    protected bool $isDefault = false;

    public function getCode(): string
    {
        return $this->code;
    }

    public function setCode(string $code): static
    {
        $this->code = $code;

        return $this;
    }

    public function isDefault(): bool
    {
        return $this->isDefault;
    }

    public function setIsDefault(bool $isDefault): static
    {
        $this->isDefault = $isDefault;

        return $this;
    }
}
