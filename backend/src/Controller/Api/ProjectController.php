<?php

namespace App\Controller\Api;

use App\Dto\Project\CreateProjectDto;
use App\Dto\Project\UpdateProjectDto;
use App\Entity\Project;
use App\Entity\ProjectMember;
use App\Entity\User;
use App\Exception\ForbiddenException;
use App\Exception\NotFoundException;
use App\Exception\UnauthorizedException;
use App\Exception\ValidationException;
use App\Repository\ProjectRoleRepository;
use App\Repository\ProjectRepository;
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

class ProjectController extends AbstractController
{
    #[Route('/api/projects', name: 'api_projects', methods: ['GET'])]
    public function index(
        Request $request,
        ProjectRepository $projectRepository,
        ApiResponseService $apiResponse
    ): JsonResponse {
        /** @var User|null $user */
        $user = $this->getUser();

        if (!$user instanceof User) {
            throw new UnauthorizedException();
        }

        /*
        |--------------------------------------------------------------------------
        | PAGINATION
        |--------------------------------------------------------------------------
        */

        $page = max(
            1,
            (int) $request->query->get('page', 1)
        );

        $limit = max(
            1,
            (int) $request->query->get('limit', 10)
        );

        /*
        |--------------------------------------------------------------------------
        | FILTERS
        |--------------------------------------------------------------------------
        */

        $filters = [
            'status' => $request->query->get('status'),
            'search' => $request->query->get('search'),
        ];

        /*
        |--------------------------------------------------------------------------
        | REPOSITORY
        |--------------------------------------------------------------------------
        */

        $result = $projectRepository->findFilteredProjectsForUser(
            $user,
            $filters,
            $page,
            $limit
        );

        /*
        |--------------------------------------------------------------------------
        | FORMAT PROJECTS
        |--------------------------------------------------------------------------
        */

        $projects = [];

        foreach ($result['data'] as $project) {

            $membership = null;

            foreach ($user->getProjectMemberships() as $projectMembership) {

                if ($projectMembership->getProject()->getId() === $project->getId()) {
                    $membership = $projectMembership;
                    break;
                }
            }

            $projects[] = [
                ...$this->formatProject($project),

                'membershipRole' => $membership ? [
                    'id' => $membership->getProjectRole()->getId(),
                    'name' => $membership->getProjectRole()->getName(),
                    'code' => $membership->getProjectRole()->getCode(),
                ] : null,
            ];
        }

        /*
        |--------------------------------------------------------------------------
        | RESPONSE
        |--------------------------------------------------------------------------
        */

        return $apiResponse->success([
            'projects' => $projects,
            'pagination' => $result['pagination'],
            'filters' => $filters,
        ], 'Projects retrieved successfully');
    }

    #[Route('/api/projects/{id}', name: 'api_project_show', methods: ['GET'])]
    public function show(
        Project $project,
        ProjectSecurityService $projectSecurityService,
        ApiResponseService $apiResponse
    ): JsonResponse {
        /** @var User|null $currentUser */
        $currentUser = $this->getUser();

        if (!$currentUser instanceof User) {
            throw new UnauthorizedException();
        }

        if (
            !$projectSecurityService->isProjectMember(
                $project,
                $currentUser
            )
        ) {
            throw new ForbiddenException(
                'Access denied to this project'
            );
        }

        return $apiResponse->success(
            $this->formatProject($project),
            'Project retrieved successfully'
        );
    }

    #[Route('/api/projects', name: 'api_project_create', methods: ['POST'])]
    public function create(
        Request $request,
        EntityManagerInterface $entityManager,
        ApiResponseService $apiResponse,
        ValidatorInterface $validator,
        SerializerInterface $serializer
    ): JsonResponse {
        $dto = $serializer->deserialize(
            $request->getContent(),
            CreateProjectDto::class,
            'json'
        );

        $errors = $validator->validate($dto);

        if (count($errors) > 0) {
            throw new ValidationException(
                $apiResponse->formatValidationErrors($errors)
            );
        }

        $project = new Project();

        $project->setName($dto->name);
        $project->setDescription($dto->description);
        $project->setStatus($dto->status);

        if ($dto->startDate) {
            $project->setStartDate(
                new \DateTimeImmutable($dto->startDate)
            );
        }

        if ($dto->endDate) {
            $project->setEndDate(
                new \DateTimeImmutable($dto->endDate)
            );
        }

        $projectErrors = $validator->validate($project);

        if (count($projectErrors) > 0) {
            throw new ValidationException(
                $apiResponse->formatValidationErrors($projectErrors)
            );
        }

        $entityManager->persist($project);
        $entityManager->flush();

        return $apiResponse->success(
            $this->formatProject($project),
            'Project created successfully',
            201
        );
    }

