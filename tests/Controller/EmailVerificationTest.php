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
use SymfonyCasts\Bundle\VerifyEmail\VerifyEmailHelperInterface;

final class EmailVerificationTest extends WebTestCase
{
    private const DEFAULT_PASSWORD = 'Test123!';

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

    private function createUnverifiedUser(string $email = 'pending@example.com'): User
    {
        /** @var UserPasswordHasherInterface $hasher */
        $hasher = static::getContainer()->get(UserPasswordHasherInterface::class);

        $user = new User();
        $user->setEmail($email);
        $user->setFirstName('Pending');
        $user->setLastName('User');
        $user->setRoles(['ROLE_USER']);
        $user->setIsVerified(false);
        $user->setPassword($hasher->hashPassword($user, self::DEFAULT_PASSWORD));

        $this->entityManager->persist($user);
        $this->entityManager->flush();

        return $user;
    }

    public function testRegistrationCreatesUnverifiedUser(): void
    {
        $this->createClientWithDatabase();

        $crawler = $this->client->request('GET', '/en/auth/register');
        $form = $crawler->selectButton('Create Account')->form([
            'registration[email]' => 'fresh@example.com',
            'registration[firstName]' => 'Fresh',
            'registration[lastName]' => 'Account',
            'registration[password]' => 'SecurePass123!',
        ]);
        $this->client->submit($form);

        $this->assertResponseRedirects('/en/auth/login');

        $user = $this->userRepository->findOneBy(['email' => 'fresh@example.com']);
        $this->assertInstanceOf(User::class, $user);
        $this->assertFalse($user->isVerified(), 'A newly registered user must not be verified.');
    }

    public function testUnverifiedUserIsRedirectedFromProtectedPage(): void
    {
        $this->createClientWithDatabase();
        $user = $this->createUnverifiedUser();

        $this->client->loginUser($user);
        $this->client->request('GET', '/en');

        $this->assertResponseRedirects('/en/auth/verify/resend');
    }

    public function testValidLinkVerifiesUser(): void
    {
        $this->createClientWithDatabase();
        $user = $this->createUnverifiedUser();

        /** @var VerifyEmailHelperInterface $helper */
        $helper = static::getContainer()->get(VerifyEmailHelperInterface::class);
        $signature = $helper->generateSignature(
            'app_auth_verify_email',
            (string) $user->getId(),
            (string) $user->getEmail(),
            ['id' => (string) $user->getId()],
        );

        $this->client->request('GET', $signature->getSignedUrl());

        $this->assertResponseRedirects('/en/auth/login');

        $this->entityManager->clear();
        $verified = $this->userRepository->find((int) $user->getId());
        $this->assertInstanceOf(User::class, $verified);
        $this->assertTrue($verified->isVerified(), 'A valid link must verify the user.');
    }

    public function testInvalidLinkDoesNotVerifyUser(): void
    {
        $this->createClientWithDatabase();
        $user = $this->createUnverifiedUser();

        $this->client->request('GET', '/en/auth/verify/email?id='.$user->getId().'&expires=9999999999&signature=tampered');

        $this->assertResponseRedirects('/en/auth/login');

        $this->entityManager->clear();
        $stillUnverified = $this->userRepository->find((int) $user->getId());
        $this->assertInstanceOf(User::class, $stillUnverified);
        $this->assertFalse($stillUnverified->isVerified(), 'A tampered link must not verify the user.');
    }

    public function testResendVerificationPageIsPublic(): void
    {
        $this->createClientWithDatabase();

        $this->client->request('GET', '/en/auth/verify/resend');

        $this->assertResponseIsSuccessful();
        $this->assertSelectorTextContains('h2', 'Verify your email');
    }

    public function testResendVerificationRedirectsToLogin(): void
    {
        $this->createClientWithDatabase();
        $this->createUnverifiedUser();

        $crawler = $this->client->request('GET', '/en/auth/verify/resend');
        $form = $crawler->selectButton('Resend verification email')->form([
            'email' => 'pending@example.com',
        ]);
        $this->client->submit($form);

        $this->assertResponseRedirects('/en/auth/login');
    }
}
