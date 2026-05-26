<?php

namespace App\Controller\Api;

use App\Entity\User;
use App\Exception\UnauthorizedException;
use App\Service\ApiResponseService;
use OpenApi\Attributes as OA;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;

#[OA\Tag(name: 'Me')]
class MeController extends AbstractController
{
    #[OA\Get(
        path: '/api/me',
        summary: 'Authenticated user profile',
        description: 'Retrieve the currently authenticated user.',
        security: [['Bearer' => []]],
        tags: ['Me'],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Authenticated user retrieved successfully'
            ),
            new OA\Response(
                response: 401,
                description: 'JWT token missing or invalid'
            ),
        ]
    )]
    #[Route('/api/me', name: 'api_me', methods: ['GET'])]
    public function me(ApiResponseService $apiResponse): JsonResponse 
    {
        /** @var User|null $user */
        $user = $this->getUser();

        if (!$user instanceof User) {
            throw new UnauthorizedException();
        }

        return $apiResponse->success([
            'id' => $user->getId(),
            'email' => $user->getEmail(),
            'roles' => $user->getRoles(),
        ], 'Authenticated user retrieved successfully');
    }
}