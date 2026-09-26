<?php

namespace App\Provider\Agency;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use App\ApiResource\AgencyManifestResource;
use App\Exception\UnprocessableEntityException;
use App\Manager\AgencyBaggageManager;
use Symfony\Component\HttpFoundation\RequestStack;

/** @implements ProviderInterface<AgencyManifestResource> */
final class AgencyManifestProvider implements ProviderInterface
{
    public function __construct(
        private AgencyBaggageManager $baggageManager,
        private RequestStack $requestStack,
    ) {
    }

    public function provide(Operation $operation, array $uriVariables = [], array $context = []): AgencyManifestResource
    {
        $request = $this->requestStack->getCurrentRequest();
        $offerId = (string) ($request?->query->get('offerId') ?? '');
        $travelDate = (string) ($request?->query->get('travelDate') ?? '');

        if ('' === $offerId) {
            throw new UnprocessableEntityException('Query offerId is required.');
        }
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $travelDate)) {
            throw new UnprocessableEntityException('Query travelDate (YYYY-MM-DD) is required.');
        }

        $data = $this->baggageManager->buildManifest($offerId, $travelDate);

        return new AgencyManifestResource(
            id: sprintf('%s-%s', $data['offerId'], $data['travelDate']),
            offerId: $data['offerId'],
            travelDate: $data['travelDate'],
            tickets: $data['tickets'],
            boardedCount: $data['boardedCount'],
            issuedCount: $data['issuedCount'],
            noShowCount: $data['noShowCount'],
        );
    }
}
