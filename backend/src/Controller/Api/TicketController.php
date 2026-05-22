<?php

namespace App\Controller\Api;

use App\Repository\TicketRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;
use App\Entity\Ticket;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Validator\Validator\ValidatorInterface;
use App\Repository\UserRepository;
use App\Repository\ProjectRepository;
use App\Service\ProjectSecurityService;
use App\Service\ApiResponseService;

class TicketController extends AbstractController
{
    #[Route('/api/tickets', name: 'api_tickets', methods: ['GET'])]
    public function index(TicketRepository $ticketRepository, ApiResponseService $apiResponse): JsonResponse
    {
        $tickets = $ticketRepository->findAll();

        $data = [];

        foreach ($tickets as $ticket) {
            $data[] = $this->formatTicket($ticket);
        }

        return $apiResponse->success($data, 'Tickets retrieved successfully');
    }

    #[Route('/api/tickets', name: 'api_ticket_create', methods: ['POST'])]
    public function create(Request $request, EntityManagerInterface $entityManager, 
    ProjectRepository $projectRepository, ProjectSecurityService $projectSecurityService, 
    ApiResponseService $apiResponse, ValidatorInterface $validator): JsonResponse 
    {
        // Récupération de l'utilisateur via le token JWT
        /** @var User|null $user */
        $user = $this->getUser();

        if (!$user instanceof User)
        {
            return $apiResponse->error('User not authenticated', 401);
        }
    
        // Récuération des données JSON envoyées
        $data = json_decode($request->getContent(), true);
        
        // Récupération du Projet
        if (!isset($data['projectId'])) {
            return $apiResponse->error('projectId is required', 400);
        }

        $project = $projectRepository->find($data['projectId']);

        if (!$project) {
            return $apiResponse->error('Project not found', 404);
        }

        // Vérifie que l'utilisateur connecté est membre du projet
        if (!$projectSecurityService->isProjectMember($project, $user)) {
            return $apiResponse->error('Access denied to this project', 403);
        }

        // Création du ticket
        $ticket = new Ticket();

        // Remplissage des attributs de ticket
        $ticket->setTitle($data['title'] ?? '');
        $ticket->setDescription($data['description'] ?? '');
        $ticket->setPriority($data['priority'] ?? '');
        // Statut par défaut
        $ticket->setStatus('todo');
        // Créateur automatique du Ticket
        $ticket->setCreatedBy($user);
        // AssignedTo reste null par défaut
        $ticket->setProject($project);

        $errors = $validator->validate($ticket);

        if (count($errors) > 0) {
            return $apiResponse->validationError($errors);
        }

        // Sauvegarde en BDD
        $entityManager->persist($ticket);
        $entityManager->flush();

        // Réponse JSON
        return $apiResponse->success(
            $this->formatTicket($ticket),
            'Ticket created successfully',
            201
        );
    }

    #[Route('/api/tickets/{id}', name: 'api_ticket_show', methods: ['GET'])]
    public function show(Ticket $ticket, ApiResponseService $apiResponse): JsonResponse
    {
        return $apiResponse->success(
            $this->formatTicket($ticket),
            'Ticket retrieved successfully'
        );
    }

    #[Route('/api/tickets/{id}', name: 'api_ticket_update', methods: ['PUT'])]
    public function update(Ticket $ticket, Request $request, EntityManagerInterface $entityManager, 
    ProjectSecurityService $projectSecurityService, ApiResponseService $apiResponse, ValidatorInterface $validator): JsonResponse 
    {
        /** @var User|null $currentUser */
        $currentUser = $this->getUser();

        if (!$currentUser instanceof User) {
            return $apiResponse->error('User not authenticated', 401);
        }

        if (!$projectSecurityService->isProjectMember($ticket->getProject(), $currentUser)) {
            return $apiResponse->error('Access denied to this project', 403);
        }

        $data = json_decode($request->getContent(), true);

        $ticket->setTitle($data['title'] ?? $ticket->getTitle());
        $ticket->setDescription($data['description'] ?? $ticket->getDescription());
        $ticket->setStatus($data['status'] ?? $ticket->getStatus());
        $ticket->setPriority($data['priority'] ?? $ticket->getPriority());
        $ticket->setUpdateAt(new \DateTimeImmutable());
        $errors = $validator->validate($ticket);

        if (count($errors) > 0) {
            return $apiResponse->validationError($errors);
        }
                $entityManager->flush();

        return $apiResponse->success(
            $this->formatTicket($ticket),
            'Ticket updated successfully'
        );
    }

