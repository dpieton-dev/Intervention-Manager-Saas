<?php

namespace App\Controller\Api;

use App\Dto\Ticket\CreateTicketDto;
use App\Dto\Ticket\UpdateTicketDto;
use App\Entity\Ticket;
use App\Entity\User;
use App\Exception\ForbiddenException;
use App\Exception\NotFoundException;
use App\Exception\UnauthorizedException;
use App\Exception\ValidationException;
use App\Repository\ProjectRepository;
use App\Repository\TicketRepository;
use App\Repository\UserRepository;
use App\Service\ApiResponseService;
use App\Service\ProjectSecurityService;
use App\Service\TicketActivityService;
use App\Service\NotificationService;
use App\Service\RealtimeService;
use Doctrine\ORM\EntityManagerInterface;
use OpenApi\Attributes as OA;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Serializer\SerializerInterface;
use Symfony\Component\Validator\Validator\ValidatorInterface;

#[OA\Tag(name: 'Tickets')]
class TicketController extends AbstractController
{
    #[OA\Get(
        path: '/api/tickets',
        summary: 'List tickets',
        description: 'Retrieve paginated tickets with optional filters.',
        security: [['Bearer' => []]],
        tags: ['Tickets'],
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
                schema: new OA\Schema(type: 'integer', example: 10)
            ),
            new OA\Parameter(
                name: 'status',
                in: 'query',
                required: false,
                schema: new OA\Schema(type: 'string', example: 'todo')
            ),
            new OA\Parameter(
                name: 'priority',
                in: 'query',
                required: false,
                schema: new OA\Schema(type: 'string', example: 'high')
            ),
            new OA\Parameter(
                name: 'search',
                in: 'query',
                required: false,
                schema: new OA\Schema(type: 'string', example: 'JWT')
            ),
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Tickets retrieved successfully'
            ),
            new OA\Response(
                response: 401,
                description: 'JWT token missing or invalid'
            ),
        ]
    )]
    #[Route('/api/tickets', name: 'api_tickets', methods: ['GET'])]
    public function index(
        Request $request,
        TicketRepository $ticketRepository,
        ApiResponseService $apiResponse
    ): JsonResponse {
        // Pagination
        $page = max(1, (int) $request->query->get('page', 1));
        $limit = max(1, (int) $request->query->get('limit', 10));

        // Filtres disponibles
        $filters = [
            'status' => $request->query->get('status'),
            'priority' => $request->query->get('priority'),
            'project' => $request->query->get('project'),
            'assignedTo' => $request->query->get('assignedTo'),
            'createdBy' => $request->query->get('createdBy'),
            'search' => $request->query->get('search'),
        ];

        $result = $ticketRepository->findFilteredTickets($filters, $page, $limit);

        $tickets = [];

        foreach ($result['data'] as $ticket) {
            $tickets[] = $this->formatTicket($ticket);
        }

        return $apiResponse->success([
            'tickets' => $tickets,
            'pagination' => $result['pagination'],
            'filters' => $filters,
        ], 'Tickets retrieved successfully');
    }

    #[OA\Get(
        path: '/api/tickets/{id}',
        summary: 'Show ticket',
        description: 'Retrieve one ticket by id.',
        security: [['Bearer' => []]],
        tags: ['Tickets'],
        parameters: [
            new OA\Parameter(
                name: 'id',
                in: 'path',
                required: true,
                schema: new OA\Schema(type: 'integer', example: 1)
            ),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Ticket retrieved successfully'),
            new OA\Response(response: 401, description: 'JWT token missing or invalid'),
            new OA\Response(response: 404, description: 'Ticket not found'),
        ]
    )]
    #[Route('/api/tickets/{id}', name: 'api_ticket_show', methods: ['GET'])]
    public function show(
        Ticket $ticket,
        ApiResponseService $apiResponse
    ): JsonResponse {

        $this->denyDeletedTicket($ticket);

        if ($ticket->getDeleteAt() !== null) {
            throw $this->createNotFoundException(
                'Ticket not found'
            );
        }
        return $apiResponse->success(
            $this->formatTicket($ticket),
            'Ticket retrieved successfully'
        );
    }

    #[OA\Post(
        path: '/api/tickets',
        summary: 'Create ticket',
        description: 'Create a new ticket in a project.',
        security: [['Bearer' => []]],
        tags: ['Tickets'],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['title', 'description', 'priority', 'projectId'],
                properties: [
                    new OA\Property(property: 'title', type: 'string', example: 'Bug login'),
                    new OA\Property(property: 'description', type: 'string', example: 'Erreur lors de la connexion utilisateur'),
                    new OA\Property(property: 'priority', type: 'string', example: 'high'),
                    new OA\Property(property: 'projectId', type: 'integer', example: 1),
                ]
            )
        ),
        responses: [
            new OA\Response(response: 201, description: 'Ticket created successfully'),
            new OA\Response(response: 401, description: 'User not authenticated'),
            new OA\Response(response: 403, description: 'Access denied to this project'),
            new OA\Response(response: 404, description: 'Project not found'),
            new OA\Response(response: 422, description: 'Validation failed'),
        ]
    )]
    #[Route('/api/tickets', name: 'api_ticket_create', methods: ['POST'])]
    public function create(
        Request $request,
        EntityManagerInterface $entityManager,
        ProjectRepository $projectRepository,
        ProjectSecurityService $projectSecurityService,
        TicketActivityService $ticketActivityService,
        ApiResponseService $apiResponse,
        ValidatorInterface $validator,
        SerializerInterface $serializer
    ): JsonResponse {
        /** @var User|null $user */
        $user = $this->getUser();

        if (!$user instanceof User) {
            throw new UnauthorizedException();
        }

        // Transforme le JSON en DTO
        $dto = $serializer->deserialize(
            $request->getContent(),
            CreateTicketDto::class,
            'json'
        );

        // Validation du DTO
        $errors = $validator->validate($dto);

        if (count($errors) > 0) {
            throw new ValidationException(
                $apiResponse->formatValidationErrors($errors)
            );
        }

        // Récupération du projet
        $project = $projectRepository->find($dto->projectId);

        if (!$project) {
            throw new NotFoundException('Project not found');
        }

        // Sécurité : seul un membre du projet peut créer un ticket
        if (!$projectSecurityService->isProjectMember($project, $user)) {
            throw new ForbiddenException('Access denied to this project');
        }

        // Création du ticket
        $ticket = new Ticket();
        $ticket->setTitle($dto->title);
        $ticket->setDescription($dto->description);
        $ticket->setPriority($dto->priority);
        $ticket->setStatus('todo');
        $ticket->setCreatedBy($user);
        $ticket->setProject($project);

        $entityManager->persist($ticket);

        // Historique : création du ticket
        $ticketActivityService->logTicketCreated($ticket, $user);

        $entityManager->flush();

        return $apiResponse->success(
            $this->formatTicket($ticket),
            'Ticket created successfully',
            201
        );
    }

    #[OA\Put(
        path: '/api/tickets/{id}',
        summary: 'Update ticket',
        description: 'Update ticket fields.',
        security: [['Bearer' => []]],
        tags: ['Tickets'],
        parameters: [
            new OA\Parameter(
                name: 'id',
                in: 'path',
                required: true,
                schema: new OA\Schema(type: 'integer', example: 1)
            ),
        ],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                properties: [
                    new OA\Property(property: 'title', type: 'string', example: 'Bug login corrigé'),
                    new OA\Property(property: 'description', type: 'string', example: 'Correction du problème de connexion'),
                    new OA\Property(property: 'status', type: 'string', example: 'in_progress'),
                    new OA\Property(property: 'priority', type: 'string', example: 'medium'),
                ]
            )
        ),
        responses: [
            new OA\Response(response: 200, description: 'Ticket updated successfully'),
            new OA\Response(response: 401, description: 'User not authenticated'),
            new OA\Response(response: 403, description: 'Access denied to this project'),
            new OA\Response(response: 422, description: 'Validation failed'),
        ]
    )]
    #[Route('/api/tickets/{id}', name: 'api_ticket_update', methods: ['PUT'])]
    public function update(
        Ticket $ticket,
        Request $request,
        EntityManagerInterface $entityManager,
        ProjectSecurityService $projectSecurityService,
        ApiResponseService $apiResponse,
        ValidatorInterface $validator,
        SerializerInterface $serializer
    ): JsonResponse {

        $this->denyDeletedTicket($ticket);

        /** @var User|null $currentUser */
        $currentUser = $this->getUser();

        if (!$currentUser instanceof User) {
            throw new UnauthorizedException();
        }

        // Sécurité : seul un membre du projet peut modifier un ticket
        if (!$projectSecurityService->isProjectMember($ticket->getProject(), $currentUser)) {
            throw new ForbiddenException('Access denied to this project');
        }

        $dto = $serializer->deserialize(
            $request->getContent(),
            UpdateTicketDto::class,
            'json'
        );

        $errors = $validator->validate($dto);

        if (count($errors) > 0) {
            throw new ValidationException(
                $apiResponse->formatValidationErrors($errors)
            );
        }

        if ($dto->title !== null) {
            $ticket->setTitle($dto->title);
        }

        if ($dto->description !== null) {
            $ticket->setDescription($dto->description);
        }

        if ($dto->status !== null) {
            $ticket->setStatus($dto->status);
        }

        if ($dto->priority !== null) {
            $ticket->setPriority($dto->priority);
        }

        $ticket->setUpdateAt(new \DateTimeImmutable());

        $entityManager->flush();

        return $apiResponse->success(
            $this->formatTicket($ticket),
            'Ticket updated successfully'
        );
    }

    #[OA\Delete(
        path: '/api/tickets/{id}',
        summary: 'Delete ticket',
        description: 'Delete a ticket. Only project managers can delete tickets.',
        security: [['Bearer' => []]],
        tags: ['Tickets'],
        parameters: [
            new OA\Parameter(
                name: 'id',
                in: 'path',
                required: true,
                schema: new OA\Schema(type: 'integer', example: 1)
            ),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Ticket deleted successfully'),
            new OA\Response(response: 401, description: 'User not authenticated'),
            new OA\Response(response: 403, description: 'Only project managers can delete tickets'),
        ]
    )]
    #[Route('/api/tickets/{id}', name: 'api_ticket_delete', methods: ['DELETE'])]
    public function delete(
        Ticket $ticket,
        EntityManagerInterface $entityManager,
        ProjectSecurityService $projectSecurityService,
        ApiResponseService $apiResponse
    ): JsonResponse {

        $this->denyDeletedTicket($ticket);

        /** @var User|null $currentUser */
        $currentUser = $this->getUser();

        if (!$currentUser instanceof User) {
            throw new UnauthorizedException();
        }

        // Sécurité : seul un project_manager peut supprimer un ticket
        if (
            !$projectSecurityService->hasProjectRole(
                $ticket->getProject(),
                $currentUser,
                'project_manager'
            )
        ) {
            throw new ForbiddenException('Only project managers can delete tickets');
        }

        //$entityManager->remove($ticket);
        $ticket->setDeleteAt(new \DateTimeImmutable());
        $entityManager->flush();

        return $apiResponse->success(
            $this->formatTicket($ticket),
            'Ticket deleted successfully'
        );
    }

    #[OA\Patch(
        path: '/api/tickets/{id}/assign',
        summary: 'Assign ticket',
        description: 'Assign a ticket to a user. Only project managers can assign tickets.',
        security: [['Bearer' => []]],
        tags: ['Tickets'],
        parameters: [
            new OA\Parameter(
                name: 'id',
                in: 'path',
                required: true,
                schema: new OA\Schema(type: 'integer', example: 1)
            ),
        ],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['assignedTo'],
                properties: [
                    new OA\Property(property: 'assignedTo', type: 'integer', example: 2),
                ]
            )
        ),
        responses: [
            new OA\Response(response: 200, description: 'Ticket assigned successfully'),
            new OA\Response(response: 401, description: 'User not authenticated'),
            new OA\Response(response: 403, description: 'Only project managers can assign tickets'),
            new OA\Response(response: 404, description: 'User not found'),
            new OA\Response(response: 422, description: 'Validation failed'),
        ]
    )]
    #[Route('/api/tickets/{id}/assign', name: 'api_ticket_assign', methods: ['PATCH'])]
    public function assign(
        Ticket $ticket,
        Request $request,
        UserRepository $userRepository,
        EntityManagerInterface $entityManager,
        ProjectSecurityService $projectSecurityService,
        TicketActivityService $ticketActivityService,
        ApiResponseService $apiResponse,
        NotificationService $notificationService
    ): JsonResponse {

        $this->denyDeletedTicket($ticket);

        /** @var User|null $currentUser */
        $currentUser = $this->getUser();

        if (!$currentUser instanceof User) {
            throw new UnauthorizedException();
        }

        // Sécurité : seul un project_manager peut assigner un ticket
        if (
            !$projectSecurityService->hasProjectRole(
                $ticket->getProject(),
                $currentUser,
                'project_manager'
            )
        ) {
            throw new ForbiddenException('Only project managers can assign tickets');
        }

        $data = json_decode($request->getContent(), true);

        if (!isset($data['assignedTo'])) {
            throw new ValidationException([
                [
                    'field' => 'assignedTo',
                    'message' => 'assignedTo is required',
                ],
            ]);
        }

        $assignedUser = $userRepository->find($data['assignedTo']);

        if (!$assignedUser) {
            throw new NotFoundException('User not found');
        }

        $ticket->setAssignedTo($assignedUser);
        $ticket->setUpdateAt(new \DateTimeImmutable());

        // Historique : assignation
        $ticketActivityService->logAssigned(
            $ticket,
            $currentUser,
            $assignedUser
        );

        $notificationService->ticketAssigned(
            $assignedUser,
            $ticket->getTitle()
        );

        $entityManager->flush();

        return $apiResponse->success(
            $this->formatTicket($ticket),
            'Ticket assigned successfully'
        );
    }

    #[OA\Patch(
        path: '/api/tickets/{id}/status',
        summary: 'Update ticket status',
        description: 'Move a ticket in the Kanban workflow.',
        security: [['Bearer' => []]],
        tags: ['Tickets'],
        parameters: [
            new OA\Parameter(
                name: 'id',
                in: 'path',
                required: true,
                schema: new OA\Schema(type: 'integer', example: 1)
            ),
        ],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['status'],
                properties: [
                    new OA\Property(
                        property: 'status',
                        type: 'string',
                        example: 'in_progress',
                        enum: ['todo', 'in_progress', 'testing', 'delivery_recette', 'done']
                    ),
                ]
            )
        ),
        responses: [
            new OA\Response(response: 200, description: 'Ticket status updated successfully'),
            new OA\Response(response: 401, description: 'User not authenticated'),
            new OA\Response(response: 403, description: 'Access denied to this project'),
            new OA\Response(response: 422, description: 'Validation failed'),
        ]
    )]
    #[Route('/api/tickets/{id}/status', name: 'api_ticket_status', methods: ['PATCH'])]
    public function updateStatus(
        Ticket $ticket,
        Request $request,
        EntityManagerInterface $entityManager,
        ProjectSecurityService $projectSecurityService,
        TicketActivityService $ticketActivityService,
        ApiResponseService $apiResponse,
        RealtimeService $realtimeService,
        NotificationService $notificationService
    ): JsonResponse {

        $this->denyDeletedTicket($ticket);

        /** @var User|null $user */
        $user = $this->getUser();

        if (!$user instanceof User) {
            throw new UnauthorizedException();
        }

        // Sécurité : seuls les membres peuvent déplacer les tickets
        if (!$projectSecurityService->isProjectMember($ticket->getProject(), $user)) {
            throw new ForbiddenException('Access denied to this project');
        }

        $allowedStatuses = [
            'todo',
            'in_progress',
            'testing',
            'delivery_recette',
            'done',
        ];

        $data = json_decode($request->getContent(), true);

        if (!isset($data['status'])) {
            throw new ValidationException([
                [
                    'field' => 'status',
                    'message' => 'status is required',
                ],
            ]);
        }

        if (!in_array($data['status'], $allowedStatuses, true)) {
            throw new ValidationException([
                [
                    'field' => 'status',
                    'message' => 'Invalid status',
                ],
                [
                    'field' => 'allowedStatuses',
                    'message' => implode(', ', $allowedStatuses),
                ],
            ]);
        }

        $oldStatus = $ticket->getStatus();

        $ticket->setStatus($data['status']);
        $ticket->setUpdateAt(new \DateTimeImmutable());

        // Historique : changement de statut
        $ticketActivityService->logStatusChanged(
            $ticket,
            $user,
            $oldStatus,
            $data['status']
        );

        // Notification utilisateur assigné
        if (
            $ticket->getAssignedTo()
            &&
            $ticket->getAssignedTo()->getId() !== $user->getId()
        ) {
            $notificationService->statusChanged(
                $ticket->getAssignedTo(),
                $ticket->getTitle(),
                $oldStatus,
                $data['status']
            );
        }

        $entityManager->flush();

        $realtimeService->project(
            $ticket->getProject()->getId(),
            [
                'type' => 'ticket_status_changed',
                'ticketId' => $ticket->getId(),
                'oldStatus' => $oldStatus,
                'newStatus' => $data['status'],
                'ticket' => $this->formatTicket($ticket),
            ]
        );

        return $apiResponse->success(
            $this->formatTicket($ticket),
            'Ticket status updated successfully'
        );
    }

    #[OA\Get(
        path: '/api/tickets/{id}/activities',
        summary: 'List ticket activities',
        description: 'Retrieve activity history for a ticket.',
        security: [['Bearer' => []]],
        tags: ['Tickets'],
        parameters: [
            new OA\Parameter(
                name: 'id',
                in: 'path',
                required: true,
                schema: new OA\Schema(type: 'integer', example: 1)
            ),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Ticket activities retrieved successfully'),
            new OA\Response(response: 401, description: 'User not authenticated'),
            new OA\Response(response: 403, description: 'Access denied to this project'),
        ]
    )]
    #[Route('/api/tickets/{id}/activities', name: 'api_ticket_activities', methods: ['GET'])]
    public function activities(
        Ticket $ticket,
        ProjectSecurityService $projectSecurityService,
        ApiResponseService $apiResponse
    ): JsonResponse {

        $this->denyDeletedTicket($ticket);

        /** @var User|null $currentUser */
        $currentUser = $this->getUser();

        if (!$currentUser instanceof User) {
            throw new UnauthorizedException();
        }

        // Seuls les membres du projet peuvent voir l’historique
        if (!$projectSecurityService->isProjectMember($ticket->getProject(), $currentUser)) {
            throw new ForbiddenException('Access denied to this project');
        }

        $activities = [];

        foreach ($ticket->getActivities() as $activity) {
            $activities[] = [
                'id' => $activity->getId(),
                'action' => $activity->getAction(),
                'description' => $activity->getDescription(),
                'createdAt' => $activity->getCreatedAt()?->format('Y-m-d H:i:s'),
                'createdBy' => [
                    'id' => $activity->getCreatedBy()?->getId(),
                    'email' => $activity->getCreatedBy()?->getEmail(),
                ],
            ];
        }

        return $apiResponse->success(
            $activities,
            'Ticket activities retrieved successfully'
        );
    }

    #[OA\Get(
        path: '/api/tickets/deleted',
        summary: 'List deleted tickets',
        description: 'Retrieve all soft deleted tickets.',
        security: [['Bearer' => []]],
        tags: ['Tickets'],
        parameters: [
            new OA\Parameter(
                name: 'page',
                in: 'query',
                required: false,
                schema: new OA\Schema(
                    type: 'integer',
                    example: 1
                )
            ),
            new OA\Parameter(
                name: 'limit',
                in: 'query',
                required: false,
                schema: new OA\Schema(
                    type: 'integer',
                    example: 10
                )
            ),
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Deleted tickets retrieved successfully'
            ),
        ]
    )]
    #[Route('/api/tickets/deleted', name: 'api_tickets_deleted', methods: ['GET'])]
    public function deleted(
        Request $request,
        TicketRepository $ticketRepository,
        ApiResponseService $apiResponse
    ): JsonResponse {
        $page = max(1, (int) $request->query->get('page', 1));
        $limit = max(1, (int) $request->query->get('limit', 10));

        $result = $ticketRepository->findDeletedTickets($page, $limit);

        $tickets = [];

        foreach ($result['data'] as $ticket) {
            $tickets[] = $this->formatTicket($ticket);
        }

        return $apiResponse->success([
            'tickets' => $tickets,
            'pagination' => $result['pagination'],
        ], 'Deleted tickets retrieved successfully');
    }

    #[OA\Post(
        path: '/api/tickets/{id}/restore',
        summary: 'Restore ticket',
        description: 'Restore a soft deleted ticket.',
        security: [['Bearer' => []]],
        tags: ['Tickets'],
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
                description: 'Ticket restored successfully'
            ),
            new OA\Response(
                response: 403,
                description: 'Only project managers can restore tickets'
            ),
            new OA\Response(
                response: 404,
                description: 'Ticket not found'
            ),
        ]
    )]
    #[Route('/api/tickets/{id}/restore', name: 'api_ticket_restore', methods: ['POST'])]
    public function restore(
        Ticket $ticket,
        EntityManagerInterface $entityManager,
        ProjectSecurityService $projectSecurityService,
        ApiResponseService $apiResponse
    ): JsonResponse {
        /** @var User|null $currentUser */
        $currentUser = $this->getUser();

        if (!$currentUser instanceof User) {
            throw new UnauthorizedException();
        }

        if (
            !$projectSecurityService->hasProjectRole(
                $ticket->getProject(),
                $currentUser,
                'project_manager'
            )
        ) {
            throw new ForbiddenException('Only project managers can restore tickets');
        }

        if ($ticket->getDeleteAt() === null) {
            return $apiResponse->success(
                $this->formatTicket($ticket),
                'Ticket is already active'
            );
        }

        $ticket->setDeleteAt(null);
        $ticket->setUpdateAt(new \DateTimeImmutable());

        $entityManager->flush();

        return $apiResponse->success(
            $this->formatTicket($ticket),
            'Ticket restored successfully'
        );
    }

    private function formatTicket(Ticket $ticket): array
    {
        return [
            'id' => $ticket->getId(),
            'title' => $ticket->getTitle(),
            'description' => $ticket->getDescription(),
            'status' => $ticket->getStatus(),
            'priority' => $ticket->getPriority(),
            'createdAt' => $ticket->getCreatedAt()?->format('Y-m-d H:i:s'),
            'updatedAt' => $ticket->getUpdateAt()?->format('Y-m-d H:i:s'),

            'project' => $ticket->getProject() ? [
                'id' => $ticket->getProject()->getId(),
                'name' => $ticket->getProject()->getName(),
            ] : null,

            'createdBy' => $ticket->getCreatedBy() ? [
                'id' => $ticket->getCreatedBy()->getId(),
                'email' => $ticket->getCreatedBy()->getEmail(),
            ] : null,

            'assignedTo' => $ticket->getAssignedTo() ? [
                'id' => $ticket->getAssignedTo()->getId(),
                'email' => $ticket->getAssignedTo()->getEmail(),
            ] : null,
        ];
    }

    private function denyDeletedTicket(Ticket $ticket): void
    {
        if ($ticket->getDeleteAt() !== null) {
            throw new NotFoundException('Ticket not found');
        }

        if ($ticket->getProject()?->getDeletedAt() !== null) {
            throw new NotFoundException('Ticket not found');
        }
    }
}