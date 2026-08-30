<?php

namespace App\Provider\PublicAgency;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\Pagination\Pagination;
use ApiPlatform\State\Pagination\TraversablePaginator;
use ApiPlatform\State\ProviderInterface;
use App\ApiResource\Public\PublicAgencyOfferResource;
use App\Domain\PublicAgency\PublicAgencyOfferMapper;
use App\Repository\AgencyOfferRepository;
use Symfony\Component\HttpFoundation\RequestStack;

/** @implements ProviderInterface<TraversablePaginator<PublicAgencyOfferResource>> */
final class PublicAgencyOfferCollectionProvider implements ProviderInterface
{
    public function __construct(
        private AgencyOfferRepository $offers,
        private PublicAgencyOfferMapper $mapper,
        private Pagination $pagination,
        private RequestStack $requestStack,
    ) {
    }

    public function provide(Operation $operation, array $uriVariables = [], array $context = []): TraversablePaginator
    {
        [$page, , $limit] = $this->pagination->getPagination($operation, $context);
        $offset = ($page - 1) * $limit;

        $request = $this->requestStack->getCurrentRequest();
        $origin = $this->queryString($request?->query->get('origin'));
        $destination = $this->queryString($request?->query->get('destination'));
        $agencyId = $this->queryString($request?->query->get('agencyId'));

        $items = $this->offers->findPublicOnlinePage($origin, $destination, $agencyId, $offset, $limit);
        $total = $this->offers->countPublicOnline($origin, $destination, $agencyId);

        $resources = array_map(
            fn ($offer) => $this->mapper->fromEntity($offer),
            $items,
        );

        return new TraversablePaginator(
            new \ArrayIterator($resources),
            $page,
            $limit,
            $total,
        );
    }

    private function queryString(mixed $value): ?string
    {
        if (!\is_string($value)) {
            return null;
        }

        $trimmed = trim($value);

        return '' === $trimmed ? null : $trimmed;
    }
}
