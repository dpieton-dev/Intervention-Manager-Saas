<?php

namespace App\Controller\Api;

use App\Dto\ProjectRole\CreateProjectRoleDto;
use App\Dto\ProjectRole\UpdateProjectRoleDto;
use App\Entity\Project;
use App\Entity\ProjectRole;
use App\Entity\User;
use App\Exception\ForbiddenException;
use App\Exception\UnauthorizedException;
use App\Exception\ValidationException;
use App\Service\ApiResponseService;
use App\Service\ProjectSecurityService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Serializer\SerializerInterface;
use Symfony\Component\Validator\Validator\ValidatorInterface;

class ProjectRoleController extends AbstractController
{
    #[Route('/api/projects/{id}/roles', name: 'api_project_roles', methods: ['GET'])]
    public function index(Project $project, ProjectSecurityService $projectSecurityService, ApiResponseService $apiResponse): JsonResponse 
    {
        /** @var User|null $currentUser */
        $currentUser = $this->getUser();

        if (!$currentUser instanceof User) 
        {
            throw new UnauthorizedException();
        }

        if (!$projectSecurityService->isProjectMember($project, $currentUser)) 
        {
            throw new ForbiddenException('Access denied to this project');
        }

        $roles = [];

        foreach ($project->getRoles() as $role) {
            $roles[] = $this->formatRole($role);
        }

        return $apiResponse->success(
            $roles,
            'Project roles retrieved successfully'
        );
    }

    #[Route('/api/projects/{id}/roles', name: 'api_project_role_create', methods: ['POST'])]
    public function create(Project $project, Request $request, EntityManagerInterface $entityManager, ProjectSecurityService $projectSecurityService,
        ApiResponseService $apiResponse, ValidatorInterface $validator, SerializerInterface $serializer): JsonResponse 
    {
        /** @var User|null $currentUser */
        $currentUser = $this->getUser();

        if (!$currentUser instanceof User) {
            throw new UnauthorizedException();
        }

        if (!$projectSecurityService->hasProjectRole($project, $currentUser, 'project_manager')) 
        {
            throw new ForbiddenException('Only project managers can create roles');
        }

        $dto = $serializer->deserialize($request->getContent(), CreateProjectRoleDto::class, 'json');

        $errors = $validator->validate($dto);

        if (count($errors) > 0)
        {
            throw new ValidationException($apiResponse->formatValidationErrors($errors));
        }

        $role = new ProjectRole();
        $role->setProject($project);
        $role->setName($dto->name);
        $role->setCode($dto->code);
        $role->setDescription($dto->description);

        $entityManager->persist($role);
        $entityManager->flush();

        return $apiResponse->success(
            $this->formatRole($role),
            'Project role created successfully',
            201
        );
    }

    #[Route('/api/project-roles/{id}', name: 'api_project_role_update', methods: ['PUT'])]
    public function update(ProjectRole $role, Request $request, EntityManagerInterface $entityManager, ProjectSecurityService $projectSecurityService,
        ApiResponseService $apiResponse, ValidatorInterface $validator, SerializerInterface $serializer): JsonResponse 
    {
        /** @var User|null $currentUser */
        $currentUser = $this->getUser();

        if (!$currentUser instanceof User)
            {
            throw new UnauthorizedException();
        }

        if (!$projectSecurityService->hasProjectRole( $role->getProject(), $currentUser, 'project_manager')) 
        {
            throw new ForbiddenException('Only project managers can manage roles');
        }

        $dto = $serializer->deserialize($request->getContent(), UpdateProjectRoleDto::class, 'json');

        $errors = $validator->validate($dto);

        if (count($errors) > 0)
        {
            throw new ValidationException($apiResponse->formatValidationErrors($errors));
        }

        if ($dto->name !== null)
        {
            $role->setName($dto->name);
        }

        if ($dto->code !== null)
        {
            $role->setCode($dto->code);
        }

        if ($dto->description !== null)
        {
            $role->setDescription($dto->description);
        }

        $entityManager->flush();

        return $apiResponse->success(
            $this->formatRole($role),
            'Project role updated successfully'
        );
    }

    #[Route('/api/project-roles/{id}', name: 'api_project_role_delete', methods: ['DELETE'])]
    public function delete(ProjectRole $role,EntityManagerInterface $entityManager,ProjectSecurityService $projectSecurityService,ApiResponseService $apiResponse): JsonResponse 
    {
        /** @var User|null $currentUser */
        $currentUser = $this->getUser();

        if (!$currentUser instanceof User) {
            throw new UnauthorizedException();
        }

        if (!$projectSecurityService->hasProjectRole($role->getProject(),$currentUser,'project_manager')) 
        {
            throw new ForbiddenException('Only project managers can manage roles');
        }

        $entityManager->remove($role);
        $entityManager->flush();

        return $apiResponse->success(
            null,
            'Project role deleted successfully'
        );
    }

    private function formatRole(ProjectRole $role): array
    {
        return [
            'id' => $role->getId(),
            'name' => $role->getName(),
            'code' => $role->getCode(),
            'description' => $role->getDescription(),
            'createdAt' => $role->getCreatedAt()?->format('Y-m-d H:i:s'),

            'project' => [
                'id' => $role->getProject()->getId(),
                'name' => $role->getProject()->getName(),
            ],
        ];
    }
}