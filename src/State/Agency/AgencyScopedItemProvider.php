<?php

namespace App\State\Agency;

use ApiPlatform\Doctrine\Orm\State\ItemProvider;
use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use App\Domain\Agency\AgencyScopedInterface;
use App\Exception\UnavailableDataException;
use App\Service\Agency\AgencyContext;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * Loads an agency-scoped item and returns 404 if it belongs to another tenant.
 *
 * @implements ProviderInterface<object|null>
 */
final class AgencyScopedItemProvider implements ProviderInterface
{
    public function __construct(
        #[Autowire(service: ItemProvider::class)]
        private ProviderInterface $itemProvider,
        private AgencyContext $agencyContext,
    ) {
    }

    public function provide(Operation $operation, array $uriVariables = [], array $context = []): object|null
    {
        $item = $this->itemProvider->provide($operation, $uriVariables, $context);

        if (null === $item) {
            return null;
        }

        if ($item instanceof AgencyScopedInterface) {
            $agency = $item->getAgency();
            if (null === $agency) {
                throw new UnavailableDataException('Resource not found.');
            }
            $this->agencyContext->assertOwns($agency);
        }

        return $item;
    }
}
