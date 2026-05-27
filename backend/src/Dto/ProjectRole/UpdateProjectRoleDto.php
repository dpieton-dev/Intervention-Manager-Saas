<?php

namespace App\Dto\ProjectRole;

use Symfony\Component\Validator\Constraints as Assert;

class UpdateProjectRoleDto
{
    #[Assert\Length(min: 3, max: 255)]
    public ?string $name = null;

    #[Assert\Length(min: 3, max: 100)]
    #[Assert\Regex(
        pattern: '/^[a-z0-9_]+$/',
        message: 'Role code must contain only lowercase letters, numbers and underscores'
    )]
    public ?string $code = null;

    #[Assert\Length(max: 1000)]
    public ?string $description = null;
}