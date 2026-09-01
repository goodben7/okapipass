<?php

namespace App\State\PublicAgency;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\ApiResource\Public\PublicAgencyBookingGroupPaymentResource;
use App\Manager\PublicAgencyPaymentManager;

/** @implements ProcessorInterface<mixed, PublicAgencyBookingGroupPaymentResource> */
final class CheckPublicAgencyBookingGroupPaymentProcessor implements ProcessorInterface
{
    public function __construct(private PublicAgencyPaymentManager $manager)
    {
    }

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): PublicAgencyBookingGroupPaymentResource
    {
        return $this->manager->checkGroupPaymentByPublicToken((string) ($uriVariables['publicToken'] ?? ''));
    }
}
