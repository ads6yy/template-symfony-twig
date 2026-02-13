<?php

declare(strict_types=1);

namespace App\Tests\E2E;

use Facebook\WebDriver\WebDriverBy;

final class NavigationE2ETest extends PantherTestCase
{
    protected function setUp(): void
    {
        static::loadFixtures();
    }

    public function testHomepageLoads(): void
    {
        $client = static::createPantherClient();
        $client->request('GET', '/');

        $this->assertStringContainsString('/en', $client->getCurrentURL());
        $this->assertSelectorTextContains('.navbar-brand', 'Template Symfony + Twig');
    }

    public function testLocaleSwitch(): void
    {
        $client = static::createPantherClient();
        $client->request('GET', '/en');

        $client->findElement(WebDriverBy::id('languageDropdown'))->click();
        $client->waitForVisibility('.dropdown-menu');
        $client->findElement(WebDriverBy::xpath('//a[@class="dropdown-item " and text()="Français"]'))->click();

        $client->waitFor('.navbar');

        $this->assertStringContainsString('/fr', $client->getCurrentURL());

        $navbarText = $client->findElement(WebDriverBy::cssSelector('.navbar'))->getText();
        $this->assertStringContainsString('Connexion', $navbarText);
    }

    public function testUnauthenticatedNavbar(): void
    {
        $client = static::createPantherClient();
        $client->request('GET', '/en');

        $navbarText = $client->findElement(WebDriverBy::cssSelector('.navbar'))->getText();
        $this->assertStringContainsString('Login', $navbarText);
        $this->assertStringContainsString('Register', $navbarText);
        $this->assertStringNotContainsString('Logout', $navbarText);
    }

    public function testAuthenticatedNavbar(): void
    {
        $client = static::createPantherClient();
        $this->loginAsUser();

        $navbarText = $client->findElement(WebDriverBy::cssSelector('.navbar'))->getText();
        $this->assertStringContainsString('John Doe', $navbarText);
        $this->assertStringContainsString('Logout', $navbarText);
        $this->assertStringNotContainsString('Register', $navbarText);
    }

    public function testAccessDeniedForRegularUser(): void
    {
        $client = static::createPantherClient();
        $this->loginAsUser();

        $crawler = $client->request('GET', '/en/users');

        $pageText = $crawler->text();
        $this->assertStringContainsString('403', $pageText);
    }
}
