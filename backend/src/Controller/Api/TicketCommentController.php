<?php

namespace App\Controller\Api;

use App\Dto\TicketComment\CreateTicketCommentDto;
use App\Entity\Ticket;
use App\Entity\TicketComment;
use App\Entity\User;
use App\Exception\ForbiddenException;
use App\Exception\NotFoundException;
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

class TicketCommentController extends AbstractController
{
    /*
    |--------------------------------------------------------------------------
    | GET COMMENTS OF A TICKET
    |--------------------------------------------------------------------------
    */

    #[Route(
        '/api/tickets/{id}/comments',
        name: 'api_ticket_comments',
        methods: ['GET']
    )]
    public function index(
        Ticket $ticket,
        ProjectSecurityService $projectSecurityService,
        ApiResponseService $apiResponse
    ): JsonResponse {

        /** @var User|null $currentUser */
        $currentUser = $this->getUser();

        // Vérifie authentification
        if (!$currentUser instanceof User) {
            throw new UnauthorizedException();
        }

        // Vérifie accès au projet
        if (
            !$projectSecurityService->isProjectMember(
                $ticket->getProject(),
                $currentUser
            )
        ) {
            throw new ForbiddenException(
                'Access denied to this project'
            );
        }

        $comments = [];

        // Formate les commentaires
        foreach ($ticket->getComments() as $comment) {

            $comments[] = $this->formatComment($comment);
        }

        return $apiResponse->success(
            $comments,
            'Ticket comments retrieved successfully'
        );
    }

    /*
    |--------------------------------------------------------------------------
    | CREATE COMMENT
    |--------------------------------------------------------------------------
    */

    #[Route(
        '/api/tickets/{id}/comments',
        name: 'api_ticket_comment_create',
        methods: ['POST']
    )]
    public function create(
        Ticket $ticket,
        Request $request,
        EntityManagerInterface $entityManager,
        ProjectSecurityService $projectSecurityService,
        ApiResponseService $apiResponse,
        ValidatorInterface $validator,
        SerializerInterface $serializer
    ): JsonResponse {

        /** @var User|null $currentUser */
        $currentUser = $this->getUser();

        // Vérifie authentification
        if (!$currentUser instanceof User) {
            throw new UnauthorizedException();
        }

        // Vérifie appartenance projet
        if (
            !$projectSecurityService->isProjectMember(
                $ticket->getProject(),
                $currentUser
            )
        ) {
            throw new ForbiddenException(
                'Access denied to this project'
            );
        }

        // Désérialisation JSON -> DTO
        $dto = $serializer->deserialize(
            $request->getContent(),
            CreateTicketCommentDto::class,
            'json'
        );

        // Validation DTO
        $errors = $validator->validate($dto);

        if (count($errors) > 0) {

            throw new ValidationException(
                $apiResponse->formatValidationErrors($errors)
            );
        }

        // Création commentaire
        $comment = new TicketComment();

        $comment->setContent($dto->content);
        $comment->setTicket($ticket);
        $comment->setCreatedBy($currentUser);

        // Validation entity
        $entityErrors = $validator->validate($comment);

        if (count($entityErrors) > 0) {

            throw new ValidationException(
                $apiResponse->formatValidationErrors($entityErrors)
            );
        }

        // Sauvegarde BDD
        $entityManager->persist($comment);
        $entityManager->flush();

        return $apiResponse->success(
            $this->formatComment($comment),
            'Comment created successfully',
            201
        );
    }

    /*
    |--------------------------------------------------------------------------
    | DELETE COMMENT
    |--------------------------------------------------------------------------
    */

    #[Route(
        '/api/ticket-comments/{id}',
        name: 'api_ticket_comment_delete',
        methods: ['DELETE']
    )]
    public function delete(
        TicketComment $comment,
        EntityManagerInterface $entityManager,
        ProjectSecurityService $projectSecurityService,
        ApiResponseService $apiResponse
    ): JsonResponse {

        /** @var User|null $currentUser */
        $currentUser = $this->getUser();

        // Vérifie authentification
        if (!$currentUser instanceof User) {
            throw new UnauthorizedException();
        }

        $project = $comment->getTicket()->getProject();

        // Vérifie si user est auteur
        $isAuthor =
            $comment->getCreatedBy()?->getId()
            === $currentUser->getId();

        // Vérifie si user est project manager
        $isProjectManager =
            $projectSecurityService->hasProjectRole(
                $project,
                $currentUser,
                'project_manager'
            );

        // Autorisation suppression
        if (!$isAuthor && !$isProjectManager) {

            throw new ForbiddenException(
                'You cannot delete this comment'
            );
        }

        // Suppression
        $entityManager->remove($comment);
        $entityManager->flush();

        return $apiResponse->success(
            null,
            'Comment deleted successfully'
        );
    }

    /*
    |--------------------------------------------------------------------------
    | FORMAT COMMENT
    |--------------------------------------------------------------------------
    */

    private function formatComment(
        TicketComment $comment
    ): array {
        return [
            'id' => $comment->getId(),

            'content' => $comment->getContent(),

            'createdAt' => $comment
                ->getCreatedAt()
                ?->format('Y-m-d H:i:s'),

            'createdBy' => [
                'id' => $comment
                    ->getCreatedBy()
                    ?->getId(),

                'email' => $comment
                    ->getCreatedBy()
                    ?->getEmail(),
            ],

            'ticket' => [
                'id' => $comment
                    ->getTicket()
                    ?->getId(),

                'title' => $comment
                    ->getTicket()
                    ?->getTitle(),
            ],
        ];
    }
}
