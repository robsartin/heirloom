# 5. Authentication via magic links password and Google OAuth2

Date: 2026-03-22

## Status

Accepted

## Context

Users need accounts to express interest in paintings. The audience is friends/family/acquaintances — not a general public SaaS. Registration friction should be minimal. We need to verify email ownership since awarded paintings will be coordinated via email.

## Decision

Support three authentication methods:

1. **Magic links** — Primary registration flow. User enters email, receives a one-time link valid for 1 hour. Clicking it creates their account (if new) and logs them in. After first login, they're prompted to set a password (optional).
2. **Password** — Users who set a password can log in with email + password. Passwords are hashed with `password_hash()` (bcrypt by default).
3. **Google OAuth2** — One-click login via Google using `league/oauth2-google`. Creates account on first use, matching by email.

All three methods converge on the same user record, matched by email address. Sessions use PHP's built-in session handling with `session_regenerate_id()` on login.

## Consequences

- **Easier:** Low-friction registration (just an email), email verification built into the flow, Google login for convenience. No "forgot password" flow needed since magic links serve that purpose.
- **Harder:** Magic links require SMTP configuration for production. In dev mode, links are logged to stderr as a fallback. Google OAuth requires a Cloud Console project and credentials.
- **Risk:** Magic link tokens are 32-byte random hex stored in the database. They're single-use and time-limited, but there's no rate limiting on magic link requests — could be added if abuse occurs.
