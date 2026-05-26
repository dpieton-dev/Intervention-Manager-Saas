<?php

namespace App\Dto\Ticket;

use Symfony\Component\Validator\Constraints as Assert;

class UpdateTicketDto
{
    #[Assert\Length(
        min: 3,
        max: 255,
        minMessage: 'Title must be at least {{ limit }} characters long',
        maxMessage: 'Title cannot be longer than {{ limit }} characters'
    )]
    public ?string $title = null;

    #[Assert\Length(
        min: 10,
        minMessage: 'Description must be at least {{ limit }} characters long'
    )]
    public ?string $description = null;

    #[Assert\Choice(
        choices: ['todo', 'in_progress', 'testing', 'delivery_recette', 'done'],
        message: 'Invalid status'
    )]
    public ?string $status = null;

    #[Assert\Choice(
        choices: ['low', 'medium', 'high', 'urgent'],
        message: 'Invalid priority'
    )]
    public ?string $priority = null;
}