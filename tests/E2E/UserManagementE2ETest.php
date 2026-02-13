<?php

declare(strict_types=1);

namespace App\Tests\E2E;

use Facebook\WebDriver\WebDriverBy;

final class UserManagementE2ETest extends PantherTestCase
{
    public function testAdminSeesUserList(): void
    {
        $client = static::createPantherClient();
        $this->loginAsAdmin();

        $client->request('GET', '/en/users');

        $this->assertSelectorTextContains('h1', 'User Management');
        $this->assertSelectorExists('table');

        $tableText = $client->findElement(WebDriverBy::cssSelector('table'))->getText();
        $this->assertStringContainsString('admin@example.com', $tableText);
        $this->assertStringContainsString('user@example.com', $tableText);
    }

    public function testAdminCanCreateUser(): void
    {
        $client = static::createPantherClient();
        $this->loginAsAdmin();

        $client->request('GET', '/en/users/new');

        $this->assertSelectorTextContains('h2', 'New User');

        $client->findElement(WebDriverBy::id('user_email'))->sendKeys('e2e-created@example.com');
        $client->findElement(WebDriverBy::id('user_firstName'))->sendKeys('E2E');
        $client->findElement(WebDriverBy::id('user_lastName'))->sendKeys('Created');
        $client->findElement(WebDriverBy::id('user_password'))->sendKeys('TestPass123!');
        $client->findElement(WebDriverBy::cssSelector('#user_roles_0'))->click();
        $client->findElement(WebDriverBy::cssSelector('button[type="submit"]'))->click();

        $client->waitFor('.alert-success');

        $this->assertStringContainsString('/en/users', $client->getCurrentURL());
        $this->assertSelectorExists('.alert-success');

        $tableText = $client->findElement(WebDriverBy::cssSelector('table'))->getText();
        $this->assertStringContainsString('e2e-created@example.com', $tableText);
    }

    public function testAdminCanViewUserProfile(): void
    {
        $client = static::createPantherClient();
        $this->loginAsAdmin();

        $client->request('GET', '/en/users');

        $client->findElement(WebDriverBy::xpath('//td[contains(text(), "user@example.com")]/ancestor::tr//a[contains(@class, "btn-info")]'))->click();

        $client->waitFor('.card');

        $this->assertSelectorTextContains('h2', 'User Profile');
        $pageText = $client->findElement(WebDriverBy::cssSelector('.card-body'))->getText();
        $this->assertStringContainsString('user@example.com', $pageText);
        $this->assertStringContainsString('John', $pageText);
        $this->assertStringContainsString('Doe', $pageText);
    }

    public function testAdminCanEditUser(): void
    {
        $client = static::createPantherClient();
        $this->loginAsAdmin();

        $client->request('GET', '/en/users');

        $client->findElement(WebDriverBy::xpath('//td[contains(text(), "jane.smith@example.com")]/ancestor::tr//a[contains(@class, "btn-warning")]'))->click();

        $client->waitFor('form');

        $this->assertSelectorTextContains('h2', 'Edit User');

        $firstNameField = $client->findElement(WebDriverBy::id('user_firstName'));
        $firstNameField->clear();
        $firstNameField->sendKeys('Janet');

        $client->findElement(WebDriverBy::cssSelector('button[type="submit"]'))->click();

        $client->waitFor('.alert-success');
        $this->assertSelectorExists('.alert-success');
    }

    public function testAdminCanDeleteUser(): void
    {
        $client = static::createPantherClient();
        $this->loginAsAdmin();

        $client->request('GET', '/en/users');

        $tableText = $client->findElement(WebDriverBy::cssSelector('table'))->getText();
        $this->assertStringContainsString('moderator@example.com', $tableText);

        $client->findElement(WebDriverBy::xpath('//td[contains(text(), "moderator@example.com")]/ancestor::tr//button[contains(@class, "btn-danger")]'))->click();

        $client->switchTo()->alert()->accept();

        $client->waitFor('.alert-success');
        $this->assertSelectorExists('.alert-success');

        $tableText = $client->findElement(WebDriverBy::cssSelector('table'))->getText();
        $this->assertStringNotContainsString('moderator@example.com', $tableText);
    }

    public function testAdminCanToggleUserStatus(): void
    {
        $client = static::createPantherClient();
        $this->loginAsAdmin();

        $client->request('GET', '/en/users');

        $client->findElement(WebDriverBy::xpath('//td[contains(text(), "user@example.com")]/ancestor::tr//a[contains(@class, "btn-info")]'))->click();

        $client->waitFor('.card');

        $client->findElement(WebDriverBy::cssSelector('form[action$="/toggle-active"] button'))->click();

        $client->waitFor('.alert');
        $this->assertSelectorExists('.alert');
    }

    public function testAdminCanChangeUserPassword(): void
    {
        $client = static::createPantherClient();
        $this->loginAsAdmin();

        $client->request('GET', '/en/users');

        $client->findElement(WebDriverBy::xpath('//td[contains(text(), "user@example.com")]/ancestor::tr//a[contains(@class, "btn-info")]'))->click();

        $client->waitFor('.card');

        $client->findElement(WebDriverBy::xpath('//a[contains(text(), "Change Password")]'))->click();

        $client->waitFor('form');
        $this->assertSelectorTextContains('h2', 'Change Password');

        $client->findElement(WebDriverBy::id('change_password_newPassword'))->sendKeys('NewE2EPass123!');
        $client->findElement(WebDriverBy::id('change_password_confirmPassword'))->sendKeys('NewE2EPass123!');
        $client->findElement(WebDriverBy::cssSelector('button[type="submit"]'))->click();

        $client->waitFor('.alert-success');
        $this->assertSelectorExists('.alert-success');
    }
}
