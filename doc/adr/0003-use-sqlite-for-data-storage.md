# 3. Use SQLite for data storage

Date: 2026-03-22

## Status

Superceded by [9. Use MySQL for data storage](0009-use-mysql-for-data-storage.md)

## Context

The application stores users, paintings, magic links, and interest records. The dataset is small (~1000 paintings, likely hundreds of users at most). The project should be easy to deploy without requiring a database server.

## Decision

Use SQLite as the sole data store, accessed via PHP's built-in `SQLite3` extension. WAL journal mode is enabled for better concurrent read performance. Foreign keys are enforced. The database file lives in `data/heirloom.db` (gitignored). Schema is managed by a single `migrate.php` script.

## Consequences

- **Easier:** Zero infrastructure — no MySQL/PostgreSQL server to install or manage. Single-file database is trivial to back up (`cp`), inspect (`sqlite3` CLI), or reset. Ships with PHP, no extensions to install.
- **Harder:** No concurrent write scaling. Only one writer at a time (WAL helps but doesn't eliminate this). No built-in migration versioning — `migrate.php` uses `CREATE TABLE IF NOT EXISTS` which won't handle schema changes cleanly.
- **Risk:** If many users submit interest simultaneously, write contention could cause brief delays. Acceptable for the expected traffic level of a painting giveaway.
