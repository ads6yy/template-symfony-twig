<?php

declare(strict_types=1);

namespace App\Tests\Controller;

use App\DataFixtures\UserFixtures;
use App\Entity\User;
use App\Repository\UserRepository;
use Doctrine\Common\DataFixtures\Executor\ORMExecutor;
use Doctrine\Common\DataFixtures\Loader;
use Doctrine\Common\DataFixtures\Purger\ORMPurger;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\SchemaTool;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

final class UserControllerTest extends WebTestCase
{
    private function createClientWithDatabase(): KernelBrowser
    {
        $client = static::createClient();

        // Create schema for SQLite database
        /** @var EntityManagerInterface $entityManager */
        $entityManager = static::getContainer()->get(EntityManagerInterface::class);
        $metadata = $entityManager->getMetadataFactory()->getAllMetadata();
        $schemaTool = new SchemaTool($entityManager);
        $schemaTool->dropSchema($metadata);
        $schemaTool->createSchema($metadata);

        // Load fixtures using Doctrine fixture executor
        $loader = new Loader();
        /** @var UserFixtures $userFixtures */
        $userFixtures = static::getContainer()->get(UserFixtures::class);
        $loader->addFixture($userFixtures);

        $purger = new ORMPurger($entityManager);
        $executor = new ORMExecutor($entityManager, $purger);
        $executor->execute($loader->getFixtures());

        return $client;
    }

    public function testUserCanRegister(): void
    {
        $client = $this->createClientWithDatabase();

        // Access the registration page
        $crawler = $client->request('GET', '/en/auth/register');

        $this->assertResponseIsSuccessful();
        $this->assertSelectorTextContains('h2', 'Register');

        // Fill and submit the registration form
        $form = $crawler->selectButton('Create Account')->form([
            'registration[email]' => 'newuser@example.com',
            'registration[firstName]' => 'New',
            'registration[lastName]' => 'User',
            'registration[password]' => 'SecurePass123!',
        ]);

        $client->submit($form);

        // Should redirect to login page after successful registration
        $this->assertResponseRedirects('/en/auth/login');

        // Verify user was created in database
        /** @var UserRepository $userRepository */
        $userRepository = static::getContainer()->get(UserRepository::class);
        $newUser = $userRepository->findOneBy(['email' => 'newuser@example.com']);
        $this->assertInstanceOf(User::class, $newUser);
        $this->assertSame('New', $newUser->getFirstName());
        $this->assertSame('User', $newUser->getLastName());
        $this->assertContains('ROLE_USER', $newUser->getRoles());
    }

    public function testUserCanLogin(): void
    {
        $client = $this->createClientWithDatabase();

        // Access the login page
        $crawler = $client->request('GET', '/en/auth/login');

        $this->assertResponseIsSuccessful();
        $this->assertSelectorTextContains('h2', 'Login');

        // Fill and submit the login form with fixture user credentials
        $form = $crawler->selectButton('Sign In')->form([
            '_username' => 'user@example.com',
            '_password' => 'Test123!',
        ]);

        $client->submit($form);

        // Should redirect after successful login
        $this->assertResponseRedirects();

        // Follow redirect and verify user is logged in
        $client->followRedirect();
        $this->assertResponseIsSuccessful();
    }

    public function testUserCanLogout(): void
    {
        $client = $this->createClientWithDatabase();

        // Login as user first
        /** @var UserRepository $userRepository */
        $userRepository = static::getContainer()->get(UserRepository::class);
        $user = $userRepository->findOneBy(['email' => 'user@example.com']);

        $this->assertInstanceOf(User::class, $user);

        $client->loginUser($user);

        // Verify user is logged in by accessing their own profile
        $client->request('GET', '/en/users/' . $user->getId());
        $this->assertResponseIsSuccessful();

        // Logout
        $client->request('GET', '/en/auth/logout');

        // Should redirect after logout
        $this->assertResponseRedirects();

        // Try to access profile again - should redirect to login
        $client->request('GET', '/en/users/' . $user->getId());
        $this->assertResponseRedirects();
    }

    public function testAdminCanCreateUser(): void
    {
        $client = $this->createClientWithDatabase();

        // Login as admin
        /** @var UserRepository $userRepository */
        $userRepository = static::getContainer()->get(UserRepository::class);
        $admin = $userRepository->findOneBy(['email' => 'admin@example.com']);

        $this->assertInstanceOf(User::class, $admin, 'Admin user not found. Make sure fixtures are loaded.');

        $client->loginUser($admin);

        // Access the new user form
        $crawler = $client->request('GET', '/en/users/new');

        $this->assertResponseIsSuccessful();
        $this->assertSelectorTextContains('h2', 'New User');

        // Fill and submit the form
        $form = $crawler->selectButton('Create User')->form([
            'user[email]' => 'testuser@example.com',
            'user[firstName]' => 'Test',
            'user[lastName]' => 'User',
            'user[password]' => 'TestPass123!',
            'user[roles][0]' => 'ROLE_USER',
        ]);

        $client->submit($form);

        // Should redirect to user index
        $this->assertResponseRedirects('/en/users');

        $client->followRedirect();
        $this->assertSelectorExists('.alert-success');

        // Verify user was created in database
        $createdUser = $userRepository->findOneBy(['email' => 'testuser@example.com']);
        $this->assertInstanceOf(User::class, $createdUser);
        $this->assertSame('Test', $createdUser->getFirstName());
        $this->assertSame('User', $createdUser->getLastName());
        $this->assertContains('ROLE_USER', $createdUser->getRoles());
    }
}
