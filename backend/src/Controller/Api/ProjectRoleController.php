<?php

namespace App\Controller\Api;

use App\Entity\Project;
use App\Entity\ProjectRole;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;
use App\Service\ProjectSecurityService;
use App\Service\ApiResponseService;
use Symfony\Component\Validator\Validator\ValidatorInterface;

class ProjectRoleController extends AbstractController
{
    #[Route('/api/projects/{id}/roles', name: 'api_project_roles', methods: ['GET'])]
    public function index(Project $project, ProjectSecurityService $projectSecurityService, ApiResponseService $apiResponse): JsonResponse
    {
        /** @var User|null $currentUser */
        $currentUser = $this->getUser();

        if (!$currentUser instanceof User) {
            return $apiResponse->error('User not authenticated', 401);
        }

        if (!$projectSecurityService->isProjectMember($project, $currentUser)) {
            return $apiResponse->error('Access denied to this project', 403);
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
    public function create(Project $project, Request $request, EntityManagerInterface $entityManager, 
    ProjectSecurityService $projectSecurityService, ApiResponseService $apiResponse, ValidatorInterface $validator): JsonResponse 
    {
        /** @var User|null $currentUser */
        $currentUser = $this->getUser();

        if (!$currentUser instanceof User) {
            return $apiResponse->error('User not authenticated', 401);
        }

        if (
            !$projectSecurityService->hasProjectRole(
                $project,
                $currentUser,
                'project_manager'
            )
        ) {
            return $apiResponse->error(
                'Only project managers can create roles',
                403
            );
        }

        $data = json_decode($request->getContent(), true);

        if (empty($data['name'])) {
            return $apiResponse->error('name is required', 400);
        }

        if (empty($data['code'])) {
            return $apiResponse->error('code is required', 400);
        }

        $role = new ProjectRole();
        $role->setProject($project);
        $role->setName($data['name']);
        $role->setCode($data['code']);
        $role->setDescription($data['description'] ?? null);

        $errors = $validator->validate($role);

        if (count($errors) > 0) {
            $validationErrors = [];

            foreach ($errors as $error) {
                $validationErrors[] = [
                    'field' => $error->getPropertyPath(),
                    'message' => $error->getMessage(),
                ];
            }

            return $apiResponse->error(
                'Validation failed',
                422,
                $validationErrors
            );
        }

        $entityManager->persist($role);
        $entityManager->flush();

        return $apiResponse->success(
            $this->formatRole($role),
            'Project role created successfully',
            201
        );
    }

    #[Route('/api/project-roles/{id}', name: 'api_project_role_update', methods: ['PUT'])]
    public function update(ProjectRole $role, Request $request, EntityManagerInterface $entityManager, 
    ProjectSecurityService $projectSecurityService, ApiResponseService $apiResponse, ValidatorInterface $validator): JsonResponse 
    {
        /** @var User|null $currentUser */
        $currentUser = $this->getUser();

        if (!$currentUser instanceof User) {
            return $apiResponse->error('User not authenticated', 401);
        }

        if (
            !$projectSecurityService->hasProjectRole(
                $role->getProject(),
                $currentUser,
                'project_manager'
            )
        ) {
            return $apiResponse->error(
                'Only project managers can manage roles',
                403
            );
        }

        $data = json_decode($request->getContent(), true);

        $role->setName($data['name'] ?? $role->getName());
        $role->setCode($data['code'] ?? $role->getCode());
        $role->setDescription($data['description'] ?? $role->getDescription());

        $errors = $validator->validate($role);

        if (count($errors) > 0) {
            $validationErrors = [];

            foreach ($errors as $error) {
                $validationErrors[] = [
                    'field' => $error->getPropertyPath(),
                    'message' => $error->getMessage(),
                ];
            }

            return $apiResponse->error(
                'Validation failed',
                422,
                $validationErrors
            );
        }
        
        $entityManager->flush();

        return $apiResponse->success(
            $this->formatRole($role),
            'Project role updated successfully'
        );
    }

    #[Route('/api/project-roles/{id}', name: 'api_project_role_delete', methods: ['DELETE'])]
    public function delete(ProjectRole $role, EntityManagerInterface $entityManager, 
    ProjectSecurityService $projectSecurityService, ApiResponseService $apiResponse): JsonResponse 
    {
        /** @var User|null $currentUser */
        $currentUser = $this->getUser();

        if (!$currentUser instanceof User) {
            return $apiResponse->error('User not authenticated', 401);
        }

        if (
            !$projectSecurityService->hasProjectRole(
                $role->getProject(),
                $currentUser,
                'project_manager'
            )
        ) {
            return $apiResponse->error(
                'Only project managers can manage roles',
                403
            );
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
