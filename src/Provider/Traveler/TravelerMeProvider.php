<?php

namespace App\Provider\Traveler;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use App\ApiResource\TravelerMeResource;
use App\Entity\User;
use App\Exception\UnauthorizedActionException;
use App\Model\UserProxyIntertace;
use Symfony\Bundle\SecurityBundle\Security;

/** @implements ProviderInterface<TravelerMeResource> */
final class TravelerMeProvider implements ProviderInterface
{
    public function __construct(private Security $security)
    {
    }

    public function provide(Operation $operation, array $uriVariables = [], array $context = []): TravelerMeResource
    {
        $user = $this->requireTraveler();

        return new TravelerMeResource(
            id: 'me',
            userId: $user->getId(),
            phone: $user->getPhone(),
            displayName: $user->getDisplayName(),
            email: $user->getEmail(),
            personType: (string) $user->getPersonType(),
            idDocument: $user->getIdDocument(),
            emergencyContactName: $user->getEmergencyContactName(),
            emergencyContactPhone: $user->getEmergencyContactPhone(),
            preferences: $user->getPreferences(),
        );
    }

    public function requireTraveler(): User
    {
        $user = $this->security->getUser();
        if (!$user instanceof User) {
            throw new UnauthorizedActionException('Authentication required.');
        }
        if (UserProxyIntertace::PERSON_TRAVELER !== $user->getPersonType()
            && !\in_array('ROLE_TRAVELER', $user->getRoles(), true)
        ) {
            throw new UnauthorizedActionException('Traveler access required.');
        }

        return $user;
    }
}
