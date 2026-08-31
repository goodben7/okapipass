<?php

namespace App\Service\Agency;

use App\Entity\Agency;
use App\Entity\AgencyDriver;
use App\Entity\AgencyMaintenanceCase;
use App\Entity\AgencyRentalContract;
use App\Entity\Notification;
use App\Enum\NotificationType;
use App\Service\NotificationService;
use Psr\Log\LoggerInterface;

final class AgencyFleetNotifier
{
    public function __construct(
        private NotificationService $notifications,
        private LoggerInterface $logger,
    ) {
    }

    public function notifyMaintenanceOpened(AgencyMaintenanceCase $case): void
    {
        $agency = $case->getAgency();
        $phone = $this->resolveAgencyPhone($agency);
        if (null === $phone) {
            return;
        }

        $transport = $case->getTransport();
        $lines = [
            'Alerte maintenance — OkapiPass Fleet',
            sprintf('Bus: %s', $transport?->getLabel() ?? '?'),
            sprintf('Dossier: %s', $case->getTitle() ?? 'Sans titre'),
            sprintf('Type: %s', $case->getType() ?? '?'),
            'Les ventes passagers sont bloquées sur ce véhicule.',
        ];

        $this->sendWhatsapp($phone, NotificationType::MAINTENANCE_ALERT, implode("\n", $lines), [
            'maintenanceCaseId' => $case->getId(),
            'transportId' => $transport?->getId(),
        ], 'maintenance.opened');
    }

    public function notifyLicenseExpiring(Agency $agency, AgencyDriver $driver): void
    {
        $phone = $this->resolveAgencyPhone($agency);
        if (null === $phone) {
            return;
        }

        $expires = $driver->getLicenseExpiresAt()?->format('d/m/Y') ?? '?';
        $lines = [
            'Alerte permis chauffeur — OkapiPass Fleet',
            sprintf('Chauffeur: %s', $driver->getFullName()),
            sprintf('Permis: %s', $driver->getLicenseNumber()),
            sprintf('Expiration: %s', $expires),
            'Merci de renouveler le permis ou de désactiver le chauffeur.',
        ];

        $this->sendWhatsapp($phone, NotificationType::DOCUMENT_EXPIRING_SOON, implode("\n", $lines), [
            'driverId' => $driver->getId(),
            'licenseExpiresAt' => $driver->getLicenseExpiresAt()?->format('Y-m-d'),
        ], 'driver.license_expiring');
    }

    public function notifyRentalPaid(AgencyRentalContract $contract, int $amount): void
    {
        $phone = trim((string) $contract->getClientPhone());
        if ('' === $phone) {
            return;
        }

        $lines = [
            'Paiement location confirmé — OkapiPass',
            sprintf('Contrat: %s', $contract->getId()),
            sprintf('Client: %s', $contract->getClientName()),
            sprintf('Bus: %s', $contract->getTransport()?->getLabel() ?? '?'),
            sprintf('Du %s au %s', $contract->getStartAt()?->format('d/m/Y H:i') ?? '?', $contract->getEndAt()?->format('d/m/Y H:i') ?? '?'),
            sprintf('Montant: %d %s', $amount, $contract->getCurrency()),
        ];

        $this->sendWhatsapp($phone, NotificationType::PAYMENT_PAID, implode("\n", $lines), [
            'rentalContractId' => $contract->getId(),
        ], 'rental.paid');
    }

    /**
     * @param array<string, mixed> $context
     */
    private function sendWhatsapp(string $phone, string $type, string $body, array $context, string $logKey): void
    {
        $notification = new Notification();
        $notification->setTarget($phone);
        $notification->setTargetType(Notification::TARGET_TYPE_WHATSAPP);
        $notification->setSentVia(Notification::SENT_VIA_WHATSAPP);
        $notification->setType($type);
        $notification->setTitle('OkapiPass Fleet');
        $notification->setBody($body);
        $notification->setTemplateContext($context);

        try {
            $this->notifications->send($notification);
        } catch (\Throwable $e) {
            $this->logger->error('agency.fleet.whatsapp.'.$logKey.'.failed', [
                'phone' => $phone,
                'exception' => $e::class,
                'message' => $e->getMessage(),
            ]);
        }
    }

    private function resolveAgencyPhone(?Agency $agency): ?string
    {
        $phone = trim((string) ($agency?->getPhone() ?? ''));
        if ('' === $phone) {
            return null;
        }

        return $phone;
    }
}
