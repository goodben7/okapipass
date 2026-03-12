<?php
namespace App\Encoder;

use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Serializer\Encoder\DecoderInterface;

class MultipartDecoder implements DecoderInterface {

    public const string FORMAT = 'multipart';

    public function __construct(private RequestStack $requestStack) {}

    /**
     * {@inheritdoc}
     */
    public function decode(string $data, string $format, array $context = []): ?array
    {
        $request = $this->requestStack->getCurrentRequest();

        if (!$request) {
            return null;
        }

        $formData = [];
        
        foreach ($request->request->all() as $key => $value) {
            if (is_string($value)) {
                $decoded = json_decode($value, true);
                $formData[$key] = (json_last_error() === JSON_ERROR_NONE && $decoded !== null) ? $decoded : $value;
            } else {
                $formData[$key] = $value;
            }
        }
    
        return array_merge($formData, $request->files->all());
    }

    /**
     * {@inheritdoc}
     */
    public function supportsDecoding(string $format): bool
    {
        return self::FORMAT === $format;
    }
}