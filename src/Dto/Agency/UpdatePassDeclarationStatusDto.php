<?php

namespace App\Dto\Agency;

use App\Entity\PassDeclaration;
use Symfony\Component\Validator\Constraints as Assert;

class UpdatePassDeclarationStatusDto
{
    public function __construct(
        #[Assert\NotBlank]
        #[Assert\Choice(callback: [PassDeclaration::class, 'getStatusesAsList'])]
        public ?string $status = null,
    ) {
    }
}
