<?php

namespace App\Repository;

use App\Entity\Ticket;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Ticket>
 */
class TicketRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Ticket::class);
    }

    public function findFilteredTickets(
        array $filters,
        int $page = 1,
        int $limit = 10
    ): array {
        $queryBuilder = $this->createQueryBuilder('t')
            ->leftJoin('t.project', 'p')
            ->leftJoin('t.assignedTo', 'a')
            ->leftJoin('t.createdBy', 'c');

        /*
        |--------------------------------------------------------------------------
        | FILTERS
        |--------------------------------------------------------------------------
        */

        // Status
        if (!empty($filters['status'])) {
            $queryBuilder
                ->andWhere('t.status = :status')
                ->setParameter('status', $filters['status']);
        }

        // Priority
        if (!empty($filters['priority'])) {
            $queryBuilder
                ->andWhere('t.priority = :priority')
                ->setParameter('priority', $filters['priority']);
        }

        // Project
        if (!empty($filters['project'])) {
            $queryBuilder
                ->andWhere('p.id = :project')
                ->setParameter('project', $filters['project']);
        }

        // Assigned To
        if (!empty($filters['assignedTo'])) {
            $queryBuilder
                ->andWhere('a.id = :assignedTo')
                ->setParameter('assignedTo', $filters['assignedTo']);
        }

        // Created By
        if (!empty($filters['createdBy'])) {
            $queryBuilder
                ->andWhere('c.id = :createdBy')
                ->setParameter('createdBy', $filters['createdBy']);
        }

        // Search
        if (!empty($filters['search'])) {
            $queryBuilder
                ->andWhere('t.title LIKE :search OR t.description LIKE :search')
                ->setParameter(
                    'search',
                    '%' . $filters['search'] . '%'
                );
        }

        /*
        |--------------------------------------------------------------------------
        | PAGINATION
        |--------------------------------------------------------------------------
        */

        $queryBuilder
            ->setFirstResult(($page - 1) * $limit)
            ->setMaxResults($limit)
            ->orderBy('t.createdAt', 'DESC');

        /*
        |--------------------------------------------------------------------------
        | RESULTS
        |--------------------------------------------------------------------------
        */

        $tickets = $queryBuilder
            ->getQuery()
            ->getResult();

        /*
        |--------------------------------------------------------------------------
        | TOTAL
        |--------------------------------------------------------------------------
        */

        $countQueryBuilder = clone $queryBuilder;

        $total = count(
            $countQueryBuilder
                ->getQuery()
                ->getResult()
        );

        return [
            'data' => $tickets,
            'pagination' => [
                'page' => $page,
                'limit' => $limit,
                'total' => $total,
            ],
        ];
    }
    
    //    /**
    //     * @return Ticket[] Returns an array of Ticket objects
    //     */
    //    public function findByExampleField($value): array
    //    {
    //        return $this->createQueryBuilder('t')
    //            ->andWhere('t.exampleField = :val')
    //            ->setParameter('val', $value)
    //            ->orderBy('t.id', 'ASC')
    //            ->setMaxResults(10)
    //            ->getQuery()
    //            ->getResult()
    //        ;
    //    }

    //    public function findOneBySomeField($value): ?Ticket
    //    {
    //        return $this->createQueryBuilder('t')
    //            ->andWhere('t.exampleField = :val')
    //            ->setParameter('val', $value)
    //            ->getQuery()
    //            ->getOneOrNullResult()
    //        ;
    //    }
}
