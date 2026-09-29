<?php

namespace Wexample\SymfonyTranslations\Service;

use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Mapping\ClassMetadata;
use Wexample\SymfonyTranslations\Attribute\Translatable;
use Wexample\SymfonyTranslations\Repository\ContentTranslationRepository;
use Wexample\SymfonyTranslations\Translation\Translator;

/**
 * Serves the #[Translatable] fields of any entity in the language asked for.
 *
 * A translation missing or made from an older source is made on the spot, all
 * the stale fields of the entity in one call to the engine, then stored: the
 * first reader in a language waits for it, the next ones read it back. The
 * entity itself is never touched, so it keeps its source text and saving it
 * never writes a translation over the original.
 */
class ContentTranslationService
{
    /** @var array<class-string, string[]> */
    private array $fieldsByClass = [];

    /** @var array<string, array<string, ?string>> Per entity and locale, by field */
    private array $resolved = [];

    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly ContentTranslationRepository $repository,
        private readonly TextTranslationService $textTranslationService,
        private readonly LocaleService $localeService,
        private readonly Translator $translator,
    ) {
    }

    /**
     * @return string[] The #[Translatable] properties of the class and its parents
     */
    public function getTranslatableFields(string $className): array
    {
        if (isset($this->fieldsByClass[$className])) {
            return $this->fieldsByClass[$className];
        }

        $fields = [];
        $reflection = new \ReflectionClass($className);

        do {
            foreach ($reflection->getProperties() as $property) {
                if (! empty($property->getAttributes(Translatable::class))) {
                    $fields[$property->getName()] = $property->getName();
                }
            }
        } while ($reflection = $reflection->getParentClass());

        return $this->fieldsByClass[$className] = array_values($fields);
    }

    public function isTranslatable(object $entity): bool
    {
        return ! empty($this->getTranslatableFields($this->getMetadata($entity)->getName()));
    }

    /**
     * @param string|null $locale The current locale if omitted
     */
    public function translateField(
        object $entity,
        string $field,
        ?string $locale = null
    ): ?string {
        return $this->translateEntity($entity, $locale)[$field]
            ?? $this->getMetadata($entity)->getFieldValue($entity, $field);
    }

    /**
     * @param bool $force Translate again what an engine already translated; values written by hand are kept
     * @return array<string, ?string> Every translatable field, by name
     */
    public function translateEntity(
        object $entity,
        ?string $locale = null,
        bool $force = false
    ): array {
        $locale ??= $this->translator->getLocale();
        $metadata = $this->getMetadata($entity);
        $entityClass = $metadata->getName();
        $entityId = $this->buildEntityId($metadata, $entity);
        $cacheKey = $entityClass.'#'.$entityId.'@'.$locale;

        if (! $force && isset($this->resolved[$cacheKey])) {
            return $this->resolved[$cacheKey];
        }

        $sources = [];
        foreach ($this->getTranslatableFields($entityClass) as $field) {
            $sources[$field] = $metadata->getFieldValue($entity, $field);
        }

        // Nothing is sent to the engine for a language the application does not
        // read content in, whatever a request or a template asks for.
        if ($locale === $this->getSourceLocale() || ! $this->localeService->hasContentLocale($locale)) {
            return $this->resolved[$cacheKey] = $sources;
        }

        $rows = $this->repository->findRowsForEntity($entityClass, $entityId, $locale);
        $engine = $this->textTranslationService->getEngineName();
        $values = [];
        $stale = [];

        foreach ($sources as $field => $source) {
            $row = $rows[$field] ?? null;

            if (null === $source || '' === $source) {
                $values[$field] = $source;
            } elseif (null !== $row && null === $row['engine']) {
                $values[$field] = $row['value'];
            } elseif (null === $row
                || $force
                || $row['source_hash'] !== TextTranslationService::hashSource($source)
                || (PendingTextTranslator::ENGINE_NAME === $row['engine'] && PendingTextTranslator::ENGINE_NAME !== $engine)) {
                $stale[$field] = $source;
            } else {
                $values[$field] = $row['value'];
            }
        }

        if (! empty($stale)) {
            $translations = $this->textTranslationService->translate($stale, $this->getSourceLocale(), $locale);

            foreach ($translations as $field => $translation) {
                $values[$field] = $translation;
                $this->repository->saveValue(
                    $entityClass,
                    $entityId,
                    $field,
                    $locale,
                    $translation,
                    TextTranslationService::hashSource($stale[$field]),
                    $engine
                );
            }
        }

        // Source order, whatever the path each field took.
        return $this->resolved[$cacheKey] = array_replace($sources, $values);
    }

    /**
     * Stores a wording written by hand, which no engine run will replace.
     */
    public function setManualTranslation(
        object $entity,
        string $field,
        string $locale,
        ?string $value
    ): void {
        $metadata = $this->getMetadata($entity);
        $source = (string) $metadata->getFieldValue($entity, $field);

        $this->repository->saveValue(
            $metadata->getName(),
            $this->buildEntityId($metadata, $entity),
            $field,
            $locale,
            $value,
            TextTranslationService::hashSource($source),
            null
        );

        $this->resolved = [];
    }

    /**
     * The language content is written in.
     */
    public function getSourceLocale(): string
    {
        return $this->localeService->getDefaultLocale();
    }

    /**
     * @return class-string[] Every mapped entity having #[Translatable] fields
     */
    public function getTranslatableClasses(): array
    {
        $classes = [];

        foreach ($this->entityManager->getMetadataFactory()->getAllMetadata() as $metadata) {
            if (! $metadata->isMappedSuperclass && ! empty($this->getTranslatableFields($metadata->getName()))) {
                $classes[] = $metadata->getName();
            }
        }

        return $classes;
    }

    private function getMetadata(object $entity): ClassMetadata
    {
        // Resolves a Doctrine proxy to the class it stands for.
        return $this->entityManager->getClassMetadata($entity::class);
    }

    private function buildEntityId(
        ClassMetadata $metadata,
        object $entity
    ): string {
        return implode('-', array_map(
            static fn (mixed $value): string => (string) $value,
            $metadata->getIdentifierValues($entity)
        ));
    }
}
