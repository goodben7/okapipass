<?php

namespace App\Service\Agency;

use App\Entity\Agency;
use App\Entity\IdempotencyRecord;
use App\Repository\IdempotencyRecordRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\RequestStack;

final class IdempotencyService
{
    public function __construct(
        private EntityManagerInterface $em,
        private IdempotencyRecordRepository $records,
        private RequestStack $requestStack,
    ) {
    }

    public function readKeyHeader(): ?string
    {
        $request = $this->requestStack->getCurrentRequest();
        if (null === $request) {
            return null;
        }
        $key = trim((string) $request->headers->get('Idempotency-Key', ''));

        return '' !== $key ? $key : null;
    }

    public function findReplay(string $scope, string $key): ?IdempotencyRecord
    {
        return $this->records->findByScopeAndKey($scope, $this->hash($key));
    }

    /**
     * @param array<string, mixed> $body
     */
    public function store(
        ?Agency $agency,
        string $scope,
        string $key,
        int $status,
        array $body,
    ): IdempotencyRecord {
        $existing = $this->findReplay($scope, $key);
        if ($existing instanceof IdempotencyRecord) {
            return $existing;
        }

        $record = new IdempotencyRecord();
        $record->setAgency($agency);
        $record->setScope($scope);
        $record->setKeyHash($this->hash($key));
        $record->setResponseStatus($status);
        $record->setResponseBody($body);
        $this->em->persist($record);
        $this->em->flush();

        return $record;
    }

    private function hash(string $key): string
    {
        return hash('sha256', $key);
    }
}
