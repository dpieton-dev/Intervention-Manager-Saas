<?php

namespace App\Dto\Profile;

use Symfony\Component\Validator\Constraints as Assert;

class UpdateProfileDto
{
    #[Assert\NotBlank(message: 'Email is required')]
    #[Assert\Email(message: 'Invalid email')]
    public ?string $email = null;
}