<?php

namespace App\Dto\User;

use Symfony\Component\Validator\Constraints as Assert;

class UpdateUserDto
{
    #[Assert\Email]
    public ?string $email = null;

    public ?string $role = null;
}