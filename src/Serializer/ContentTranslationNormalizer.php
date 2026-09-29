<?php

namespace Wexample\SymfonyTranslations\Serializer;

use Doctrine\Persistence\Proxy;
use Symfony\Component\Serializer\Normalizer\NormalizerAwareInterface;
use Symfony\Component\Serializer\Normalizer\NormalizerAwareTrait;
use Symfony\Component\Serializer\Normalizer\NormalizerInterface;
use Wexample\SymfonyTranslations\Service\ContentTranslationService;

/**
 * An entity sent by the api carries its #[Translatable] fields in the language
 * of the request, taken from the cookie or the Accept-Language header since an
 * api url has no locale in it.
 *
 * It lets the other normalizers do the work, then replaces the values of the
 * fields they wrote under their own name.
 */
class ContentTranslationNormalizer implements NormalizerInterface, NormalizerAwareInterface
{
    use NormalizerAwareTrait;

    private const string ALREADY_CALLED = 'wexample_content_translation_normalizer_called';

    public function __construct(
        private readonly ContentTranslationService $contentTranslationService,
    ) {
    }

    public function normalize(
        mixed $data,
        ?string $format = null,
        array $context = []
    ): array|string|int|float|bool|\ArrayObject|null {
        $context[self::ALREADY_CALLED.spl_object_id($data)] = true;
        $normalized = $this->normalizer->normalize($data, $format, $context);

        if (! is_array($normalized)) {
            return $normalized;
        }

        foreach ($this->contentTranslationService->translateEntity($data) as $field => $value) {
            if (array_key_exists($field, $normalized)) {
                $normalized[$field] = $value;
            }
        }

        return $normalized;
    }

    public function supportsNormalization(
        mixed $data,
        ?string $format = null,
        array $context = []
    ): bool {
        return is_object($data)
            && ! isset($context[self::ALREADY_CALLED.spl_object_id($data)])
            && ! empty($this->contentTranslationService->getTranslatableFields(
                $data instanceof Proxy ? get_parent_class($data) : $data::class
            ));
    }

    public function getSupportedTypes(?string $format): array
    {
        return ['object' => false];
    }
}
