<?php

namespace App\Controller\Api;

use App\Repository\TicketRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;
use App\Entity\Ticket;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\Request;

class TicketController extends AbstractController
{
    #[Route('/api/tickets', name: 'api_tickets', methods: ['GET'])]
    public function index(TicketRepository $ticketRepository): JsonResponse
    {
        $tickets = $ticketRepository->findAll();

        $data = [];

        foreach ($tickets as $ticket) {
            $data[] = [
                'id' => $ticket->getId(),
                'title' => $ticket->getTitle(),
                'description' => $ticket->getDescription(),
                'status' => $ticket->getStatus(),
                'priority' => $ticket->getPriority(),
                'createdAt' => $ticket->getCreatedAt()?->format('Y-m-d H:i:s'),
            ];
        }

        return $this->json($data);
    }

    #[Route('/api/tickets', name: 'api_ticket_create', methods: ['POST'])]
    public function create(Request $request, EntityManagerInterface $entityManager): JsonResponse 
    {
        // Récuération des données JSON envoyées
        $data = json_decode($request->getContent(), true);
        
        // Création du ticket
        $ticket = new Ticket();

        // Remplissage des attributs de ticket
        $ticket->setTitle($data['title'] ?? '');
        $ticket->setDescription($data['description'] ?? '');
        $ticket->setPriority($data['priority'] ?? '');

        // Sauvegarde en BDD
        $entityManager->persist($ticket);
        $entityManager->flush();

        // Réponse JSON
        return $this->json([
            'message' => 'Ticket created successfully',
            'ticket' => [
                'id' => $ticket->getId(),
                'title' => $ticket->getTitle(),
                'status' => $ticket->getStatus(),
            ]
        ], 201);
    }
}
