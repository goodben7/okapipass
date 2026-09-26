<?php

namespace App\State\Agency;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Dto\Agency\UpdateAgencyWebhookDto;
use App\Entity\AgencyWebhookSubscription;
use App\Exception\UnavailableDataException;
use App\Manager\AgencyWebhookManager;

/** @implements ProcessorInterface<UpdateAgencyWebhookDto, AgencyWebhookSubscription> */
final class UpdateAgencyWebhookProcessor implements ProcessorInterface
{
    public function __construct(private AgencyWebhookManager $manager)
    {
    }

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): AgencyWebhookSubscription
    {
        $entity = $context['previous_data'] ?? $data;
        if (!$entity instanceof AgencyWebhookSubscription) {
            throw new UnavailableDataException('AgencyWebhookSubscription not found.');
        }
        \assert($data instanceof UpdateAgencyWebhookDto);

        return $this->manager->update($entity, $data);
    }
}
