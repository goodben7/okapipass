<?php

namespace App\Serializer;

use App\Entity\Payment;
use App\Entity\Ticket;
use App\Repository\PaymentRepository;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Serializer\Normalizer\NormalizerInterface;

class TicketNormalizer implements NormalizerInterface
{
    public function __construct(
        #[Autowire(service: 'serializer.normalizer.object')]
        private readonly NormalizerInterface $normalizer,
        private readonly PaymentRepository $payments,
    ) {
    }

    /**
     * @param Ticket $object
     */
    public function normalize($object, ?string $format = null, array $context = []): array|string|int|float|bool|\ArrayObject|null
    {
        $formUrl = null;

        $payment = $this->payments->findOneBy(['ticket' => $object]);
        if ($payment instanceof Payment && Payment::METHOD_CARD === $payment->getMethod()) {
            $paymentId = $payment->getId();
            if (null !== $paymentId && '' !== \trim($paymentId)) {
                $formUrl = \sprintf('/api/payments/%s/card/form', $paymentId);
            }
        }

        $object->setFormUrl($formUrl);

        return $this->normalizer->normalize($object, $format, $context);
    }

    public function supportsNormalization($data, ?string $format = null, array $context = []): bool
    {
        return $data instanceof Ticket;
    }

    public function getSupportedTypes(?string $format = null): array
    {
        return [Ticket::class => true];
    }
}

