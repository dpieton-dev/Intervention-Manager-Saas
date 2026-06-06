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

    #[OA\Get(
        path: '/api/admin/stats/activity',
        summary: 'Admin daily activity statistics',
        description: 'Retrieve today activity counters for admin dashboard.',
        security: [['Bearer' => []]],
        tags: ['Admin Stats'],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Admin activity statistics retrieved successfully'
            ),
            new OA\Response(
                response: 403,
                description: 'Access denied'
            ),
        ]
    )]
    #[Route('/api/admin/stats/activity', name: 'api_admin_stats_activity', methods: ['GET'])]
    public function activity(
        EntityManagerInterface $entityManager,
        ApiResponseService $apiResponse
    ): JsonResponse {
        // Début de la journée actuelle
        $startOfDay = new \DateTimeImmutable('today');

        // Fin de la journée actuelle
        $endOfDay = new \DateTimeImmutable('tomorrow');

        return $apiResponse->success(
            [
                'today' => [
                    'usersCreated' => $this->countCreatedBetween(
                        $entityManager,
                        User::class,
                        $startOfDay,
                        $endOfDay
                    ),

                    'projectsCreated' => $this->countCreatedBetween(
                        $entityManager,
                        Project::class,
                        $startOfDay,
                        $endOfDay
                    ),

                    'ticketsCreated' => $this->countCreatedBetween(
                        $entityManager,
                        Ticket::class,
                        $startOfDay,
                        $endOfDay
                    ),

                    'commentsCreated' => $this->countCreatedBetween(
                        $entityManager,
                        TicketComment::class,
                        $startOfDay,
                        $endOfDay
                    ),

                    'attachmentsUploaded' => $this->countCreatedBetween(
                        $entityManager,
                        TicketAttachment::class,
                        $startOfDay,
                        $endOfDay
                    ),

                    'auditLogsCreated' => $this->countCreatedBetween(
                        $entityManager,
                        AuditLog::class,
                        $startOfDay,
                        $endOfDay
                    ),
                ],
            ],
            'Admin activity statistics retrieved successfully'
        );
    }

    private function countCreatedBetween(
        EntityManagerInterface $entityManager,
        string $entityClass,
        \DateTimeImmutable $start,
        \DateTimeImmutable $end
    ): int {
        return (int) $entityManager
            ->getRepository($entityClass)
            ->createQueryBuilder('e')
            ->select('COUNT(e.id)')
            ->andWhere('e.createdAt >= :start')
            ->andWhere('e.createdAt < :end')
            ->setParameter('start', $start)
            ->setParameter('end', $end)
            ->getQuery()
            ->getSingleScalarResult();
    }

    
}