<?php

namespace Wexample\SymfonyTranslations\Tests\Unit\Serializer;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Serializer\Normalizer\NormalizerInterface;
use Wexample\SymfonyTranslations\Attribute\Translatable;
use Wexample\SymfonyTranslations\Serializer\ContentTranslationNormalizer;
use Wexample\SymfonyTranslations\Service\ContentTranslationService;

class ContentTranslationNormalizerTest extends TestCase
{
    public function testReplacesTranslatableFieldsWrittenByTheOtherNormalizers(): void
    {
        $entity = new ContentTranslationNormalizerTestArticle();

        $service = $this->createStub(ContentTranslationService::class);
        $service->method('getTranslatableFields')->willReturn(['title']);
        $service->method('translateEntity')->willReturn(['title' => 'Bonjour']);

        $normalizer = new ContentTranslationNormalizer($service);

        $inner = $this->createMock(NormalizerInterface::class);
        $inner->expects($this->once())
            ->method('normalize')
            ->willReturnCallback(function (mixed $data, ?string $format, array $context) use ($normalizer): array {
                // The inner chain must not hand the same object back to it.
                $this->assertFalse($normalizer->supportsNormalization($data, $format, $context));

                return ['slug' => 'hello', 'title' => 'Hello'];
            });
        $normalizer->setNormalizer($inner);

        $this->assertTrue($normalizer->supportsNormalization($entity));
        $this->assertSame(['slug' => 'hello', 'title' => 'Bonjour'], $normalizer->normalize($entity, 'json'));
    }

    public function testIgnoresObjectsWithoutTranslatableFields(): void
    {
        $service = $this->createStub(ContentTranslationService::class);
        $service->method('getTranslatableFields')->willReturn([]);

        $this->assertFalse((new ContentTranslationNormalizer($service))->supportsNormalization(new \stdClass()));
    }
}

class ContentTranslationNormalizerTestArticle
{
    public string $slug = 'hello';

    #[Translatable]
    public string $title = 'Hello';
}
