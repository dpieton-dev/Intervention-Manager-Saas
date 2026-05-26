<?php

namespace App\Controller\Api;

use App\Dto\TicketComment\CreateTicketCommentDto;
use App\Entity\Ticket;
use App\Entity\TicketComment;
use App\Entity\User;
use App\Exception\ForbiddenException;
use App\Exception\UnauthorizedException;
use App\Exception\ValidationException;
use App\Service\ApiResponseService;
use App\Service\ProjectSecurityService;
use App\Service\TicketActivityService;
use Doctrine\ORM\EntityManagerInterface;
use OpenApi\Attributes as OA;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Serializer\SerializerInterface;
use Symfony\Component\Validator\Validator\ValidatorInterface;

#[OA\Tag(name: 'Ticket Comments')]
class TicketCommentController extends AbstractController
{
    #[OA\Get(
        path: '/api/tickets/{id}/comments',
        summary: 'List ticket comments',
        description: 'Retrieve comments for a ticket.',
        security: [['Bearer' => []]],
        tags: ['Ticket Comments'],
        parameters: [
            new OA\Parameter(
                name: 'id',
                in: 'path',
                required: true,
                schema: new OA\Schema(type: 'integer', example: 1)
            ),
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Ticket comments retrieved successfully'
            ),
            new OA\Response(
                response: 401,
                description: 'JWT token missing or invalid'
            ),
            new OA\Response(
                response: 403,
                description: 'Access denied to this project'
            ),
        ]
    )]
    #[Route('/api/tickets/{id}/comments', name: 'api_ticket_comments', methods: ['GET'])]
    public function index(
        Ticket $ticket,
        ProjectSecurityService $projectSecurityService,
        ApiResponseService $apiResponse
    ): JsonResponse {
        /** @var User|null $currentUser */
        $currentUser = $this->getUser();

        if (!$currentUser instanceof User) {
            throw new UnauthorizedException();
        }

        // Sécurité : seuls les membres du projet peuvent lire les commentaires
        if (!$projectSecurityService->isProjectMember($ticket->getProject(), $currentUser)) {
            throw new ForbiddenException('Access denied to this project');
        }

        $comments = [];

        foreach ($ticket->getComments() as $comment) {
            $comments[] = $this->formatComment($comment);
        }

        return $apiResponse->success(
            $comments,
            'Ticket comments retrieved successfully'
        );
    }

    #[OA\Post(
        path: '/api/tickets/{id}/comments',
        summary: 'Create ticket comment',
        description: 'Add a comment to a ticket.',
        security: [['Bearer' => []]],
        tags: ['Ticket Comments'],
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
                required: ['content'],
                properties: [
                    new OA\Property(
                        property: 'content',
                        type: 'string',
                        example: 'Le bug a été reproduit.'
                    ),
                ]
            )
        ),
        responses: [
            new OA\Response(
                response: 201,
                description: 'Comment created successfully'
            ),
            new OA\Response(
                response: 401,
                description: 'User not authenticated'
            ),
            new OA\Response(
                response: 403,
                description: 'Access denied to this project'
            ),
            new OA\Response(
                response: 422,
                description: 'Validation failed'
            ),
        ]
    )]
    #[Route('/api/tickets/{id}/comments', name: 'api_ticket_comment_create', methods: ['POST'])]
    public function create(
        Ticket $ticket,
        Request $request,
        EntityManagerInterface $entityManager,
        ProjectSecurityService $projectSecurityService,
        TicketActivityService $ticketActivityService,
        ApiResponseService $apiResponse,
        ValidatorInterface $validator,
        SerializerInterface $serializer
    ): JsonResponse {
        /** @var User|null $currentUser */
        $currentUser = $this->getUser();

        if (!$currentUser instanceof User) {
            throw new UnauthorizedException();
        }

        // Sécurité : seuls les membres peuvent commenter
        if (!$projectSecurityService->isProjectMember($ticket->getProject(), $currentUser)) {
            throw new ForbiddenException('Access denied to this project');
        }

        $dto = $serializer->deserialize(
            $request->getContent(),
            CreateTicketCommentDto::class,
            'json'
        );

        $errors = $validator->validate($dto);

        if (count($errors) > 0) {
            throw new ValidationException(
                $apiResponse->formatValidationErrors($errors)
            );
        }

        $comment = new TicketComment();
        $comment->setContent($dto->content);
        $comment->setTicket($ticket);
        $comment->setCreatedBy($currentUser);

        $entityErrors = $validator->validate($comment);

        if (count($entityErrors) > 0) {
            throw new ValidationException(
                $apiResponse->formatValidationErrors($entityErrors)
            );
        }

        $entityManager->persist($comment);

        // Historique : commentaire ajouté
        $ticketActivityService->logCommentAdded(
            $ticket,
            $currentUser
        );

        $entityManager->flush();

        return $apiResponse->success(
            $this->formatComment($comment),
            'Comment created successfully',
            201
        );
    }

    #[OA\Delete(
        path: '/api/ticket-comments/{id}',
        summary: 'Delete ticket comment',
        description: 'Delete a ticket comment.',
        security: [['Bearer' => []]],
        tags: ['Ticket Comments'],
        parameters: [
            new OA\Parameter(
                name: 'id',
                in: 'path',
                required: true,
                schema: new OA\Schema(type: 'integer', example: 1)
            ),
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Comment deleted successfully'
            ),
            new OA\Response(
                response: 401,
                description: 'User not authenticated'
            ),
            new OA\Response(
                response: 403,
                description: 'You cannot delete this comment'
            ),
        ]
    )]
    #[Route('/api/ticket-comments/{id}', name: 'api_ticket_comment_delete', methods: ['DELETE'])]
    public function delete(
        TicketComment $comment,
        EntityManagerInterface $entityManager,
        ProjectSecurityService $projectSecurityService,
        TicketActivityService $ticketActivityService,
        ApiResponseService $apiResponse
    ): JsonResponse {
        /** @var User|null $currentUser */
        $currentUser = $this->getUser();

        if (!$currentUser instanceof User) {
            throw new UnauthorizedException();
        }

        $project = $comment->getTicket()->getProject();

        // Auteur du commentaire
        $isAuthor = $comment->getCreatedBy()?->getId() === $currentUser->getId();

        // Chef de projet
        $isProjectManager = $projectSecurityService->hasProjectRole(
            $project,
            $currentUser,
            'project_manager'
        );

        // Seul l’auteur ou le chef de projet peut supprimer
        if (!$isAuthor && !$isProjectManager) {
            throw new ForbiddenException('You cannot delete this comment');
        }

        // Historique avant suppression
        $ticketActivityService->logCommentDeleted(
            $comment->getTicket(),
            $currentUser
        );

        $entityManager->remove($comment);
        $entityManager->flush();

        return $apiResponse->success(
            null,
            'Comment deleted successfully'
        );
    }

    private function formatComment(TicketComment $comment): array
    {
        return [
            'id' => $comment->getId(),
            'content' => $comment->getContent(),
            'createdAt' => $comment->getCreatedAt()?->format('Y-m-d H:i:s'),

            'createdBy' => [
                'id' => $comment->getCreatedBy()?->getId(),
                'email' => $comment->getCreatedBy()?->getEmail(),
            ],

            'ticket' => [
                'id' => $comment->getTicket()?->getId(),
                'title' => $comment->getTicket()?->getTitle(),
            ],
        ];
    }
}