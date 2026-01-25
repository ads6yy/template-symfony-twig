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
    private const USER_EMAIL = 'user@example.com';
    private const ADMIN_EMAIL = 'admin@example.com';
    private const OTHER_USER_EMAIL = 'jane.smith@example.com';
    private const DEFAULT_PASSWORD = 'Test123!';

    private KernelBrowser $client;
    private UserRepository $userRepository;

    private function createClientWithDatabase(): void
    {
        $this->client = static::createClient();

        /** @var EntityManagerInterface $entityManager */
        $entityManager = static::getContainer()->get(EntityManagerInterface::class);
        $metadata = $entityManager->getMetadataFactory()->getAllMetadata();
        $schemaTool = new SchemaTool($entityManager);
        $schemaTool->dropSchema($metadata);
        $schemaTool->createSchema($metadata);

        $loader = new Loader();
        /** @var UserFixtures $userFixtures */
        $userFixtures = static::getContainer()->get(UserFixtures::class);
        $loader->addFixture($userFixtures);

        $purger = new ORMPurger($entityManager);
        $executor = new ORMExecutor($entityManager, $purger);
        $executor->execute($loader->getFixtures());

        /** @var UserRepository $repository */
        $repository = static::getContainer()->get(UserRepository::class);
        $this->userRepository = $repository;
    }

    private function getUser(string $email = self::USER_EMAIL): User
    {
        $user = $this->userRepository->findOneBy(['email' => $email]);
        $this->assertInstanceOf(User::class, $user, "User with email {$email} not found.");

        return $user;
    }

    private function loginAs(string $email): User
    {
        $user = $this->getUser($email);
        $this->client->loginUser($user);

        return $user;
    }

    private function loginAsUser(): User
    {
        return $this->loginAs(self::USER_EMAIL);
    }

    private function loginAsAdmin(): User
    {
        return $this->loginAs(self::ADMIN_EMAIL);
    }

    public function testUserCanRegister(): void
    {
        $this->createClientWithDatabase();

        $crawler = $this->client->request('GET', '/en/auth/register');

        $this->assertResponseIsSuccessful();
        $this->assertSelectorTextContains('h2', 'Register');

        $form = $crawler->selectButton('Create Account')->form([
            'registration[email]' => 'newuser@example.com',
            'registration[firstName]' => 'New',
            'registration[lastName]' => 'User',
            'registration[password]' => 'SecurePass123!',
        ]);

        $this->client->submit($form);

        $this->assertResponseRedirects('/en/auth/login');

        $newUser = $this->getUser('newuser@example.com');
        $this->assertSame('New', $newUser->getFirstName());
        $this->assertSame('User', $newUser->getLastName());
        $this->assertContains('ROLE_USER', $newUser->getRoles());
    }

    public function testUserCanLogin(): void
    {
        $this->createClientWithDatabase();

        $crawler = $this->client->request('GET', '/en/auth/login');

        $this->assertResponseIsSuccessful();
        $this->assertSelectorTextContains('h2', 'Login');

        $form = $crawler->selectButton('Sign In')->form([
            '_username' => self::USER_EMAIL,
            '_password' => self::DEFAULT_PASSWORD,
        ]);

        $this->client->submit($form);

        $this->assertResponseRedirects();

        $this->client->followRedirect();
        $this->assertResponseIsSuccessful();
    }

    public function testUserCanLogout(): void
    {
        $this->createClientWithDatabase();

        $user = $this->loginAsUser();

        $this->client->request('GET', '/en/users/'.$user->getId());
        $this->assertResponseIsSuccessful();

        $this->client->request('GET', '/en/auth/logout');
        $this->assertResponseRedirects();

        $this->client->request('GET', '/en/users/'.$user->getId());
        $this->assertResponseRedirects();
    }

    public function testAdminCanCreateUser(): void
    {
        $this->createClientWithDatabase();

        $this->loginAsAdmin();

        $crawler = $this->client->request('GET', '/en/users/new');

        $this->assertResponseIsSuccessful();
        $this->assertSelectorTextContains('h2', 'New User');

        $form = $crawler->selectButton('Create User')->form([
            'user[email]' => 'testuser@example.com',
            'user[firstName]' => 'Test',
            'user[lastName]' => 'User',
            'user[password]' => 'TestPass123!',
            'user[roles][0]' => 'ROLE_USER',
        ]);

        $this->client->submit($form);

        $this->assertResponseRedirects('/en/users');

        $this->client->followRedirect();
        $this->assertSelectorExists('.alert-success');

        $createdUser = $this->getUser('testuser@example.com');
        $this->assertSame('Test', $createdUser->getFirstName());
        $this->assertSame('User', $createdUser->getLastName());
        $this->assertContains('ROLE_USER', $createdUser->getRoles());
    }

    public function testUserCanOnlyAccessOwnProfile(): void
    {
        $this->createClientWithDatabase();

        $user = $this->loginAsUser();
        $admin = $this->getUser(self::ADMIN_EMAIL);

        $this->client->request('GET', '/en/users/'.$user->getId());
        $this->assertResponseIsSuccessful();

        $this->client->request('GET', '/en/users/'.$admin->getId());
        $this->assertResponseStatusCodeSame(403);
    }

    public function testAdminCanAccessAllUserProfiles(): void
    {
        $this->createClientWithDatabase();

        $admin = $this->loginAsAdmin();
        $user = $this->getUser(self::USER_EMAIL);

        $this->client->request('GET', '/en/users/'.$admin->getId());
        $this->assertResponseIsSuccessful();

        $this->client->request('GET', '/en/users/'.$user->getId());
        $this->assertResponseIsSuccessful();
    }

    public function testUserCanOnlyEditOwnProfile(): void
    {
        $this->createClientWithDatabase();

        $user = $this->loginAsUser();
        $admin = $this->getUser(self::ADMIN_EMAIL);

        $this->client->request('GET', '/en/users/'.$user->getId().'/edit');
        $this->assertResponseIsSuccessful();

        $this->client->request('GET', '/en/users/'.$admin->getId().'/edit');
        $this->assertResponseStatusCodeSame(403);
    }

    public function testAdminCanEditAllProfiles(): void
    {
        $this->createClientWithDatabase();

        $admin = $this->loginAsAdmin();
        $user = $this->getUser(self::USER_EMAIL);

        $this->client->request('GET', '/en/users/'.$admin->getId().'/edit');
        $this->assertResponseIsSuccessful();

        $this->client->request('GET', '/en/users/'.$user->getId().'/edit');
        $this->assertResponseIsSuccessful();
    }

    public function testAdminCanDeleteUser(): void
    {
        $this->createClientWithDatabase();

        $this->loginAsAdmin();
        $otherUser = $this->getUser(self::OTHER_USER_EMAIL);
        $userId = $otherUser->getId();

        $crawler = $this->client->request('GET', '/en/users/'.$userId);
        $this->assertResponseIsSuccessful();

        $form = $crawler->selectButton('Delete')->form();
        $this->client->submit($form);

        $this->assertResponseRedirects('/en/users');

        $deletedUser = $this->userRepository->find($userId);
        $this->assertNull($deletedUser);
    }

    public function testUserCannotDeleteAnotherUser(): void
    {
        $this->createClientWithDatabase();

        $user = $this->loginAsUser();
        $otherUser = $this->getUser(self::OTHER_USER_EMAIL);

        $this->client->request('GET', '/en/users/'.$user->getId());

        $this->client->request('POST', '/en/users/'.$otherUser->getId().'/delete', [
            '_token' => 'fake_token',
        ]);

        $this->assertResponseStatusCodeSame(403);
    }

    public function testAdminCanToggleUserActiveStatus(): void
    {
        $this->createClientWithDatabase();

        $this->loginAsAdmin();
        $user = $this->getUser(self::USER_EMAIL);

        $crawler = $this->client->request('GET', '/en/users/'.$user->getId());
        $this->assertResponseIsSuccessful();

        $form = $crawler->filter('form[action$="/toggle-active"]')->form();
        $this->client->submit($form);

        $this->assertResponseRedirects();
    }

    public function testUserCannotToggleUserActiveStatus(): void
    {
        $this->createClientWithDatabase();

        $user = $this->loginAsUser();
        $otherUser = $this->getUser(self::OTHER_USER_EMAIL);

        $this->client->request('GET', '/en/users/'.$user->getId());

        $this->client->request('POST', '/en/users/'.$otherUser->getId().'/toggle-active', [
            '_token' => 'fake_token',
        ]);

        $this->assertResponseStatusCodeSame(403);
    }

    public function testUserCanChangeOwnPassword(): void
    {
        $this->createClientWithDatabase();

        $user = $this->loginAsUser();

        $crawler = $this->client->request('GET', '/en/users/'.$user->getId().'/change-password');
        $this->assertResponseIsSuccessful();

        $form = $crawler->selectButton('Change Password')->form([
            'change_password[oldPassword]' => self::DEFAULT_PASSWORD,
            'change_password[newPassword]' => 'NewPass456!',
            'change_password[confirmPassword]' => 'NewPass456!',
        ]);

        $this->client->submit($form);

        $this->assertResponseRedirects('/en/users/'.$user->getId());
    }

    public function testAdminCanChangeAnotherUserPassword(): void
    {
        $this->createClientWithDatabase();

        $this->loginAsAdmin();
        $user = $this->getUser(self::USER_EMAIL);

        $crawler = $this->client->request('GET', '/en/users/'.$user->getId().'/change-password');
        $this->assertResponseIsSuccessful();

        $form = $crawler->selectButton('Change Password')->form([
            'change_password[newPassword]' => 'AdminChanged123!',
            'change_password[confirmPassword]' => 'AdminChanged123!',
        ]);

        $this->client->submit($form);

        $this->assertResponseRedirects('/en/users/'.$user->getId());
    }

    public function testUserCannotChangeAnotherUserPassword(): void
    {
        $this->createClientWithDatabase();

        $this->loginAsUser();
        $otherUser = $this->getUser(self::OTHER_USER_EMAIL);

        $this->client->request('GET', '/en/users/'.$otherUser->getId().'/change-password');

        $this->assertResponseStatusCodeSame(403);
    }
}
