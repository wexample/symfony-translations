<?php

namespace Wexample\SymfonyTranslations\Class;

use Wexample\SymfonyTranslations\Service\ContentTranslationService;

/**
 * An entity as a template reads it, its #[Translatable] fields in the current
 * language: `translated(article).title` is the translated title, and anything
 * else — `id`, `dateCreated`, `author.name` — is read from the entity itself.
 *
 * A read-only view: the entity it wraps is never changed, so nothing written
 * from a template can reach the database.
 */
class TranslatedEntity
{
    /** @var array<string, ?string>|null */
    private ?array $translations = null;

    public function __construct(
        private readonly object $entity,
        private readonly ContentTranslationService $contentTranslationService,
        private readonly ?string $locale = null,
    ) {
    }

    public function getEntity(): object
    {
        return $this->entity;
    }

    public function __isset(string $name): bool
    {
        return $this->isTranslated($name)
            || property_exists($this->entity, $name)
            || null !== $this->findGetter($name);
    }

    public function __get(string $name): mixed
    {
        if ($this->isTranslated($name)) {
            return $this->getTranslations()[$name];
        }

        if ($getter = $this->findGetter($name)) {
            return $this->entity->$getter();
        }

        return $this->entity->$name;
    }

    /**
     * `getTitle()` answers like `title`; any other method is the entity's.
     */
    public function __call(
        string $method,
        array $arguments
    ): mixed {
        if (preg_match('/^(get|is|has)(.+)$/', $method, $matches) && $this->isTranslated(lcfirst($matches[2]))) {
            return $this->getTranslations()[lcfirst($matches[2])];
        }

        return $this->entity->$method(...$arguments);
    }

    private function isTranslated(string $name): bool
    {
        return array_key_exists($name, $this->getTranslations());
    }

    /**
     * Resolved on first read, so wrapping an entity costs nothing until a
     * field is shown.
     *
     * @return array<string, ?string>
     */
    private function getTranslations(): array
    {
        return $this->translations ??= $this->contentTranslationService->translateEntity($this->entity, $this->locale);
    }

    private function findGetter(string $name): ?string
    {
        foreach (['get', 'is', 'has'] as $prefix) {
            if (method_exists($this->entity, $prefix.ucfirst($name))) {
                return $prefix.ucfirst($name);
            }
        }

        return null;
    }
}
