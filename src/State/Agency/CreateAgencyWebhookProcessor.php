<?php

namespace App\State\Agency;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Dto\Agency\CreateAgencyWebhookDto;
use App\Entity\AgencyWebhookSubscription;
use App\Manager\AgencyWebhookManager;

/** @implements ProcessorInterface<CreateAgencyWebhookDto, AgencyWebhookSubscription> */
final class CreateAgencyWebhookProcessor implements ProcessorInterface
{
    public function __construct(private AgencyWebhookManager $manager)
    {
    }

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): AgencyWebhookSubscription
    {
        \assert($data instanceof CreateAgencyWebhookDto);

        return $this->manager->create($data);
    }
}
