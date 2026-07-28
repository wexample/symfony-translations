<?php

namespace Wexample\SymfonyTranslations\Repository;

use Doctrine\Persistence\ManagerRegistry;
use Wexample\SymfonyHelpers\Repository\AbstractRepository;
use Wexample\SymfonyTranslations\Entity\AbstractLocale;
use Wexample\SymfonyTranslations\Entity\AbstractTranslation;

/**
 * @method AbstractTranslation|null find($id, $lockMode = null, $lockVersion = null)
 * @method AbstractTranslation|null findOneBy(array $criteria, array $orderBy = null)
 * @method AbstractTranslation[]    findAll()
 * @method AbstractTranslation[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
abstract class TranslationRepository extends AbstractRepository
{
    public function __construct(
        ManagerRegistry $registry,
        $entityClass = AbstractTranslation::class
    ) {
        parent::__construct($registry, $entityClass);
    }

    public function findByLocaleAndKey(AbstractLocale $locale, string $key): ?AbstractTranslation
    {
        return $this->findOneBy([
            'locale' => $locale,
            'key' => $key,
        ]);
    }

    /**
     * @return AbstractTranslation[]
     */
    public function findAllByLocale(AbstractLocale $locale): array
    {
        return $this->findBy(['locale' => $locale]);
    }
}
