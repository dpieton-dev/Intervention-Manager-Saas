<?php

namespace App\Controller\Api;

use App\Entity\AuditLog;
use App\Entity\Project;
use App\Entity\Ticket;
use App\Entity\TicketAttachment;
use App\Entity\TicketComment;
use App\Entity\User;
use App\Service\ApiResponseService;
use Doctrine\ORM\EntityManagerInterface;
use OpenApi\Attributes as OA;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[OA\Tag(name: 'Admin Stats')]
#[IsGranted('ROLE_ADMIN')]
class AdminStatsController extends AbstractController
{
    /*
    |--------------------------------------------------------------------------
    | GLOBAL ADMIN STATS
    |--------------------------------------------------------------------------
    |
    | Endpoint réservé aux administrateurs.
    | Il retourne les principaux compteurs du SaaS.
    |
    */

    #[OA\Get(
        path: '/api/admin/stats',
        summary: 'Admin global statistics',
        description: 'Retrieve global SaaS statistics for admin dashboard.',
        security: [['Bearer' => []]],
        tags: ['Admin Stats'],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Admin statistics retrieved successfully'
            ),
            new OA\Response(
                response: 403,
                description: 'Access denied'
            ),
        ]
    )]
    #[Route('/api/admin/stats', name: 'api_admin_stats', methods: ['GET'])]
    public function index(
        EntityManagerInterface $entityManager,
        ApiResponseService $apiResponse
    ): JsonResponse {
        $userRepository = $entityManager->getRepository(User::class);
        $projectRepository = $entityManager->getRepository(Project::class);
        $ticketRepository = $entityManager->getRepository(Ticket::class);
        $commentRepository = $entityManager->getRepository(TicketComment::class);
        $attachmentRepository = $entityManager->getRepository(TicketAttachment::class);
        $auditLogRepository = $entityManager->getRepository(AuditLog::class);

        return $apiResponse->success(
            [
                'users' => [
                    'total' => $userRepository->count([]),
                    'active' => $userRepository->count([
                        'isActive' => true,
                        'deletedAt' => null,
                    ]),
                    'inactive' => $userRepository->count([
                        'isActive' => false,
                        'deletedAt' => null,
                    ]),
                    'deleted' => $userRepository->countDeletedUsers(),
                ],

                'projects' => [
                    'total' => $projectRepository->count([]),
                    'active' => $projectRepository->count([
                        'deletedAt' => null,
                    ]),
                    'deleted' => $projectRepository->countDeletedProjects(),
                ],

                'tickets' => [
                    'total' => $ticketRepository->count([]),
                    'active' => $ticketRepository->count([
                        'deleteAt' => null,
                    ]),
                    'deleted' => $ticketRepository->countDeletedTickets(),
                ],

                'collaboration' => [
                    'comments' => $commentRepository->count([]),
                    'attachments' => $attachmentRepository->count([]),
                ],

                'audit' => [
                    'logs' => $auditLogRepository->count([]),
                ],
            ],
            'Admin statistics retrieved successfully'
        );
    }
}