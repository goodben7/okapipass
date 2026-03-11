<?php

namespace App\Service;

use App\Entity\Ticket;
use App\Repository\TicketRepository;

class TicketUniqueReferenceGenerator
{
    public function __construct(private TicketRepository $tickets)
    {
    }

    public function generateFor(Ticket $ticket): string
    {
        for ($i = 0; $i < 50; $i++) {
            $ref = $this->generate8Chars();
            $existing = $this->tickets->findOneBy(['uniqueReference' => $ref]);

            if (null === $existing) {
                return $ref;
            }
        }

        throw new \RuntimeException('Unable to generate a unique ticket reference');
    }

    private function generate8Chars(): string
    {
        $alphabet = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';
        $bytes = \random_bytes(5);

        $buffer = 0;
        $bitsInBuffer = 0;
        $out = '';

        for ($i = 0; $i < 5; $i++) {
            $buffer = ($buffer << 8) | \ord($bytes[$i]);
            $bitsInBuffer += 8;

            while ($bitsInBuffer >= 5 && \strlen($out) < 8) {
                $bitsInBuffer -= 5;
                $index = ($buffer >> $bitsInBuffer) & 31;
                $out .= $alphabet[$index];
            }
        }

        if (\strlen($out) < 8) {
            $out = \str_pad($out, 8, $alphabet[0]);
        }

        return $out;
    }
}
