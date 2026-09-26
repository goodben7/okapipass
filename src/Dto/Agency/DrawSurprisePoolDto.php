<?php

namespace App\Dto\Agency;

use Symfony\Component\Validator\Constraints as Assert;

final class DrawSurprisePoolDto
{
    public function __construct(
        #[Assert\Length(max: 16)]
        public ?string $userId = null,
    ) {
    }
}
