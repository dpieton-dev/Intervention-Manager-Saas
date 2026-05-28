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
    public function notify(User $user, string $type, string $message, string $level = 'info'): void 
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

                'level' => $level,

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
            sprintf('Ticket assigned to you: "%s"', $ticketTitle),
            'success'
        );
    }

    public function commentAdded(
        User $user,
        string $ticketTitle
    ): void {
        $this->notify(
            $user,
            'comment_added',
            sprintf('New comment on ticket: "%s"', $ticketTitle),
            'info'
        );
    }

    public function statusChanged(
        User $user,
        string $ticketTitle,
        string $oldStatus,
        string $newStatus
    ): void {
        $this->notify(
            $user,
            'ticket_status_changed',
            sprintf('Ticket "%s" moved from %s to %s', $ticketTitle, $oldStatus, $newStatus),
            'info'
        );
    }

    public function attachmentUploaded(
        User $user,
        string $ticketTitle
    ): void {
        $this->notify(
            $user,
            'attachment_uploaded',
            sprintf('New attachment added on ticket: "%s"', $ticketTitle),
            'success'
        );
    }

    public function warning(User $user, string $message): void 
    {
        $this->notify($user, 'warning', $message, 'warning');
    }

    public function error(User $user, string $message): void 
    {
        $this->notify($user, 'error', $message, 'error');
    }
}