<?php

namespace App\State\Agency;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\ApiResource\AgencyObligationBootstrapResource;
use App\Dto\Agency\BootstrapAgencyObligationsDto;
use App\Manager\AgencyObligationManager;

/** @implements ProcessorInterface<BootstrapAgencyObligationsDto, AgencyObligationBootstrapResource> */
final class BootstrapAgencyObligationsProcessor implements ProcessorInterface
{
    public function __construct(private AgencyObligationManager $manager)
    {
    }

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): AgencyObligationBootstrapResource
    {
        \assert($data instanceof BootstrapAgencyObligationsDto);
        $created = $this->manager->bootstrap($data);

        return new AgencyObligationBootstrapResource(
            id: 'bootstrap',
            createdCount: \count($created),
            obligations: array_map(static fn ($o) => [
                'id' => $o->getId(),
                'title' => $o->getTitle(),
                'dueDate' => $o->getDueDate()?->format('Y-m-d'),
                'typeCode' => $o->getType()?->getCode(),
                'urgency' => $o->getUrgency(),
            ], $created),
        );
    }
}
