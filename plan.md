# Plan: Add Panther E2E Tests (Issue #19)

## Context

The project already has 15 functional tests in `tests/Controller/UserControllerTest.php` using Symfony's `WebTestCase` + `BrowserKit` (simulated HTTP client). Panther tests add **real browser testing** — they launch a headless Chrome/Firefox and interact with the actual rendered DOM, catching JS errors, CSS issues, and real browser behavior that BrowserKit cannot.

## What Will Change

### 1. Install Symfony Panther dependency
- Add `symfony/panther: ^2.4` to `require-dev` in `composer.json`
- Run `composer install`
- Panther auto-detects Chrome/Chromium and downloads chromedriver as needed

### 2. Update PHPUnit configuration
- Add a new `<testsuite name="e2e">` entry in `phpunit.dist.xml` pointing to `tests/E2E/`
- Add `PANTHER_NO_HEADLESS=0` env var (headless mode for CI)

### 3. Create base E2E test class: `tests/E2E/PantherTestCase.php`
- Extends `Symfony\Component\Panther\PantherTestCase`
- Provides shared helpers:
  - `loadFixtures()` — runs SchemaTool + fixture loading (same pattern as existing tests)
  - `loginAs(string $email, string $password)` — navigates to `/en/auth/login`, fills form, submits
  - `loginAsUser()` / `loginAsAdmin()` — convenience shortcuts

### 4. Create E2E test classes

**`tests/E2E/AuthenticationE2ETest.php`** — Auth flows in a real browser
| Test | What it verifies |
|------|-----------------|
| `testLoginPageLoads` | Login page renders with form fields visible |
| `testSuccessfulLogin` | Fill email/password, submit, verify redirect & navbar shows user name |
| `testFailedLogin` | Wrong password shows error message in browser |
| `testLogout` | Click logout link, verify navbar reverts to Login/Register links |
| `testRegistration` | Fill registration form, submit, verify redirect to login page with success flash |

**`tests/E2E/UserManagementE2ETest.php`** — Admin CRUD in a real browser
| Test | What it verifies |
|------|-----------------|
| `testAdminSeesUserList` | Login as admin, navigate to `/en/users`, verify table with users renders |
| `testAdminCanCreateUser` | Fill new user form, submit, verify flash message & user appears in list |
| `testAdminCanViewUserProfile` | Click on a user, verify profile details render |
| `testAdminCanEditUser` | Edit user first/last name, submit, verify changes reflected |
| `testAdminCanDeleteUser` | Click delete, accept JS confirm dialog, verify user removed from list |
| `testAdminCanToggleUserStatus` | Click toggle-active, verify status change in profile |
| `testAdminCanChangeUserPassword` | Fill change password form (no old password needed), submit, verify success |

**`tests/E2E/NavigationE2ETest.php`** — Layout & navigation in a real browser
| Test | What it verifies |
|------|-----------------|
| `testHomepageLoads` | Root `/` redirects to `/en`, page renders |
| `testLocaleSwitch` | Click language switcher to French, verify URL changes to `/fr` and page content is in French |
| `testUnauthenticatedNavbar` | Verify Login/Register links visible, no profile/logout links |
| `testAuthenticatedNavbar` | After login, verify profile link & logout visible, no Login/Register links |
| `testAccessDeniedForRegularUser` | Login as user, navigate to `/en/users`, verify 403/redirect |

### 5. Run code quality checks
- Run `php-cs-fixer fix` for code style
- Run `phpstan analyse` to ensure level 8 compliance
- Run the Panther tests to verify they pass

## Files Created/Modified

| File | Action |
|------|--------|
| `composer.json` | Modified — add `symfony/panther` to require-dev |
| `composer.lock` | Modified — updated by composer |
| `phpunit.dist.xml` | Modified — add `e2e` testsuite |
| `tests/E2E/PantherTestCase.php` | **New** — base class with helpers |
| `tests/E2E/AuthenticationE2ETest.php` | **New** — 5 test methods |
| `tests/E2E/UserManagementE2ETest.php` | **New** — 7 test methods |
| `tests/E2E/NavigationE2ETest.php` | **New** — 5 test methods |

## What Won't Change
- Existing `tests/Controller/UserControllerTest.php` — untouched
- No changes to source code (`src/`), templates, or configuration
- API routes are not covered (Panther is for browser testing; API tests are a separate concern)

## Total: ~17 E2E test methods across 3 test classes
