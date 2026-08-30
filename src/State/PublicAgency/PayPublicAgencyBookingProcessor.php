<?php

namespace App\State\PublicAgency;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\ApiResource\Public\PublicAgencyBookingPaymentResource;
use App\ApiResource\Public\PublicAgencyBookingResource;
use App\Dto\Public\PayPublicAgencyBookingDto;
use App\Manager\PublicAgencyPaymentManager;

/** @implements ProcessorInterface<PayPublicAgencyBookingDto, PublicAgencyBookingPaymentResource> */
final class PayPublicAgencyBookingProcessor implements ProcessorInterface
{
    public function __construct(private PublicAgencyPaymentManager $manager)
    {
    }

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): PublicAgencyBookingPaymentResource
    {
        \assert($data instanceof PayPublicAgencyBookingDto);

        return $this->manager->initiatePayment(
            (string) ($uriVariables['publicToken'] ?? ''),
            (string) $data->method,
        );
    }
}
