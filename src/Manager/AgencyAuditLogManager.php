<?php

namespace App\Manager;

use App\Entity\Agency;
use App\Entity\AgencyAuditLog;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;

final class AgencyAuditLogManager
{
    public function __construct(
        private EntityManagerInterface $em,
        private LoggerInterface $logger,
    ) {
    }

    /**
     * Best-effort audit — never throws into callers.
     *
     * @param array<string, mixed>|null $meta
     */
    public function log(
        Agency $agency,
        ?User $actor,
        string $action,
        string $entityType,
        string $entityId,
        ?array $meta = null,
    ): void {
        try {
            $entry = new AgencyAuditLog();
            $entry->setAgency($agency);
            $entry->setActor($actor);
            $entry->setAction($action);
            $entry->setEntityType($entityType);
            $entry->setEntityId($entityId);
            $entry->setMeta($meta);
            $this->em->persist($entry);
            $this->em->flush();
        } catch (\Throwable $e) {
            $this->logger->warning('agency.audit_log.failed', [
                'action' => $action,
                'entityType' => $entityType,
                'entityId' => $entityId,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
