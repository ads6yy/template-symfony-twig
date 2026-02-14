<?php

declare(strict_types=1);

namespace App\Tests\E2E;

use Facebook\WebDriver\WebDriverBy;

final class AuthenticationE2ETest extends PantherTestCase
{
    public function testLoginPageLoads(): void
    {
        $client = static::createPantherClient();
        $client->request('GET', '/en/auth/login');

        $this->assertSelectorTextContains('h2', 'Login');
        $this->assertSelectorExists('#username');
        $this->assertSelectorExists('#password');
        $this->assertSelectorExists('button[type="submit"]');
    }

    public function testSuccessfulLogin(): void
    {
        $client = static::createPantherClient();
        $client->request('GET', '/en/auth/login');

        $client->findElement(WebDriverBy::id('username'))->sendKeys('admin@example.com');
        $client->findElement(WebDriverBy::id('password'))->sendKeys('Test123!');
        $client->findElement(WebDriverBy::cssSelector('button[type="submit"]'))->click();

        $client->waitFor('.navbar');

        $navbarText = $client->findElement(WebDriverBy::cssSelector('.navbar'))->getText();
        $this->assertStringContainsString('Admin System', $navbarText);
        $this->assertStringContainsString('Logout', $navbarText);
    }

    public function testFailedLogin(): void
    {
        $client = static::createPantherClient();
        $client->request('GET', '/en/auth/login');

        $client->findElement(WebDriverBy::id('username'))->sendKeys('admin@example.com');
        $client->findElement(WebDriverBy::id('password'))->sendKeys('WrongPassword!');
        $client->findElement(WebDriverBy::cssSelector('button[type="submit"]'))->click();

        $client->waitFor('.alert-danger');
        $this->assertSelectorExists('.alert-danger');
    }

    public function testLogout(): void
    {
        $client = static::createPantherClient();
        $this->loginAsAdmin();

        $navbarText = $client->findElement(WebDriverBy::cssSelector('.navbar'))->getText();
        $this->assertStringContainsString('Logout', $navbarText);

        $client->findElement(WebDriverBy::xpath('//a[contains(text(), "Logout")]'))->click();
        $client->waitFor('.navbar');

        $navbarText = $client->findElement(WebDriverBy::cssSelector('.navbar'))->getText();
        $this->assertStringContainsString('Login', $navbarText);
        $this->assertStringContainsString('Register', $navbarText);
    }

    public function testRegistration(): void
    {
        $client = static::createPantherClient();
        $client->request('GET', '/en/auth/register');

        $this->assertSelectorTextContains('h2', 'Register');

        $client->findElement(WebDriverBy::id('registration_email'))->sendKeys('newpanther@example.com');
        $client->findElement(WebDriverBy::id('registration_firstName'))->sendKeys('Panther');
        $client->findElement(WebDriverBy::id('registration_lastName'))->sendKeys('Test');
        $client->findElement(WebDriverBy::id('registration_password'))->sendKeys('SecurePass123!');
        $client->findElement(WebDriverBy::cssSelector('button[type="submit"]'))->click();

        $client->waitFor('.alert');

        $this->assertStringContainsString('/en/auth/login', $client->getCurrentURL());
        $this->assertSelectorExists('.alert-success');
    }
}
