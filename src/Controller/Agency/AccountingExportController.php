<?php

namespace App\Controller\Agency;

use App\Exception\UnprocessableEntityException;
use App\Manager\AccountingAgencyManager;
use App\Security\AgencyPortalAccess;
use Symfony\Component\ExpressionLanguage\Expression;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

final class AccountingExportController
{
    public function __construct(private AccountingAgencyManager $accounting)
    {
    }

    #[Route(
        path: '/api/agency/accounting/exports/journal.csv',
        name: 'agency_accounting_journal_csv',
        methods: ['GET'],
    )]
    #[IsGranted(new Expression(AgencyPortalAccess::EXPRESSION))]
    public function journalCsv(Request $request): Response
    {
        $fromRaw = (string) $request->query->get('from', '');
        $toRaw = (string) $request->query->get('to', '');

        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $fromRaw) || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $toRaw)) {
            throw new UnprocessableEntityException('Query parameters from and to (YYYY-MM-DD) are required.');
        }

        $from = \DateTimeImmutable::createFromFormat('Y-m-d', $fromRaw);
        $to = \DateTimeImmutable::createFromFormat('Y-m-d', $toRaw);
        if (false === $from || false === $to) {
            throw new UnprocessableEntityException('Invalid date range.');
        }

        $agency = $this->accounting->requireAgency();
        $rows = $this->accounting->getJournal($agency, $from, $to);

        $filename = sprintf('journal-%s-%s.csv', $fromRaw, $toRaw);

        $response = new StreamedResponse(function () use ($rows): void {
            $handle = fopen('php://output', 'w');
            if (false === $handle) {
                return;
            }

            fputcsv($handle, ['id', 'entryDate', 'account', 'direction', 'amount', 'currency', 'sourceType', 'sourceId', 'label']);

            foreach ($rows as $row) {
                fputcsv($handle, [
                    $row->getId(),
                    $row->getEntryDate()?->format('Y-m-d'),
                    $row->getAccount(),
                    $row->getDirection(),
                    $row->getAmount(),
                    $row->getCurrency(),
                    $row->getSourceType(),
                    $row->getSourceId(),
                    $row->getLabel(),
                ]);
            }

            fclose($handle);
        });

        $response->headers->set('Content-Type', 'text/csv; charset=utf-8');
        $response->headers->set('Content-Disposition', 'attachment; filename="'.$filename.'"');

        return $response;
    }

    #[Route(
        path: '/api/agency/accounting/exports/journal.xls',
        name: 'agency_accounting_journal_xls',
        methods: ['GET'],
    )]
    #[IsGranted(new Expression(AgencyPortalAccess::EXPRESSION))]
    public function journalXls(Request $request): Response
    {
        $fromRaw = (string) $request->query->get('from', '');
        $toRaw = (string) $request->query->get('to', '');

        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $fromRaw) || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $toRaw)) {
            throw new UnprocessableEntityException('Query parameters from and to (YYYY-MM-DD) are required.');
        }

        $from = \DateTimeImmutable::createFromFormat('Y-m-d', $fromRaw);
        $to = \DateTimeImmutable::createFromFormat('Y-m-d', $toRaw);
        if (false === $from || false === $to) {
            throw new UnprocessableEntityException('Invalid date range.');
        }

        $agency = $this->accounting->requireAgency();
        $rows = $this->accounting->getJournal($agency, $from, $to);

        $filename = sprintf('journal-%s-%s.xls', $fromRaw, $toRaw);

        $response = new StreamedResponse(function () use ($rows): void {
            $handle = fopen('php://output', 'w');
            if (false === $handle) {
                return;
            }

            // CSV body with Excel MIME (compatible with Excel open)
            fputcsv($handle, ['id', 'entryDate', 'account', 'direction', 'amount', 'currency', 'sourceType', 'sourceId', 'label'], ';');

            foreach ($rows as $row) {
                fputcsv($handle, [
                    $row->getId(),
                    $row->getEntryDate()?->format('Y-m-d'),
                    $row->getAccount(),
                    $row->getDirection(),
                    $row->getAmount(),
                    $row->getCurrency(),
                    $row->getSourceType(),
                    $row->getSourceId(),
                    $row->getLabel(),
                ], ';');
            }

            fclose($handle);
        });

        $response->headers->set('Content-Type', 'application/vnd.ms-excel; charset=utf-8');
        $response->headers->set('Content-Disposition', 'attachment; filename="'.$filename.'"');

        return $response;
    }
}
