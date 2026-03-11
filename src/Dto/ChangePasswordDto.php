<?php

namespace App\Dto;

use Symfony\Component\Validator\Constraints as Assert;

class ChangePasswordDto
{
    #[Assert\NotNull()]
    #[Assert\NotBlank()]
    public string $actualPassword;

    #[Assert\NotNull()]
    #[Assert\NotBlank()]
    public string $newPassword;
}