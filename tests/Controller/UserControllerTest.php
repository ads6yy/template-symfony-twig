<?php

declare(strict_types=1);

namespace App\Tests\Controller;

use App\Entity\User;
use App\Repository\UserRepository;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

final class UserControllerTest extends WebTestCase
{
    public function testCreateUserAsAdmin(): void
    {
        $client = static::createClient();

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
