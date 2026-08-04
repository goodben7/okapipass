<?php

namespace App\Provider\Agency;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use App\ApiResource\OntPassTariffResource;
use App\Entity\GoPass;
use App\Repository\GoPassRepository;
use Symfony\Component\HttpFoundation\RequestStack;

/** @implements ProviderInterface<list<OntPassTariffResource>> */
final class OntPassTariffProvider implements ProviderInterface
{
    public function __construct(
        private GoPassRepository $goPasses,
        private RequestStack $requestStack,
    ) {
    }

    public function provide(Operation $operation, array $uriVariables = [], array $context = []): array
    {
        $code = $this->requestStack->getCurrentRequest()?->query->get('code');
        $qb = $this->goPasses->createQueryBuilder('g')->andWhere('g.active = true');
        if (\is_string($code) && '' !== $code) {
            $qb->andWhere('g.code = :code OR g.transportType = :code')
                ->setParameter('code', strtoupper($code));
        }
        $qb->orderBy('g.label', 'ASC');

        /** @var list<GoPass> $items */
        $items = $qb->getQuery()->getResult();
        $out = [];
        foreach ($items as $g) {
            $out[] = new OntPassTariffResource(
                code: (string) $g->getCode(),
                label: (string) $g->getLabel(),
                price: $g->getPrice() ?? 0,
                currency: (string) $g->getCurrency(),
                transportType: (string) $g->getTransportType(),
                active: (bool) $g->isActive(),
            );
        }

        return $out;
    }
}
