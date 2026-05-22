<?php

namespace App\Dto\Ticket;

use Symfony\Component\Validator\Constraints as Assert;

class CreateTicketDto
{
    #[Assert\NotBlank(message: 'Title is required')]
    #[Assert\Length(
        min: 3,
        max: 255
    )]
    public ?string $title = null;

    #[Assert\NotBlank(message: 'Description is required')]
    #[Assert\Length(
        min: 10
    )]
    public ?string $description = null;

    #[Assert\NotBlank(message: 'Priority is required')]
    #[Assert\Choice(
        choices: ['low', 'medium', 'high', 'urgent'],
        message: 'Invalid priority'
    )]
    public ?string $priority = null;

    #[Assert\NotBlank(message: 'projectId is required')]
    public ?int $projectId = null;
}