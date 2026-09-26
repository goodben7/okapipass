<?php

namespace App\Provider\Traveler;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use App\Entity\TravelerPass;
use App\Manager\TravelerPassManager;
use App\Manager\TravelerTicketManager;

/** @implements ProviderInterface<list<TravelerPass>> */
final class TravelerPassCollectionProvider implements ProviderInterface
{
    public function __construct(
        private TravelerTicketManager $ticketManager,
        private TravelerPassManager $passManager,
    ) {
    }

    public function provide(Operation $operation, array $uriVariables = [], array $context = []): array
    {
        $user = $this->ticketManager->requireTraveler();

        return $this->passManager->listForUser($user);
    }
}
