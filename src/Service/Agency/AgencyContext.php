<?php

namespace App\Service\Agency;

use App\Domain\Agency\AgencyStaffRole;
use App\Entity\Agency;
use App\Entity\AgencyStaffMember;
use App\Entity\User;
use App\Enum\EntityType;
use App\Exception\UnauthorizedActionException;
use App\Exception\UnavailableDataException;
use App\Exception\UnprocessableEntityException;
use App\Model\UserProxyIntertace;
use App\Repository\AgencyRepository;
use App\Repository\AgencyStaffMemberRepository;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * Resolves the partner agency for the current JWT user + staff RBAC.
 */
class AgencyContext
{
    public function __construct(
        private Security $security,
        private AgencyRepository $agencies,
        private AgencyStaffMemberRepository $staffMembers,
        private RequestStack $requestStack,
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

    public function isElevated(): bool
    {
        $user = $this->getUser();
        $roles = $user->getRoles();

        if (\in_array('ROLE_SUPER_ADMIN', $roles, true)
            || \in_array('ROLE_SYSTEM_ADMIN', $roles, true)
            || \in_array('ROLE_ONT_ADMIN', $roles, true)
            || \in_array('ROLE_ONT_AGENT', $roles, true)
        ) {
            return true;
        }

        return \in_array($user->getPersonType(), [
            UserProxyIntertace::PERSON_ONT_ADMIN,
            UserProxyIntertace::PERSON_ONT_AGENT,
        ], true);
    }

    public function requirePartner(): User
    {
        $user = $this->getUser();

        if ($this->isElevated()) {
            return $user;
        }

        if (UserProxyIntertace::PERSON_PARTNER !== $user->getPersonType()) {
            throw new UnauthorizedActionException('Partner agency access required.');
        }

        return $user;
    }

    public function requireAgency(?string $agencyId = null): Agency
    {
        $user = $this->requirePartner();

        if ($this->isElevated()) {
            $resolvedId = $this->resolveAgencyIdParam($agencyId);
            if (null === $resolvedId) {
                throw new UnprocessableEntityException(
                    'Query parameter "agencyId" is required for ONT/admin agency portal access.'
                );
            }

            $agency = $this->agencies->find($resolvedId);
            if (!$agency instanceof Agency) {
                throw new UnavailableDataException(sprintf('Agency "%s" not found.', $resolvedId));
            }

            return $agency;
        }

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

    public function assertOwns(?Agency $agency): void
    {
        if (null === $agency) {
            throw new UnavailableDataException('Agency resource not found.');
        }

        if ($this->isElevated()) {
            return;
        }

        $current = $this->requireAgency();

        if ($current->getId() !== $agency->getId()) {
            throw new UnavailableDataException('Agency resource not found.');
        }
    }

    public function resolveStaffRole(): string
    {
        $user = $this->requirePartner();

        if ($this->isElevated()) {
            return AgencyStaffRole::ADMIN;
        }

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
            && !$this->isElevated()
        ) {
            throw new UnauthorizedActionException(sprintf('Missing permission "%s".', $permission));
        }
    }

    public function peekAgencyId(): ?string
    {
        return $this->resolveAgencyIdParam(null);
    }

    private function resolveAgencyIdParam(?string $explicit): ?string
    {
        if (null !== $explicit && '' !== trim($explicit)) {
            return trim($explicit);
        }

        $request = $this->requestStack->getCurrentRequest();
        if (null === $request) {
            return null;
        }

        $fromQuery = $request->query->get('agencyId');
        if (\is_string($fromQuery) && '' !== trim($fromQuery)) {
            return trim($fromQuery);
        }

        $fromBody = null;
        try {
            $payload = $request->toArray();
            $fromBody = $payload['agencyId'] ?? null;
        } catch (\Throwable) {
            $fromBody = null;
        }
        if (\is_string($fromBody) && '' !== trim($fromBody)) {
            return trim($fromBody);
        }

        return null;
    }
}
