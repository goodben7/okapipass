<?php

namespace App\Domain\Agency;

use App\Entity\GoPass;
use App\Exception\UnavailableDataException;
use App\Repository\GoPassRepository;

/**
 * Pass / FPT pricing (spec §5.2).
 */
final class AgencyPricingService
{
    public function __construct(
        private GoPassRepository $goPasses,
        private PassValidationService $passValidation,
    ) {
    }

    /**
     * @return array{hasExistingPass: bool, passPrice: int, currency: string, tariff: ?GoPass}
     */
    public function quote(?string $okapiPassRef): array
    {
        $tariff = $this->resolveRoutierTariff();
        $ref = null !== $okapiPassRef ? strtoupper(trim($okapiPassRef)) : '';

        if ('' === $ref) {
            return [
                'hasExistingPass' => false,
                'passPrice' => (int) round((float) $tariff->getPrice()),
                'currency' => $tariff->getCurrency() ?? 'CDF',
                'tariff' => $tariff,
            ];
        }

        $validation = $this->passValidation->validate($ref, throwOnInvalid: $this->passValidation->isStrict());
        $hasExistingPass = $validation['valid'] || (!$this->passValidation->isStrict() && '' !== $ref);

        // Soft mode: unknown ref still treated as existing Pass (mock-compatible).
        if (!$this->passValidation->isStrict() && !$validation['valid']) {
            $hasExistingPass = true;
        }

        $passPrice = $hasExistingPass ? 0 : (int) round((float) $tariff->getPrice());

        return [
            'hasExistingPass' => $hasExistingPass,
            'passPrice' => $passPrice,
            'currency' => $tariff->getCurrency() ?? 'CDF',
            'tariff' => $tariff,
        ];
    }

    public function resolveRoutierTariff(): GoPass
    {
        $goPass = $this->goPasses->findOneBy(['code' => 'ROUTIER', 'active' => true])
            ?? ($this->goPasses->findActiveRoutier(1)[0] ?? null);

        if (!$goPass instanceof GoPass) {
            throw new UnavailableDataException('ONT Pass tariff ROUTIER is not configured.');
        }

        return $goPass;
    }
}
