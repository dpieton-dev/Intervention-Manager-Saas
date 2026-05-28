<?php

namespace App\Service;

use App\Entity\Notification;
use App\Entity\User;
use App\Service\RealtimeService;
use Doctrine\ORM\EntityManagerInterface;

class NotificationService
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private RealtimeService $realtimeService
    ) {
    }

    /**
     * Crée une notification pour un utilisateur.
     */
    public function notify(User $user, string $type, string $message): void 
    {
        $notification = new Notification();

        $notification->setUser($user);
        $notification->setType($type);
        $notification->setMessage($message);
        $notification->setIsRead(false);

        $this->entityManager->persist($notification);

        $unreadCount = 0;

        foreach (
            $user->getNotifications()
            as $existingNotification
        ) {

            if (!$existingNotification->isRead()) {
                $unreadCount++;
            }
        }

        $unreadCount++;

        $this->realtimeService->notification(
            $user->getId(),
            [
                'type' => $type,

                'message' => $message,

                'createdAt' => $notification
                    ->getCreatedAt()
                    ?->format('Y-m-d H:i:s'),

                'unreadCount' => $unreadCount,
            ]
        );
    }

    /**
     * Notification quand un ticket est assigné.
     */
    public function ticketAssigned(User $assignedUser,string $ticketTitle): void 
    {
        $this->notify(
            $assignedUser,
            'ticket_assigned',
            sprintf('Ticket assigned to you: "%s"', $ticketTitle)
        );
    }
}