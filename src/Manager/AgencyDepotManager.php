<?php

namespace App\Manager;

use App\Domain\Agency\AgencyPermission;
use App\Dto\Agency\CreateAgencyDepotDto;
use App\Dto\Agency\UpdateAgencyDepotDto;
use App\Entity\AgencyDepot;
use App\Exception\UnprocessableEntityException;
use App\Repository\AgencyDepotRepository;
use App\Service\Agency\AgencyContext;
use Doctrine\ORM\EntityManagerInterface;

final class AgencyDepotManager
{
    public function __construct(
        private EntityManagerInterface $em,
        private AgencyContext $agencyContext,
        private AgencyDepotRepository $depots,
    ) {
    }

    public function create(CreateAgencyDepotDto $dto): AgencyDepot
    {
        $this->agencyContext->requirePermission(AgencyPermission::ACCOUNTING_READ);
        $agency = $this->agencyContext->requireAgency();

        $code = strtoupper(trim((string) $dto->code));
        $existing = $this->depots->findOneBy(['agency' => $agency, 'code' => $code]);
        if ($existing instanceof AgencyDepot) {
            throw new UnprocessableEntityException('A depot with this code already exists.');
        }

        $depot = new AgencyDepot();
        $depot->setAgency($agency);
        $depot->setCode($code);
        $depot->setLabel((string) $dto->label);
        $depot->setActive(false !== $dto->active);

        $this->em->persist($depot);
        $this->em->flush();

        return $depot;
    }

    public function update(AgencyDepot $depot, UpdateAgencyDepotDto $dto): AgencyDepot
    {
        $this->agencyContext->requirePermission(AgencyPermission::ACCOUNTING_READ);
        $this->agencyContext->assertOwns($depot->getAgency());

        if (null !== $dto->code) {
            $code = strtoupper(trim($dto->code));
            $existing = $this->depots->findOneBy(['agency' => $depot->getAgency(), 'code' => $code]);
            if ($existing instanceof AgencyDepot && $existing->getId() !== $depot->getId()) {
                throw new UnprocessableEntityException('A depot with this code already exists.');
            }
            $depot->setCode($code);
        }
        if (null !== $dto->label) {
            $depot->setLabel($dto->label);
        }
        if (null !== $dto->active) {
            $depot->setActive($dto->active);
        }

        $this->em->flush();

        return $depot;
    }
}
