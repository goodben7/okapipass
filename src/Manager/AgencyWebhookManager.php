<?php

namespace App\Manager;

use App\Domain\Agency\AgencyPermission;
use App\Dto\Agency\CreateAgencyWebhookDto;
use App\Dto\Agency\UpdateAgencyWebhookDto;
use App\Entity\AgencyWebhookSubscription;
use App\Service\Agency\AgencyContext;
use Doctrine\ORM\EntityManagerInterface;

final class AgencyWebhookManager
{
    public function __construct(
        private EntityManagerInterface $em,
        private AgencyContext $agencyContext,
    ) {
    }

    public function create(CreateAgencyWebhookDto $dto): AgencyWebhookSubscription
    {
        $this->agencyContext->requirePermission(AgencyPermission::STAFF_WRITE);
        $agency = $this->agencyContext->requireAgency();

        $sub = new AgencyWebhookSubscription();
        $sub->setAgency($agency);
        $sub->setUrl((string) $dto->url);
        $sub->setSecret((string) $dto->secret);
        $sub->setEvents(array_values($dto->events ?? []));
        $sub->setActive(false !== $dto->active);

        $this->em->persist($sub);
        $this->em->flush();

        return $sub;
    }

    public function update(AgencyWebhookSubscription $sub, UpdateAgencyWebhookDto $dto): AgencyWebhookSubscription
    {
        $this->agencyContext->requirePermission(AgencyPermission::STAFF_WRITE);
        $this->agencyContext->assertOwns($sub->getAgency());

        if (null !== $dto->url) {
            $sub->setUrl($dto->url);
        }
        if (null !== $dto->secret) {
            $sub->setSecret($dto->secret);
        }
        if (null !== $dto->events) {
            $sub->setEvents(array_values($dto->events));
        }
        if (null !== $dto->active) {
            $sub->setActive($dto->active);
        }

        $this->em->flush();

        return $sub;
    }

    public function delete(AgencyWebhookSubscription $sub): void
    {
        $this->agencyContext->requirePermission(AgencyPermission::STAFF_WRITE);
        $this->agencyContext->assertOwns($sub->getAgency());
        $this->em->remove($sub);
        $this->em->flush();
    }
}
