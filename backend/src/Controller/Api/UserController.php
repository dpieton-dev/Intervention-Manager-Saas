<?php

namespace App\Controller\Api;

use App\Dto\User\CreateUserDto;
use App\Dto\User\UpdateUserDto;
use App\Entity\User;
use App\Exception\ForbiddenException;
use App\Exception\ValidationException;
use App\Exception\NotFoundException;
use App\Repository\UserRepository;
use App\Service\ApiResponseService;
use App\Service\AuditLogService;
use Doctrine\ORM\EntityManagerInterface;
use OpenApi\Attributes as OA;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\Serializer\SerializerInterface;
use Symfony\Component\Validator\Validator\ValidatorInterface;

#[OA\Tag(name: 'Users')]
#[IsGranted('ROLE_ADMIN')]
class UserController extends AbstractController
{
    #[OA\Get(
        path: '/api/users',
        summary: 'List users',
        security: [['Bearer' => []]],
        tags: ['Users']
    )]
    #[Route('/api/users', name: 'api_users', methods: ['GET'])]
    public function index(
        UserRepository $userRepository,
        ApiResponseService $apiResponse
    ): JsonResponse {
        $users = [];

        foreach ($userRepository->findActiveUsers() as $user) {
            $users[] = $this->formatUser($user);
        }

        return $apiResponse->success($users, 'Users retrieved successfully');
    }

    #[OA\Get(
        path: '/api/users/deleted',
        summary: 'List deleted users',
        description: 'Retrieve soft deleted users.',
        security: [['Bearer' => []]],
        tags: ['Users'],
        responses: [
            new OA\Response(response: 200, description: 'Deleted users retrieved successfully'),
            new OA\Response(response: 401, description: 'User not authenticated'),
        ]
    )]
    #[Route('/api/users/deleted', name: 'api_users_deleted', methods: ['GET'])]
    public function deleted(
        UserRepository $userRepository,
        ApiResponseService $apiResponse
    ): JsonResponse {
        $users = [];

        foreach ($userRepository->findDeletedUsers() as $user) {
            $users[] = $this->formatUser($user);
        }

        return $apiResponse->success(
            $users,
            'Deleted users retrieved successfully'
        );
    }
    
    #[OA\Post(
        path: '/api/users/{id}/restore',
        summary: 'Restore user',
        description: 'Restore a soft deleted user and reactivate the account.',
        security: [['Bearer' => []]],
        tags: ['Users'],
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
            new OA\Response(response: 200, description: 'User restored successfully'),
            new OA\Response(response: 401, description: 'User not authenticated'),
            new OA\Response(response: 403, description: 'Access denied'),
            new OA\Response(response: 404, description: 'User not found'),
        ]
    )]
    #[Route('/api/users/{id}/restore', name: 'api_user_restore', methods: ['POST'])]
    public function restore(
        User $user,
        EntityManagerInterface $entityManager,
        ApiResponseService $apiResponse,
        AuditLogService $auditLogService
    ): JsonResponse {
        if ($user->getDeletedAt() === null) {
            return $apiResponse->success(
                $this->formatUser($user),
                'User is already active'
            );
        }

        $user->setDeletedAt(null);
        $user->setIsActive(true);

        /** @var User|null $currentUser */
        $currentUser = $this->getUser();

        $auditLogService->log(
            'USER_RESTORED',
            sprintf('User "%s" was restored', $user->getEmail()),
            $currentUser instanceof User ? $currentUser : null,
            'User',
            $user->getId()
        );

        $entityManager->flush();

        return $apiResponse->success(
            $this->formatUser($user),
            'User restored successfully'
        );
    }

    #[OA\Get(
        path: '/api/users/{id}',
        summary: 'Show user',
        security: [['Bearer' => []]],
        tags: ['Users'],
        parameters: [
            new OA\Parameter(
                name: 'id',
                in: 'path',
                required: true,
                schema: new OA\Schema(type: 'integer', example: 1)
            ),
        ]
    )]
    #[Route('/api/users/{id}', name: 'api_user_show', methods: ['GET'])]
    public function show(
        User $user,
        ApiResponseService $apiResponse
    ): JsonResponse {

        $this->denyDeletedUser($user);

        return $apiResponse->success(
            $this->formatUser($user),
            'User retrieved successfully'
        );
    }

    #[OA\Post(
        path: '/api/users',
        summary: 'Create user',
        security: [['Bearer' => []]],
        tags: ['Users'],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['email', 'password', 'role'],
                properties: [
                    new OA\Property(property: 'email', type: 'string', example: 'user@example.com'),
                    new OA\Property(property: 'password', type: 'string', example: 'password123'),
                    new OA\Property(property: 'role', type: 'string', example: 'ROLE_USER'),
                    new OA\Property(property: 'isActive', type: 'boolean', example: true),
                ]
            )
        )
    )]
    #[Route('/api/users', name: 'api_user_create', methods: ['POST'])]
    public function create(
        Request $request,
        EntityManagerInterface $entityManager,
        ApiResponseService $apiResponse,
        ValidatorInterface $validator,
        SerializerInterface $serializer,
        UserPasswordHasherInterface $passwordHasher,
        AuditLogService $auditLogService
    ): JsonResponse {
        $dto = $serializer->deserialize(
            $request->getContent(),
            CreateUserDto::class,
            'json'
        );

        $errors = $validator->validate($dto);

        if (count($errors) > 0) {
            throw new ValidationException(
                $apiResponse->formatValidationErrors($errors)
            );
        }

        if (!in_array($dto->role, ['ROLE_USER', 'ROLE_ADMIN'], true)) {
            throw new ValidationException([
                [
                    'field' => 'role',
                    'message' => 'Invalid role',
                ],
            ]);
        }

        $user = new User();
        $user->setEmail($dto->email);
        $user->setRoles([$dto->role]);
        $user->setIsActive(
            $dto->isActive ?? true
        );
        $user->setPassword(
            $passwordHasher->hashPassword($user, $dto->password)
        );

        $entityManager->persist($user);
        $entityManager->flush();

        /** @var User|null $currentUser */
        $currentUser = $this->getUser();

        $auditLogService->log(
            'USER_CREATED',
            sprintf('User "%s" was created', $user->getEmail()),
            $currentUser instanceof User ? $currentUser : null,
            'User',
            $user->getId()
        );
        
        $entityManager->flush();

        return $apiResponse->success(
            $this->formatUser($user),
            'User created successfully',
            201
        );
    }

    #[OA\Put(
        path: '/api/users/{id}',
        summary: 'Update user',
        security: [['Bearer' => []]],
        tags: ['Users'],
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
                    new OA\Property(property: 'email', type: 'string', example: 'updated@example.com'),
                    new OA\Property(property: 'role', type: 'string', example: 'ROLE_ADMIN'),
                    new OA\Property(property: 'isActive',type: 'boolean',example: false),
                ]
            )
        )
    )]
    #[Route('/api/users/{id}', name: 'api_user_update', methods: ['PUT'])]
    public function update(
        User $user,
        Request $request,
        EntityManagerInterface $entityManager,
        ApiResponseService $apiResponse,
        ValidatorInterface $validator,
        SerializerInterface $serializer,
        AuditLogService $auditLogService
    ): JsonResponse {

        $this->denyDeletedUser($user);
        
        $dto = $serializer->deserialize(
            $request->getContent(),
            UpdateUserDto::class,
            'json'
        );

        $errors = $validator->validate($dto);

        if (count($errors) > 0) {
            throw new ValidationException(
                $apiResponse->formatValidationErrors($errors)
            );
        }

        if ($dto->email !== null) {
            $user->setEmail($dto->email);
        }

        if ($dto->role !== null) {
            if (!in_array($dto->role, ['ROLE_USER', 'ROLE_ADMIN'], true)) {
                throw new ValidationException([
                    [
                        'field' => 'role',
                        'message' => 'Invalid role',
                    ],
                ]);
            }

            $user->setRoles([$dto->role]);
        }

        if ($dto->isActive !== null) {
            $user->setIsActive($dto->isActive);
        }

        /** @var User|null $currentUser */
        $currentUser = $this->getUser();

        $auditLogService->log(
            'USER_UPDATED',
            sprintf('User "%s" was updated', $user->getEmail()),
            $currentUser instanceof User ? $currentUser : null,
            'User',
            $user->getId()
        );

        $entityManager->flush();

        return $apiResponse->success(
            $this->formatUser($user),
            'User updated successfully'
        );
    }

    #[OA\Delete(
        path: '/api/users/{id}',
        summary: 'Delete user',
        security: [['Bearer' => []]],
        tags: ['Users'],
        parameters: [
            new OA\Parameter(
                name: 'id',
                in: 'path',
                required: true,
                schema: new OA\Schema(type: 'integer', example: 1)
            ),
        ]
    )]
    #[Route('/api/users/{id}', name: 'api_user_delete', methods: ['DELETE'])]
    public function delete(
        User $user,
        EntityManagerInterface $entityManager,
        ApiResponseService $apiResponse,
        AuditLogService $auditLogService
    ): JsonResponse {
        $this->denyDeletedUser($user);

        /** @var User|null $currentUser */
        $currentUser = $this->getUser();

        if ($currentUser instanceof User && $currentUser->getId() === $user->getId()) {
            throw new ForbiddenException('You cannot delete your own account');
        }

        // Soft delete + désactivation
        $user->setDeletedAt(new \DateTimeImmutable());
        //$entityManager->remove($user);
        $user->setIsActive(false);

        $auditLogService->log(
            'USER_DELETED',
            sprintf('User "%s" was soft deleted', $user->getEmail()),
            $currentUser instanceof User ? $currentUser : null,
            'User',
            $user->getId()
        );

        $entityManager->flush();

        return $apiResponse->success(null, 'User disabled successfully');
    }

    private function formatUser(User $user): array
    {
        return [
            'id' => $user->getId(),
            'email' => $user->getEmail(),
            'roles' => $user->getRoles(),
            'isActive' => $user->isActive(),
            'deletedAt' => $user->getDeletedAt()?->format('Y-m-d H:i:s'),
        ];
    }

    private function denyDeletedUser(User $user): void
    {
        if ($user->getDeletedAt() !== null) {
            throw new NotFoundException('User not found');
        }
    }
}