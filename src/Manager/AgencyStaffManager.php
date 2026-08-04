<?php

namespace App\Manager;

use App\Domain\Agency\AgencyStaffRole;
use App\Dto\Agency\CreateAgencyStaffDto;
use App\Dto\Agency\UpdateAgencyStaffDto;
use App\Entity\AgencyStaffMember;
use App\Entity\User;
use App\Exception\ConflictException;
use App\Exception\UnavailableDataException;
use App\Exception\UnprocessableEntityException;
use App\Model\UserProxyIntertace;
use App\Repository\AgencyStaffMemberRepository;
use App\Repository\ProfileRepository;
use App\Repository\UserRepository;
use App\Service\Agency\AgencyContext;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

class AgencyStaffManager
{
    public function __construct(
        private EntityManagerInterface $em,
        private AgencyContext $agencyContext,
        private AgencyStaffMemberRepository $staffMembers,
        private ProfileRepository $profiles,
        private UserRepository $users,
        private UserPasswordHasherInterface $hasher,
    ) {
    }

    public function create(CreateAgencyStaffDto $dto): AgencyStaffMember
    {
        $this->agencyContext->requirePermission(AgencyStaffRole::STAFF_WRITE);
        $agency = $this->agencyContext->requireAgency();

        $email = strtolower(trim((string) $dto->email));
        if (null !== $this->users->findOneBy(['email' => $email])) {
            throw new ConflictException(sprintf('User with email "%s" already exists.', $email));
        }

        $profile = $this->profiles->findOneBy(['personType' => UserProxyIntertace::PERSON_PARTNER]);
        if (null === $profile) {
            throw new UnavailableDataException('PARTNER profile is not configured.');
        }

        $user = new User();
        $user->setEmail($email);
        $user->setDisplayName($dto->displayName ?: $email);
        $user->setPhone($dto->phone);
        $user->setProfile($profile);
        $user->setPersonType(UserProxyIntertace::PERSON_PARTNER);
        // Do not set holderId — UNIQ_HOLDER allows only the agency owner.
        $user->setOwnerId($agency->getId());
        $user->setPlainPassword((string) $dto->password);
        $user->setPassword($this->hasher->hashPassword($user, (string) $dto->password));
        $user->setCreatedAt(new \DateTimeImmutable('now'));

        $member = new AgencyStaffMember();
        $member->setAgency($agency);
        $member->setUser($user);
        $member->setRole((string) $dto->role);
        $member->setActive(true);

        $this->em->persist($user);
        $this->em->persist($member);
        $this->em->flush();

        return $member;
    }

    public function update(AgencyStaffMember $member, UpdateAgencyStaffDto $dto): AgencyStaffMember
    {
        $this->agencyContext->requirePermission(AgencyStaffRole::STAFF_WRITE);
        $this->agencyContext->assertOwns($member->getAgency());

        if ($this->isOwnerAccount($member)) {
            throw new UnprocessableEntityException('Cannot modify the agency owner staff record this way.');
        }

        if (null !== $dto->role) {
            $member->setRole($dto->role);
        }
        if (null !== $dto->active) {
            $member->setActive($dto->active);
        }

        $this->em->flush();

        return $member;
    }

    public function delete(AgencyStaffMember $member): void
    {
        $this->agencyContext->requirePermission(AgencyStaffRole::STAFF_WRITE);
        $this->agencyContext->assertOwns($member->getAgency());

        if ($this->isOwnerAccount($member)) {
            throw new UnprocessableEntityException('Cannot delete the agency owner.');
        }

        $member->setActive(false);
        $this->em->flush();
    }

    private function isOwnerAccount(AgencyStaffMember $member): bool
    {
        return $member->getUser()?->getId() === $member->getAgency()?->getUserId();
    }
}
