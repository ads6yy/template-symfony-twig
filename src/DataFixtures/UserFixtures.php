<?php

declare(strict_types=1);

namespace App\DataFixtures;

use App\Entity\User;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

final class UserFixtures extends Fixture
{
    public const string ADMIN_USER_REFERENCE = 'admin-user';
    public const string USER_USER_REFERENCE = 'user-user';

    public function __construct(
        private readonly UserPasswordHasherInterface $passwordHasher,
    ) {
    }

    public function load(ObjectManager $manager): void
    {
        // Données de test provenant de test_users.php
        $testUsers = [
            [
                'email' => 'admin@example.com',
                'firstName' => 'Admin',
                'lastName' => 'System',
                'password' => 'Admin123!@#',
                'roles' => ['ROLE_ADMIN'],
                'isActive' => true,
                'reference' => self::ADMIN_USER_REFERENCE,
            ],
            [
                'email' => 'user@example.com',
                'firstName' => 'John',
                'lastName' => 'Doe',
                'password' => 'User123!@#',
                'roles' => ['ROLE_USER'],
                'isActive' => true,
                'reference' => self::USER_USER_REFERENCE,
            ],
            [
                'email' => 'jane.smith@example.com',
                'firstName' => 'Jane',
                'lastName' => 'Smith',
                'password' => 'Jane123!@#',
                'roles' => ['ROLE_USER'],
                'isActive' => true,
            ],
            [
                'email' => 'moderator@example.com',
                'firstName' => 'Mod',
                'lastName' => 'Erator',
                'password' => 'Mod123!@#',
                'roles' => ['ROLE_ADMIN'],
                'isActive' => false,
            ],
        ];

        // Create users
        foreach ($testUsers as $userData) {
            $user = new User();
            $user->setEmail($userData['email']);
            $user->setFirstName($userData['firstName']);
            $user->setLastName($userData['lastName']);
            $user->setRoles($userData['roles']);
            $user->setIsActive($userData['isActive']);

            // Hash the password
            $hashedPassword = $this->passwordHasher->hashPassword($user, $userData['password']);
            $user->setPassword($hashedPassword);

            $manager->persist($user);

            // Ajouter une référence pour les tests
            if (isset($userData['reference'])) {
                $this->addReference($userData['reference'], $user);
            }
        }

        $manager->flush();
    }
}
