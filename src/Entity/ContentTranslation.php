<?php

namespace Wexample\SymfonyTranslations\Entity;

use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Wexample\SymfonyHelpers\Entity\AbstractEntity;
use Wexample\SymfonyTranslations\Repository\ContentTranslationRepository;

/**
 * One field of one entity, in one language.
 *
 * Entities are referred to by class and identifier rather than by relation, so
 * that any entity can be translated without a change to its own table.
 */
#[ORM\Entity(repositoryClass: ContentTranslationRepository::class)]
#[ORM\Table(name: 'content_translation')]
#[ORM\UniqueConstraint(columns: ['entity_class', 'entity_id', 'field', 'locale'])]
#[ORM\Index(columns: ['entity_class', 'entity_id', 'locale'])]
class ContentTranslation extends AbstractEntity
{
    #[ORM\Column(name: 'entity_class', type: Types::STRING, length: 255)]
    protected string $entityClass;

    #[ORM\Column(name: 'entity_id', type: Types::STRING, length: 64)]
    protected string $entityId;

    #[ORM\Column(name: 'field', type: Types::STRING, length: 128)]
    protected string $field;

    #[ORM\Column(name: 'locale', type: Types::STRING, length: 16)]
    protected string $locale;

    #[ORM\Column(name: 'value', type: Types::TEXT, nullable: true)]
    protected ?string $value = null;

    /** The hash of the source the value was made from: when it no longer matches, the value is stale. */
    #[ORM\Column(name: 'source_hash', type: Types::STRING, length: 64)]
    protected string $sourceHash;

    /**
     * The locale the value was translated from, null for the entity's own text.
     * Another one's translation when the value was made from it: the source hash
     * is then that translation's.
     */
    #[ORM\Column(name: 'source_locale', type: Types::STRING, length: 16, nullable: true)]
    protected ?string $sourceLocale = null;

    /** The engine that made the value, null when it was written by hand and must never be replaced. */
    #[ORM\Column(name: 'engine', type: Types::STRING, length: 64, nullable: true)]
    protected ?string $engine = null;

    #[ORM\Column(name: 'updated_at', type: Types::DATETIME_IMMUTABLE)]
    protected \DateTimeImmutable $updatedAt;

    public function __construct()
    {
        parent::__construct();

        $this->updatedAt = new \DateTimeImmutable();
    }

    public function getEntityClass(): string
    {
        return $this->entityClass;
    }

    public function getEntityId(): string
    {
        return $this->entityId;
    }

    public function getField(): string
    {
        return $this->field;
    }

    public function getLocale(): string
    {
        return $this->locale;
    }

    public function getValue(): ?string
    {
        return $this->value;
    }

    public function getSourceHash(): string
    {
        return $this->sourceHash;
    }

    public function getSourceLocale(): ?string
    {
        return $this->sourceLocale;
    }

    public function getEngine(): ?string
    {
        return $this->engine;
    }

    public function isManual(): bool
    {
        return null === $this->engine;
    }

    public function getUpdatedAt(): \DateTimeImmutable
    {
        return $this->updatedAt;
    }
}
