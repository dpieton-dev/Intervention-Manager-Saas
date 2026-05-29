<?php

namespace App\Dto\Auth;

use Symfony\Component\Validator\Constraints as Assert;

class ForgotPasswordDto
{
    #[Assert\NotBlank]
    #[Assert\Email]
    public ?string $email = null;
}