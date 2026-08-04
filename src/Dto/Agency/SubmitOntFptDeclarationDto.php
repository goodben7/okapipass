<?php

namespace App\Dto\Agency;

use Symfony\Component\Validator\Constraints as Assert;

class SubmitOntFptDeclarationDto
{
    public function __construct(
        /** IRI or id of PassDeclaration */
        #[Assert\NotBlank]
        public ?string $declaration = null,
    ) {
    }
}
