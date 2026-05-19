<?php

namespace App\Controller\Api;

use App\Entity\Project;
use App\Entity\ProjectMember;
use App\Entity\User;
use App\Repository\ProjectRepository;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

class ProjectController extends AbstractController
{
    #[Route('/api/projects', name: 'api_projects', methods: ['GET'])]
    public function index(): JsonResponse
    {
        /** @var User|null $user */
        $user = $this->getUser();

        if (!$user instanceof User) {
            return $this->json([
                'message' => 'User not authenticated',
            ], 401);
        }

        $projects = [];

        // Parcours des memberships du user connecté
        foreach ($user->getProjectMemberships() as $membership) {

            $project = $membership->getProject();

            $projects[] = [
                ...$this->formatProject($project),

                // Rôle du user dans le projet
                'membershipRole' => $membership->getRole(),
            ];
        }

        return $this->json($projects);
    }

    #[Route('/api/projects/{id}', name: 'api_project_show', methods: ['GET'])]
    public function show(Project $project): JsonResponse
    {
        return $this->json($this->formatProject($project));
    }

    #[Route('/api/projects', name: 'api_project_create', methods: ['POST'])]
    public function create(Request $request, EntityManagerInterface $entityManager): JsonResponse 
    {
        $data = json_decode($request->getContent(), true);

        $project = new Project();

        $project->setName($data['name'] ?? '');
        $project->setDescription($data['description'] ?? '');
        $project->setStatus($data['status'] ?? 'active');

        $project->setStartDate(
            new \DateTimeImmutable($data['startDate'])
        );

        $project->setEndDate(
            new \DateTimeImmutable($data['endDate'])
        );

        $entityManager->persist($project);
        $entityManager->flush();

        return $this->json([
            'message' => 'Project created successfully',
            'project' => $this->formatProject($project),
        ], 201);
    }

    #[Route('/api/projects/{id}', name: 'api_project_update', methods: ['PUT'])]
    public function update(Project $project, Request $request, EntityManagerInterface $entityManager): JsonResponse 
    {
        $data = json_decode($request->getContent(), true);

        $project->setName($data['name'] ?? $project->getName());

        $project->setDescription(
            $data['description'] ?? $project->getDescription()
        );

        $project->setStatus(
            $data['status'] ?? $project->getStatus()
        );

        if (isset($data['startDate'])) {
            $project->setStartDate(
                new \DateTimeImmutable($data['startDate'])
            );
        }

        if (isset($data['endDate'])) {
            $project->setEndDate(
                new \DateTimeImmutable($data['endDate'])
            );
        }

        $entityManager->flush();

        return $this->json([
            'message' => 'Project updated successfully',
            'project' => $this->formatProject($project),
        ]);
    }

    #[Route('/api/projects/{id}', name: 'api_project_delete', methods: ['DELETE'])]
    public function delete(Project $project, EntityManagerInterface $entityManager): JsonResponse 
    {
        $entityManager->remove($project);
        $entityManager->flush();

        return $this->json([
            'message' => 'Project deleted successfully',
        ]);
    }

    #[Route('/api/projects/{id}/board', name: 'api_project_board', methods: ['GET'])]
    public function board(Project $project): JsonResponse
    {
        // Structure Kanban
        $board = [
            'todo' => [],
            'in_progress' => [],
            'testing' => [],
            'done' => [],
        ];

        // Parcours des tickets du projet
        foreach ($project->getTickets() as $ticket) {

            $ticketData = [
                'id' => $ticket->getId(),
                'title' => $ticket->getTitle(),
                'description' => $ticket->getDescription(),
                'priority' => $ticket->getPriority(),

                'createdBy' => $ticket->getCreatedBy() ? [
                    'id' => $ticket->getCreatedBy()->getId(),
                    'email' => $ticket->getCreatedBy()->getEmail(),
                ] : null,

                'assignedTo' => $ticket->getAssignedTo() ? [
                    'id' => $ticket->getAssignedTo()->getId(),
                    'email' => $ticket->getAssignedTo()->getEmail(),
                ] : null,
            ];

            // Ajoute le ticket dans la bonne colonne
            $board[$ticket->getStatus()][] = $ticketData;
        }

        return $this->json([
            'project' => [
                'id' => $project->getId(),
                'name' => $project->getName(),
            ],
            'board' => $board,
        ]);
    }

    #[Route('/api/projects/{id}/members', name: 'api_project_add_member', methods: ['POST'])]
    public function addMember(Project $project, Request $request, UserRepository $userRepository, EntityManagerInterface $entityManager): JsonResponse 
    {
        // Récupération des données JSON envoyées
        $data = json_decode($request->getContent(), true);

        // Vérifie que userId est présent
        if (!isset($data['userId'])) {
            return $this->json([
                'message' => 'userId is required',
            ], 400);
        }

        // Vérifie que role est présent
        if (!isset($data['role'])) {
            return $this->json([
                'message' => 'role is required',
            ], 400);
        }

        // Rôles autorisés dans un projet
        $allowedRoles = [
            'project_manager',
            'lead_developer',
            'developer',
            'tester',
            'devops',
        ];

        // Vérifie que le rôle envoyé est valide
        if (!in_array($data['role'], $allowedRoles, true)) {
            return $this->json([
                'message' => 'Invalid role',
                'allowedRoles' => $allowedRoles,
            ], 400);
        }

        // Recherche de l’utilisateur à ajouter au projet
        $user = $userRepository->find($data['userId']);

        if (!$user) {
            return $this->json([
                'message' => 'User not found',
            ], 404);
        }

        // Création du lien User <-> Project
        $member = new ProjectMember();
        $member->setProject($project);
        $member->setUser($user);
        $member->setRole($data['role']);

        // Sauvegarde en base
        $entityManager->persist($member);
        $entityManager->flush();

        return $this->json([
            'message' => 'Member added successfully',
            'member' => [
                'id' => $member->getId(),
                'role' => $member->getRole(),
                'user' => [
                    'id' => $user->getId(),
                    'email' => $user->getEmail(),
                ],
                'project' => [
                    'id' => $project->getId(),
                    'name' => $project->getName(),
                ],
            ],
        ], 201);
    }

    private function formatProject(Project $project): array
    {
        $tickets = [];

        foreach ($project->getTickets() as $ticket) {
            $tickets[] = [
                'id' => $ticket->getId(),
                'title' => $ticket->getTitle(),
                'status' => $ticket->getStatus(),
                'priority' => $ticket->getPriority(),
            ];
        }

        return [
            'id' => $project->getId(),
            'name' => $project->getName(),
            'description' => $project->getDescription(),
            'status' => $project->getStatus(),
            'startDate' => $project->getStartDate()?->format('Y-m-d'),
            'endDate' => $project->getEndDate()?->format('Y-m-d'),
            'createdAt' => $project->getCreatedAt()?->format('Y-m-d H:i:s'),
            'tickets' => $tickets,
        ];
    }
}
