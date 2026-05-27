<?php

namespace App\Service;

use App\Entity\Notification;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;

class NotificationService
{
    public function __construct(
        private EntityManagerInterface $entityManager
    ) {
    }

    /**
     * Crée une notification pour un utilisateur.
     */
    public function notify(
        User $user,
        string $type,
        string $message
    ): void {
        $notification = new Notification();

        $notification->setUser($user);
        $notification->setType($type);
        $notification->setMessage($message);
        $notification->setIsRead(false);

        $this->entityManager->persist($notification);
    }

    /**
     * Notification quand un ticket est assigné.
     */
    public function ticketAssigned(
        User $assignedUser,
        string $ticketTitle
    ): void {
        $this->notify(
            $assignedUser,
            'ticket_assigned',
            sprintf('Ticket assigned to you: "%s"', $ticketTitle)
        );
    }
}