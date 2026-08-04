<?php
namespace App\Serializer;

use Symfony\Component\HttpFoundation\File\File;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\Serializer\Normalizer\DenormalizerInterface;

final class UploadedFileDenormalizer implements DenormalizerInterface
{
    /**
     * {@inheritdoc}
     */
    public function denormalize($data, string $type, ?string $format = null, array $context = []): File
    {
        return $data;
    }

    /**
     * {@inheritdoc}
     */
    public function supportsDenormalization($data, $type, $format = null, array $context = []): bool
    {
        return $data instanceof File && (\is_a($type, File::class, true) || File::class === $type || UploadedFile::class === $type);
    }

    public function getSupportedTypes(?string $format = null): array
    {
        return [
            File::class => true,
            UploadedFile::class => true,
        ];
    }
}