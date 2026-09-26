<?php

namespace App\Dto\Agency;

use Symfony\Component\Validator\Constraints as Assert;

final class ResolveCashHandoverDto
{
    public function __construct(
        #[Assert\NotBlank]
        #[Assert\Length(max: 500)]
        public ?string $resolutionJustification = null,
    ) {
    }
}
