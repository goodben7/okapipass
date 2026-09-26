<?php

namespace App\State\Traveler;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\ApiResource\TravelerMeResource;
use App\Dto\Traveler\UpdateTravelerMeDto;
use App\Provider\Traveler\TravelerMeProvider;
use Doctrine\ORM\EntityManagerInterface;

/** @implements ProcessorInterface<UpdateTravelerMeDto, TravelerMeResource> */
final class UpdateTravelerMeProcessor implements ProcessorInterface
{
    public function __construct(
        private TravelerMeProvider $meProvider,
        private EntityManagerInterface $em,
    ) {
    }

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): TravelerMeResource
    {
        \assert($data instanceof UpdateTravelerMeDto);
        $user = $this->meProvider->requireTraveler();

        if (null !== $data->displayName) {
            $user->setDisplayName($data->displayName);
        }
        if (null !== $data->email) {
            $user->setEmail($data->email);
        }
        if (null !== $data->idDocument) {
            $user->setIdDocument($data->idDocument);
        }
        if (null !== $data->emergencyContactName) {
            $user->setEmergencyContactName($data->emergencyContactName);
        }
        if (null !== $data->emergencyContactPhone) {
            $user->setEmergencyContactPhone($data->emergencyContactPhone);
        }
        if (null !== $data->preferences) {
            $user->setPreferences($data->preferences);
        }
        $this->em->flush();

        return $this->meProvider->provide($operation, $uriVariables, $context);
    }
}