    #[Route('/api/projects/{id}', name: 'api_project_update', methods: ['PUT'])]
    public function update(
        Project $project,
        Request $request,
        EntityManagerInterface $entityManager,
        ProjectSecurityService $projectSecurityService,
        ApiResponseService $apiResponse,
        ValidatorInterface $validator,
        SerializerInterface $serializer
    ): JsonResponse {
        /** @var User|null $currentUser */
        $currentUser = $this->getUser();

        if (!$currentUser instanceof User) {
            throw new UnauthorizedException();
        }

        if (
            !$projectSecurityService->hasProjectRole(
                $project,
                $currentUser,
                'project_manager'
            )
        ) {
            throw new ForbiddenException(
                'Only project managers can update projects'
            );
        }

        $dto = $serializer->deserialize(
            $request->getContent(),
            UpdateProjectDto::class,
            'json'
        );

        $errors = $validator->validate($dto);

        if (count($errors) > 0) {
            throw new ValidationException(
                $apiResponse->formatValidationErrors($errors)
            );
        }

        if ($dto->name !== null) {
            $project->setName($dto->name);
        }

        if ($dto->description !== null) {
            $project->setDescription($dto->description);
        }

        if ($dto->status !== null) {
            $project->setStatus($dto->status);
        }

        if ($dto->startDate !== null) {
            $project->setStartDate(
                new \DateTimeImmutable($dto->startDate)
            );
        }

        if ($dto->endDate !== null) {
            $project->setEndDate(
                new \DateTimeImmutable($dto->endDate)
            );
        }

        $projectErrors = $validator->validate($project);

        if (count($projectErrors) > 0) {
            throw new ValidationException(
                $apiResponse->formatValidationErrors($projectErrors)
            );
        }

        $entityManager->flush();

        return $apiResponse->success(
            $this->formatProject($project),
            'Project updated successfully'
        );
    }

    #[Route('/api/projects/{id}', name: 'api_project_delete', methods: ['DELETE'])]
    public function delete(
        Project $project,
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
                $project,
                $currentUser,
                'project_manager'
            )
        ) {
            throw new ForbiddenException(
                'Only project managers can delete projects'
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
    public function board(
        Project $project,
        ProjectSecurityService $projectSecurityService,
        ApiResponseService $apiResponse
    ): JsonResponse {
        /** @var User|null $currentUser */
        $currentUser = $this->getUser();

        if (!$currentUser instanceof User) {
            throw new UnauthorizedException();
        }

        if (
            !$projectSecurityService->isProjectMember(
                $project,
                $currentUser
            )
        ) {
            throw new ForbiddenException(
                'Access denied to this project'
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
    public function addMember(
        Project $project,
        Request $request,
        UserRepository $userRepository,
        ProjectRoleRepository $projectRoleRepository,
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
                $project,
                $currentUser,
                'project_manager'
            )
        ) {
            throw new ForbiddenException(
                'Only project managers can add members'
            );
        }

        $data = json_decode($request->getContent(), true);

        if (!isset($data['userId'])) {
            throw new ValidationException([
                [
                    'field' => 'userId',
                    'message' => 'userId is required',
                ],
            ]);
        }

        if (!isset($data['projectRoleId'])) {
            throw new ValidationException([
                [
                    'field' => 'projectRoleId',
                    'message' => 'projectRoleId is required',
                ],
            ]);
        }

        $user = $userRepository->find($data['userId']);

        if (!$user) {
            throw new NotFoundException(
                'User not found'
            );
        }

        $projectRole = $projectRoleRepository->find($data['projectRoleId']);

        if (!$projectRole) {
            throw new NotFoundException(
                'Project role not found'
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