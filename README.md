# Symfony + Twig

This repository serves as a **reference implementation** for standard web application features using **Symfony** and *
*Twig**.

Instead of being a blank starter kit, this project implements "classic" functionalities in a standard, best-practice
way. It is designed to act as a code bank or knowledge base that I can refer to when building new projects.

![Symfony](https://img.shields.io/badge/symfony-%23000000.svg?style=for-the-badge&logo=symfony&logoColor=white)
![Twig](https://img.shields.io/badge/twig-%23BBCD42.svg?style=for-the-badge&logo=twig&logoColor=white)
![PHP](https://img.shields.io/badge/php-%23777BB4.svg?style=for-the-badge&logo=php&logoColor=white)
![Docker](https://img.shields.io/badge/docker-%230db7ed.svg?style=for-the-badge&logo=docker&logoColor=white)

## 🛠️ Tech Stack

* **Framework:** Symfony 8.0
* **Frontend:** Twig
* **Language:** PHP 8.4+
* **Environment:** Docker

## 🚀 Quick Start

```bash
# Install dependencies
composer install

# Run unit and functional tests
composer test

# Run E2E tests (requires browser drivers - see below)
composer test:e2e

# Run QA checks
composer qa

# Run linting
composer lint

# Run static analysis
composer analyse
```

### Running E2E Tests

E2E tests use Symfony Panther and require browser drivers. **First-time setup:**

```bash
# Install browser drivers (geckodriver, chromedriver)
vendor/bin/bdi detect drivers

# Then run E2E tests
composer test:e2e
```

**Note:** Browser drivers are platform-specific and regenerated locally. They are not committed to the repository.

## 📚 Documentation

- **[CLAUDE.md](CLAUDE.md)** - AI assistant guidance for working with this codebase

## 📅 Roadmap & Upcoming Features

This project is evolving as I implement more "classic" modules. You can track the progress of specific features below:

| Feature / Module             | Description                                                   | Status |
|:-----------------------------|:--------------------------------------------------------------|:-------|
| **User Authentication**      | Standard Login/Registration using Symfony Security.           | ✅ Done |
| **Database & Entity**        | Clean implementation of Doctrine Entities and Migrations.     | ✅ Done |
| **Admin Dashboard**          | Basic administration for user management.                     | ✅ Done |
| **Form**                     | Form handling with validation and email notification.         | ✅ Done |
| **Mailer**                   | Symfony Mailer integration for sending emails.                | ✅ Done |
| **Translation System**       | Multi-language support with Symfony Translation.              | ✅ Done |
| **Symfony Message**          | Message/Queue handling implementation with RabbitMQ.          | ✅ Done |
| **Swagger**                  | API documentation with Swagger/OpenAPI.                       | ✅ Done |
| **QA CI**                    | Quality assurance tools (PHPStan, PHP-CS-Fixer, PHPMD, etc.). | ✅ Done |
| **Workflow**                 | Basic Symfony Workflow implementation                         | ✅ Done |
| **Basic PHPUnit Tests**      | Unit and functional testing setup.                            | ✅ Done |
| **Symfony Notifier**         | Desktop notification implementation.                          | ✅ Done |
| **Renovate implementation**  | Renovate setup.                                               | ✅ Done |
| **Panther PHPUnit Tests**    | Panther testing setup.                                        | ✅ Done |
| **Security Hardening**       | Rate limiting, password policies, security headers.           | ✅ Done |
| **Health Check Endpoint**    | System health monitoring endpoint.                            | ✅ Done |
| **Email Verification**       | Email verification on user registration.                      | ⏳ Todo |
| **Password Reset**           | Password reset functionality via email.                       | ⏳ Todo |
| **Two-Factor Auth (2FA)**    | 2FA implementation (TOTP/SMS).                                | ⏳ Todo |
| **API Versioning**           | API versioning and pagination support.                        | ⏳ Todo |
| **Application Monitoring**   | Integration with Sentry or New Relic.                         | ⏳ Todo |
| **Performance Optimization** | Redis caching, OPcache configuration.                         | ⏳ Todo |

*Progress tracked via [Template - Symfony Twig](https://github.com/users/ads6yy/projects/1).*

## 📄 License

This code is open-source. Feel free to use these patterns and implementations in your own projects.
