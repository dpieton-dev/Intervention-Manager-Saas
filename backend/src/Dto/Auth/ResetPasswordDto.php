<?php

namespace App\Dto\Auth;

use Symfony\Component\Validator\Constraints as Assert;

class ResetPasswordDto
{
    #[Assert\NotBlank]
    public ?string $token = null;

    #[Assert\NotBlank]
    #[Assert\Length(min: 6)]
    public ?string $password = null;
}