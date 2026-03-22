# 9. Use MySQL for data storage

Date: 2026-03-22

## Status

Accepted

Supercedes  [3. Use SQLite for data storage](0003-use-sqlite-for-data-storage.md)

## Context

The application stores users, paintings, magic links, and interest records. The initial implementation used SQLite for simplicity, but MySQL is preferred for production deployment — it handles concurrent writes better, is standard on most hosting platforms, and aligns with the deployment target.

## Decision

Use MySQL 8+ as the data store, accessed via PDO. The `Database` class wraps PDO with convenience methods (`fetchOne`, `fetchAll`, `execute`, `scalar`). Connection parameters are configured via `.env` file (`DB_HOST`, `DB_PORT`, `DB_NAME`, `DB_USER`, `DB_PASS`). Schema is managed by `migrate.php` using `CREATE TABLE IF NOT EXISTS`. The admin user is seeded on first migration.

## Consequences

- **Easier:** Better concurrent write handling, standard on hosting platforms, familiar to most PHP developers, better tooling (phpMyAdmin, MySQL Workbench, etc.).
- **Harder:** Requires a running MySQL server for development and production. More setup steps than SQLite's zero-config approach.
- **Migration:** Replaced SQLite3 extension calls with PDO. SQL syntax adjusted for MySQL (e.g., `AUTO_INCREMENT` instead of `AUTOINCREMENT`, `NOW()` instead of `datetime('now')`).
