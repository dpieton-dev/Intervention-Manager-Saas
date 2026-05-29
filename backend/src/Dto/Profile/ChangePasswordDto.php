<?php

namespace App\Dto\Profile;

use Symfony\Component\Validator\Constraints as Assert;

class ChangePasswordDto
{
    #[Assert\NotBlank(message: 'Current password is required')]
    public ?string $currentPassword = null;

    #[Assert\NotBlank(message: 'New password is required')]
    #[Assert\Length(
        min: 6,
        minMessage: 'New password must be at least {{ limit }} characters long'
    )]
    public ?string $newPassword = null;
}