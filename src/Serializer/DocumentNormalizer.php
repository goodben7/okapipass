<?php
namespace App\Serializer;

use App\Entity\Document;
use Vich\UploaderBundle\Storage\StorageInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Serializer\Normalizer\NormalizerInterface;

class DocumentNormalizer implements NormalizerInterface {

    public function __construct(
        #[Autowire(service: 'serializer.normalizer.object')]
        private readonly NormalizerInterface $normalizer,
        private readonly StorageInterface $storage
    )
    {
    }

    /**
     * @param Document $object
     */
    public function normalize($object, ?string $format = null, array $context = []): array|string|int|float|bool|\ArrayObject|null
    {
        $object->setContentUrl($this->storage->resolveUri($object, 'file'));
        $object->setContentUrlSecondary($this->storage->resolveUri($object, 'fileSecondary'));

        return $this->normalizer->normalize($object, $format, $context);
    }

    public function supportsNormalization($data, ?string $format = null, array $context = []): bool
    {
        return $data instanceof Document;
    }
    
    public function getSupportedTypes(?string $format = null) : array
    {
        return [Document::class => true];
    }
}