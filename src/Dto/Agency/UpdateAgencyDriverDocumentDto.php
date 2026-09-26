<?php

namespace App\Dto\Agency;

use App\Entity\AgencyDriverDocument;
use Symfony\Component\Validator\Constraints as Assert;

final class UpdateAgencyDriverDocumentDto
{
    public function __construct(
        #[Assert\Choice(callback: [AgencyDriverDocument::class, 'getTypesAsList'])]
        public ?string $type = null,

        #[Assert\Length(max: 160)]
        public ?string $label = null,

        #[Assert\Regex(pattern: '/^\d{4}-\d{2}-\d{2}$/')]
        public ?string $issuedAt = null,

        #[Assert\Regex(pattern: '/^\d{4}-\d{2}-\d{2}$/')]
        public ?string $expiresAt = null,

        #[Assert\Length(max: 512)]
        public ?string $fileUrl = null,

        public ?string $notes = null,
    ) {
    }
}
