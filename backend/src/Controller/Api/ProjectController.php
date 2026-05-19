<?php

namespace App\Controller\Api;

use App\Entity\Project;
use App\Repository\ProjectRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

class ProjectController extends AbstractController
{
    #[Route('/api/projects', name: 'api_projects', methods: ['GET'])]
    public function index(ProjectRepository $projectRepository): JsonResponse
    {
        $projects = $projectRepository->findAll();

        $data = [];

        foreach ($projects as $project) {
            $data[] = $this->formatProject($project);
        }

        return $this->json($data);
    }

    #[Route('/api/projects/{id}', name: 'api_project_show', methods: ['GET'])]
    public function show(Project $project): JsonResponse
    {
        return $this->json($this->formatProject($project));
    }

    #[Route('/api/projects', name: 'api_project_create', methods: ['POST'])]
    public function create(
        Request $request,
        EntityManagerInterface $entityManager
    ): JsonResponse {
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
    public function update(
        Project $project,
        Request $request,
        EntityManagerInterface $entityManager
    ): JsonResponse {
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
    public function delete(
        Project $project,
        EntityManagerInterface $entityManager
    ): JsonResponse {
        $entityManager->remove($project);
        $entityManager->flush();

        return $this->json([
            'message' => 'Project deleted successfully',
        ]);
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
