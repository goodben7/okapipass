<?php

namespace App\Service\Agency;

use App\Entity\AgencyTransport;
use App\Repository\AgencyMaintenanceCaseRepository;
use App\Repository\AgencyWorkOrderRepository;

final class AgencyTransportMaintenanceSync
{
    public function __construct(
        private AgencyMaintenanceCaseRepository $cases,
        private AgencyWorkOrderRepository $workOrders,
    ) {
    }

    public function syncTransportMaintenanceStatus(AgencyTransport $transport): void
    {
        $blocking = $this->cases->countBlockingByTransport($transport)
            + $this->workOrders->countImmobilizingByTransport($transport);

        if ($blocking > 0) {
            if (AgencyTransport::STATUS_INACTIVE !== $transport->getStatus()) {
                $transport->setStatus(AgencyTransport::STATUS_MAINTENANCE);
            }

            return;
        }

        if (AgencyTransport::STATUS_MAINTENANCE === $transport->getStatus()) {
            $transport->setStatus(AgencyTransport::STATUS_ACTIVE);
        }
    }
}
