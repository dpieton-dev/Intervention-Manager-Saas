<?php

namespace App\Controller\Api;

use App\Entity\Project;
use App\Entity\ProjectRole;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

class ProjectRoleController extends AbstractController
{
    #[Route('/api/projects/{id}/roles', name: 'api_project_roles', methods: ['GET'])]
    public function index(Project $project): JsonResponse
    {
        $roles = [];

        foreach ($project->getRoles() as $role) {
            $roles[] = $this->formatRole($role);
        }

        return $this->json($roles);
    }

    #[Route('/api/projects/{id}/roles', name: 'api_project_role_create', methods: ['POST'])]
    public function create(Project $project, Request $request, EntityManagerInterface $entityManager): JsonResponse 
    {
        // Récupère les données JSON envoyées
        $data = json_decode($request->getContent(), true);

        // Vérifie le nom du rôle
        if (empty($data['name'])) {
            return $this->json(['message' => 'name is required'], 400);
        }

        // Vérifie le code du rôle
        if (empty($data['code'])) {
            return $this->json(['message' => 'code is required'], 400);
        }

        // Création du rôle projet
        $role = new ProjectRole();
        $role->setProject($project);
        $role->setName($data['name']);
        $role->setCode($data['code']);
        $role->setDescription($data['description'] ?? null);

        // Sauvegarde en base
        $entityManager->persist($role);
        $entityManager->flush();

        return $this->json([
            'message' => 'Project role created successfully',
            'role' => $this->formatRole($role),
        ], 201);
    }

    #[Route('/api/project-roles/{id}', name: 'api_project_role_update', methods: ['PUT'])]
    public function update(ProjectRole $role, Request $request, EntityManagerInterface $entityManager): JsonResponse 
    {
        $data = json_decode($request->getContent(), true);

        $role->setName($data['name'] ?? $role->getName());
        $role->setCode($data['code'] ?? $role->getCode());
        $role->setDescription($data['description'] ?? $role->getDescription());

        $entityManager->flush();

        return $this->json([
            'message' => 'Project role updated successfully',
            'role' => $this->formatRole($role),
        ]);
    }

    #[Route('/api/project-roles/{id}', name: 'api_project_role_delete', methods: ['DELETE'])]
    public function delete(ProjectRole $role, EntityManagerInterface $entityManager): JsonResponse 
    {
        $entityManager->remove($role);
        $entityManager->flush();

        return $this->json([
            'message' => 'Project role deleted successfully',
        ]);
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
