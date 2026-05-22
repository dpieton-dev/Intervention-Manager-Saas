<?php

namespace App\Controller\Api;

use App\Entity\Project;
use App\Entity\ProjectMember;
use App\Entity\User;
use App\Repository\ProjectRepository;
use App\Repository\ProjectRoleRepository;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;
use App\Service\ProjectSecurityService;
use App\Service\ApiResponseService;

class ProjectController extends AbstractController
{
    #[Route('/api/projects', name: 'api_projects', methods: ['GET'])]
    public function index(ApiResponseService $apiResponse): JsonResponse 
    {
        /** @var User|null $user */
        $user = $this->getUser();

        if (!$user instanceof User) {
            return $apiResponse->error(
                'User not authenticated',
                401
            );
        }

        $projects = [];

        foreach ($user->getProjectMemberships() as $membership) {

            $project = $membership->getProject();

            $projects[] = [
                ...$this->formatProject($project),

                'membershipRole' => [
                    'id' => $membership->getProjectRole()->getId(),
                    'name' => $membership->getProjectRole()->getName(),
                    'code' => $membership->getProjectRole()->getCode(),
                ],
            ];
        }

        return $apiResponse->success(
            $projects,
            'Projects retrieved successfully'
        );
    }

    #[Route('/api/projects/{id}', name: 'api_project_show', methods: ['GET'])]
    public function show(Project $project, ProjectSecurityService $projectSecurityService, ApiResponseService $apiResponse): JsonResponse
    {
        /** @var User|null $currentUser */
        $currentUser = $this->getUser();

        if (!$currentUser instanceof User) {
            return $apiResponse->error(
                'User not authenticated',
                401
            );
        }

        if (
            !$projectSecurityService->isProjectMember(
                $project,
                $currentUser
            )
        ) {
            return $apiResponse->error(
                'Access denied to this project',
                403
            );
        }

        return $apiResponse->success(
            $this->formatProject($project),
            'Project retrieved successfully'
        );
    }

    #[Route('/api/projects', name: 'api_project_create', methods: ['POST'])]
    public function create(Request $request, EntityManagerInterface $entityManager, ApiResponseService $apiResponse): JsonResponse 
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

        return $apiResponse->success(
            $this->formatProject($project),
            'Project created successfully',
            201
        );
    }

    #[Route('/api/projects/{id}', name: 'api_project_update', methods: ['PUT'])]
    public function update(Project $project, Request $request, EntityManagerInterface $entityManager, ProjectSecurityService $projectSecurityService, ApiResponseService $apiResponse): JsonResponse 
    {
        /** @var User|null $currentUser */
        $currentUser = $this->getUser();

        if (!$currentUser instanceof User) {
            return $apiResponse->error(
                'User not authenticated',
                401
            );
        }

        if (
            !$projectSecurityService->hasProjectRole(
                $project,
                $currentUser,
                'project_manager'
            )
        ) {
            return $apiResponse->error(
                'Only project managers can update projects',
                403
            );
        }

        $data = json_decode($request->getContent(), true);

        $project->setName($data['name'] ?? $project->getName());
        $project->setDescription($data['description'] ?? $project->getDescription());
        $project->setStatus($data['status'] ?? $project->getStatus());

        if (!empty($data['startDate'])) {
            $project->setStartDate(new \DateTimeImmutable($data['startDate']));
        }

        if (!empty($data['endDate'])) {
            $project->setEndDate(new \DateTimeImmutable($data['endDate']));
        }

        $entityManager->flush();

        return $apiResponse->success(
            $this->formatProject($project),
            'Project updated successfully'
        );
    }

    #[Route('/api/projects/{id}', name: 'api_project_delete', methods: ['DELETE'])]
    public function delete(Project $project, EntityManagerInterface $entityManager, ProjectSecurityService $projectSecurityService, ApiResponseService $apiResponse): JsonResponse 
    {
        /** @var User|null $currentUser */
        $currentUser = $this->getUser();

        if (!$currentUser instanceof User) {
            return $apiResponse->error(
                'User not authenticated',
                401
            );
        }

        if (
            !$projectSecurityService->hasProjectRole(
                $project,
                $currentUser,
                'project_manager'
            )
        ) {
            return $apiResponse->error(
                'Only project managers can delete projects',
                403
            );
        }

        $entityManager->remove($project);
        $entityManager->flush();

        return $apiResponse->success(
            null,
            'Project deleted successfully'
        );
    }

    #[Route('/api/projects/{id}/board', name: 'api_project_board', methods: ['GET'])]
    public function board(Project $project, ProjectSecurityService $projectSecurityService, ApiResponseService $apiResponse): JsonResponse
    {
        // Vérification si user est associé au projet
        /** @var User|null $currentUser */
        $currentUser = $this->getUser();

        if (!$currentUser instanceof User) {
            return $apiResponse->error(
                'User not authenticated',
                401
            );
        }

        if (
            !$projectSecurityService->isProjectMember(
                $project,
                $currentUser
            )
        ) {
            return $apiResponse->error(
                'Access denied to this project',
                403
            );
        }

        $board = [
            'todo' => [],
            'in_progress' => [],
            'testing' => [],
            'delivery_recette' => [],
            'done' => [],
        ];

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

            $board[$ticket->getStatus()][] = $ticketData;
        }

        return $apiResponse->success([
            'project' => [
                'id' => $project->getId(),
                'name' => $project->getName(),
            ],
            'board' => $board,
        ], 'Project board retrieved successfully');
    }

    #[Route('/api/projects/{id}/members', name: 'api_project_add_member', methods: ['POST'])]
    public function addMember(Project $project,
    Request $request, UserRepository $userRepository, ProjectRoleRepository $projectRoleRepository, EntityManagerInterface $entityManager, 
    ProjectSecurityService $projectSecurityService, ApiResponseService $apiResponse): JsonResponse
    {
        /** @var User|null $currentUser */
        $currentUser = $this->getUser();

        if (!$currentUser instanceof User) {
            return $apiResponse->error(
                'User not authenticated',
                401
            );
        }

        if (
            !$projectSecurityService->hasProjectRole(
                $project,
                $currentUser,
                'project_manager'
            )
        ) {
            return $apiResponse->error(
                'Only project managers can add members',
                403
            );
        }

        $data = json_decode($request->getContent(), true);

        if (!isset($data['userId'])) {
            return $apiResponse->error(
                'userId is required',
                400
            );
        }

        if (!isset($data['projectRoleId'])) {
            return $apiResponse->error(
                'projectRoleId is required',
                400
            );
        }

        $user = $userRepository->find($data['userId']);

        if (!$user) {
            return $apiResponse->error(
                'User not found',
                404
            );
        }

        $projectRole = $projectRoleRepository->find($data['projectRoleId']);

        if (!$projectRole) {
            return $apiResponse->error(
                'Project role not found',
                404
            );
        }

        $member = new ProjectMember();
        $member->setProject($project);
        $member->setUser($user);
        $member->setProjectRole($projectRole);

        $entityManager->persist($member);
        $entityManager->flush();

        return $apiResponse->success([
            'id' => $member->getId(),

            'projectRole' => [
                'id' => $projectRole->getId(),
                'name' => $projectRole->getName(),
                'code' => $projectRole->getCode(),
            ],

            'user' => [
                'id' => $user->getId(),
                'email' => $user->getEmail(),
            ],

            'project' => [
                'id' => $project->getId(),
                'name' => $project->getName(),
            ],
        ], 'Member added successfully', 201);
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
