<?php

namespace App\Tests\Functional\Agency;

use App\Entity\AgencyObligation;
use App\Entity\AgencyObligationType;

final class AgencyComplianceCalendarTest extends AgencyApiTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->seedObligationTypes();
    }

    public function testListObligationTypes(): void
    {
        $ws = $this->createPartnerWorkspace('ComplTypes');
        $types = $this->api('GET', '/api/agency/obligation-types', $ws['token'], null, 200);
        $member = $types['member'] ?? $types['hydra:member'] ?? (array_is_list($types) ? $types : []);
        self::assertNotEmpty($member);
        $codes = array_column($member, 'code');
        self::assertContains('FPT_MONTHLY', $codes);
        self::assertContains('AGENCY_LICENSE', $codes);
    }

    public function testCreateObligationAndSeeOnCalendar(): void
    {
        $ws = $this->createPartnerWorkspace('ComplCreate');
        $due = (new \DateTimeImmutable('+10 days'))->format('Y-m-d');

        $created = $this->api('POST', '/api/agency/obligations', $ws['token'], [
            'title' => 'Assurance flotte 2026',
            'dueDate' => $due,
            'type' => 'FLEET_INSURANCE',
            'reference' => 'POL-123',
            'reminderDays' => 15,
        ], 201);

        self::assertSame(AgencyObligation::STATUS_OPEN, $created['status'] ?? null);
        self::assertSame(AgencyObligation::URGENCY_DUE_SOON, $created['urgency'] ?? null);
        self::assertSame('FLEET_INSURANCE', $created['type']['code'] ?? null);

        $from = (new \DateTimeImmutable('today'))->format('Y-m-d');
        $to = (new \DateTimeImmutable('+40 days'))->format('Y-m-d');
        $calendar = $this->api(
            'GET',
            '/api/agency/compliance/calendar?from='.$from.'&to='.$to,
            $ws['token'],
            null,
            200,
        );

        self::assertSame('compliance-calendar', $calendar['id'] ?? null);
        self::assertGreaterThanOrEqual(1, $calendar['kpis']['open'] ?? 0);
        self::assertGreaterThanOrEqual(1, $calendar['kpis']['dueSoon'] ?? 0);
        $ids = array_column($calendar['events'] ?? [], 'id');
        self::assertContains($created['id'], $ids);
    }

    public function testBootstrapCreatesDefaultObligationsIdempotently(): void
    {
        $ws = $this->createPartnerWorkspace('ComplBoot');

        $first = $this->api('POST', '/api/agency/obligations/bootstrap', $ws['token'], [
            'fromDate' => (new \DateTimeImmutable('today'))->format('Y-m-d'),
        ], 201);
        self::assertGreaterThanOrEqual(1, $first['createdCount'] ?? 0);

        $second = $this->api('POST', '/api/agency/obligations/bootstrap', $ws['token'], [
            'fromDate' => (new \DateTimeImmutable('today'))->format('Y-m-d'),
        ], 201);
        self::assertSame(0, $second['createdCount'] ?? -1);
    }

    public function testCompleteObligation(): void
    {
        $ws = $this->createPartnerWorkspace('ComplDone');
        $created = $this->api('POST', '/api/agency/obligations', $ws['token'], [
            'title' => 'Attestation fiscale',
            'dueDate' => (new \DateTimeImmutable('+60 days'))->format('Y-m-d'),
            'type' => 'TAX_CLEARANCE',
        ], 201);

        $done = $this->api(
            'POST',
            '/api/agency/obligations/'.$created['id'].'/complete',
            $ws['token'],
            null,
            200,
        );

        self::assertSame(AgencyObligation::STATUS_COMPLETED, $done['status'] ?? null);
        self::assertSame(AgencyObligation::URGENCY_DONE, $done['urgency'] ?? null);
        self::assertNotEmpty($done['completedAt'] ?? null);
    }

    public function testOverdueUrgency(): void
    {
        $ws = $this->createPartnerWorkspace('ComplLate');
        $created = $this->api('POST', '/api/agency/obligations', $ws['token'], [
            'title' => 'Licence expirée',
            'dueDate' => (new \DateTimeImmutable('-5 days'))->format('Y-m-d'),
            'type' => 'AGENCY_LICENSE',
        ], 201);

        self::assertSame(AgencyObligation::URGENCY_OVERDUE, $created['urgency'] ?? null);
        self::assertLessThan(0, $created['daysRemaining'] ?? 0);
    }

    private function seedObligationTypes(): void
    {
        if (null !== $this->em->getRepository(AgencyObligationType::class)->findOneBy(['code' => 'FPT_MONTHLY'])) {
            return;
        }

        $specs = [
            ['AGENCY_LICENSE', 'Agrément / licence ONT', AgencyObligationType::CATEGORY_LICENSE, 12, 45, 10],
            ['FLEET_INSURANCE', 'Assurance flotte', AgencyObligationType::CATEGORY_INSURANCE, 12, 30, 20],
            ['TECHNICAL_INSPECTION', 'Visite technique', AgencyObligationType::CATEGORY_TECHNICAL, 6, 21, 30],
            ['FPT_MONTHLY', 'Déclaration FPT mensuelle', AgencyObligationType::CATEGORY_FPT, 1, 7, 40],
            ['TAX_CLEARANCE', 'Attestation fiscale', AgencyObligationType::CATEGORY_TAX, 12, 30, 50],
            ['TRANSPORT_AUTH', 'Autorisation de transport', AgencyObligationType::CATEGORY_LICENSE, 12, 45, 60],
        ];

        foreach ($specs as [$code, $label, $category, $months, $reminder, $sort]) {
            $type = new AgencyObligationType();
            $type->setCode($code);
            $type->setLabel($label);
            $type->setCategory($category);
            $type->setDefaultValidityMonths($months);
            $type->setReminderDays($reminder);
            $type->setSortOrder($sort);
            $type->setActive(true);
            $this->em->persist($type);
        }
        $this->em->flush();
    }
}
