<?php

namespace App\State\Agency;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Dto\Agency\CreateAccountingDailyCloseDto;
use App\Entity\AccountingDailyClose;
use App\Exception\UnprocessableEntityException;
use App\Manager\AccountingAgencyManager;
use App\Manager\AgencyAuditLogManager;
use App\Service\Agency\AgencyContext;

/** @implements ProcessorInterface<CreateAccountingDailyCloseDto, AccountingDailyClose> */
final class CreateAccountingDailyCloseProcessor implements ProcessorInterface
{
    public function __construct(
        private AgencyContext $agencyContext,
        private AccountingAgencyManager $accounting,
        private AgencyAuditLogManager $auditLog,
    ) {
    }

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): AccountingDailyClose
    {
        if (!$data instanceof CreateAccountingDailyCloseDto) {
            throw new \InvalidArgumentException('Expected CreateAccountingDailyCloseDto.');
        }

        $agency = $this->agencyContext->requireAgency();
        $user = $this->agencyContext->getUser();
        $businessDate = $this->parseDate((string) $data->businessDate);

        $close = $this->accounting->closeDay($agency, $user, $businessDate, $data->notes);

        $this->auditLog->log(
            $agency,
            $user,
            'accounting.daily_close',
            'AccountingDailyClose',
            (string) $close->getId(),
            ['businessDate' => $businessDate->format('Y-m-d')],
        );

        return $close;
    }

    private function parseDate(string $date): \DateTimeImmutable
    {
        $d = \DateTimeImmutable::createFromFormat('Y-m-d', $date);
        if (false === $d) {
            throw new UnprocessableEntityException('Invalid businessDate, expected YYYY-MM-DD.');
        }

        return $d->setTime(0, 0);
    }
}
