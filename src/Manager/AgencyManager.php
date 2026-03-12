<?php

namespace App\Manager;

use App\Entity\Agency;
use App\Entity\User;
use App\Enum\EntityType;
use App\Exception\UnavailableDataException;
use App\Message\Command\CommandBusInterface;
use App\Message\Command\CreateUserCommand;
use App\Message\Query\GetUserDetails;
use App\Message\Query\QueryBusInterface;
use App\Model\NewAgencyModel;
use App\Model\UpdateAgencyModel;
use App\Model\UserProxyIntertace;
use App\Repository\ProfileRepository;
use App\Service\ActivityEventDispatcher;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\SecurityBundle\Security;

class AgencyManager
{
    public function __construct(
        private EntityManagerInterface $em,
        private Security $security,
        private QueryBusInterface $queries,
        private ActivityEventDispatcher $eventDispatcher,
        private ProfileRepository $profileRepository,
        private CommandBusInterface $bus,
    ) {
    }

    public function createFrom(NewAgencyModel $model): Agency
    {
        $userId = $this->security->getUser()?->getUserIdentifier();
        $createdBy = null;

        if (null !== $userId) {
            /** @var User $createdBy */
            $createdBy = $this->queries->ask(new GetUserDetails($userId));
        }

        $agency = new Agency();

        $agency->setName($model->name);
        $agency->setEmail($model->email);
        $agency->setPhone($model->phone);
        $agency->setAddress($model->address);
        $agency->setStatus(Agency::STATUS_ACTIVE);
        $agency->setCreatedAt(new \DateTimeImmutable('now'));
        $agency->setCreatedBy($createdBy);

        $this->em->persist($agency);

        $profile =  $this->profileRepository->findOneBy(['personType' => UserProxyIntertace::PERSON_PARTNER]); 

        if (null === $profile) {
            throw new UnavailableDataException('cannot find profile with person type: partner');
        }
        
        $user = $this->bus->dispatch(
            new CreateUserCommand(
            $agency->getEmail(),
            $agency->getEmail(),
            $profile,
            $agency->getPhone(),
            $agency->getName(),
            $agency->getId(),
            EntityType::AGENCY
            )
        );

        $agency->setUserId($user->getId()); 

        $this->em->flush();

        $this->eventDispatcher->dispatch($agency, Agency::EVENT_AGENCY_CREATED);  

        return $agency;
    }

    public function updateFrom(string $agencyId, UpdateAgencyModel $model): Agency
    {
        $agency = $this->findAgency($agencyId);

        if (null !== $model->name) {
            $agency->setName($model->name);
        }

        if (null !== $model->email) {
            $agency->setEmail($model->email);
        }

        if (null !== $model->phone) {
            $agency->setPhone($model->phone);
        }

        if (null !== $model->address) {
            $agency->setAddress($model->address);
        }

        if (null !== $model->status) {
            $agency->setStatus($model->status);
        }

        $this->em->flush();

        $this->eventDispatcher->dispatch($agency, Agency::EVENT_AGENCY_UPDATED);   

        return $agency;
    }

    private function findAgency(string $agencyId): Agency
    {
        $agency = $this->em->find(Agency::class, $agencyId);

        if (null === $agency) {
            throw new UnavailableDataException(sprintf('cannot find agency with id: %s', $agencyId));
        }

        return $agency;
    }
}
