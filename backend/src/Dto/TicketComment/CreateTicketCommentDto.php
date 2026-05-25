<?php

namespace App\Dto\TicketComment;

use Symfony\Component\Validator\Constraints as Assert;

class CreateTicketComment
{
    // Contenu du commentaire
    #[Assert\NotBlank(message: 'Comment content is required')]
    #[Assert\Length(
        min: 2,
        max: 5000,
        minMessage: 'Comment must be at least {{ limit }} characters long',
        maxMessage: 'Comment cannot be longer than {{ limit }} characters'
    )]
    public ?string $content = null;
}