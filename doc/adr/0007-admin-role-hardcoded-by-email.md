# 7. Admin role hardcoded by email

Date: 2026-03-22

## Status

Accepted

## Context

The application needs an admin who can upload paintings, view interested users, award paintings, and delete listings. This is a single-admin project — only one person (the painting owner) manages the giveaway.

## Decision

The admin user is seeded in `migrate.php` with a hardcoded email (`rob.sartin@gmail.com`), the `is_admin` flag set to 1, and a local development password (`foo`). The admin can log in via password (for local testing) or Google OAuth2 (for production). There is no admin registration flow, no role management UI, and no way to promote users to admin through the application.

## Consequences

- **Easier:** No role management complexity, no privilege escalation surface, no admin invite flow to build. Adding another admin is a single SQL statement.
- **Harder:** Adding admins requires database access. No self-service admin onboarding.
- **Acceptable because:** This is a single-owner project. The owner has database access. If multiple admins are ever needed, a simple admin management page can be added, but YAGNI applies strongly here.
