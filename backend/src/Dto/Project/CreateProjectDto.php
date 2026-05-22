<?php

namespace App\Dto\Project;

use Symfony\Component\Validator\Constraints as Assert;

class CreateProjectDto
{
    #[Assert\NotBlank(message: 'Project name is required')]
    #[Assert\Length(min: 3, max: 255)]
    public ?string $name = null;

    #[Assert\Length(min: 10)]
    public ?string $description = null;

    #[Assert\NotBlank(message: 'Status is required')]
    #[Assert\Choice(choices: ['active', 'on_hold', 'completed', 'archived'])]
    public ?string $status = 'active';

    #[Assert\NotBlank(message: 'Start date is required')]
    public ?string $startDate = null;

    public ?string $endDate = null;
}