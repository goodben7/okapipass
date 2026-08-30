<?php

namespace App\State\PublicAgency;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\ApiResource\Public\PublicAgencyBookingPaymentResource;
use App\Manager\PublicAgencyPaymentManager;

/** @implements ProcessorInterface<null, PublicAgencyBookingPaymentResource> */
final class CheckPublicAgencyPaymentProcessor implements ProcessorInterface
{
    public function __construct(private PublicAgencyPaymentManager $manager)
    {
    }

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): PublicAgencyBookingPaymentResource
    {
        return $this->manager->checkPaymentByPublicToken((string) ($uriVariables['publicToken'] ?? ''));
    }
}
