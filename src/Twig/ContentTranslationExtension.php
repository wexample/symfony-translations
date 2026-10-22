<?php

namespace Wexample\SymfonyTranslations\Twig;

use Twig\TwigFunction;
use Wexample\SymfonyHelpers\Twig\AbstractExtension;
use Wexample\SymfonyTranslations\Class\TranslatedEntity;
use Wexample\SymfonyTranslations\Service\ContentTranslationService;

class ContentTranslationExtension extends AbstractExtension
{
    public function __construct(
        private readonly ContentTranslationService $contentTranslationService,
    ) {
    }

    public function getFunctions(): array
    {
        return [
            new TwigFunction(
                'translated',
                [
                    $this,
                    'translated',
                ]
            ),
        ];
    }

    /**
     * The entity with its #[Translatable] fields in the current language, or in
     * the one given. A list is wrapped item by item.
     *
     * @param object|iterable<object> $entity
     * @return TranslatedEntity|TranslatedEntity[]
     */
    public function translated(
        object|iterable $entity,
        ?string $locale = null
    ): TranslatedEntity|array {
        if (is_iterable($entity)) {
            $translated = [];

            foreach ($entity as $key => $item) {
                $translated[$key] = $this->translated($item, $locale);
            }

            return $translated;
        }

        return new TranslatedEntity($entity, $this->contentTranslationService, $locale);
    }
}
