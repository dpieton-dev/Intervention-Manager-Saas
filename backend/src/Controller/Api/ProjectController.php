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
use OpenApi\Attributes as OA;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Serializer\SerializerInterface;
use Symfony\Component\Validator\Validator\ValidatorInterface;

#[OA\Tag(name: 'Projects')]
class ProjectController extends AbstractController
{
    #[OA\Get(
        path: '/api/projects',
        summary: 'List projects',
        description: 'Retrieve paginated projects available for the authenticated user.',
        security: [['Bearer' => []]],
        tags: ['Projects'],
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
                schema: new OA\Schema(type: 'string', example: 'active')
            ),
            new OA\Parameter(
                name: 'search',
                in: 'query',
                required: false,
                schema: new OA\Schema(type: 'string', example: 'SaaS')
            ),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Projects retrieved successfully'),
            new OA\Response(response: 401, description: 'User not authenticated'),
        ]
    )]
    #[Route('/api/projects', name: 'api_projects', methods: ['GET'])]
    public function index(Request $request, ProjectRepository $projectRepository, ApiResponseService $apiResponse): JsonResponse 
    {
        /** @var User|null $user */
        $user = $this->getUser();

        if (!$user instanceof User) {
            throw new UnauthorizedException();
        }

        // PAGINATION
        $page = max(1, (int) $request->query->get('page', 1));
        $limit = max(1,(int) $request->query->get('limit', 10));

        // FILTERS
        $filters = [
            'status' => $request->query->get('status'),
            'search' => $request->query->get('search'),
        ];

        // REPOSITORY
        $result = $projectRepository->findFilteredProjectsForUser(
            $user,
            $filters,
            $page,
            $limit
        );

        // FORMAT PROJECTS
        $projects = [];
        foreach ($result['data'] as $project) 
        {
            $membership = null;
            foreach ($user->getProjectMemberships() as $projectMembership) 
            {
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

        // RESPONSE
        return $apiResponse->success([
            'projects' => $projects,
            'pagination' => $result['pagination'],
            'filters' => $filters,
        ], 'Projects retrieved successfully');
    }

    #[OA\Get(
        path: '/api/projects/{id}',
        summary: 'Show project',
        description: 'Retrieve one project by id.',
        security: [['Bearer' => []]],
        tags: ['Projects'],
        parameters: [
            new OA\Parameter(
                name: 'id',
                in: 'path',
                required: true,
                schema: new OA\Schema(type: 'integer', example: 1)
            ),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Project retrieved successfully'),
            new OA\Response(response: 401, description: 'User not authenticated'),
            new OA\Response(response: 403, description: 'Access denied to this project'),
            new OA\Response(response: 404, description: 'Project not found'),
        ]
    )]
    #[Route('/api/projects/{id}', name: 'api_project_show', methods: ['GET'])]
    public function show(Project $project, ProjectSecurityService $projectSecurityService, ApiResponseService $apiResponse): JsonResponse 
    {
        $this->denyDeletedProject($project);

        /** @var User|null $currentUser */
        $currentUser = $this->getUser();

        if (!$currentUser instanceof User) {
            throw new UnauthorizedException();
        }

        if (!$projectSecurityService->isProjectMember($project, $currentUser)) 
        {
            throw new ForbiddenException(
                'Access denied to this project'
            );
        }

        return $apiResponse->success(
            $this->formatProject($project),
            'Project retrieved successfully'
        );
    }

    #[OA\Post(
        path: '/api/projects',
        summary: 'Create project',
        description: 'Create a new project.',
        security: [['Bearer' => []]],
        tags: ['Projects'],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['name', 'description'],
                properties: [
                    new OA\Property(
                        property: 'name',
                        type: 'string',
                        example: 'Intervention Manager SaaS'
                    ),
                    new OA\Property(
                        property: 'description',
                        type: 'string',
                        example: 'Projet SaaS Symfony Angular'
                    ),
                ]
            )
        ),
        responses: [
            new OA\Response(response: 201, description: 'Project created successfully'),
            new OA\Response(response: 401, description: 'User not authenticated'),
            new OA\Response(response: 422, description: 'Validation failed'),
        ]
    )]
    #[Route('/api/projects', name: 'api_project_create', methods: ['POST'])]
    public function create(Request $request, EntityManagerInterface $entityManager, ApiResponseService $apiResponse,ValidatorInterface $validator, SerializerInterface $serializer): JsonResponse 
    {
        $dto = $serializer->deserialize(
            $request->getContent(),
            CreateProjectDto::class,
            'json'
        );

        $errors = $validator->validate($dto);

        if (count($errors) > 0) {
            throw new ValidationException($apiResponse->formatValidationErrors($errors));
        }

        $project = new Project();
        $project->setName($dto->name);
        $project->setDescription($dto->description);
        $project->setStatus($dto->status);

        if ($dto->startDate) {
            $project->setStartDate(new \DateTimeImmutable($dto->startDate));
        }

        if ($dto->endDate) {
            $project->setEndDate(new \DateTimeImmutable($dto->endDate));
        }

        $projectErrors = $validator->validate($project);

        if (count($projectErrors) > 0) {
            throw new ValidationException($apiResponse->formatValidationErrors($projectErrors));
        }

        $entityManager->persist($project);
        $entityManager->flush();

        return $apiResponse->success(
            $this->formatProject($project),
            'Project created successfully',
            201
        );
    }

    #[OA\Put(
        path: '/api/projects/{id}',
        summary: 'Update project',
        description: 'Update an existing project.',
        security: [['Bearer' => []]],
        tags: ['Projects'],
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
                    new OA\Property(
                        property: 'name',
                        type: 'string',
                        example: 'Nouveau nom projet'
                    ),
                    new OA\Property(
                        property: 'description',
                        type: 'string',
                        example: 'Nouvelle description'
                    ),
                    new OA\Property(
                        property: 'status',
                        type: 'string',
                        example: 'active'
                    ),
                ]
            )
        ),
        responses: [
            new OA\Response(response: 200, description: 'Project updated successfully'),
            new OA\Response(response: 401, description: 'User not authenticated'),
            new OA\Response(response: 403, description: 'Access denied'),
            new OA\Response(response: 422, description: 'Validation failed'),
        ]
    )]
    #[Route('/api/projects/{id}', name: 'api_project_update', methods: ['PUT'])]
    public function update(Project $project, Request $request,EntityManagerInterface $entityManager,
        ProjectSecurityService $projectSecurityService, ApiResponseService $apiResponse, ValidatorInterface $validator,SerializerInterface $serializer): JsonResponse 
    {
        $this->denyDeletedProject($project);

        /** @var User|null $currentUser */
        $currentUser = $this->getUser();

        if (!$currentUser instanceof User) {
            throw new UnauthorizedException();
        }

        if (!$projectSecurityService->hasProjectRole($project, $currentUser,'project_manager')) 
        {
            throw new ForbiddenException('Only project managers can update projects');
        }

        $dto = $serializer->deserialize($request->getContent(), UpdateProjectDto::class, 'json');
        $errors = $validator->validate($dto);

        if (count($errors) > 0) {
            throw new ValidationException($apiResponse->formatValidationErrors($errors));
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
            $project->setStartDate(new \DateTimeImmutable($dto->startDate));
        }

        if ($dto->endDate !== null) {
            $project->setEndDate(new \DateTimeImmutable($dto->endDate));
        }

        $projectErrors = $validator->validate($project);

        if (count($projectErrors) > 0) {
            throw new ValidationException($apiResponse->formatValidationErrors($projectErrors));
        }

        $entityManager->flush();

        return $apiResponse->success(
            $this->formatProject($project),
            'Project updated successfully'
        );
    }

    #[OA\Delete(
        path: '/api/projects/{id}',
        summary: 'Delete project',
        description: 'Delete a project.',
        security: [['Bearer' => []]],
        tags: ['Projects'],
        parameters: [
            new OA\Parameter(
                name: 'id',
                in: 'path',
                required: true,
                schema: new OA\Schema(type: 'integer', example: 1)
            ),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Project deleted successfully'),
            new OA\Response(response: 401, description: 'User not authenticated'),
            new OA\Response(response: 403, description: 'Access denied'),
        ]
    )]
    #[Route('/api/projects/{id}', name: 'api_project_delete', methods: ['DELETE'])]
    public function delete(Project $project, EntityManagerInterface $entityManager, ProjectSecurityService $projectSecurityService, ApiResponseService $apiResponse): JsonResponse 
    {
        $this->denyDeletedProject($project);

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
            throw new ForbiddenException('Only project managers can delete projects');
        }

        // Soft delete : on ne supprime pas physiquement le projet
        $project->setDeletedAt(new \DateTimeImmutable());

        $entityManager->flush();

        return $apiResponse->success(
            $this->formatProject($project),
            'Project deleted successfully'
        );
    }

    #[OA\Get(
        path: '/api/projects/{id}/board',
        summary: 'Project Kanban board',
        description: 'Retrieve project tickets grouped by Kanban status.',
        security: [['Bearer' => []]],
        tags: ['Projects'],
        parameters: [
            new OA\Parameter(
                name: 'id',
                in: 'path',
                required: true,
                schema: new OA\Schema(type: 'integer', example: 1)
            ),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Project board retrieved successfully'),
            new OA\Response(response: 401, description: 'User not authenticated'),
            new OA\Response(response: 403, description: 'Access denied to this project'),
            new OA\Response(response: 404, description: 'Project not found'),
        ]
    )]
    #[Route('/api/projects/{id}/board', name: 'api_project_board', methods: ['GET'])]
    public function board(Project $project, ProjectSecurityService $projectSecurityService, ApiResponseService $apiResponse): JsonResponse 
    {
        $this->denyDeletedProject($project);

        /** @var User|null $currentUser */
        $currentUser = $this->getUser();

        if (!$currentUser instanceof User) {
            throw new UnauthorizedException();
        }

        if (!$projectSecurityService->isProjectMember($project,$currentUser))
        {
            throw new ForbiddenException('Access denied to this project');
        }

        $board = [
            'todo' => [],
            'in_progress' => [],
            'testing' => [],
            'delivery_recette' => [],
            'done' => [],
        ];

        foreach ($project->getTickets() as $ticket) 
        {
            if ($ticket->getDeleteAt() !== null) {
                continue;
            }

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

    #[OA\Post(
        path: '/api/projects/{id}/members',
        summary: 'Add project member',
        description: 'Add a user to a project with a dynamic project role.',
        security: [['Bearer' => []]],
        tags: ['Projects'],
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
                required: ['userId', 'projectRoleId'],
                properties: [
                    new OA\Property(property: 'userId', type: 'integer', example: 2),
                    new OA\Property(property: 'projectRoleId', type: 'integer', example: 1),
                ]
            )
        ),
        responses: [
            new OA\Response(response: 201, description: 'Member added successfully'),
            new OA\Response(response: 401, description: 'User not authenticated'),
            new OA\Response(response: 403, description: 'Only project managers can add members'),
            new OA\Response(response: 404, description: 'User or role not found'),
            new OA\Response(response: 422, description: 'Validation failed'),
        ]
    )]
    #[Route('/api/projects/{id}/members', name: 'api_project_add_member', methods: ['POST'])]
    public function addMember(Project $project,Request $request,UserRepository $userRepository,ProjectRoleRepository $projectRoleRepository,
        EntityManagerInterface $entityManager,ProjectSecurityService $projectSecurityService,ApiResponseService $apiResponse): JsonResponse 
    {
        $this->denyDeletedProject($project);

        /** @var User|null $currentUser */
        $currentUser = $this->getUser();

        if (!$currentUser instanceof User) {
            throw new UnauthorizedException();
        }

        if (!$projectSecurityService->hasProjectRole($project,$currentUser,'project_manager')) 
        {
            throw new ForbiddenException('Only project managers can add members');
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
            throw new NotFoundException('User not found');
        }

        $projectRole = $projectRoleRepository->find($data['projectRoleId']);

        if (!$projectRole) {
            throw new NotFoundException('Project role not found');
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

    #[OA\Get(
        path: '/api/projects/deleted',
        summary: 'List deleted projects',
        description: 'Retrieve soft deleted projects for the authenticated user.',
        security: [['Bearer' => []]],
        tags: ['Projects'],
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
                description: 'Deleted projects retrieved successfully'
            ),
            new OA\Response(
                response: 401,
                description: 'User not authenticated'
            ),
        ]
    )]
    #[Route('/api/projects/deleted', name: 'api_projects_deleted', methods: ['GET'])]
    public function deleted(
        Request $request,
        ProjectRepository $projectRepository,
        ApiResponseService $apiResponse
    ): JsonResponse {
        /** @var User|null $user */
        $user = $this->getUser();

        if (!$user instanceof User) {
            throw new UnauthorizedException();
        }

        $page = max(
            1,
            (int) $request->query->get('page', 1)
        );

        $limit = max(
            1,
            (int) $request->query->get('limit', 10)
        );

        $result = $projectRepository->findDeletedProjectsForUser(
            $user,
            $page,
            $limit
        );

        $projects = [];

        foreach ($result['data'] as $project) {
            $projects[] = $this->formatProject($project);
        }

        return $apiResponse->success(
            [
                'projects' => $projects,
                'pagination' => $result['pagination'],
            ],
            'Deleted projects retrieved successfully'
        );
    }

    

    private function formatProject(Project $project): array
    {
        $tickets = [];

        foreach ($project->getTickets() as $ticket) {
            if ($ticket->getDeleteAt() !== null) {
                continue;
            }

            $tickets[] = [
                'id' => $ticket->getId(),
                'title' => $ticket->getTitle(),
                'status' => $ticket->getStatus(),
                'priority' => $ticket->getPriority(),
                'deletedAt' => $project->getDeletedAt()?->format('Y-m-d H:i:s'),
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

    private function denyDeletedProject(Project $project): void
    {
        if ($project->getDeletedAt() !== null) {
            throw new NotFoundException('Project not found');
        }
    }
}