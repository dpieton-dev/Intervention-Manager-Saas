<?php

namespace App\Controller\Api;

use App\Entity\AuditLog;
use App\Entity\User;
use App\Exception\UnauthorizedException;
use App\Repository\AuditLogRepository;
use App\Service\ApiResponseService;
use OpenApi\Attributes as OA;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[OA\Tag(name: 'Audit Logs')]
#[IsGranted('ROLE_ADMIN')]
class AuditLogController extends AbstractController
{
    /*
    |--------------------------------------------------------------------------
    | LIST AUDIT LOGS
    |--------------------------------------------------------------------------
    */

    #[OA\Get(
        path: '/api/audit-logs',
        summary: 'List audit logs',
        description: 'Retrieve admin audit logs.',
        security: [['Bearer' => []]],
        tags: ['Audit Logs'],
        parameters: [
            new OA\Parameter(
                name: 'page',
                in: 'query',
                required: false,
                schema: new OA\Schema(type: 'integer', example: 1)
            ),
            new OA\Parameter(
                name: 'limit',
                in: 'query',
                required: false,
                schema: new OA\Schema(type: 'integer', example: 20)
            ),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Audit logs retrieved successfully'),
            new OA\Response(response: 401, description: 'User not authenticated'),
            new OA\Response(response: 403, description: 'Access denied'),
        ]
    )]
    #[Route('/api/audit-logs', name: 'api_audit_logs', methods: ['GET'])]
    public function index(
        Request $request,
        AuditLogRepository $auditLogRepository,
        ApiResponseService $apiResponse
    ): JsonResponse {
        /** @var User|null $currentUser */
        $currentUser = $this->getUser();

        if (!$currentUser instanceof User) {
            throw new UnauthorizedException();
        }

        // Pagination simple
        $page = max(1, (int) $request->query->get('page', 1));
        $limit = max(1, (int) $request->query->get('limit', 20));

        $queryBuilder = $auditLogRepository->createQueryBuilder('a')
            ->leftJoin('a.createdBy', 'u')
            ->orderBy('a.createdAt', 'DESC');

        $total = count(
            (clone $queryBuilder)
                ->getQuery()
                ->getResult()
        );

        $logs = $queryBuilder
            ->setFirstResult(($page - 1) * $limit)
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();

        $data = [];

        foreach ($logs as $log) {
            $data[] = $this->formatAuditLog($log);
        }

        return $apiResponse->success(
            [
                'logs' => $data,
                'pagination' => [
                    'page' => $page,
                    'limit' => $limit,
                    'total' => $total,
                ],
            ],
            'Audit logs retrieved successfully'
        );
    }

    /*
    |--------------------------------------------------------------------------
    | SHOW AUDIT LOG
    |--------------------------------------------------------------------------
    */

    #[OA\Get(
        path: '/api/audit-logs/{id}',
        summary: 'Show audit log',
        description: 'Retrieve one audit log.',
        security: [['Bearer' => []]],
        tags: ['Audit Logs'],
        parameters: [
            new OA\Parameter(
                name: 'id',
                in: 'path',
                required: true,
                schema: new OA\Schema(type: 'integer', example: 1)
            ),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Audit log retrieved successfully'),
            new OA\Response(response: 401, description: 'User not authenticated'),
            new OA\Response(response: 403, description: 'Access denied'),
            new OA\Response(response: 404, description: 'Audit log not found'),
        ]
    )]
    #[Route('/api/audit-logs/{id}', name: 'api_audit_log_show', methods: ['GET'])]
    public function show(
        AuditLog $auditLog,
        ApiResponseService $apiResponse
    ): JsonResponse {
        return $apiResponse->success(
            $this->formatAuditLog($auditLog),
            'Audit log retrieved successfully'
        );
    }

    /*
    |--------------------------------------------------------------------------
    | FORMAT AUDIT LOG
    |--------------------------------------------------------------------------
    */

    private function formatAuditLog(AuditLog $auditLog): array
    {
        return [
            'id' => $auditLog->getId(),
            'action' => $auditLog->getAction(),
            'targetType' => $auditLog->getTargetType(),
            'targetId' => $auditLog->getTargetId(),
            'message' => $auditLog->getMessage(),
            'ipAddress' => $auditLog->getIpAddress(),
            'userAgent' => $auditLog->getUserAgent(),
            'createdAt' => $auditLog->getCreatedAt()?->format('Y-m-d H:i:s'),

            'createdBy' => $auditLog->getCreatedBy() ? [
                'id' => $auditLog->getCreatedBy()->getId(),
                'email' => $auditLog->getCreatedBy()->getEmail(),
            ] : null,
        ];
    }
}