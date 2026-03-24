# Testing Guide

## Running Tests

```bash
composer test          # PHPUnit
composer spec          # PHPSpec
composer check         # Both (used by CI)
```

Or directly: `php vendor/bin/phpunit`

## Test Structure

All PHPUnit tests live in `tests/` as a flat directory of `*Test.php` files. PHPSpec specs live in `spec/Heirloom/`.

- **PHPUnit** — covers controllers, services, and integration behavior
- **PHPSpec** — covers value objects and simple class behavior

Configuration: `phpunit.xml` (bootstrap: `tests/bootstrap.php`), `phpspec.yml`.

## In-Memory SQLite for Database Tests

Tests that need a database create an in-memory SQLite instance in `setUp()`:

```php
$pdo = new PDO('sqlite::memory:');
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
$pdo->exec("CREATE TABLE ...");
$this->db = new Database($pdo);
```

This avoids any external database dependency. The `Database` class accepts an injected PDO, so tests pass their own connection. Each test gets a fresh database — no cleanup needed.

## TDD Workflow

All new code follows strict red-green-refactor (see ADR 0010):

1. Write one failing test
2. Write minimum code to pass
3. Refactor while green
4. Repeat

Never write a batch of tests up front. The feedback loop is the point.

### When modifying existing tests

- **Keep** if the behavior is still correct
- **Change** if requirements genuinely changed (document why in the commit)
- **Delete** if it tests removed behavior or an implementation detail
- **Never** change a test just to make it pass

## Test File Naming

Files are named `{Feature}Test.php` in `tests/`, e.g. `RateLimiterTest.php`, `AuthTest.php`, `CsrfTest.php`. Classes use the `Heirloom\Tests` namespace and extend `PHPUnit\Framework\TestCase`.

Test methods use `testDescriptiveBehaviorName` (e.g. `testIsAllowedReturnsTrueWithNoAttempts`).

## Adding a New Test

1. Create `tests/YourFeatureTest.php`
2. Use namespace `Heirloom\Tests` and extend `TestCase`
3. If the test needs a database, set up in-memory SQLite in `setUp()` (see pattern above)
4. Follow TDD: write the failing test first, then implement
5. Run `composer test` to confirm all tests pass

## CI

GitHub Actions runs `composer check` (PHPUnit + PHPSpec) on every PR. See `.github/workflows/tests.yml` and ADR 0011.
