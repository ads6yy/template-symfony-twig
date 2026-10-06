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
docker-compose -f deploy/docker-compose.yml up -d   # MariaDB:3400, App:8400, Mailpit:8401, RabbitMQ UI:8402, Dependency-Track UI:8403 / API:8404
```

Dependency-Track v5 (`dtrack-*` services, PostgreSQL 18) requires `DTRACK_DB_PASSWORD` in `deploy/.env`; `DTRACK_API_BASE_URL` (default `http://localhost:8404`) is the API URL as seen from the browser.

### SBOM upload to Dependency-Track
`deploy/php/sbom-upload.sh` builds a CycloneDX 1.6 JSON SBOM of the Composer dependencies (dev excluded) with the `cyclonedx/cyclonedx-php-composer` plugin, then POSTs it to `/api/v1/bom` with `autoCreate=true`. It runs daily at 03:00 via cron in the php container (`deploy/php/sbom-upload.cron`, copied into the image; `cron` is started by supervisor, which first dumps the `DTRACK_*`/`COMPOSER_*` variables to `/etc/sbom-upload.env` since cron does not inherit the container environment). Logs: `/var/log/supervisor/sbom-upload.log`. Requires `DTRACK_API_KEY` in `deploy/.env` (team with `BOM_UPLOAD` + `PROJECT_CREATION_UPLOAD`); `DTRACK_API_URL`, `DTRACK_PROJECT_NAME`, `DTRACK_PROJECT_VERSION` default to `http://dtrack-apiserver:8080`, `symfony-twig`, `main`. Manual run: `docker exec symfony-php-apache /srv/deploy/php/sbom-upload.sh`.

## Architecture

### Dual Authentication System
- **Web**: Form login with CSRF at `/auth/login`, handled by `security.yaml` `main` firewall
- **API**: JSON login at `/api/login`, handled by `security.yaml` `api` firewall with custom handlers in `src/Api/Security/`
- Success handler (`src/Security/AuthenticationSuccessHandler.php`) routes admins to user list, regular users to their profile

### Locale-Prefixed Routing
All web routes are prefixed with `/{_locale}` (en|fr). API routes under `/api` have no locale prefix. Root `/` redirects to `/en`. The `LocaleSubscriber` syncs locale between URL and session.

### Controller Separation
- `src/Controller/`: Web controllers (locale-prefixed routes)
- `src/Api/Controller/`: REST API controllers (no locale prefix, JSON responses)

### Async Email via Messenger
Emails dispatch `SendEmailMessage` to an async queue (configured in `messenger.yaml`). Handler in `src/MessageHandler/SendEmailMessageHandler.php` sends via Symfony Mailer. Transport: Doctrine in dev, configurable via `MESSENGER_TRANSPORT_DSN`.

### User Account Workflow
State machine in `config/packages/workflow.yaml` manages `AccountStatus` enum (ACTIVE/SUSPENDED/BANNED) on the User entity. Transitions: suspend, unsuspend, ban. Used in `UserController::toggleActive()`.

### Email Verification (web)
Self-registration creates users with `User::$isVerified = false` and emails a signed verification link (`symfonycasts/verify-email-bundle`, default 1h TTL) via the async `SendEmailMessage` flow. `AuthController::verifyUserEmail()` validates the signature and flips the flag; `resendVerification()` re-sends (CSRF-protected, no user enumeration). `EmailVerificationSubscriber` redirects any authenticated-but-unverified user to the resend page for every web route (`/api` is exempt: verification is web-only). The `isVerified` flag is intentionally separate from `AccountStatus` (moderation). Fixtures are pre-verified.

### Password Reset (web)
`ResetPasswordController` (`symfonycasts/reset-password-bundle`) drives the flow: request form → signed token stored in `ResetPasswordRequest` (dedicated entity/table) → reset email (async `SendEmailMessage`, default 1h TTL) → new-password form (reuses `ChangePasswordType` with `require_old_password: false`). Tokens are single-use (`removeResetRequest`) and the request/check-email pages never reveal whether an account exists (no enumeration). The concrete `ResetPasswordHelper` is aliased in `services.yaml` for `generateFakeResetToken()`.

### Two-Factor Authentication (web)
TOTP via `scheb/2fa-bundle` (`scheb/2fa-totp` + `scheb/2fa-backup-code`), configured on the `main` firewall only (`/api` is exempt). `User` implements `TwoFactorInterface` and `BackupCodeInterface`: a non-null `totpSecret` means 2FA is enabled; `backupCodes` holds SHA-256 hashes of 10 single-use codes (both fields are stripped from the serialized session user). `TwoFactorController` (`/users/{id}/two-factor/...`) handles enable (pending seed kept in session, QR code rendered as SVG data URI by `endroid/qr-code`, confirmed by a valid code), one-time display/download of backup codes, disable (owner only) and admin reset (recovery when device and codes are lost). The login challenge is the bundle's form at `/{_locale}/auth/2fa` (template `auth/2fa_form.html.twig`), accepting a TOTP or a backup code; invalid codes count towards `login_throttling`. Tests freeze time by replacing the `clock` service with a `MockClock`.

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
