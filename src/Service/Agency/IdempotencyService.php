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
     * Claim key via INSERT IGNORE (MySQL) before side-effects — avoids race duplicates
     * without closing the EntityManager on unique conflicts.
     *
     * @return array{0: IdempotencyRecord, 1: bool} [record, isNewClaim]
     */
    public function claimOrReplay(?Agency $agency, string $scope, string $key): array
    {
        $hash = $this->hash($key);
        $existing = $this->records->findByScopeAndKey($scope, $hash);
        if ($existing instanceof IdempotencyRecord) {
            return [$existing, false];
        }

        $id = $this->generateRecordId();
        $inserted = (int) $this->em->getConnection()->executeStatement(
            'INSERT IGNORE INTO `idempotency_record`
                (IK_ID, IK_AGENCY, IK_SCOPE, IK_KEY_HASH, IK_RESPONSE_STATUS, IK_RESPONSE_BODY, IK_CREATED_AT)
             VALUES (?, ?, ?, ?, 0, NULL, ?)',
            [
                $id,
                $agency?->getId(),
                $scope,
                $hash,
                (new \DateTimeImmutable('now'))->format('Y-m-d H:i:s'),
            ],
        );

        $record = $this->records->findByScopeAndKey($scope, $hash);
        if (!$record instanceof IdempotencyRecord) {
            throw new \RuntimeException('Idempotency claim failed: record not found after insert.');
        }

        return [$record, 1 === $inserted];
    }

    /**
     * @param array<string, mixed> $body
     */
    public function complete(IdempotencyRecord $record, int $status, array $body): void
    {
        $record->setResponseStatus($status);
        $record->setResponseBody($body);
        $this->em->flush();
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
        [$record, $isNew] = $this->claimOrReplay($agency, $scope, $key);
        if ($isNew || 0 === $record->getResponseStatus()) {
            $this->complete($record, $status, $body);
        }

        return $record;
    }

    private function hash(string $key): string
    {
        return hash('sha256', $key);
    }

    private function generateRecordId(): string
    {
        $letters = '';
        for ($i = 0; $i < 4; ++$i) {
            $letters .= 'abcdefghijklmnopqrstuvwxyz'[random_int(0, 25)];
        }

        return IdempotencyRecord::ID_PREFIX.strtoupper($letters.(new \DateTimeImmutable('now'))->format('mdHis'));
    }
}
