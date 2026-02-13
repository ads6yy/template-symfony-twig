<?php

declare(strict_types=1);

namespace App\Tests\E2E;

use App\DataFixtures\UserFixtures;
use Doctrine\Common\DataFixtures\Executor\ORMExecutor;
use Doctrine\Common\DataFixtures\Loader;
use Doctrine\Common\DataFixtures\Purger\ORMPurger;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\SchemaTool;
use Doctrine\Persistence\ManagerRegistry;
use Facebook\WebDriver\WebDriverBy;
use Symfony\Component\Panther\Client;
use Symfony\Component\Panther\PantherTestCase as BasePantherTestCase;

abstract class PantherTestCase extends BasePantherTestCase
{
    private const string USER_EMAIL = 'user@example.com';
    private const string ADMIN_EMAIL = 'admin@example.com';
    private const string DEFAULT_PASSWORD = 'Test123!';

    private static bool $fixturesLoaded = false;

    protected static function loadFixtures(): void
    {
        if (self::$fixturesLoaded) {
            return;
        }

        $kernel = static::createKernel();
        $kernel->boot();

        /** @var ManagerRegistry $doctrine */
        $doctrine = $kernel->getContainer()->get('doctrine');
        /** @var EntityManagerInterface $entityManager */
        $entityManager = $doctrine->getManager();
        $metadata = $entityManager->getMetadataFactory()->getAllMetadata();
        $schemaTool = new SchemaTool($entityManager);
        $schemaTool->dropSchema($metadata);
        $schemaTool->createSchema($metadata);

        $loader = new Loader();
        /** @var UserFixtures $userFixtures */
        $userFixtures = $kernel->getContainer()->get(UserFixtures::class);
        $loader->addFixture($userFixtures);

        $purger = new ORMPurger($entityManager);
        $executor = new ORMExecutor($entityManager, $purger);
        $executor->execute($loader->getFixtures());

        $kernel->shutdown();

        self::$fixturesLoaded = true;
    }

    protected function loginAsUser(): void
    {
        $this->loginAs(self::USER_EMAIL, self::DEFAULT_PASSWORD);
    }

    protected function loginAsAdmin(): void
    {
        $this->loginAs(self::ADMIN_EMAIL, self::DEFAULT_PASSWORD);
    }

    private function loginAs(string $email, string $password): void
    {
        /** @var Client $client */
        $client = static::$pantherClient;

        $client->request('GET', '/en/auth/login');

        $client->findElement(WebDriverBy::id('username'))->sendKeys($email);
        $client->findElement(WebDriverBy::id('password'))->sendKeys($password);
        $client->findElement(WebDriverBy::cssSelector('button[type="submit"]'))->click();

        $client->waitFor('.navbar');
    }
}
