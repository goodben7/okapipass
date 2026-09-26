<?php

namespace App\Provider\Agency;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use App\Entity\AgencyAuditLog;
use App\Service\Agency\AgencyContext;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * Supports ?from=&to=&action= on top of agency scoping.
 *
 * @implements ProviderInterface<AgencyAuditLog>
 */
final class AgencyAuditLogCollectionProvider implements ProviderInterface
{
    public function __construct(
        private AgencyContext $agencyContext,
        private EntityManagerInterface $em,
        private RequestStack $requestStack,
    ) {
    }

    public function provide(Operation $operation, array $uriVariables = [], array $context = []): object|array|null
    {
        $request = $this->requestStack->getCurrentRequest();
        $from = (string) ($request?->query->get('from') ?? '');
        $to = (string) ($request?->query->get('to') ?? '');
        $action = (string) ($request?->query->get('action') ?? '');

        $agency = $this->agencyContext->requireAgency();
        $qb = $this->em->createQueryBuilder()
            ->select('a')
            ->from(AgencyAuditLog::class, 'a')
            ->andWhere('a.agency = :agency')
            ->setParameter('agency', $agency)
            ->orderBy('a.createdAt', 'DESC');

        if ('' !== $action) {
            $qb->andWhere('a.action = :action')->setParameter('action', $action);
        }
        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $from)) {
            $qb->andWhere('a.createdAt >= :from')->setParameter('from', new \DateTimeImmutable($from.' 00:00:00'));
        }
        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $to)) {
            $qb->andWhere('a.createdAt <= :to')->setParameter('to', new \DateTimeImmutable($to.' 23:59:59'));
        }

        return $qb->getQuery()->getResult();
    }
}
