<?php

namespace App\Command;

use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

#[AsCommand(
    name: 'app:create-user',
    description: 'Create a test user with a hashed password'
)]
class CreateUserCommand extends Command
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private UserPasswordHasherInterface $passwordHasher
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        // Création d’un nouvel utilisateur
        $user = new User();

        // Email utilisé pour tester la connexion JWT
        $user->setEmail('admin@example.com');

        // Rôle administrateur pour nos futurs tests
        $user->setRoles(['ROLE_ADMIN']);

        // Hash sécurisé du mot de passe
        $hashedPassword = $this->passwordHasher->hashPassword(
            $user,
            'password123'
        );

        // Enregistrement du mot de passe hashé
        $user->setPassword($hashedPassword);

        // Prépare l’objet pour l’insertion en base
        $this->entityManager->persist($user);

        // Exécute réellement l’insertion SQL
        $this->entityManager->flush();

        $output->writeln('User created successfully: admin@example.com');

        return Command::SUCCESS;
    }
}