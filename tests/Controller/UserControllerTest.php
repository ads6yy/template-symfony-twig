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
