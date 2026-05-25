<?php

namespace App\Service;

use App\Entity\Ticket;
use App\Entity\TicketActivity;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;

class TicketActivityService
{
    public function __construct(
        private EntityManagerInterface $entityManager
    ) {
    }

    /*
    |--------------------------------------------------------------------------
    | CREATE ACTIVITY
    |--------------------------------------------------------------------------
    */

    public function log(
        Ticket $ticket,
        User $user,
        string $action,
        string $description
    ): void {

        $activity = new TicketActivity();

        $activity->setTicket($ticket);

        $activity->setCreatedBy($user);

        $activity->setAction($action);

        $activity->setDescription($description);

        $this->entityManager->persist($activity);
    }

    /*
    |--------------------------------------------------------------------------
    | TICKET CREATED
    |--------------------------------------------------------------------------
    */

    public function logTicketCreated(
        Ticket $ticket,
        User $user
    ): void {

        $this->log(
            $ticket,
            $user,
            'ticket_created',
            sprintf(
                '%s created ticket "%s"',
                $user->getEmail(),
                $ticket->getTitle()
            )
        );
    }

    /*
    |--------------------------------------------------------------------------
    | STATUS CHANGED
    |--------------------------------------------------------------------------
    */

    public function logStatusChanged(
        Ticket $ticket,
        User $user,
        string $oldStatus,
        string $newStatus
    ): void {

        $this->log(
            $ticket,
            $user,
            'status_changed',
            sprintf(
                '%s changed status from "%s" to "%s"',
                $user->getEmail(),
                $oldStatus,
                $newStatus
            )
        );
    }

    /*
    |--------------------------------------------------------------------------
    | TICKET ASSIGNED
    |--------------------------------------------------------------------------
    */

    public function logAssigned(
        Ticket $ticket,
        User $user,
        User $assignedUser
    ): void {

        $this->log(
            $ticket,
            $user,
            'ticket_assigned',
            sprintf(
                '%s assigned ticket to %s',
                $user->getEmail(),
                $assignedUser->getEmail()
            )
        );
    }

    /*
    |--------------------------------------------------------------------------
    | COMMENT ADDED
    |--------------------------------------------------------------------------
    */

    public function logCommentAdded(
        Ticket $ticket,
        User $user
    ): void {

        $this->log(
            $ticket,
            $user,
            'comment_added',
            sprintf(
                '%s added a comment',
                $user->getEmail()
            )
        );
    }

    /*
    |--------------------------------------------------------------------------
    | COMMENT DELETED
    |--------------------------------------------------------------------------
    */

    public function logCommentDeleted(
        Ticket $ticket,
        User $user
    ): void {

        $this->log(
            $ticket,
            $user,
            'comment_deleted',
            sprintf(
                '%s deleted a comment',
                $user->getEmail()
            )
        );
    }
}