    #[Route('/api/tickets/{id}', name: 'api_ticket_delete', methods: ['DELETE'])]
    public function delete(Ticket $ticket, EntityManagerInterface $entityManager, 
    ProjectSecurityService $projectSecurityService, ApiResponseService $apiResponse): JsonResponse 
    {
        /** @var User|null $currentUser */
        $currentUser = $this->getUser();

        if (!$currentUser instanceof User) {
            return $apiResponse->error('User not authenticated', 401);
        }

        if (
            !$projectSecurityService->hasProjectRole(
                $ticket->getProject(),
                $currentUser,
                'project_manager'
            )
        ) {
            return $apiResponse->error('Only project managers can delete tickets', 403);
        }

        $entityManager->remove($ticket);
        $entityManager->flush();

        return $apiResponse->success(
            null,
            'Ticket deleted successfully'
        );
    }

    #[Route('/api/tickets/{id}/assign', name: 'api_ticket_assign', methods: ['PATCH'])]
    public function assign(Ticket $ticket, Request $request, UserRepository $userRepository, EntityManagerInterface $entityManager, 
    ProjectSecurityService $projectSecurityService, ApiResponseService $apiResponse): JsonResponse 
    {
        /** @var User|null $currentUser */
        $currentUser = $this->getUser();

        if (!$currentUser instanceof User) {
            return $apiResponse->error('User not authenticated', 401);
        }

        if (
            !$projectSecurityService->hasProjectRole(
                $ticket->getProject(),
                $currentUser,
                'project_manager'
            )
        ) {
            return $apiResponse->error('Only project managers can assign tickets', 403);
        }

        $data = json_decode($request->getContent(), true);

        if (!isset($data['assignedTo'])) {
            return $apiResponse->error('assignedTo is required', 400);
        }

        $assignedUser = $userRepository->find($data['assignedTo']);

        if (!$assignedUser) {
            return $apiResponse->error('User not found', 404);
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
    public function updateStatus(Ticket $ticket, Request $request, EntityManagerInterface $entityManager, 
    ProjectSecurityService $projectSecurityService, ApiResponseService $apiResponse): JsonResponse 
    {
        /** @var User|null $user */
        $user = $this->getUser();

        if (!$user instanceof User) {
            return $apiResponse->error('User not authenticated', 401);
        }

        if (!$projectSecurityService->isProjectMember($ticket->getProject(), $user)) {
            return $apiResponse->error('Access denied to this project', 403);
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
            return $apiResponse->error('status is required', 400);
        }

        if (!in_array($data['status'], $allowedStatuses, true)) {
            return $apiResponse->error('Invalid status', 400, [
                'allowedStatuses' => $allowedStatuses,
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
            'createdBy' => $ticket->getCreatedBy() ? [
                'id' => $ticket->getCreatedBy()->getId(),
                'email' => $ticket->getCreatedBy()->getEmail(),
            ] : null,
            'assignedTo' => $ticket->getAssignedTo() ? [
                'id' => $ticket->getAssignedTo()->getId(),
                'email' => $ticket->getAssignedTo()->getEmail(),
            ] : null,
            'project' => $ticket->getProject() ? [
                'id' => $ticket->getProject()->getId(),
                'name' => $ticket->getProject()->getName(),
            ] : null,
        ];
    }
    
}
