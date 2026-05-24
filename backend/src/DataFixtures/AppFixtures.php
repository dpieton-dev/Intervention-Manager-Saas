<?php

namespace App\DataFixtures;

use App\Entity\Project;
use App\Entity\ProjectMember;
use App\Entity\ProjectRole;
use App\Entity\Ticket;
use App\Entity\User;
use App\Entity\TicketComment;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

class AppFixtures extends Fixture
{
    public function __construct(
        private UserPasswordHasherInterface $passwordHasher
    ) {
    }

    public function load(ObjectManager $manager): void
    {
        /*
        |--------------------------------------------------------------------------
        | USERS
        |--------------------------------------------------------------------------
        */

        $admin = new User();
        $admin->setEmail('admin@example.com');
        $admin->setRoles(['ROLE_ADMIN', 'ROLE_USER']);
        $admin->setPassword(
            $this->passwordHasher->hashPassword(
                $admin,
                'password123'
            )
        );

        $manager->persist($admin);

        $developer = new User();
        $developer->setEmail('developer@example.com');
        $developer->setRoles(['ROLE_USER']);
        $developer->setPassword(
            $this->passwordHasher->hashPassword(
                $developer,
                'password123'
            )
        );

        $manager->persist($developer);

        /*
        |--------------------------------------------------------------------------
        | PROJECT
        |--------------------------------------------------------------------------
        */

        $project = new Project();
        $project->setName('Intervention Manager SaaS');
        $project->setDescription('Plateforme SaaS de gestion de tickets et projets');
        $project->setStatus('active');
        $project->setStartDate(new \DateTimeImmutable('2026-05-01'));
        $project->setEndDate(new \DateTimeImmutable('2026-12-31'));

        $manager->persist($project);

        /*
        |--------------------------------------------------------------------------
        | PROJECT ROLES
        |--------------------------------------------------------------------------
        */

        $projectManagerRole = new ProjectRole();
        $projectManagerRole->setProject($project);
        $projectManagerRole->setName('Chef de projet');
        $projectManagerRole->setCode('project_manager');
        $projectManagerRole->setDescription('Gestion complète du projet');

        $manager->persist($projectManagerRole);

        $developerRole = new ProjectRole();
        $developerRole->setProject($project);
        $developerRole->setName('Développeur');
        $developerRole->setCode('developer');
        $developerRole->setDescription('Développement des fonctionnalités');

        $manager->persist($developerRole);

        /*
        |--------------------------------------------------------------------------
        | PROJECT MEMBERS
        |--------------------------------------------------------------------------
        */

        $adminMember = new ProjectMember();
        $adminMember->setProject($project);
        $adminMember->setUser($admin);
        $adminMember->setProjectRole($projectManagerRole);

        $manager->persist($adminMember);

        $developerMember = new ProjectMember();
        $developerMember->setProject($project);
        $developerMember->setUser($developer);
        $developerMember->setProjectRole($developerRole);

        $manager->persist($developerMember);

        /*
        |--------------------------------------------------------------------------
        | TICKETS
        |--------------------------------------------------------------------------
        */

        $ticket1 = new Ticket();
        $ticket1->setTitle('Créer authentification JWT');
        $ticket1->setDescription('Mise en place de la sécurité JWT avec Symfony');
        $ticket1->setStatus('done');
        $ticket1->setPriority('high');
        $ticket1->setProject($project);
        $ticket1->setCreatedBy($admin);
        $ticket1->setAssignedTo($developer);

        $manager->persist($ticket1);

        $ticket2 = new Ticket();
        $ticket2->setTitle('Créer board Kanban');
        $ticket2->setDescription('Développement du board Kanban Angular');
        $ticket2->setStatus('in_progress');
        $ticket2->setPriority('medium');
        $ticket2->setProject($project);
        $ticket2->setCreatedBy($admin);
        $ticket2->setAssignedTo($developer);

        $manager->persist($ticket2);

        $ticket3 = new Ticket();
        $ticket3->setTitle('Créer système notifications');
        $ticket3->setDescription('Notifications temps réel WebSocket');
        $ticket3->setStatus('todo');
        $ticket3->setPriority('urgent');
        $ticket3->setProject($project);
        $ticket3->setCreatedBy($admin);

        $manager->persist($ticket3);

        /*
        |--------------------------------------------------------------------------
        | COMMENTS
        |--------------------------------------------------------------------------
        */

        $comment1 = new TicketComment();
        $comment1->setContent('Le système JWT est terminé');
        $comment1->setTicket($ticket1);
        $comment1->setCreatedBy($admin);

        $manager->persist($comment1);

        $comment2 = new TicketComment();
        $comment2->setContent('Le board Angular est en cours');
        $comment2->setTicket($ticket2);
        $comment2->setCreatedBy($developer);

        $manager->persist($comment2);

        /*
        |--------------------------------------------------------------------------
        | FLUSH
        |--------------------------------------------------------------------------
        */

        $manager->flush();
    }
}
