<?php

namespace App\Domain\Agency;

use App\Entity\IssuedOkapiPass;
use App\Exception\UnprocessableEntityException;
use App\Repository\IssuedOkapiPassRepository;

/**
 * Validates OP-… Pass references (spec §6.10).
 */
final class PassValidationService
{
    public function __construct(
        private IssuedOkapiPassRepository $passes,
        private bool $strict = false,
    ) {
    }

    /**
     * @return array{
     *     valid: bool,
     *     ref: string,
     *     holder: string|null,
     *     status: string,
     *     expiresAt: string|null,
     *     warning: string|null
     * }
     */
    public function validate(string $ref, bool $throwOnInvalid = false): array
    {
        $ref = strtoupper(trim($ref));
        if ('' === $ref) {
            throw new UnprocessableEntityException('Pass reference is required.');
        }

        $pass = $this->passes->findOneByReference($ref);
        if (null === $pass) {
            if ($this->strict || $throwOnInvalid) {
                throw new UnprocessableEntityException(sprintf('Pass "%s" is invalid or unknown.', $ref));
            }

            return [
                'valid' => false,
                'ref' => $ref,
                'holder' => null,
                'status' => 'UNKNOWN',
                'expiresAt' => null,
                'warning' => 'Pass reference not found in ONT registry (soft mode).',
            ];
        }

        $valid = $pass->isCurrentlyValid();
        if (!$valid && ($this->strict || $throwOnInvalid)) {
            throw new UnprocessableEntityException(sprintf('Pass "%s" is not ACTIVE.', $ref));
        }

        return [
            'valid' => $valid,
            'ref' => $ref,
            'holder' => $pass->getHolderName(),
            'status' => $valid ? IssuedOkapiPass::STATUS_ACTIVE : $pass->getStatus(),
            'expiresAt' => $pass->getExpiresAt()?->format(\DateTimeInterface::ATOM),
            'warning' => $valid ? null : 'Pass is not currently valid.',
        ];
    }

    public function isStrict(): bool
    {
        return $this->strict;
    }
}
