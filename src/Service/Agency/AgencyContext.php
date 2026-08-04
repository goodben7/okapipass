<?php

namespace App\Service\Agency;

use App\Domain\Agency\AgencyStaffRole;
use App\Entity\Agency;
use App\Entity\AgencyStaffMember;
use App\Entity\User;
use App\Enum\EntityType;
use App\Exception\UnauthorizedActionException;
use App\Exception\UnavailableDataException;
use App\Model\UserProxyIntertace;
use App\Repository\AgencyRepository;
use App\Repository\AgencyStaffMemberRepository;
use Symfony\Bundle\SecurityBundle\Security;

/**
 * Resolves the partner agency for the current JWT user + staff RBAC.
 */
class AgencyContext
{
    public function __construct(
        private Security $security,
        private AgencyRepository $agencies,
        private AgencyStaffMemberRepository $staffMembers,
    ) {
    }

    public function getUser(): User
    {
        $user = $this->security->getUser();

        if (!$user instanceof User) {
            throw new UnauthorizedActionException('Authentication required.');
        }

        return $user;
    }

    public function requirePartner(): User
    {
        $user = $this->getUser();

        if (UserProxyIntertace::PERSON_PARTNER !== $user->getPersonType()
            && !\in_array('ROLE_SUPER_ADMIN', $user->getRoles(), true)
            && !\in_array('ROLE_SYSTEM_ADMIN', $user->getRoles(), true)
        ) {
            throw new UnauthorizedActionException('Partner agency access required.');
        }

        return $user;
    }

    public function requireAgency(): Agency
    {
        $user = $this->requirePartner();
        $agency = $this->findAgencyForUser($user);

        if (null === $agency) {
            throw new UnavailableDataException('No agency linked to the current partner user.');
        }

        return $agency;
    }

    public function findAgencyForUser(User $user): ?Agency
    {
        $staff = $this->staffMembers->findActiveForUser($user);
        if (null !== $staff) {
            return $staff->getAgency();
        }

        if (null !== $user->getId()) {
            $byPartnerUser = $this->agencies->findOneByPartnerUserId($user->getId());
            if (null !== $byPartnerUser) {
                return $byPartnerUser;
            }
        }

        if (EntityType::AGENCY === $user->getHolderType() && null !== $user->getHolderId()) {
            return $this->agencies->find($user->getHolderId());
        }

        if (null !== $user->getOwnerId()) {
            $byOwner = $this->agencies->find($user->getOwnerId());
            if (null !== $byOwner) {
                return $byOwner;
            }
        }

        return null;
    }

    public function assertOwns(Agency $agency): void
    {
        $current = $this->requireAgency();

        if ($current->getId() !== $agency->getId()) {
            throw new UnavailableDataException('Agency resource not found.');
        }
    }

    public function resolveStaffRole(): string
    {
        $user = $this->requirePartner();
        $agency = $this->requireAgency();

        if ($agency->getUserId() === $user->getId()
            || \in_array('ROLE_SUPER_ADMIN', $user->getRoles(), true)
            || \in_array('ROLE_SYSTEM_ADMIN', $user->getRoles(), true)
        ) {
            return AgencyStaffRole::ADMIN;
        }

        $staff = $this->staffMembers->findOneByAgencyAndUser($agency, $user);
        if (null !== $staff && $staff->isActive()) {
            return $staff->getRole();
        }

        // Partner linked via holder without staff row → owner-level access
        return AgencyStaffRole::ADMIN;
    }

    public function getStaffMember(): ?AgencyStaffMember
    {
        return $this->staffMembers->findActiveForUser($this->getUser());
    }

    /**
     * @return list<string>
     */
    public function defaultPermissions(): array
    {
        return AgencyStaffRole::permissionsFor($this->resolveStaffRole());
    }

    public function requirePermission(string $permission): void
    {
        if (!\in_array($permission, $this->defaultPermissions(), true)
            && !\in_array('ROLE_SUPER_ADMIN', $this->getUser()->getRoles(), true)
        ) {
            throw new UnauthorizedActionException(sprintf('Missing permission "%s".', $permission));
        }
    }
}
