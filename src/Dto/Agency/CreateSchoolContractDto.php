<?php

namespace App\Dto\Agency;

use App\Entity\Agency;
use App\Entity\SchoolContract;
use Symfony\Component\Validator\Constraints as Assert;

final class CreateSchoolContractDto
{
    public function __construct(
        #[Assert\NotBlank]
        #[Assert\Length(max: 160)]
        public ?string $schoolName = null,

        #[Assert\Length(max: 20)]
        public ?string $schoolPhone = null,

        #[Assert\Length(max: 255)]
        public ?string $schoolAddress = null,

        #[Assert\NotBlank]
        public ?string $offer = null,

        public ?string $transport = null,

        #[Assert\NotBlank]
        #[Assert\Regex(pattern: '/^\d{4}-\d{2}-\d{2}$/')]
        public ?string $startDate = null,

        #[Assert\NotBlank]
        #[Assert\Regex(pattern: '/^\d{4}-\d{2}-\d{2}$/')]
        public ?string $endDate = null,

        #[Assert\Choice(callback: [SchoolContract::class, 'getStatusesAsList'])]
        public ?string $status = SchoolContract::STATUS_DRAFT,

        #[Assert\PositiveOrZero]
        public ?int $monthlyFee = 0,

        #[Assert\Currency]
        public ?string $currency = Agency::DEFAULT_CURRENCY,

        /** @var list<array{code?: string, label?: string, order?: int, time?: string}>|null */
        public ?array $stops = null,

        public ?string $notes = null,
    ) {
    }
}
