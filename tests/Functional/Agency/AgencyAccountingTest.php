<?php

namespace App\Tests\Functional\Agency;

use App\Entity\AccountingJournal;
use App\Entity\AgencyPayment;
use App\Manager\AccountingAgencyManager;

final class AgencyAccountingTest extends AgencyApiTestCase
{
    public function testRecordJournalFromPaidPayment(): void
    {
        $ws = $this->createPartnerWorkspace('Acct');

        $payment = new AgencyPayment();
        $payment->setAgency($ws['agency']);
        $payment->setReference('ABP-TEST-'.strtoupper($this->suffix));
        $payment->setAmount(12000);
        $payment->setCurrency('CDF');
        $payment->setMethod(AgencyPayment::METHOD_MOBILE_MONEY);
        $payment->setStatus(AgencyPayment::STATUS_PAID);
        $payment->setChannel(AgencyPayment::CHANNEL_ONLINE);
        $payment->setProvider(AgencyPayment::PROVIDER_FLEXPAY);
        $payment->setPaidAt(new \DateTimeImmutable());
        $this->em->persist($payment);
        $this->em->flush();

        /** @var AccountingAgencyManager $manager */
        $manager = static::getContainer()->get(AccountingAgencyManager::class);
        $manager->recordFromAgencyPayment($payment);

        /** @var list<AccountingJournal> $journals */
        $journals = $this->em->getRepository(AccountingJournal::class)->findBy([
            'sourceType' => AccountingJournal::SOURCE_AGENCY_PAYMENT,
            'sourceId' => $payment->getId(),
        ]);
        self::assertCount(2, $journals);

        $byAccount = [];
        foreach ($journals as $journal) {
            $byAccount[$journal->getAccount()] = $journal;
        }

        self::assertArrayHasKey(AccountingJournal::ACCOUNT_MM, $byAccount);
        self::assertSame(12000, $byAccount[AccountingJournal::ACCOUNT_MM]->getAmount());
        self::assertSame(AccountingJournal::DIRECTION_DEBIT, $byAccount[AccountingJournal::ACCOUNT_MM]->getDirection());

        self::assertArrayHasKey(AccountingJournal::ACCOUNT_SALES, $byAccount);
        self::assertSame(12000, $byAccount[AccountingJournal::ACCOUNT_SALES]->getAmount());
        self::assertSame(AccountingJournal::DIRECTION_CREDIT, $byAccount[AccountingJournal::ACCOUNT_SALES]->getDirection());

        $list = $this->api('GET', '/api/agency/accounting/journal', $ws['token'], null, 200);
        $member = $list['member'] ?? $list['hydra:member'] ?? (array_is_list($list) ? $list : []);
        self::assertNotEmpty($member);

        $close = $this->api('POST', '/api/agency/accounting/daily-closes', $ws['token'], [
            'businessDate' => (new \DateTimeImmutable())->format('Y-m-d'),
            'notes' => 'Test close',
        ], 201);
        self::assertSame('CLOSED', $close['status'] ?? null);
        self::assertGreaterThanOrEqual(12000, $close['mmTotal'] ?? 0);
    }
}
