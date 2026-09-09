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
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use SymfonyCasts\Bundle\ResetPassword\ResetPasswordHelperInterface;

final class ResetPasswordTest extends WebTestCase
{
    private const USER_EMAIL = 'user@example.com';
    private const NEW_PASSWORD = 'BrandNewPass123!';

    private KernelBrowser $client;
    private UserRepository $userRepository;
    private EntityManagerInterface $entityManager;

    private function createClientWithDatabase(): void
    {
        $this->client = static::createClient();

        /** @var EntityManagerInterface $entityManager */
        $entityManager = static::getContainer()->get(EntityManagerInterface::class);
        $this->entityManager = $entityManager;

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

    private function generateTokenFor(string $email): string
    {
        $user = $this->userRepository->findOneBy(['email' => $email]);
        self::assertInstanceOf(User::class, $user);

        /** @var ResetPasswordHelperInterface $helper */
        $helper = static::getContainer()->get(ResetPasswordHelperInterface::class);

        return $helper->generateResetToken($user)->getToken();
    }

    public function testForgotPasswordPageLoads(): void
    {
        $this->createClientWithDatabase();

        $this->client->request('GET', '/en/auth/reset-password');

        $this->assertResponseIsSuccessful();
        $this->assertSelectorTextContains('h2', 'Reset your password');
    }

    public function testRequestRedirectsToCheckEmailForKnownUser(): void
    {
        $this->createClientWithDatabase();

        $crawler = $this->client->request('GET', '/en/auth/reset-password');
        $form = $crawler->selectButton('Send reset link')->form([
            'reset_password_request[email]' => self::USER_EMAIL,
        ]);
        $this->client->submit($form);

        $this->assertResponseRedirects('/en/auth/reset-password/check-email');
    }

    public function testRequestRedirectsToCheckEmailForUnknownUser(): void
    {
        $this->createClientWithDatabase();

        $crawler = $this->client->request('GET', '/en/auth/reset-password');
        $form = $crawler->selectButton('Send reset link')->form([
            'reset_password_request[email]' => 'nobody@example.com',
        ]);
        $this->client->submit($form);

        // Same outcome as a known user: no account enumeration.
        $this->assertResponseRedirects('/en/auth/reset-password/check-email');
    }

    public function testCheckEmailPageLoads(): void
    {
        $this->createClientWithDatabase();

        $this->client->request('GET', '/en/auth/reset-password/check-email');

        $this->assertResponseIsSuccessful();
        $this->assertSelectorTextContains('h2', 'Check your email');
    }

    public function testValidTokenResetsPassword(): void
    {
        $this->createClientWithDatabase();
        $token = $this->generateTokenFor(self::USER_EMAIL);

        // Hitting the tokenized URL stores the token in session and redirects.
        $this->client->request('GET', '/en/auth/reset-password/reset/'.$token);
        $this->assertResponseRedirects('/en/auth/reset-password/reset');
        $crawler = $this->client->followRedirect();

        $form = $crawler->selectButton('Reset password')->form([
            'change_password[newPassword]' => self::NEW_PASSWORD,
            'change_password[confirmPassword]' => self::NEW_PASSWORD,
        ]);
        $this->client->submit($form);

        $this->assertResponseRedirects('/en/auth/login');

        // The new password is now valid.
        $this->entityManager->clear();
        $user = $this->userRepository->findOneBy(['email' => self::USER_EMAIL]);
        self::assertInstanceOf(User::class, $user);

        /** @var UserPasswordHasherInterface $hasher */
        $hasher = static::getContainer()->get(UserPasswordHasherInterface::class);
        self::assertTrue($hasher->isPasswordValid($user, self::NEW_PASSWORD));
    }

    public function testTokenIsSingleUse(): void
    {
        $this->createClientWithDatabase();
        $token = $this->generateTokenFor(self::USER_EMAIL);

        // First use: consume the token.
        $this->client->request('GET', '/en/auth/reset-password/reset/'.$token);
        $crawler = $this->client->followRedirect();
        $form = $crawler->selectButton('Reset password')->form([
            'change_password[newPassword]' => self::NEW_PASSWORD,
            'change_password[confirmPassword]' => self::NEW_PASSWORD,
        ]);
        $this->client->submit($form);
        $this->assertResponseRedirects('/en/auth/login');

        // Second use of the same token must fail and bounce to the request page.
        $this->client->request('GET', '/en/auth/reset-password/reset/'.$token);
        $this->client->followRedirect(); // tokenized -> tokenless
        $this->assertResponseRedirects('/en/auth/reset-password');
    }

    public function testInvalidTokenRedirectsToRequest(): void
    {
        $this->createClientWithDatabase();

        $this->client->request('GET', '/en/auth/reset-password/reset/invalid-token-value');
        $this->client->followRedirect(); // tokenized -> tokenless
        $this->assertResponseRedirects('/en/auth/reset-password');
    }
}
