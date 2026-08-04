<?php

namespace App\Doctrine\Extension;

use ApiPlatform\Doctrine\Orm\Extension\QueryCollectionExtensionInterface;
use ApiPlatform\Doctrine\Orm\Util\QueryNameGeneratorInterface;
use ApiPlatform\Metadata\Operation;
use App\Domain\Agency\AgencyScopedInterface;
use App\Exception\UnauthorizedActionException;
use App\Model\UserProxyIntertace;
use App\Service\Agency\AgencyContext;
use Doctrine\ORM\QueryBuilder;

/**
 * Scopes ONLY future /api/agency portal entities implementing AgencyScopedInterface.
 * Does not touch App\Entity\Agency (/api/agencies) — ONT/admin lists stay global.
 */
final class AgencyTenantExtension implements QueryCollectionExtensionInterface
{
    public function __construct(
        private AgencyContext $agencyContext,
    ) {
    }

    public function applyToCollection(
        QueryBuilder $queryBuilder,
        QueryNameGeneratorInterface $queryNameGenerator,
        string $resourceClass,
        ?Operation $operation = null,
        array $context = [],
    ): void {
        if (!is_a($resourceClass, AgencyScopedInterface::class, true)) {
            return;
        }

        $user = $this->agencyContext->getUser();

        // Elevated roles can browse all tenants (support / ONT tools later).
        if (\in_array('ROLE_SUPER_ADMIN', $user->getRoles(), true)
            || \in_array('ROLE_SYSTEM_ADMIN', $user->getRoles(), true)
            || UserProxyIntertace::PERSON_ONT_ADMIN === $user->getPersonType()
        ) {
            return;
        }

        if (UserProxyIntertace::PERSON_PARTNER !== $user->getPersonType()) {
            throw new UnauthorizedActionException('Partner agency access required.');
        }

        $agency = $this->agencyContext->findAgencyForUser($user);
        if (null === $agency) {
            // Force empty result without leaking other tenants.
            $queryBuilder->andWhere('1 = 0');

            return;
        }

        $rootAlias = $queryBuilder->getRootAliases()[0];
        $param = $queryNameGenerator->generateParameterName('agency');
        $queryBuilder
            ->andWhere(sprintf('%s.agency = :%s', $rootAlias, $param))
            ->setParameter($param, $agency);
    }
}
