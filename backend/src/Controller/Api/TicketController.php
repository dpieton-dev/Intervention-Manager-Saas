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
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Serializer\SerializerInterface;
use Symfony\Component\Validator\Validator\ValidatorInterface;

class TicketController extends AbstractController
{
    #[Route('/api/tickets', name: 'api_tickets', methods: ['GET'])]
    public function index(Request $request, TicketRepository $ticketRepository, ApiResponseService $apiResponse): JsonResponse 
    {

        //PAGINATION

        $page = max(
            1,
            (int) $request->query->get('page', 1)
        );

        $limit = max(
            1,
            (int) $request->query->get('limit', 10)
        );

        // FILTERS

        $filters = [
            'status' => $request->query->get('status'),
            'priority' => $request->query->get('priority'),
            'project' => $request->query->get('project'),
            'assignedTo' => $request->query->get('assignedTo'),
            'createdBy' => $request->query->get('createdBy'),
            'search' => $request->query->get('search'),
        ];

        // REPOSITORY

        $result = $ticketRepository->findFilteredTickets(
            $filters,
            $page,
            $limit
        );

        // FORMAT TICKETS

        $tickets = [];

        foreach ($result['data'] as $ticket) {
            $tickets[] = $this->formatTicket($ticket);
        }

        //RESPONSE

        return $apiResponse->success([
            'tickets' => $tickets,
            'pagination' => $result['pagination'],
            'filters' => $filters,
        ], 'Tickets retrieved successfully');
    }

    #[Route('/api/tickets/{id}', name: 'api_ticket_show', methods: ['GET'])]
    public function show(Ticket $ticket, ApiResponseService $apiResponse): JsonResponse 
    {
        return $apiResponse->success(
            $this->formatTicket($ticket),
            'Ticket retrieved successfully'
        );
    }

    #[Route('/api/tickets', name: 'api_ticket_create', methods: ['POST'])]
    public function create(Request $request, EntityManagerInterface $entityManager, ProjectRepository $projectRepository, ProjectSecurityService $projectSecurityService,
        ApiResponseService $apiResponse, ValidatorInterface $validator, SerializerInterface $serializer): JsonResponse
    {
        /** @var User|null $user */
        $user = $this->getUser();

        if (!$user instanceof User) {
            throw new UnauthorizedException();
        }

        $dto = $serializer->deserialize($request->getContent(), CreateTicketDto::class, 'json');

        $errors = $validator->validate($dto);

        if (count($errors) > 0) {
            throw new ValidationException($apiResponse->formatValidationErrors($errors));
        }

        $project = $projectRepository->find($dto->projectId);

        if (!$project) {
            throw new NotFoundException('Project not found');
        }

        if (!$projectSecurityService->isProjectMember($project, $user)) {
            throw new ForbiddenException('Access denied to this project');
        }

        $ticket = new Ticket();
        $ticket->setTitle($dto->title);
        $ticket->setDescription($dto->description);
        $ticket->setPriority($dto->priority);
        $ticket->setStatus('todo');
        $ticket->setCreatedBy($user);
        $ticket->setProject($project);

        $entityManager->persist($ticket);
        $entityManager->flush();

        return $apiResponse->success(
            $this->formatTicket($ticket),
            'Ticket created successfully',
            201
        );
    }

    #[Route('/api/tickets/{id}', name: 'api_ticket_update', methods: ['PUT'])]
    public function update(Ticket $ticket, Request $request, EntityManagerInterface $entityManager, ProjectSecurityService $projectSecurityService,
        ApiResponseService $apiResponse, ValidatorInterface $validator, SerializerInterface $serializer): JsonResponse
    {
        /** @var User|null $currentUser */
        $currentUser = $this->getUser();

        if (!$currentUser instanceof User) {
            throw new UnauthorizedException();
        }

        if (!$projectSecurityService->isProjectMember($ticket->getProject(), $currentUser)) 
        {
            throw new ForbiddenException('Access denied to this project');
        }

        $dto = $serializer->deserialize($request->getContent(), UpdateTicketDto::class, 'json');

        $errors = $validator->validate($dto);

        if (count($errors) > 0) {
            throw new ValidationException($apiResponse->formatValidationErrors($errors));
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

    #[Route('/api/tickets/{id}', name: 'api_ticket_delete', methods: ['DELETE'])]
    public function delete(Ticket $ticket, EntityManagerInterface $entityManager, ProjectSecurityService $projectSecurityService, ApiResponseService $apiResponse): JsonResponse
    {
        /** @var User|null $currentUser */
        $currentUser = $this->getUser();

        if (!$currentUser instanceof User) {
            throw new UnauthorizedException();
        }

        if (!$projectSecurityService->hasProjectRole($ticket->getProject(), $currentUser, 'project_manager')) 
        {
            throw new ForbiddenException('Only project managers can delete tickets');
        }

        $entityManager->remove($ticket);
        $entityManager->flush();

        return $apiResponse->success(
            null,
            'Ticket deleted successfully'
        );
    }

    #[Route('/api/tickets/{id}/assign', name: 'api_ticket_assign', methods: ['PATCH'])]
    public function assign(
        Ticket $ticket, Request $request, UserRepository $userRepository, EntityManagerInterface $entityManager, 
        ProjectSecurityService $projectSecurityService, ApiResponseService $apiResponse): JsonResponse 
    {
        /** @var User|null $currentUser */
        $currentUser = $this->getUser();

        if (!$currentUser instanceof User)
        {
            throw new UnauthorizedException();
        }

        if (!$projectSecurityService->hasProjectRole($ticket->getProject(), $currentUser,'project_manager')) 
        {
            throw new ForbiddenException('Only project managers can assign tickets');
        }

        $data = json_decode($request->getContent(), true);

        if (!isset($data['assignedTo']))
        {
            throw new ValidationException([
                [
                    'field' => 'assignedTo',
                    'message' => 'assignedTo is required',
                ],
            ]);
        }

        $assignedUser = $userRepository->find($data['assignedTo']);

        if (!$assignedUser)
        {
            throw new NotFoundException('User not found');
        }

        $ticket->setAssignedTo($assignedUser);
        $ticket->setUpdateAt(new \DateTimeImmutable());

        $entityManager->flush();

        return $apiResponse->success(
            $this->formatTicket($ticket),
            'Ticket assigned successfully'
        );
    }

    #[Route('/api/tickets/{id}/status', name: 'api_ticket_status', methods: ['PATCH'])]
    public function updateStatus(Ticket $ticket, Request $request, EntityManagerInterface $entityManager, ProjectSecurityService $projectSecurityService,
        ApiResponseService $apiResponse): JsonResponse
    {
        /** @var User|null $user */
        $user = $this->getUser();

        if (!$user instanceof User)
        {
            throw new UnauthorizedException();
        }

        if (!$projectSecurityService->isProjectMember($ticket->getProject(), $user))
        {
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

        if (!isset($data['status']))
        {
            throw new ValidationException([
                [
                    'field' => 'status',
                    'message' => 'status is required',
                ],
            ]);
        }

        if (!in_array($data['status'], $allowedStatuses, true))
        {
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

        $ticket->setStatus($data['status']);
        $ticket->setUpdateAt(new \DateTimeImmutable());

        $entityManager->flush();

        return $apiResponse->success(
            $this->formatTicket($ticket),
            'Ticket status updated successfully'
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
}