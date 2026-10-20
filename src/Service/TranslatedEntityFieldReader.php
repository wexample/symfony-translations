<?php

namespace Wexample\SymfonyTranslations\Service;

use Doctrine\Persistence\Proxy;
use Wexample\SymfonyHelpers\Interface\EntityFieldReaderInterface;

/**
 * A #[Translatable] field read in the visitor's language, wherever an entity
 * is described without a template to wrap it in translated(): its schema.org
 * data, nested entities included.
 */
class TranslatedEntityFieldReader implements EntityFieldReaderInterface
{
    public function __construct(
        private readonly ContentTranslationService $contentTranslationService,
    ) {
    }

    public function supportsField(object $entity, string $field): bool
    {
        return in_array(
            $field,
            $this->contentTranslationService->getTranslatableFields(
                $entity instanceof Proxy ? get_parent_class($entity) : $entity::class
            ),
            true
        );
    }

    public function readField(object $entity, string $field, ?string $locale = null): mixed
    {
        return $this->contentTranslationService->translateField($entity, $field, $locale);
    }
}
