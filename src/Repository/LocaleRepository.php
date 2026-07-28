<?php

namespace Wexample\SymfonyTranslations\Repository;

use Doctrine\Persistence\ManagerRegistry;
use Wexample\SymfonyHelpers\Repository\AbstractRepository;
use Wexample\SymfonyTranslations\Entity\AbstractLocale;

/**
 * @method AbstractLocale|null find($id, $lockMode = null, $lockVersion = null)
 * @method AbstractLocale|null findOneBy(array $criteria, array $orderBy = null)
 * @method AbstractLocale|null findOneByCode(string $code)
 * @method AbstractLocale[]    findAll()
 * @method AbstractLocale[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
abstract class LocaleRepository extends AbstractRepository
{
    public function __construct(
        ManagerRegistry $registry,
        $entityClass = AbstractLocale::class
    ) {
        parent::__construct($registry, $entityClass);
    }

    public function findDefault(): ?AbstractLocale
    {
        return $this->findOneBy(['isDefault' => true]);
    }
}
