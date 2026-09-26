<?php

namespace App\State\Agency;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Entity\AgencyWebhookSubscription;
use App\Exception\UnavailableDataException;
use App\Manager\AgencyWebhookManager;

/** @implements ProcessorInterface<AgencyWebhookSubscription|null, void> */
final class DeleteAgencyWebhookProcessor implements ProcessorInterface
{
    public function __construct(private AgencyWebhookManager $manager)
    {
    }

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): void
    {
        if (!$data instanceof AgencyWebhookSubscription) {
            throw new UnavailableDataException('Webhook not found.');
        }

        $this->manager->delete($data);
    }
}
