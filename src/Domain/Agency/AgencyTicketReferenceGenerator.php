<?php

namespace App\Domain\Agency;

use App\Entity\Agency;
use App\Entity\AgencyTicketSequence;
use App\Exception\ConflictException;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Atomic global VP-{YYYY}-{#####} (spec §5.3).
 * Global because AgencyTicket.reference is unique and looked up by reference.
 */
final class AgencyTicketReferenceGenerator
{
    public function __construct(
        private EntityManagerInterface $em,
    ) {
    }

    public function next(Agency $agency, ?\DateTimeImmutable $at = null): string
    {
        $at ??= new \DateTimeImmutable('now');
        $year = (int) $at->format('Y');
        $conn = $this->em->getConnection();

        $conn->beginTransaction();
        try {
            $row = $conn->fetchAssociative(
                'SELECT AS_ID, AS_LAST_NUMBER FROM `agency_ticket_sequence` WHERE AS_YEAR = ? ORDER BY AS_ID ASC LIMIT 1 FOR UPDATE',
                [$year]
            );

            if (false === $row || null === $row) {
                $id = $this->newSequenceId();
                $conn->insert('agency_ticket_sequence', [
                    'AS_ID' => $id,
                    'AS_AGENCY' => $agency->getId(),
                    'AS_YEAR' => $year,
                    'AS_LAST_NUMBER' => 1,
                ]);
                $next = 1;
            } else {
                $next = (int) $row['AS_LAST_NUMBER'] + 1;
                if ($next > 99999) {
                    throw new ConflictException(sprintf('Ticket sequence exhausted for year %d.', $year));
                }
                $conn->update(
                    'agency_ticket_sequence',
                    ['AS_LAST_NUMBER' => $next],
                    ['AS_ID' => $row['AS_ID']]
                );
            }

            $conn->commit();
        } catch (\Throwable $e) {
            if ($conn->isTransactionActive()) {
                $conn->rollBack();
            }
            throw $e;
        }

        return sprintf('VP-%d-%05d', $year, $next);
    }

    private function newSequenceId(): string
    {
        $randomLetters = substr(str_shuffle('ABCDEFGHIJKLMNOPQRSTUVWXYZ'), 0, 4);
        $dateTimeString = (new \DateTimeImmutable('now'))->format('mdHi');

        return AgencyTicketSequence::ID_PREFIX.$randomLetters.$dateTimeString;
    }
}
