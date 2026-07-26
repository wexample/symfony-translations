<?php

namespace Wexample\SymfonyTranslations\Repository;

use Doctrine\Persistence\ManagerRegistry;
use Wexample\SymfonyHelpers\Repository\AbstractRepository;
use Wexample\SymfonyTranslations\Entity\Locale;

/**
 * @method Locale|null find($id, $lockMode = null, $lockVersion = null)
 * @method Locale|null findOneBy(array $criteria, array $orderBy = null)
 * @method Locale|null findOneByCode(string $code)
 * @method Locale[]    findAll()
 * @method Locale[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
abstract class LocaleRepository extends AbstractRepository
{
    public function __construct(
        ManagerRegistry $registry,
        $entityClass = Locale::class
    ) {
        parent::__construct($registry, $entityClass);
    }

    public function findDefault(): ?Locale
    {
        return $this->findOneBy(['isDefault' => true]);
    }
}
