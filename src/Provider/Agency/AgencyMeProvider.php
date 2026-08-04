<?php

namespace App\Provider\Agency;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use App\ApiResource\AgencyMeResource;
use App\Entity\GoPass;
use App\Repository\GoPassRepository;
use App\Service\Agency\AgencyContext;

/** @implements ProviderInterface<AgencyMeResource> */
final class AgencyMeProvider implements ProviderInterface
{
    public function __construct(
        private AgencyContext $agencyContext,
        private GoPassRepository $goPasses,
    ) {
    }

    public function provide(Operation $operation, array $uriVariables = [], array $context = []): AgencyMeResource
    {
        $agency = $this->agencyContext->requireAgency();
        $ontPass = $this->resolveOntPass();

        return new AgencyMeResource(
            id: 'me',
            agency: [
                'id' => $agency->getId(),
                'name' => $agency->getName(),
                'email' => $agency->getEmail(),
                'phone' => $agency->getPhone(),
                'address' => $agency->getAddress(),
                'licenseNumber' => $agency->getLicenseNumber(),
                'defaultCurrency' => $agency->getDefaultCurrency(),
                'supportedCurrencies' => $agency->getSupportedCurrencies(),
                'status' => $agency->getStatus(),
            ],
            ontPass: $ontPass,
            permissions: $this->agencyContext->defaultPermissions(),
            staffRole: $this->agencyContext->resolveStaffRole(),
        );
    }

    /**
     * @return array<string, mixed>|null
     */
    private function resolveOntPass(): ?array
    {
        $goPass = $this->goPasses->findOneBy(['code' => 'ROUTIER', 'active' => true])
            ?? ($this->goPasses->findActiveRoutier(1)[0] ?? null);

        if (!$goPass instanceof GoPass) {
            return null;
        }

        return [
            'code' => $goPass->getCode(),
            'label' => $goPass->getLabel(),
            'price' => $goPass->getPrice(),
            'currency' => $goPass->getCurrency(),
        ];
    }
}
