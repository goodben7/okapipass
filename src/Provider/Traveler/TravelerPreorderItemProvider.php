<?php

namespace App\Provider\Traveler;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use App\Entity\TravelerPreorder;
use App\Exception\UnavailableDataException;
use App\Repository\TravelerPreorderRepository;

/** @implements ProviderInterface<TravelerPreorder> */
final class TravelerPreorderItemProvider implements ProviderInterface
{
    public function __construct(
        private TravelerPreorderRepository $preorders,
        private TravelerMeProvider $travelerMe,
    ) {
    }

    public function provide(Operation $operation, array $uriVariables = [], array $context = []): TravelerPreorder
    {
        $preorder = $this->preorders->find($uriVariables['id'] ?? null);
        if (!$preorder instanceof TravelerPreorder) {
            throw new UnavailableDataException('Preorder not found.');
        }

        $user = $this->travelerMe->requireTraveler();
        if ($preorder->getUser()?->getId() !== $user->getId()) {
            throw new UnavailableDataException('Preorder not found.');
        }

        return $preorder;
    }
}
