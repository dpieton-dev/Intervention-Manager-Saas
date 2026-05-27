<?php

namespace App\Controller\Api;

use App\Entity\Notification;
use App\Entity\User;
use App\Exception\ForbiddenException;
use App\Exception\UnauthorizedException;
use App\Service\ApiResponseService;
use Doctrine\ORM\EntityManagerInterface;
use OpenApi\Attributes as OA;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;

#[OA\Tag(name: 'Notifications')]
class NotificationController extends AbstractController
{
    /*
    |--------------------------------------------------------------------------
    | LIST NOTIFICATIONS
    |--------------------------------------------------------------------------
    */

    #[OA\Get(
        path: '/api/notifications',
        summary: 'List user notifications',
        description: 'Retrieve notifications for the authenticated user.',
        security: [['Bearer' => []]],
        tags: ['Notifications'],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Notifications retrieved successfully'
            ),
            new OA\Response(
                response: 401,
                description: 'User not authenticated'
            ),
        ]
    )]
    #[Route(
        '/api/notifications',
        name: 'api_notifications',
        methods: ['GET']
    )]
    public function index(
        ApiResponseService $apiResponse
    ): JsonResponse {

        /** @var User|null $currentUser */
        $currentUser = $this->getUser();

        if (!$currentUser instanceof User) {
            throw new UnauthorizedException();
        }

        $notifications = [];

        foreach (
            $currentUser->getNotifications()
            as $notification
        ) {

            $notifications[] = $this->formatNotification(
                $notification
            );
        }

        return $apiResponse->success(
            $notifications,
            'Notifications retrieved successfully'
        );
    }

    /*
    |--------------------------------------------------------------------------
    | MARK AS READ
    |--------------------------------------------------------------------------
    */

    #[OA\Patch(
        path: '/api/notifications/{id}/read',
        summary: 'Mark notification as read',
        description: 'Mark a notification as read.',
        security: [['Bearer' => []]],
        tags: ['Notifications'],
        parameters: [
            new OA\Parameter(
                name: 'id',
                in: 'path',
                required: true,
                schema: new OA\Schema(
                    type: 'integer',
                    example: 1
                )
            ),
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Notification marked as read'
            ),
            new OA\Response(
                response: 401,
                description: 'User not authenticated'
            ),
            new OA\Response(
                response: 403,
                description: 'Access denied'
            ),
        ]
    )]
    #[Route(
        '/api/notifications/{id}/read',
        name: 'api_notification_read',
        methods: ['PATCH']
    )]
    public function markAsRead(
        Notification $notification,
        EntityManagerInterface $entityManager,
        ApiResponseService $apiResponse
    ): JsonResponse {

        /** @var User|null $currentUser */
        $currentUser = $this->getUser();

        if (!$currentUser instanceof User) {
            throw new UnauthorizedException();
        }

        // Vérifie propriétaire notification
        if (
            $notification->getUser()?->getId()
            !== $currentUser->getId()
        ) {

            throw new ForbiddenException(
                'Access denied'
            );
        }

        $notification->setIsRead(true);

        $entityManager->flush();

        return $apiResponse->success(
            $this->formatNotification(
                $notification
            ),
            'Notification marked as read'
        );
    }

    /*
    |--------------------------------------------------------------------------
    | FORMAT NOTIFICATION
    |--------------------------------------------------------------------------
    */

    private function formatNotification(
        Notification $notification
    ): array {

        return [
            'id' => $notification->getId(),

            'type' => $notification->getType(),

            'message' => $notification->getMessage(),

            'isRead' => $notification->isRead(),

            'createdAt' => $notification
                ->getCreatedAt()
                ?->format('Y-m-d H:i:s'),
        ];
    }
}