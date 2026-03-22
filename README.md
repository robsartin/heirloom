# Heirloom Gallery

A web application for giving away paintings. Users browse a gallery, express interest in paintings they'd like, and administrators select recipients and award paintings.

Built with PHP 8.1+ and MySQL. No framework — just a small, focused codebase.

## What it does

- **Public gallery** with paginated grid of available paintings
- **User accounts** via magic link email, password, or Google OAuth2
- **Express interest** — logged-in users claim paintings they want, with an optional message
- **Admin dashboard** — upload paintings (batch PNG/JPEG), view who wants what, award paintings to chosen users, edit titles/descriptions
- **Sortable admin columns** — sort by title, interest count, last interest date, or upload date
- **Filters** — available, wanted (has interest), awarded, all

## Project structure

```
heirloom/
├── public/                 # Web root (point your server here)
│   ├── index.php           # Front controller — all requests route through here
│   ├── .htaccess           # Apache rewrite rules
│   ├── .user.ini           # PHP upload limits for production
│   ├── css/style.css
│   └── uploads/            # Uploaded painting images (gitignored)
├── src/                    # Application code (PSR-4: Heirloom\)
│   ├── Config.php          # .env file parser
│   ├── Database.php        # PDO wrapper (MySQL, injectable for testing)
│   ├── Router.php          # Simple regex-based URL router
│   ├── Auth.php            # Session auth, magic links, password, OAuth
│   ├── Template.php        # Minimal template renderer with XSS escaping
│   └── Controllers/
│       ├── GalleryController.php   # Public gallery + interest toggle
│       ├── AuthController.php      # Login, register, magic link, OAuth, logout
│       └── AdminController.php     # Dashboard, upload, edit, award, delete
├── templates/              # PHP view templates
│   ├── layout.php          # Base HTML layout (nav, footer)
│   ├── gallery.php         # Painting grid with pagination
│   ├── painting.php        # Single painting detail + interest form
│   ├── login.php, register.php, set-password.php
│   └── admin/
│       ├── dashboard.php   # Sortable table with filters
│       ├── upload.php      # Batch upload form
│       └── manage.php      # Edit painting, view interests, award
├── tests/                  # PHPUnit tests
├── spec/                   # PHPSpec specs (for future use)
├── doc/adr/                # Architecture Decision Records
├── migrate.php             # Database schema + admin seed
├── seed-test-users.php     # Test data: 5 users with humorous interest reasons
├── php-dev.ini             # PHP config for local dev (large upload limits)
├── phpunit.xml             # PHPUnit configuration
├── phpspec.yml             # PHPSpec configuration
├── composer.json
└── .env.example            # Environment config template
```

## Design decisions

Architectural decisions are documented as ADRs in `doc/adr/`. Key ones:

- **No framework** (ADR 0002) — minimal dependencies, easy to understand entirely
- **MySQL via PDO** (ADR 0009) — standard hosting compatibility, concurrent writes
- **Three auth methods** (ADR 0005) — magic links for registration, password for convenience, Google OAuth for one-click
- **Server-side pagination** (ADR 0004) — offset/limit, 12 per page public, 20 per page admin
- **CSS-only image resizing** (ADR 0006) — originals stored as-is, `object-fit: contain` in gallery grid
- **TDD workflow** (ADR 0010) — all new code follows red-green-refactor, one test at a time

## Development workflow

All new code follows **strict TDD** (see ADR 0010):

1. Write one failing test
2. Write minimum code to pass
3. Refactor while green
4. Repeat

When modifying existing tests: keep if behavior is still correct, change if requirements changed, delete if testing removed behavior. Never change a test just to make it pass.

## Local development setup

### Prerequisites

- PHP 8.1+
- MySQL 8+
- Composer

On macOS with Homebrew:

```bash
brew install php mysql composer
brew services start mysql
```

### Install and run

```bash
# Clone
git clone git@github.com:robsartin/heirloom.git
cd heirloom

# Install dependencies
composer install

# Configure environment
cp .env.example .env
# Edit .env with your MySQL credentials (root with no password works for local dev)

# Create database and seed admin user
php migrate.php

# (Optional) Seed test users with sample interest data
php seed-test-users.php

# Start dev server with generous upload limits
php -c php-dev.ini -S localhost:8080 -t public/
```

Visit `http://localhost:8080`.

### Test accounts

| Account | Email | Password | Role |
|---------|-------|----------|------|
| Admin | rob.sartin@gmail.com | foo | Admin — full access |
| Test user | f@f.com | f | Regular user |
| Example users | *.@example.com | test | Regular users (seeded by seed-test-users.php) |

### Running tests

