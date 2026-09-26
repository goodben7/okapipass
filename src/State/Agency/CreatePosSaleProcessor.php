<?php

namespace App\State\Agency;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\ApiResource\PosSaleResource;
use App\Dto\Agency\CreatePosSaleDto;
use App\Entity\IdempotencyRecord;
use App\Manager\PosManager;
use App\Service\Agency\AgencyContext;
use App\Service\Agency\IdempotencyService;

/** @implements ProcessorInterface<CreatePosSaleDto, PosSaleResource> */
final class CreatePosSaleProcessor implements ProcessorInterface
{
    public function __construct(
        private PosManager $manager,
        private IdempotencyService $idempotency,
        private AgencyContext $agencyContext,
    ) {
    }

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): PosSaleResource
    {
        \assert($data instanceof CreatePosSaleDto);

        $key = $this->idempotency->readKeyHeader();
        if (null !== $key) {
            $replay = $this->idempotency->findReplay(IdempotencyRecord::SCOPE_POS_SALE, $key);
            if ($replay instanceof IdempotencyRecord) {
                $body = $replay->getResponseBody() ?? [];

                return new PosSaleResource(
                    id: (string) ($body['ticketId'] ?? $body['id'] ?? ''),
                    sessionId: (string) ($body['sessionId'] ?? ''),
                    bookingId: (string) ($body['bookingId'] ?? ''),
                    ticketId: (string) ($body['ticketId'] ?? ''),
                    paymentId: (string) ($body['paymentId'] ?? ''),
                    amount: (int) ($body['amount'] ?? 0),
                );
            }
        }

        $result = $this->manager->sale($data);

        $resource = new PosSaleResource(
            id: $result['ticketId'],
            sessionId: (string) $result['session']->getId(),
            bookingId: $result['bookingId'],
            ticketId: $result['ticketId'],
            paymentId: $result['paymentId'],
            amount: $result['amount'],
        );

        if (null !== $key) {
            $this->idempotency->store(
                $this->agencyContext->requireAgency(),
                IdempotencyRecord::SCOPE_POS_SALE,
                $key,
                201,
                [
                    'id' => $resource->id,
                    'sessionId' => $resource->sessionId,
                    'bookingId' => $resource->bookingId,
                    'ticketId' => $resource->ticketId,
                    'paymentId' => $resource->paymentId,
                    'amount' => $resource->amount,
                ],
            );
        }

        return $resource;
    }
}
