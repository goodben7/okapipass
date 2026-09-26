<?php

namespace App\Repository;

use App\Entity\Agency;
use App\Entity\AgencyWebhookSubscription;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/** @extends ServiceEntityRepository<AgencyWebhookSubscription> */
class AgencyWebhookSubscriptionRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, AgencyWebhookSubscription::class);
    }

    /**
     * @return list<AgencyWebhookSubscription>
     */
    public function findActiveForEvent(Agency $agency, string $event): array
    {
        /** @var list<AgencyWebhookSubscription> $subs */
        $subs = $this->createQueryBuilder('w')
            ->andWhere('w.agency = :agency')
            ->andWhere('w.active = true')
            ->setParameter('agency', $agency)
            ->getQuery()
            ->getResult();

        return array_values(array_filter(
            $subs,
            static fn (AgencyWebhookSubscription $s): bool => \in_array($event, $s->getEvents(), true),
        ));
    }
}