```bash
# PHPUnit (52 tests)
composer test

# PHPSpec
composer spec

# Both
composer check
```

### Google OAuth (optional for local dev)

1. Create OAuth credentials at [Google Cloud Console](https://console.cloud.google.com/apis/credentials)
2. Set authorized redirect URI to `http://localhost:8080/auth/google/callback`
3. Add `GOOGLE_CLIENT_ID` and `GOOGLE_CLIENT_SECRET` to `.env`

Without OAuth configured, users can still register and log in via magic links or password.

### Magic links in local dev

Without SMTP configured, magic link URLs are logged to PHP's error log (visible in the terminal running the dev server). Look for lines like:

```
Magic link for user@example.com: http://localhost:8080/auth/magic/abc123...
```

## Deploying to InfinityFree

InfinityFree provides free PHP + MySQL hosting. The free plan is suitable for a low-traffic painting giveaway.

### 1. Create an account

Sign up at [infinityfree.com](https://www.infinityfree.com/) and create a hosting account. Choose a free subdomain or connect a custom domain.

### 2. Create the MySQL database

1. In cPanel, go to **MySQL Databases**
2. Create a new database (e.g., `heirloom`)
3. Note the credentials shown: **DB Hostname**, **DB Name**, **DB Username**, **DB Password** — these are different from your account login

### 3. Prepare files for upload

On your local machine:

```bash
# Install production dependencies only
composer install --no-dev --optimize-autoloader

# Create your production .env
cp .env.example .env
```

Edit `.env` with InfinityFree's MySQL credentials:

```
DB_HOST=sqlXXX.infinityfree.com    # from cPanel MySQL details
DB_PORT=3306
DB_NAME=ifXXXX_heirloom            # from cPanel
DB_USER=ifXXXX_xxxxxxx             # from cPanel
DB_PASS=your-db-password            # from cPanel

APP_URL=https://your-subdomain.infinityfreeapp.com
```

### 4. Upload files

1. In cPanel, open **File Manager**
2. Navigate to the `htdocs` folder (this is the web root)
3. Upload the **contents of `public/`** directly into `htdocs/` — so `index.php`, `.htaccess`, `.user.ini`, `css/`, and `uploads/` go into `htdocs/`
4. Create a folder **outside** `htdocs` (e.g., at the same level) called `heirloom-app`
5. Upload `src/`, `templates/`, `vendor/`, `.env`, and `migrate.php` into `heirloom-app/`

### 5. Update paths in index.php

Since `public/` contents are in `htdocs/` and everything else is in `heirloom-app/`, update the require path in `htdocs/index.php`:

```php
// Change this:
require_once __DIR__ . '/../vendor/autoload.php';
// To this:
require_once __DIR__ . '/../heirloom-app/vendor/autoload.php';
```

And update `Config::load()`:

```php
// Change this:
Config::load(__DIR__ . '/../.env');
// To this:
Config::load(__DIR__ . '/../heirloom-app/.env');
```

Similarly, update the `$baseDir` in `Template.php` or pass the correct path.

### 6. Run the migration

Visit the migration script in your browser or use InfinityFree's cron job feature:

```
https://your-subdomain.infinityfreeapp.com/../heirloom-app/migrate.php
```

Or import the schema via phpMyAdmin (available in cPanel under the database's "Admin" button).

### 7. Configure uploads directory

Ensure `htdocs/uploads/` exists and is writable. InfinityFree's file manager can create this directory.

### InfinityFree limitations to be aware of

- **No SSH access** — file uploads are via File Manager or FTP
- **No Composer on server** — run `composer install --no-dev` locally, then upload the `vendor/` folder
- **File size limits** — InfinityFree may enforce its own upload limits beyond what `.user.ini` sets
- **No custom PHP CLI** — migrations must be run via browser or phpMyAdmin import
- **Free SSL** via InfinityFree's built-in option — enable it in cPanel

### Alternative: FTP deployment

You can also deploy via FTP. Credentials are in cPanel under **FTP Accounts**. Use any FTP client (FileZilla, Cyberduck, etc.) to upload files to the `htdocs` directory.

## SMTP configuration for production

For magic link emails to actually send, configure SMTP in `.env`:

```
MAIL_HOST=smtp.gmail.com
MAIL_PORT=587
MAIL_USERNAME=your-email@gmail.com
MAIL_PASSWORD=your-app-password
MAIL_FROM=noreply@yourdomain.com
MAIL_FROM_NAME=Heirloom Gallery
```

For Gmail, you'll need an [App Password](https://myaccount.google.com/apppasswords) (requires 2FA enabled on your Google account).

## License

Private project.
