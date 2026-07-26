<?php

namespace Wexample\SymfonyTranslations\Repository;

use Doctrine\Persistence\ManagerRegistry;
use Wexample\SymfonyHelpers\Repository\AbstractRepository;
use Wexample\SymfonyTranslations\Entity\Locale;
use Wexample\SymfonyTranslations\Entity\Translation;

/**
 * @method Translation|null find($id, $lockMode = null, $lockVersion = null)
 * @method Translation|null findOneBy(array $criteria, array $orderBy = null)
 * @method Translation[]    findAll()
 * @method Translation[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
abstract class TranslationRepository extends AbstractRepository
{
    public function __construct(
        ManagerRegistry $registry,
        $entityClass = Translation::class
    ) {
        parent::__construct($registry, $entityClass);
    }

    public function findByLocaleAndKey(Locale $locale, string $key): ?Translation
    {
        return $this->findOneBy([
            'locale' => $locale,
            'key' => $key,
        ]);
    }

    /**
     * @return Translation[]
     */
    public function findAllByLocale(Locale $locale): array
    {
        return $this->findBy(['locale' => $locale]);
    }
}
