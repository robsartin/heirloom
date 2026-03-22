# 2. Use PHP 8 with no framework

Date: 2026-03-22

## Status

Accepted

## Context

We need a web application to display ~1000 paintings and allow users to express interest. This is a personal/family project, not a commercial product. The app needs to be simple to deploy, maintain, and understand. PHP is widely available on commodity hosting.

## Decision

Use PHP 8.1+ with no full framework (no Laravel, Symfony, etc.). Instead, use a minimal custom router, a thin database wrapper around SQLite3, and Composer only for OAuth and mail libraries. PSR-4 autoloading via Composer organizes the codebase into `src/` with a `Heirloom\` namespace.

## Consequences

- **Easier:** Minimal dependencies, fast startup, trivial to deploy (just PHP + SQLite), easy to understand the entire codebase. No framework upgrade treadmill.
- **Harder:** No ORM, no built-in CSRF protection, no middleware pipeline, no CLI tooling (artisan, etc.). Security concerns (XSS, SQL injection) must be handled manually via prepared statements and `htmlspecialchars`.
- **Risk:** If the project grows significantly in scope, the lack of framework conventions could slow development. Acceptable given the fixed, well-scoped nature of this project.
