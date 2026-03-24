# Security

Overview of security measures in Heirloom Gallery. For architecture details, see `DEVELOPER.md` and `ARCHITECTURE.md`.

## CSRF Protection

All POST requests are validated by the `Csrf` class in `public/index.php`. A per-session token is generated via `random_bytes(32)` and stored in `$_SESSION['csrf_token']`. Every form includes a hidden field rendered by `Csrf::hiddenField()`. Invalid or missing tokens return 403.

Validation uses `hash_equals()` to prevent timing attacks.

## XSS Prevention

All user-supplied data rendered in templates is escaped via `Template::escape()`, which wraps `htmlspecialchars()` with `ENT_QUOTES`. Templates call `$e()` (an alias for `Template::escape()`) on every variable.

## Session Hardening

Session configuration in `public/index.php`:

- `session.use_strict_mode = 1` — rejects uninitialized session IDs
- `session.use_only_cookies = 1` — prevents session ID in URLs
- `session.cookie_httponly = 1` — blocks JavaScript access to session cookie
- `session.cookie_secure = 1` — set when HTTPS is detected
- Idle timeout via `Auth::checkSessionTimeout()` / `touchActivity()`

## Security Headers

Set on every response in `public/index.php`:

- `X-Frame-Options: DENY` — prevents clickjacking
- `X-Content-Type-Options: nosniff` — prevents MIME sniffing
- `X-XSS-Protection: 1; mode=block` — legacy XSS filter
- `Referrer-Policy: strict-origin-when-cross-origin`
- `Strict-Transport-Security` — HSTS when serving over HTTPS

## Rate Limiting

The `RateLimiter` class throttles login and registration attempts per email address. Attempts are stored in the `login_attempts` table with a configurable window (default: 5 attempts per 15 minutes). Successful login clears the counter.

## Input Validation

The `InputValidator` class enforces length limits on user input:

| Field | Max Length |
|-------|-----------|
| Shipping address | 500 |
| Interest message | 1,000 |
| Painting title | 255 |
| Painting description | 5,000 |

## Password Requirements

Enforced by `AuthController::validatePassword()`:

- Minimum 12 characters
- At least one uppercase letter
- At least one lowercase letter
- At least one number

Passwords are hashed with `password_hash()` (bcrypt by default).

## Reporting Vulnerabilities

If you find a security issue, please email the project owner directly rather than opening a public issue.
