<?php

namespace App\Controller\Api;

use App\Entity\Ticket;
use App\Entity\TicketAttachment;
use App\Entity\User;
use App\Exception\ForbiddenException;
use App\Exception\NotFoundException;
use App\Exception\UnauthorizedException;
use App\Exception\ValidationException;
use App\Service\ApiResponseService;
use App\Service\ProjectSecurityService;
use App\Service\TicketActivityService;
use App\Service\NotificationService;
use Doctrine\ORM\EntityManagerInterface;
use OpenApi\Attributes as OA;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\File\Exception\FileException;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\String\Slugger\SluggerInterface;

#[OA\Tag(name: 'Ticket Attachments')]
class TicketAttachmentController extends AbstractController
{
    /*
    |--------------------------------------------------------------------------
    | GET ATTACHMENTS
    |--------------------------------------------------------------------------
    */

    #[OA\Get(
        path: '/api/tickets/{id}/attachments',
        summary: 'List ticket attachments',
        description: 'Retrieve attachments for a ticket.',
        security: [['Bearer' => []]],
        tags: ['Ticket Attachments'],
        parameters: [
            new OA\Parameter(
                name: 'id',
                in: 'path',
                required: true,
                schema: new OA\Schema(type: 'integer', example: 1)
            ),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Attachments retrieved successfully'),
            new OA\Response(response: 401, description: 'User not authenticated'),
            new OA\Response(response: 403, description: 'Access denied to this project'),
        ]
    )]
    #[Route(
        '/api/tickets/{id}/attachments',
        name: 'api_ticket_attachments',
        methods: ['GET']
    )]
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

        // Vérifie accès projet
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

        $attachments = [];

        foreach ($ticket->getAttachments() as $attachment) {

            $attachments[] = $this->formatAttachment(
                $attachment
            );
        }

        return $apiResponse->success(
            $attachments,
            'Attachments retrieved successfully'
        );
    }

    /*
    |--------------------------------------------------------------------------
    | UPLOAD ATTACHMENT
    |--------------------------------------------------------------------------
    */

    #[OA\Post(
        path: '/api/tickets/{id}/attachments',
        summary: 'Upload ticket attachment',
        description: 'Upload an attachment for a ticket. Allowed files: JPG, PNG, WEBP, PDF. Max size: 5MB.',
        security: [['Bearer' => []]],
        tags: ['Ticket Attachments'],
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
            content: new OA\MediaType(
                mediaType: 'multipart/form-data',
                schema: new OA\Schema(
                    required: ['file'],
                    properties: [
                        new OA\Property(
                            property: 'file',
                            type: 'string',
                            format: 'binary',
                            description: 'Attachment file'
                        ),
                    ]
                )
            )
        ),
        responses: [
            new OA\Response(response: 201, description: 'Attachment uploaded successfully'),
            new OA\Response(response: 401, description: 'User not authenticated'),
            new OA\Response(response: 403, description: 'Access denied to this project'),
            new OA\Response(response: 422, description: 'Validation failed'),
        ]
    )]
    #[Route(
        '/api/tickets/{id}/attachments',
        name: 'api_ticket_attachment_upload',
        methods: ['POST']
    )]
    public function upload(
        Ticket $ticket,
        Request $request,
        EntityManagerInterface $entityManager,
        ProjectSecurityService $projectSecurityService,
        TicketActivityService $ticketActivityService,
        ApiResponseService $apiResponse,
        SluggerInterface $slugger,
        NotificationService $notificationService
    ): JsonResponse {

        /** @var User|null $currentUser */
        $currentUser = $this->getUser();

        if (!$currentUser instanceof User) {
            throw new UnauthorizedException();
        }

        if (!$projectSecurityService->isProjectMember($ticket->getProject(), $currentUser)) {
            throw new ForbiddenException('Access denied to this project');
        }

        $file = $request->files->get('file');

        if (!$file) {
            throw new ValidationException([
                ['field' => 'file', 'message' => 'File is required'],
            ]);
        }

        $originalName = $file->getClientOriginalName();
        $mimeType = $file->getMimeType();
        $size = $file->getSize();

        $allowedMimeTypes = [
            'image/jpeg',
            'image/png',
            'image/webp',
            'application/pdf',
        ];

        if (!in_array($mimeType, $allowedMimeTypes, true)) {
            throw new ValidationException([
                ['field' => 'file', 'message' => 'Invalid file type'],
            ]);
        }

        $maxSize = 5 * 1024 * 1024;

        if ($size > $maxSize) {
            throw new ValidationException([
                ['field' => 'file', 'message' => 'File too large (max 5MB)'],
            ]);
        }

        $originalFilename = pathinfo($originalName, PATHINFO_FILENAME);
        $safeFilename = $slugger->slug($originalFilename);

        $newFilename = $safeFilename . '-' . uniqid() . '.' . $file->guessExtension();

        try {
            $file->move(
                $this->getParameter('kernel.project_dir') . '/public/uploads/tickets',
                $newFilename
            );
        } catch (FileException $e) {
            throw new ValidationException([
                ['field' => 'file', 'message' => 'File upload failed'],
            ]);
        }

        $attachment = new TicketAttachment();
        $attachment->setFilename($newFilename);
        $attachment->setOriginalName($originalName);
        $attachment->setMimeType($mimeType);
        $attachment->setSize($size);
        $attachment->setTicket($ticket);
        $attachment->setCreatedBy($currentUser);

        $entityManager->persist($attachment);

        $ticketActivityService->log(
            $ticket,
            $currentUser,
            'attachment_added',
            sprintf(
                '%s uploaded attachment "%s"',
                $currentUser->getEmail(),
                $originalName
            )
        );

        $entityManager->flush();

        /*
        |--------------------------------------------------------------------------
        | NOTIFICATION
        |--------------------------------------------------------------------------
        */

        // Notification utilisateur assigné
        if (
            $ticket->getAssignedTo()
            &&
            $ticket->getAssignedTo()->getId()
            !== $currentUser->getId()
        ) {

            $notificationService->attachmentUploaded(
                $ticket->getAssignedTo(),
                $ticket->getTitle()
            );
        }

        return $apiResponse->success(
            $this->formatAttachment($attachment),
            'Attachment uploaded successfully',
            201
        );
    }

    /*
    |--------------------------------------------------------------------------
    | DELETE ATTACHMENT
    |--------------------------------------------------------------------------
    */

    #[OA\Delete(
        path: '/api/ticket-attachments/{id}',
        summary: 'Delete ticket attachment',
        description: 'Delete an attachment. Only the uploader or a project manager can delete it.',
        security: [['Bearer' => []]],
        tags: ['Ticket Attachments'],
        parameters: [
            new OA\Parameter(
                name: 'id',
                in: 'path',
                required: true,
                schema: new OA\Schema(type: 'integer', example: 1)
            ),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Attachment deleted successfully'),
            new OA\Response(response: 401, description: 'User not authenticated'),
            new OA\Response(response: 403, description: 'You cannot delete this attachment'),
            new OA\Response(response: 404, description: 'Attachment not found'),
        ]
    )]
    #[Route(
        '/api/ticket-attachments/{id}',
        name: 'api_ticket_attachment_delete',
        methods: ['DELETE']
    )]
    public function delete(
        TicketAttachment $attachment,
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

        $project = $attachment
            ->getTicket()
            ->getProject();

        // Auteur upload
        $isAuthor =
            $attachment->getCreatedBy()?->getId()
            === $currentUser->getId();

        // Chef projet
        $isProjectManager =
            $projectSecurityService->hasProjectRole(
                $project,
                $currentUser,
                'project_manager'
            );

        if (!$isAuthor && !$isProjectManager) {

            throw new ForbiddenException(
                'You cannot delete this attachment'
            );
        }

        /*
        |--------------------------------------------------------------------------
        | DELETE PHYSICAL FILE
        |--------------------------------------------------------------------------
        */

        $filePath =
            $this->getParameter(
                'kernel.project_dir'
            )
            . '/public/uploads/tickets/'
            . $attachment->getFilename();

        if (file_exists($filePath)) {
            unlink($filePath);
        }

        /*
        |--------------------------------------------------------------------------
        | ACTIVITY LOG
        |--------------------------------------------------------------------------
        */

        $ticketActivityService->log(
            $attachment->getTicket(),
            $currentUser,
            'attachment_deleted',
            sprintf(
                '%s deleted attachment "%s"',
                $currentUser->getEmail(),
                $attachment->getOriginalName()
            )
        );

        $entityManager->remove($attachment);

        $entityManager->flush();

        return $apiResponse->success(
            null,
            'Attachment deleted successfully'
        );
    }

    /*
    |--------------------------------------------------------------------------
    | FORMAT ATTACHMENT
    |--------------------------------------------------------------------------
    */

    private function formatAttachment(
        TicketAttachment $attachment
    ): array {

        return [
            'id' => $attachment->getId(),

            'filename' => $attachment->getFilename(),

            'originalName' => $attachment->getOriginalName(),

            'mimeType' => $attachment->getMimeType(),

            'size' => $attachment->getSize(),

            'url' => '/uploads/tickets/'
                . $attachment->getFilename(),

            'createdAt' => $attachment
                ->getCreatedAt()
                ?->format('Y-m-d H:i:s'),

            'createdBy' => [
                'id' => $attachment
                    ->getCreatedBy()
                    ?->getId(),

                'email' => $attachment
                    ->getCreatedBy()
                    ?->getEmail(),
            ],
        ];
    }
}