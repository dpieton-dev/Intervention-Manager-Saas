<?php

namespace App\Controller\Api;

use App\Entity\Project;
use App\Entity\ProjectPresence;
use App\Entity\User;
use App\Exception\UnauthorizedException;
use App\Service\ApiResponseService;
use App\Service\ProjectSecurityService;
use App\Service\RealtimeService;
use App\Exception\ForbiddenException;
use Doctrine\ORM\EntityManagerInterface;
use OpenApi\Attributes as OA;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;

#[OA\Tag(name: 'Presence')]
class PresenceController extends AbstractController
{
    /*
    |--------------------------------------------------------------------------
    | UPDATE USER PRESENCE
    |--------------------------------------------------------------------------
    */

    #[OA\Post(
        path: '/api/projects/{id}/presence',
        summary: 'Update project presence',
        description: 'Update current user presence on a project board.',
        security: [['Bearer' => []]],
        tags: ['Presence'],
        parameters: [
            new OA\Parameter(
                name: 'id',
                in: 'path',
                required: true,
                schema: new OA\Schema(
                    type: 'integer',
                    example: 1
                )
            ),
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Presence updated successfully'
            ),
        ]
    )]
    #[Route(
        '/api/projects/{id}/presence',
        name: 'api_project_presence',
        methods: ['POST']
    )]
    public function update(
        Project $project,
        EntityManagerInterface $entityManager,
        ProjectSecurityService $projectSecurityService,
        RealtimeService $realtimeService,
        ApiResponseService $apiResponse
    ): JsonResponse {

        /** @var User|null $currentUser */
        $currentUser = $this->getUser();

        if (!$currentUser instanceof User) {
            throw new UnauthorizedException();
        }

        // Vérifie accès projet
        if (!$projectSecurityService->isProjectMember($project, $currentUser)) {
            throw new ForbiddenException('Access denied to this project');
        }

        // Recherche présence existante
        $presence = $entityManager
            ->getRepository(ProjectPresence::class)
            ->findOneBy([
                'project' => $project,
                'user' => $currentUser,
            ]);

        // Sinon crée présence
        if (!$presence instanceof ProjectPresence) {

            $presence = new ProjectPresence();

            $presence->setProject($project);

            $presence->setUser($currentUser);

            $entityManager->persist($presence);
        }

        // Update activité
        $presence->setLastSeenAt(
            new \DateTimeImmutable()
        );

        $entityManager->flush();

        /*
        |--------------------------------------------------------------------------
        | ONLINE USERS
        |--------------------------------------------------------------------------
        */

        $onlineUsers = [];

        foreach (
            $project->getPresences()
            as $projectPresence
        ) {

            $lastSeenAt = $projectPresence
                ->getLastSeenAt();

            // Utilisateur online < 60 secondes
            if (
                $lastSeenAt instanceof \DateTimeImmutable
                &&
                $lastSeenAt > new \DateTimeImmutable('-60 seconds')
            ) {

                $onlineUsers[] = [
                    'id' => $projectPresence
                        ->getUser()
                        ?->getId(),

                    'email' => $projectPresence
                        ->getUser()
                        ?->getEmail(),
                ];
            }
        }

        /*
        |--------------------------------------------------------------------------
        | REALTIME EVENT
        |--------------------------------------------------------------------------
        */

        $realtimeService->project(
            $project->getId(),
            [
                'type' => 'presence_updated',

                'projectId' => $project->getId(),

                'onlineUsers' => $onlineUsers,
            ]
        );

        return $apiResponse->success(
            [
                'onlineUsers' => $onlineUsers,
            ],
            'Presence updated successfully'
        );
    }
}