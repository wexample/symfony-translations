<?php

namespace Wexample\SymfonyTranslations\Repository;

use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use Doctrine\DBAL\Types\Types;
use Symfony\Bridge\Doctrine\Types\UuidType;
use Symfony\Component\Uid\Uuid;
use Wexample\SymfonyHelpers\Repository\AbstractRepository;
use Wexample\SymfonyTranslations\Entity\ContentTranslation;

/**
 * Reads and writes translations through the connection, never the unit of
 * work: a translation is made while a page is being read, and flushing there
 * would also write whatever else the request has changed and not yet saved.
 *
 * @method ContentTranslation|null find($id, $lockMode = null, $lockVersion = null)
 * @method ContentTranslation|null findOneBy(array $criteria, array $orderBy = null)
 * @method ContentTranslation[]    findAll()
 * @method ContentTranslation[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class ContentTranslationRepository extends AbstractRepository
{
    public static function getEntityClassName(): string
    {
        return ContentTranslation::class;
    }

    /**
     * @return array<string, array{value: ?string, source_hash: string, engine: ?string}> By field
     */
    public function findRowsForEntity(
        string $entityClass,
        string $entityId,
        string $locale
    ): array {
        $rows = $this->getEntityManager()->getConnection()->fetchAllAssociative(
            'SELECT field, value, source_hash, engine FROM '.$this->getTableName()
            .' WHERE entity_class = ? AND entity_id = ? AND locale = ?',
            [$entityClass, $entityId, $locale]
        );

        return array_column($rows, null, 'field');
    }

    /**
     * @param string|null $engine Null for a value written by hand
     */
    public function saveValue(
        string $entityClass,
        string $entityId,
        string $field,
        string $locale,
        ?string $value,
        string $sourceHash,
        ?string $engine,
    ): void {
        $connection = $this->getEntityManager()->getConnection();
        $criteria = [
            'entity_class' => $entityClass,
            'entity_id' => $entityId,
            'field' => $field,
            'locale' => $locale,
        ];
        $data = [
            'value' => $value,
            'source_hash' => $sourceHash,
            'engine' => $engine,
            'updated_at' => new \DateTimeImmutable(),
        ];
        $types = ['updated_at' => Types::DATETIME_IMMUTABLE, 'id' => UuidType::NAME];

        if ($connection->update($this->getTableName(), $data, $criteria, $types) > 0) {
            return;
        }

        try {
            $connection->insert($this->getTableName(), ['id' => Uuid::v7()] + $criteria + $data, $types);
        } catch (UniqueConstraintViolationException) {
            // Inserted by a concurrent request between the update and the insert.
            $connection->update($this->getTableName(), $data, $criteria, $types);
        }
    }

    private function getTableName(): string
    {
        return $this->getClassMetadata()->getTableName();
    }
}
