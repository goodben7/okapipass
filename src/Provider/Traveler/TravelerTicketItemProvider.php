<?php

namespace App\Provider\Traveler;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use App\ApiResource\TravelerTicketResource;
use App\Manager\TravelerTicketManager;

/** @implements ProviderInterface<TravelerTicketResource> */
final class TravelerTicketItemProvider implements ProviderInterface
{
    public function __construct(
        private TravelerTicketManager $manager,
        private TravelerTicketCollectionProvider $collectionProvider,
    ) {
    }

    public function provide(Operation $operation, array $uriVariables = [], array $context = []): TravelerTicketResource
    {
        $ticket = $this->manager->getOwnedTicket((string) ($uriVariables['id'] ?? ''));

        return $this->collectionProvider->map($ticket);
    }
}
