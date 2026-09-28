<?php

namespace App\Tests\Functional;

use App\Entity\Checkpoint;
use App\Entity\GoPass;
use App\Entity\Payment;
use App\Entity\Ticket;
use App\Tests\Functional\Agency\AgencyApiTestCase;

final class TicketIdempotencyTest extends AgencyApiTestCase
{
    public function testIdempotencyKeyReplaysTicketCreate(): void
    {
        $ws = $this->createPartnerWorkspace('TktIk');
        $goPass = $this->em->getRepository(GoPass::class)->findOneBy(['code' => 'ROUTIER']);
        self::assertInstanceOf(GoPass::class, $goPass);

        $dep = new Checkpoint();
        $dep->setLabel('Lubumbashi-IK-'.$this->suffix);
        $dep->setActive(true);
        $arr = new Checkpoint();
        $arr->setLabel('Kolwezi-IK-'.$this->suffix);
        $arr->setActive(true);
        $this->em->persist($dep);
        $this->em->persist($arr);
        $this->em->flush();

        $key = 'ticket-idem-'.$this->suffix;
        $body = [
            'displayName' => 'Jean Kabongo',
            'phone' => '+2438281'.random_int(10000, 99999),
            'identifier' => 'CD-IK-'.$this->suffix,
            'goPass' => '/api/go_passes/'.$goPass->getId(),
            'departure' => '/api/checkpoints/'.$dep->getId(),
            'arrival' => '/api/checkpoints/'.$arr->getId(),
            'method' => Payment::METHOD_CASH,
        ];

        $first = $this->apiWithHeaders('POST', '/api/tickets', $ws['token'], $body, [
            'HTTP_IDEMPOTENCY_KEY' => $key,
        ], 201);
        $second = $this->apiWithHeaders('POST', '/api/tickets', $ws['token'], $body, [
            'HTTP_IDEMPOTENCY_KEY' => $key,
        ], 201);

        self::assertSame($first['id'] ?? null, $second['id'] ?? null);
        self::assertNotEmpty($first['id'] ?? null);

        $count = (int) $this->em->createQueryBuilder()
            ->select('COUNT(t.id)')
            ->from(Ticket::class, 't')
            ->where('t.phone = :phone')
            ->setParameter('phone', $body['phone'])
            ->getQuery()
            ->getSingleScalarResult();
        self::assertSame(1, $count);
    }
}
