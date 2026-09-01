<?php

namespace App\State\PublicAgency;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\ApiResource\Public\PublicAgencyBookingGroupPaymentResource;
use App\Dto\Public\PayPublicAgencyBookingDto;
use App\Manager\PublicAgencyPaymentManager;

/** @implements ProcessorInterface<PayPublicAgencyBookingDto, PublicAgencyBookingGroupPaymentResource> */
final class PayPublicAgencyBookingGroupProcessor implements ProcessorInterface
{
    public function __construct(private PublicAgencyPaymentManager $manager)
    {
    }

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): PublicAgencyBookingGroupPaymentResource
    {
        \assert($data instanceof PayPublicAgencyBookingDto);

        return $this->manager->initiateGroupPayment(
            (string) ($uriVariables['publicToken'] ?? ''),
            (string) $data->method,
            $data->payerPhone,
        );
    }
}
