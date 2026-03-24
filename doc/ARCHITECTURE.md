# Architecture

High-level overview of Heirloom Gallery's architecture. For diagrams and class details, see `DEVELOPER.md`. For setup instructions, see `README.md`.

## Directory Structure

```
public/          Web root — index.php front controller, static assets, uploads
src/             Application code (PSR-4: Heirloom\)
  Controllers/   GalleryController, AuthController, AdminController
templates/       PHP view templates with layout wrapper
tests/           PHPUnit test suite
spec/            PHPSpec behavioral specs
doc/adr/         Architecture Decision Records (0001-0011)
```

## Request Lifecycle

Every HTTP request flows through a single entry point:

1. `public/index.php` loads config, starts session, sets security headers
2. `Auth::checkSessionTimeout()` expires idle sessions
3. POST requests are validated for CSRF tokens
4. `Router::dispatch()` matches the URL to a controller method
5. The controller checks auth (login/admin), queries the database, and calls `Template::render()`
6. The template renders HTML within a shared layout

No framework — just PHP 8 classes wired together in the front controller (ADR 0002).

## Key Classes

| Class | Responsibility |
|-------|---------------|
| `Router` | Regex-based URL matching, dispatches to controller callables |
| `Auth` | Session management, login/logout, magic links, OAuth, email notifications |
| `Database` | PDO wrapper; accepts injected PDO for testability |
| `Template` | Renders PHP templates with XSS escaping and layout wrapping |
| `Csrf` | Per-session CSRF token generation and validation |
| `RateLimiter` | Throttles login/register attempts via `login_attempts` table |
| `InputValidator` | Enforces field length limits |
| `SiteSettings` | Database-backed key/value configuration |
| `Mailer` | Interface with `SmtpMailer` (production) and `LogMailer` (dev/test) |
| `Thumbnail` | GD-based image thumbnail generation on upload |
| `Config` | Parses `.env` files into static key/value store |
| `ErrorHandler` | Formats exceptions for error logging |

## Dependency Flow

```
index.php
  -> Config (loads .env)
  -> Database (PDO singleton, or injected in tests)
  -> SiteSettings (reads from database)
  -> Auth (depends on Database, SiteSettings, Mailer)
  -> Controllers (depend on Database, Auth, SiteSettings)
  -> Template (renders response)
```

Controllers never instantiate their own dependencies — everything is constructed in `index.php` and passed via constructor injection.

## Database

MySQL in production (ADR 0009), in-memory SQLite for tests. Seven tables:

- `users` — accounts with optional password hash and admin flag
- `paintings` — gallery items with award tracking
- `interests` — many-to-many between users and paintings
- `magic_links` — single-use email login tokens
- `login_attempts` — rate limiting data
- `award_log` — audit trail for award/unassign actions
- `site_settings` — admin-configurable key/value pairs

Schema is created by `migrate.php`. See `DEVELOPER.md` for the full ER diagram.

## Design Decisions

All significant decisions are recorded as ADRs in `doc/adr/`. Key ones:

| ADR | Topic |
|-----|-------|
| 0002 | PHP 8, no framework |
| 0005 | Three auth methods (magic link, password, Google OAuth) |
| 0009 | MySQL via PDO (supersedes SQLite, ADR 0003) |
| 0010 | Strict TDD for all new code |
| 0011 | Branch-based development with PR to main |
