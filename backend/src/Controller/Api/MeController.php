<?php

namespace App\Controller\Api;

use App\Dto\Profile\ChangePasswordDto;
use App\Dto\Profile\UpdateProfileDto;
use App\Entity\User;
use App\Exception\UnauthorizedException;
use App\Exception\ValidationException;
use App\Service\ApiResponseService;
use Doctrine\ORM\EntityManagerInterface;
use OpenApi\Attributes as OA;
use Symfony\Component\HttpFoundation\File\Exception\FileException;
use Symfony\Component\String\Slugger\SluggerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Serializer\SerializerInterface;
use Symfony\Component\Validator\Validator\ValidatorInterface;

#[OA\Tag(name: 'Me')]
class MeController extends AbstractController
{
    #[Route('/api/me', name: 'api_me', methods: ['GET'])]
    public function me(ApiResponseService $apiResponse): JsonResponse
    {
        /** @var User|null $user */
        $user = $this->getUser();

        if (!$user instanceof User) {
            throw new UnauthorizedException();
        }

        return $apiResponse->success(
            $this->formatUser($user),
            'Authenticated user retrieved successfully'
        );
    }

    #[OA\Put(
        path: '/api/me',
        summary: 'Update authenticated user profile',
        security: [['Bearer' => []]],
        tags: ['Me']
    )]
    #[Route('/api/me', name: 'api_me_update', methods: ['PUT'])]
    public function updateProfile(
        Request $request,
        EntityManagerInterface $entityManager,
        ApiResponseService $apiResponse,
        ValidatorInterface $validator,
        SerializerInterface $serializer
    ): JsonResponse {
        /** @var User|null $user */
        $user = $this->getUser();

        if (!$user instanceof User) {
            throw new UnauthorizedException();
        }

        $dto = $serializer->deserialize(
            $request->getContent(),
            UpdateProfileDto::class,
            'json'
        );

        $errors = $validator->validate($dto);

        if (count($errors) > 0) {
            throw new ValidationException(
                $apiResponse->formatValidationErrors($errors)
            );
        }

        $user->setEmail($dto->email);

        $entityManager->flush();

        return $apiResponse->success(
            $this->formatUser($user),
            'Profile updated successfully'
        );
    }

    #[OA\Patch(
        path: '/api/me/password',
        summary: 'Change authenticated user password',
        security: [['Bearer' => []]],
        tags: ['Me']
    )]
    #[Route('/api/me/password', name: 'api_me_password', methods: ['PATCH'])]
    public function changePassword(
        Request $request,
        EntityManagerInterface $entityManager,
        ApiResponseService $apiResponse,
        ValidatorInterface $validator,
        SerializerInterface $serializer,
        UserPasswordHasherInterface $passwordHasher
    ): JsonResponse {
        /** @var User|null $user */
        $user = $this->getUser();

        if (!$user instanceof User) {
            throw new UnauthorizedException();
        }

        $dto = $serializer->deserialize(
            $request->getContent(),
            ChangePasswordDto::class,
            'json'
        );

        $errors = $validator->validate($dto);

        if (count($errors) > 0) {
            throw new ValidationException(
                $apiResponse->formatValidationErrors($errors)
            );
        }

        if (!$passwordHasher->isPasswordValid($user, $dto->currentPassword)) {
            throw new ValidationException([
                [
                    'field' => 'currentPassword',
                    'message' => 'Current password is invalid',
                ],
            ]);
        }

        $user->setPassword(
            $passwordHasher->hashPassword($user, $dto->newPassword)
        );

        $entityManager->flush();

        return $apiResponse->success(
            null,
            'Password changed successfully'
        );
    }

    #[Route('/api/me/avatar', name: 'api_me_avatar_upload', methods: ['POST'])]
    public function uploadAvatar(
        Request $request,
        EntityManagerInterface $entityManager,
        ApiResponseService $apiResponse,
        SluggerInterface $slugger
    ): JsonResponse {
        /** @var User|null $user */
        $user = $this->getUser();

        if (!$user instanceof User) {
            throw new UnauthorizedException();
        }

        $file = $request->files->get('avatar');

        if (!$file) {
            throw new ValidationException([
                ['field' => 'avatar', 'message' => 'Avatar file is required'],
            ]);
        }

        $mimeType = $file->getMimeType();
        $originalName = $file->getClientOriginalName();

        if (!in_array($mimeType, ['image/jpeg', 'image/png', 'image/webp'], true)) {
            throw new ValidationException([
                ['field' => 'avatar', 'message' => 'Invalid avatar type'],
            ]);
        }

        if ($file->getSize() > 2 * 1024 * 1024) {
            throw new ValidationException([
                ['field' => 'avatar', 'message' => 'Avatar too large (max 2MB)'],
            ]);
        }

        if ($user->getAvatar()) {
            $oldPath = $this->getParameter('kernel.project_dir') . '/public/uploads/avatars/' . $user->getAvatar();

            if (file_exists($oldPath)) {
                unlink($oldPath);
            }
        }

        $safeName = $slugger->slug(pathinfo($originalName, PATHINFO_FILENAME));
        $newFilename = $safeName . '-' . uniqid() . '.' . $file->guessExtension();

        try {
            $file->move(
                $this->getParameter('kernel.project_dir') . '/public/uploads/avatars',
                $newFilename
            );
        } catch (FileException) {
            throw new ValidationException([
                ['field' => 'avatar', 'message' => 'Avatar upload failed'],
            ]);
        }

        $user->setAvatar($newFilename);

        $entityManager->flush();

        return $apiResponse->success(
            $this->formatUser($user),
            'Avatar uploaded successfully'
        );
    }

    #[Route('/api/me/avatar', name: 'api_me_avatar_delete', methods: ['DELETE'])]
    public function deleteAvatar(
        EntityManagerInterface $entityManager,
        ApiResponseService $apiResponse
    ): JsonResponse {
        /** @var User|null $user */
        $user = $this->getUser();

        if (!$user instanceof User) {
            throw new UnauthorizedException();
        }

        if ($user->getAvatar()) {
            $filePath = $this->getParameter('kernel.project_dir') . '/public/uploads/avatars/' . $user->getAvatar();

            if (file_exists($filePath)) {
                unlink($filePath);
            }

            $user->setAvatar(null);
            $entityManager->flush();
        }

        return $apiResponse->success(
            $this->formatUser($user),
            'Avatar deleted successfully'
        );
    }

    private function formatUser(User $user): array
    {
        return [
            'id' => $user->getId(),
            'email' => $user->getEmail(),
            'roles' => $user->getRoles(),
            'avatar' => $user->getAvatar()
                ? '/uploads/avatars/' . $user->getAvatar()
                : null,
        ];
    }
}