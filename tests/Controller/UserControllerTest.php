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
        $client->request('GET', '/en/users/'.$user->getId());
        $this->assertResponseIsSuccessful();

        // Logout
        $client->request('GET', '/en/auth/logout');

        // Should redirect after logout
        $this->assertResponseRedirects();

        // Try to access profile again - should redirect to login
        $client->request('GET', '/en/users/'.$user->getId());
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

    public function testUserCanOnlyAccessOwnProfile(): void
    {
        $client = $this->createClientWithDatabase();

        /** @var UserRepository $userRepository */
        $userRepository = static::getContainer()->get(UserRepository::class);

        $user = $userRepository->findOneBy(['email' => 'user@example.com']);
        $admin = $userRepository->findOneBy(['email' => 'admin@example.com']);

        $this->assertInstanceOf(User::class, $user);
        $this->assertInstanceOf(User::class, $admin);

        $client->loginUser($user);

        // User can access their own profile
        $client->request('GET', '/en/users/'.$user->getId());
        $this->assertResponseIsSuccessful();

        // User cannot access another user's profile
        $client->request('GET', '/en/users/'.$admin->getId());
        $this->assertResponseStatusCodeSame(403);
    }

    public function testAdminCanAccessAllUserProfiles(): void
    {
        $client = $this->createClientWithDatabase();

        /** @var UserRepository $userRepository */
        $userRepository = static::getContainer()->get(UserRepository::class);

        $admin = $userRepository->findOneBy(['email' => 'admin@example.com']);
        $user = $userRepository->findOneBy(['email' => 'user@example.com']);

        $this->assertInstanceOf(User::class, $admin);
        $this->assertInstanceOf(User::class, $user);

        $client->loginUser($admin);

        // Admin can access their own profile
        $client->request('GET', '/en/users/'.$admin->getId());
        $this->assertResponseIsSuccessful();

        // Admin can access other user's profile
        $client->request('GET', '/en/users/'.$user->getId());
        $this->assertResponseIsSuccessful();
    }

    public function testUserCanOnlyEditOwnProfile(): void
    {
        $client = $this->createClientWithDatabase();

        /** @var UserRepository $userRepository */
        $userRepository = static::getContainer()->get(UserRepository::class);

        $user = $userRepository->findOneBy(['email' => 'user@example.com']);
        $admin = $userRepository->findOneBy(['email' => 'admin@example.com']);

        $this->assertInstanceOf(User::class, $user);
        $this->assertInstanceOf(User::class, $admin);

        $client->loginUser($user);

        // User can access their own edit page
        $client->request('GET', '/en/users/'.$user->getId().'/edit');
        $this->assertResponseIsSuccessful();

        // User cannot access another user's edit page
        $client->request('GET', '/en/users/'.$admin->getId().'/edit');
        $this->assertResponseStatusCodeSame(403);
    }

    public function testAdminCanEditAllProfiles(): void
    {
        $client = $this->createClientWithDatabase();

        /** @var UserRepository $userRepository */
        $userRepository = static::getContainer()->get(UserRepository::class);

        $admin = $userRepository->findOneBy(['email' => 'admin@example.com']);
        $user = $userRepository->findOneBy(['email' => 'user@example.com']);

        $this->assertInstanceOf(User::class, $admin);
        $this->assertInstanceOf(User::class, $user);

        $client->loginUser($admin);

        // Admin can access their own edit page
        $client->request('GET', '/en/users/'.$admin->getId().'/edit');
        $this->assertResponseIsSuccessful();

        // Admin can access other user's edit page
        $client->request('GET', '/en/users/'.$user->getId().'/edit');
        $this->assertResponseIsSuccessful();
    }

    public function testAdminCanDeleteUser(): void
    {
        $client = $this->createClientWithDatabase();

        /** @var UserRepository $userRepository */
        $userRepository = static::getContainer()->get(UserRepository::class);

        $admin = $userRepository->findOneBy(['email' => 'admin@example.com']);
        $user = $userRepository->findOneBy(['email' => 'jane.smith@example.com']);

        $this->assertInstanceOf(User::class, $admin);
        $this->assertInstanceOf(User::class, $user);

        $userId = $user->getId();

        $client->loginUser($admin);

        // Access user profile page to get the CSRF token
        $crawler = $client->request('GET', '/en/users/'.$userId);
        $this->assertResponseIsSuccessful();

        // Get CSRF token from the delete form
        $form = $crawler->selectButton('Delete')->form();
        $client->submit($form);

        // Should redirect to user index
        $this->assertResponseRedirects('/en/users');

        // Verify user was deleted
        $deletedUser = $userRepository->find($userId);
        $this->assertNull($deletedUser);
    }

    public function testUserCannotAnotherDeleteUser(): void
    {
        $client = $this->createClientWithDatabase();

        /** @var UserRepository $userRepository */
        $userRepository = static::getContainer()->get(UserRepository::class);

        $user = $userRepository->findOneBy(['email' => 'user@example.com']);
        $otherUser = $userRepository->findOneBy(['email' => 'jane.smith@example.com']);

        $this->assertInstanceOf(User::class, $user);
        $this->assertInstanceOf(User::class, $otherUser);

        $otherUserId = $otherUser->getId();

        $client->loginUser($user);

        // Make a request to initialize session
        $client->request('GET', '/en/users/'.$user->getId());

        // Try to delete another user directly via POST
        $client->request('POST', '/en/users/'.$otherUserId.'/delete', [
            '_token' => 'fake_token',
        ]);

        $this->assertResponseStatusCodeSame(403);
    }

    public function testAdminCanToggleUserActiveStatus(): void
    {
        $client = $this->createClientWithDatabase();

        /** @var UserRepository $userRepository */
        $userRepository = static::getContainer()->get(UserRepository::class);

        $admin = $userRepository->findOneBy(['email' => 'admin@example.com']);
        $user = $userRepository->findOneBy(['email' => 'user@example.com']);

        $this->assertInstanceOf(User::class, $admin);
        $this->assertInstanceOf(User::class, $user);

        $userId = $user->getId();

        $client->loginUser($admin);

        // Access user profile page to initialize session
        $crawler = $client->request('GET', '/en/users/'.$userId);
        $this->assertResponseIsSuccessful();

        // Find the toggle form by action URL and submit it
        $form = $crawler->filter('form[action$="/toggle-active"]')->form();
        $client->submit($form);

        // Should redirect after toggle
        $this->assertResponseRedirects();
    }

    public function testUserCannotToggleUserActiveStatus(): void
    {
        $client = $this->createClientWithDatabase();

        /** @var UserRepository $userRepository */
        $userRepository = static::getContainer()->get(UserRepository::class);

        $user = $userRepository->findOneBy(['email' => 'user@example.com']);
        $otherUser = $userRepository->findOneBy(['email' => 'jane.smith@example.com']);

        $this->assertInstanceOf(User::class, $user);
        $this->assertInstanceOf(User::class, $otherUser);

        $otherUserId = $otherUser->getId();

        $client->loginUser($user);

        // Make a request to initialize session
        $client->request('GET', '/en/users/'.$user->getId());

        // Try to toggle another user's active status
        $client->request('POST', '/en/users/'.$otherUserId.'/toggle-active', [
            '_token' => 'fake_token',
        ]);

        $this->assertResponseStatusCodeSame(403);
    }
}
