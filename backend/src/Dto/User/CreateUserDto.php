<?php

namespace App\Dto\User;

use Symfony\Component\Validator\Constraints as Assert;

class CreateUserDto
{
    #[Assert\NotBlank]
    #[Assert\Email]
    public ?string $email = null;

    #[Assert\NotBlank]
    #[Assert\Length(min: 6)]
    public ?string $password = null;

    #[Assert\NotBlank]
    public ?string $role = 'ROLE_USER';

    public ?bool $isActive = true;
}