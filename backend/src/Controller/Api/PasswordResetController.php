<?php

namespace App\Controller\Api;

use App\Dto\Auth\ForgotPasswordDto;
use App\Dto\Auth\ResetPasswordDto;
use App\Entity\User;
use App\Exception\ValidationException;
use App\Repository\UserRepository;
use App\Service\ApiResponseService;
use Doctrine\ORM\EntityManagerInterface;
use OpenApi\Attributes as OA;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Serializer\SerializerInterface;
use Symfony\Component\Validator\Validator\ValidatorInterface;

#[OA\Tag(name: 'Password Reset')]
class PasswordResetController extends AbstractController
{
    #[OA\Post(
        path: '/api/forgot-password',
        summary: 'Request password reset',
        tags: ['Password Reset']
    )]
    #[Route('/api/forgot-password', methods: ['POST'])]
    public function forgotPassword(
        Request $request,
        SerializerInterface $serializer,
        ValidatorInterface $validator,
        UserRepository $userRepository,
        EntityManagerInterface $entityManager,
        ApiResponseService $apiResponse
    ): JsonResponse {

        $dto = $serializer->deserialize(
            $request->getContent(),
            ForgotPasswordDto::class,
            'json'
        );

        $errors = $validator->validate($dto);

        if (count($errors) > 0) {
            throw new ValidationException(
                $apiResponse->formatValidationErrors($errors)
            );
        }

        $user = $userRepository->findOneBy([
            'email' => $dto->email,
        ]);

        if ($user instanceof User) {

            $user->setResetToken(
                bin2hex(random_bytes(32))
            );

            $user->setResetTokenExpireAt(
                new \DateTimeImmutable('+1 hour')
            );

            $entityManager->flush();

            return $apiResponse->success(
                [
                    'token' => $user->getResetToken(),
                    'expiresAt' => $user
                        ->getResetTokenExpireAt()
                        ?->format('Y-m-d H:i:s'),
                ],
                'Reset token generated successfully'
            );
        }

        return $apiResponse->success(
            null,
            'If the email exists, a reset link has been generated'
        );
    }

    #[OA\Post(
        path: '/api/reset-password',
        summary: 'Reset password',
        tags: ['Password Reset']
    )]
    #[Route('/api/reset-password', methods: ['POST'])]
    public function resetPassword(
        Request $request,
        SerializerInterface $serializer,
        ValidatorInterface $validator,
        UserRepository $userRepository,
        EntityManagerInterface $entityManager,
        UserPasswordHasherInterface $passwordHasher,
        ApiResponseService $apiResponse
    ): JsonResponse {

        $dto = $serializer->deserialize(
            $request->getContent(),
            ResetPasswordDto::class,
            'json'
        );

        $errors = $validator->validate($dto);

        if (count($errors) > 0) {
            throw new ValidationException(
                $apiResponse->formatValidationErrors($errors)
            );
        }

        $user = $userRepository->findOneBy([
            'resetToken' => $dto->token,
        ]);

        if (
            !$user instanceof User
            || !$user->getResetTokenExpireAt()
            || $user->getResetTokenExpireAt() < new \DateTimeImmutable()
        ) {
            throw new ValidationException([
                [
                    'field' => 'token',
                    'message' => 'Invalid or expired token',
                ],
            ]);
        }

        $user->setPassword(
            $passwordHasher->hashPassword(
                $user,
                $dto->password
            )
        );

        $user->setResetToken(null);
        $user->setResetTokenExpireAt(null);

        $entityManager->flush();

        return $apiResponse->success(
            null,
            'Password reset successfully'
        );
    }
}