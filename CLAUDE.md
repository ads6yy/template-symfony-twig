# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Project Overview

Symfony 8.0 + Twig reference implementation for standard web application features. Uses PHP 8.4, Docker (MariaDB + PHP-Apache + Mailpit), and serves as a code bank/knowledge base for building new projects.

## Common Commands

```bash
# Dependencies
composer install

# Tests (SQLite in-memory for test env)
vendor/bin/phpunit
vendor/bin/phpunit tests/path/to/SpecificTest.php
vendor/bin/phpunit --filter testMethodName

# Code quality (all run in CI)
vendor/bin/php-cs-fixer fix --dry-run --diff    # check style
vendor/bin/php-cs-fixer fix                      # fix style
vendor/bin/phpstan analyse --memory-limit=1G     # static analysis (level 8)
vendor/bin/phpcpd src --verbose                  # copy-paste detection
composer audit                                   # security check

# Database
php bin/console doctrine:migrations:migrate
php bin/console doctrine:fixtures:load

# Docker
docker-compose -f deploy/docker-compose.yml up -d   # MariaDB:3400, App:8400, Mailpit:8401
```

## Architecture

### Dual Authentication System
- **Web**: Form login with CSRF at `/auth/login`, handled by `security.yaml` `main` firewall
- **API**: JSON login at `/api/login`, handled by `security.yaml` `api` firewall with custom handlers in `src/Api/Security/`
- Success handler (`src/Security/AuthenticationSuccessHandler.php`) routes admins to user list, regular users to their profile

### Locale-Prefixed Routing
All web routes are prefixed with `/{_locale}` (en|fr). API routes under `/api` have no locale prefix. Root `/` redirects to `/en`. The `LocaleSubscriber` syncs locale between URL and session.

### Controller Separation
- `src/Controller/` — Web controllers (locale-prefixed routes)
- `src/Api/Controller/` — REST API controllers (no locale prefix, JSON responses)

### Async Email via Messenger
Emails dispatch `SendEmailMessage` to an async queue (configured in `messenger.yaml`). Handler in `src/MessageHandler/SendEmailMessageHandler.php` sends via Symfony Mailer. Transport: Doctrine in dev, configurable via `MESSENGER_TRANSPORT_DSN`.

### User Account Workflow
State machine in `config/packages/workflow.yaml` manages `AccountStatus` enum (ACTIVE/SUSPENDED/BANNED) on the User entity. Transitions: suspend, unsuspend, ban. Used in `UserController::toggleActive()`.

### Forms
`BaseUserType` is the abstract parent. `UserType` extends it (admin mode adds role selection). `RegistrationType` returns array data (not bound to entity). `ChangePasswordType` conditionally shows old password field (skipped for admins).

## Code Style

- PHP-CS-Fixer with `@Symfony` + `@Symfony:risky` rules
- `declare_strict_types` required in all PHP files
- Non-Yoda style (`$var === true`, not `true === $var`)
- No `protected` methods (converted to `private`)
- Fully qualified imports (classes, functions, constants)
- PHPStan level 8

## Test Environment

- Database: SQLite (`var/test.db`)
- Mailer: `null://null` (no emails sent)
- Messenger: `doctrine://default?auto_setup=0`

## Fixtures

Test users in `src/DataFixtures/UserFixtures.php`:
- `admin@example.com` / `Test123!` (ROLE_ADMIN)
- `user@example.com` / `Test123!` (ROLE_USER)

## Key Paths

- Security config: `config/packages/security.yaml`
- Translations: `translations/messages.{en,fr}.yaml`, `security.{en,fr}.yaml`, `validators.{en,fr}.yaml`
- Email templates: `templates/emails/`
- Twig extensions: `src/Twig/` (DateExtension for locale-aware dates, LocaleSwitcherExtension for language toggle URLs)
