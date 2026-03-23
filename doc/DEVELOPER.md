# Heirloom Gallery - Developer Documentation

## System Overview

```mermaid
graph TB
    subgraph Client
        Browser[Browser]
    end

    subgraph Server["PHP Application"]
        FC[index.php\nFront Controller]
        CSRF[Csrf]
        Router[Router]
        Auth[Auth]
        RL[RateLimiter]
        SS[SiteSettings]
        GC[GalleryController]
        AC[AuthController]
        ADC[AdminController]
        DB[Database PDO]
        ML[Mailer]
        TH[Thumbnail]
        TPL[Template]
    end

    subgraph External
        MySQL[(MySQL)]
        Google[Google OAuth2]
        SMTP[SMTP Server]
        Uploads[public/uploads/]
    end

    Browser -->|HTTP| FC
    FC --> CSRF
    FC --> Router
    Router --> GC
    Router --> AC
    Router --> ADC
    GC --> Auth
    AC --> Auth
    AC --> RL
    ADC --> Auth
    GC --> DB
    AC --> DB
    ADC --> DB
    Auth --> DB
    Auth --> ML
    ADC --> TH
    GC --> TPL
    AC --> TPL
    ADC --> TPL
    DB --> MySQL
    AC -->|OAuth2| Google
    ML -->|SmtpMailer| SMTP
    ADC -->|upload| Uploads
    Browser -->|static files| Uploads
```

## Request Lifecycle

Every request flows through a single entry point with CSRF protection and session management.

```mermaid
sequenceDiagram
    participant B as Browser
    participant I as index.php
    participant CSRF as Csrf
    participant R as Router
    participant C as Controller
    participant A as Auth
    participant D as Database
    participant T as Template

    B->>I: HTTP Request
    I->>I: Config::load(.env)
    I->>I: session_start()
    I->>A: checkSessionTimeout()
    I->>A: touchActivity()
    alt POST request
        I->>CSRF: validate(token)
        alt Invalid token
            I-->>B: 403 Forbidden
        end
    end
    I->>R: dispatch(method, uri)
    R->>R: Match route pattern
    R->>C: Call handler(params)
    C->>A: requireLogin() or requireAdmin()
    A->>D: SELECT user WHERE id = ?
    C->>D: Query data
    D-->>C: Results
    C->>T: Template::render(name, data)
    T-->>B: HTML Response
```

## Authentication Flow

The application supports three authentication methods that converge on a single user record matched by email. Registration is via magic link only. Google OAuth is for returning users.

### Magic Link Registration and Login

```mermaid
sequenceDiagram
    participant U as User
    participant App as Application
    participant RL as RateLimiter
    participant DB as Database
    participant Mail as Mailer

    U->>App: POST /register {email, name}
    App->>RL: isAllowed(email)
    alt Rate limited
        App-->>U: "Too many attempts"
    end
    App->>DB: findOrCreateUserByEmail(email, name)
    App->>RL: record(email)
    App->>App: createMagicLink(email)
    App->>DB: INSERT magic_links {email, token}
    App->>Mail: send(EmailMessage)
    Mail-->>U: Email with login link
    Note over Mail: Link: /auth/magic/{token}<br/>Expiry: configurable (default 60min)<br/>Single use

    U->>App: GET /auth/magic/{token}
    App->>DB: SELECT magic_links WHERE token AND used=0 AND not expired
    alt Valid token
        App->>DB: UPDATE magic_links SET used=1
        App->>DB: findOrCreateUserByEmail(email)
        App->>App: loginUser(userId)
        alt No password or forgot-password flag
            App-->>U: Redirect to /set-password
        else Has password
            App-->>U: Redirect to saved URL or /
        end
    else Invalid or expired token
        App-->>U: "Invalid or expired login link"
    end
```

### Password Login

```mermaid
sequenceDiagram
    participant U as User
    participant App as Application
    participant RL as RateLimiter
    participant DB as Database

    U->>App: POST /login {email, password}
    App->>RL: isAllowed(email)
    alt Rate limited
        App-->>U: "Too many attempts"
    end
    App->>DB: SELECT user WHERE email = ?
    alt User found with password_hash
        App->>App: password_verify(password, hash)
        alt Password matches
            App->>RL: clear(email)
            App->>App: loginUser(userId)
            App-->>U: Redirect to saved URL or /
        else Wrong password
            App->>RL: record(email)
            App-->>U: "Invalid email or password"
        end
    else No user or no password set
        App->>RL: record(email)
        App-->>U: "Invalid email or password"
    end
```

### Google OAuth2 Login (existing users only)

```mermaid
sequenceDiagram
    participant U as User
    participant App as Application
    participant G as Google
    participant DB as Database

    U->>App: GET /auth/google
    App->>App: Generate OAuth state token
    App->>App: Store state in session
    App-->>U: Redirect to Google consent screen

    U->>G: Authorize application
    G-->>U: Redirect to /auth/google/callback

    U->>App: GET /auth/google/callback
    App->>App: Verify state matches session
    App->>G: Exchange code for access token
    G-->>App: Access token
    App->>G: Get user profile
    G-->>App: email and name
    App->>DB: findUserByEmail(email)
    alt User exists
        App->>App: loginUser(userId)
        App-->>U: Redirect to saved URL or /
    else No account found
        App-->>U: Redirect to /register with error
    end
```

### Magic Link Token Lifecycle

```mermaid
stateDiagram-v2
    [*] --> Created: createMagicLink()
    Created --> Valid: Token exists, used=0, not expired
    Valid --> Consumed: consumeMagicLink() sets used=1
    Valid --> Expired: Age exceeds configured expiry
    Consumed --> [*]: Cannot be reused
    Expired --> [*]: Rejected on next attempt

    note right of Valid: Expiry configurable via\nadmin settings (default 60min)
```

## Award and Notification Flow

```mermaid
sequenceDiagram
    participant Admin as Admin
    participant App as Application
    participant DB as Database
    participant Mail as Mailer

    Admin->>App: POST /admin/painting/{id}/award {user_id}
    App->>DB: UPDATE paintings SET awarded_to, awarded_at
    App->>DB: INSERT award_log (awarded action)
    App->>DB: SELECT winner email and painting title
    App->>Mail: sendAwardNotification(winner email, title)
    Mail-->>Admin: Winner: "You've been awarded..."
    App->>DB: SELECT other interested users
    App->>Mail: sendLoserNotifications(loser emails, title)
    Mail-->>Admin: Losers: "Painting awarded to another..."
    App-->>Admin: Redirect to manage page
```

## Painting Lifecycle

```mermaid
stateDiagram-v2
    [*] --> Uploaded: Admin uploads image + thumbnail
    Uploaded --> Available: Visible in gallery for logged-in users

    Available --> HasInterest: User clicks "I want this"
    HasInterest --> Available: User withdraws interest
    HasInterest --> HasInterest: More users express interest

    HasInterest --> Awarded: Admin awards to user
    Available --> Awarded: Admin awards to user
    Awarded --> Shipped: Admin adds tracking number
    Shipped --> Awarded: Admin clears tracking
    Awarded --> Available: Admin unassigns
    Shipped --> Available: Admin unassigns

    Available --> [*]: Admin deletes
    HasInterest --> [*]: Admin deletes
    Awarded --> [*]: Admin deletes
    Shipped --> [*]: Admin deletes

    note right of Awarded: Winner + losers notified by email
    note right of Available: Anonymous visitors see landing page
```

## Database Schema

```mermaid
erDiagram
    users {
        INT id PK
        VARCHAR email UK
        VARCHAR name
        VARCHAR password_hash
        TEXT shipping_address
        TINYINT is_admin
        DATETIME created_at
    }

    paintings {
        INT id PK
        VARCHAR title
        TEXT description
        VARCHAR filename
        VARCHAR original_filename
        INT awarded_to FK
        DATETIME awarded_at
        VARCHAR tracking_number
        DATETIME created_at
    }

    interests {
        INT id PK
        INT painting_id FK
        INT user_id FK
        TEXT message
        DATETIME created_at
    }

    magic_links {
        INT id PK
        VARCHAR email
        VARCHAR token UK
        TINYINT used
        DATETIME created_at
    }

    award_log {
        INT id PK
        INT painting_id FK
        INT user_id FK
        INT awarded_by FK
        ENUM action
        DATETIME created_at
    }

    login_attempts {
        INT id PK
        VARCHAR identifier
        DATETIME attempted_at
    }

    site_settings {
        VARCHAR setting_key PK
        VARCHAR setting_value
        VARCHAR label
        VARCHAR description
    }

    users ||--o{ interests : "expresses"
    paintings ||--o{ interests : "receives"
    users ||--o{ paintings : "awarded_to"
    paintings ||--o{ award_log : "history"
    users ||--o{ award_log : "recipient"
    users ||--o{ award_log : "admin"
```

## Route Map

### Gallery Routes (GalleryController)

```mermaid
graph LR
    GET_ROOT["GET /"] --> GC1["index (landing or gallery)"]
    GET_PAINTING["GET /painting/id"] --> GC2["show (requires login)"]
    POST_INTEREST["POST /painting/id/interest"] --> GC3[expressInterest]
    GET_MY["GET /my-paintings"] --> GC4[myPaintings]
    GET_SITEMAP["GET /sitemap.xml"] --> GC5[sitemapXml]
```

### Auth Routes (AuthController)

```mermaid
graph LR
    GET_LOGIN["GET /login"] --> AC1[loginForm]
    POST_LOGIN["POST /login"] --> AC2[login]
    GET_REG["GET /register"] --> AC3[registerForm]
    POST_REG["POST /register"] --> AC4[register]
    GET_MAGIC["GET /auth/magic/token"] --> AC5[magicLogin]
    GET_GOOGLE["GET /auth/google"] --> AC6[googleRedirect]
    GET_GCALLBACK["GET /auth/google/callback"] --> AC7[googleCallback]
    GET_LOGOUT["GET /logout"] --> AC8[logout]
    GET_SETPW["GET /set-password"] --> AC9[setPasswordForm]
    POST_SETPW["POST /set-password"] --> AC10[setPassword]
    GET_PROFILE["GET /profile"] --> AC11[profileForm]
    POST_PROFILE["POST /profile"] --> AC12[updateProfile]
```

### Admin Routes (AdminController)

```mermaid
graph LR
    GET_ADMIN["GET /admin"] --> AD1[dashboard]
    GET_UPLOAD["GET /admin/upload"] --> AD2[uploadForm]
    POST_UPLOAD["POST /admin/upload"] --> AD3[upload]
    GET_MANAGE["GET /admin/painting/id"] --> AD4[managePainting]
    POST_EDIT["POST /admin/painting/id/edit"] --> AD5[edit]
    POST_AWARD["POST /admin/painting/id/award"] --> AD6[award]
    POST_TRACK["POST /admin/painting/id/tracking"] --> AD7[updateTracking]
    POST_DELETE["POST /admin/painting/id/delete"] --> AD8[delete]
    GET_SETTINGS["GET /admin/settings"] --> AD9[settingsForm]
    POST_SETTINGS["POST /admin/settings"] --> AD10[updateSettings]
    GET_EXP["GET /admin/export/paintings"] --> AD11[exportPaintings]
    GET_EXU["GET /admin/export/users"] --> AD12[exportUsers]
```

## Class Diagram

```mermaid
classDiagram
    class Auth {
        -Database db
        -SiteSettings settings
        -Mailer mailer
        +user() array?
        +userId() int?
        +isLoggedIn() bool
        +isAdmin() bool
        +requireLogin() void
        +requireAdmin() void
        +loginUser(userId) void
        +logout() void
        +attemptPasswordLogin(email, password) array?
        +findUserByEmail(email) array?
        +findOrCreateUserByEmail(email, name) array
        +createMagicLink(email) string
        +consumeMagicLink(token) string?
        +sendMagicLink(email, token) bool
        +sendAwardNotification(email, title) bool
        +sendLoserNotifications(emails, title) void
        +checkSessionTimeout() void
        +touchActivity() void
        +consumeRedirect() string
    }

    class Mailer {
        <<interface>>
        +send(EmailMessage) bool
    }

    class SmtpMailer {
        +send(EmailMessage) bool
    }

    class LogMailer {
        +send(EmailMessage) bool
        +getLastMessage() EmailMessage?
        +getAllMessages() EmailMessage[]
    }

    class EmailMessage {
        +string to
        +string subject
        +string htmlBody
        +string textBody
    }

    Mailer <|.. SmtpMailer
    Mailer <|.. LogMailer
    Auth --> Mailer
    Auth --> EmailMessage
    Auth --> Database
    Auth --> SiteSettings
```

## Project Structure

```
heirloom/
├── public/                     Web root (server document root)
│   ├── index.php               Front controller with CSRF, session timeout
│   ├── .htaccess               Apache rewrite + cache headers
│   ├── .user.ini               PHP upload/memory limits (production)
│   ├── robots.txt              Search engine directives
│   ├── css/style.css           Stylesheet (cache-busted via ?v=)
│   └── uploads/                Uploaded images + thumbnails
├── src/                        Application code (PSR-4: Heirloom\)
│   ├── Auth.php                Authentication, sessions, email notifications
│   ├── Config.php              .env file parser
│   ├── Csrf.php                CSRF token generation and validation
│   ├── Database.php            PDO wrapper (MySQL, injectable for tests)
│   ├── EmailMessage.php        Email value object (to, subject, body)
│   ├── LogMailer.php           Dev mailer — logs to error_log
│   ├── Mailer.php              Mailer interface
│   ├── RateLimiter.php         Login/register attempt throttling
│   ├── Router.php              Regex-based URL router
│   ├── SiteSettings.php        Database-backed key/value settings
│   ├── SmtpMailer.php          Production mailer via PHPMailer
│   ├── Template.php            View renderer with globals and XSS escaping
│   ├── Thumbnail.php           GD-based image thumbnail generation
│   └── Controllers/
│       ├── AdminController     Dashboard, upload, edit, award, export, settings
│       ├── AuthController      Login, register, OAuth, profile, password
│       └── GalleryController   Gallery, painting detail, interest, my-paintings
├── templates/                  PHP view templates
│   ├── layout.php              Base HTML with nav, OG tags, footer
│   ├── landing.php             Anonymous landing page
│   ├── gallery.php             Painting grid with search/sort
│   ├── painting.php            Single painting detail
│   ├── my-paintings.php        User's wanted/awarded/lost paintings
│   ├── profile.php             User profile with shipping address
│   ├── login.php               Login form (password + magic link + Google)
│   ├── register.php            Registration form (magic link only)
│   ├── set-password.php        Optional password setup
│   ├── error.php               Styled error page (404, 403, 500)
│   ├── partials/alerts.php     Reusable error/success flash alerts
│   └── admin/
│       ├── dashboard.php       Stats bar, sortable table, filters, export
│       ├── upload.php           Batch image upload form
│       ├── manage.php           Edit, award, tracking, history, delete
│       └── settings.php         Grouped site settings form
├── tests/                      PHPUnit test suite (~250 tests)
├── spec/                       PHPSpec behavioral specs (29 examples)
├── doc/                        Documentation and ADRs
│   ├── adr/                    Architecture Decision Records (0001-0011)
│   ├── DEVELOPER.md            This file
│   └── USER_GUIDE.md           End user and admin guide
├── .github/
│   ├── workflows/tests.yml     CI: PHPUnit + PHPSpec on every PR
│   ├── workflows/pr-review.yml Automated Claude PR review
│   └── CODEOWNERS              @robsartin owns all files
├── migrate.php                 Schema creation + seed data
├── seed-test-users.php         Sample users with interest messages
├── php-dev.ini                 PHP config for local dev
├── phpunit.xml                 PHPUnit configuration
├── phpspec.yml                 PHPSpec configuration
├── composer.json               Dependencies and scripts
└── .env.example                Environment variable template
```

## Development Workflow

All new code follows **strict TDD** (ADR 0010):

```mermaid
graph LR
    R[RED\nWrite one\nfailing test] --> G[GREEN\nMinimal code\nto pass]
    G --> RF[REFACTOR\nClean up\nkeep green]
    RF --> R
```

1. Create a feature branch: `git checkout -b feature/my-change`
2. Write one failing test
3. Write minimum code to make it pass
4. Refactor while keeping tests green
5. Repeat until the feature is complete
6. Push and open a PR to `main`
7. CI must pass (PHPUnit + PHPSpec)
8. Merge

### Running tests

```bash
composer test          # PHPUnit
composer spec          # PHPSpec
composer check         # Both
```

### Running locally

```bash
composer install
cp .env.example .env   # Edit with your MySQL credentials
php migrate.php        # Create schema and seed admin
php seed-test-users.php  # Optional: add sample data
php -c php-dev.ini -S localhost:8080 -t public/
```

## Key Design Decisions

| ADR | Decision |
|-----|----------|
| 0002 | PHP 8, no framework |
| 0004 | Server-side offset/limit pagination |
| 0005 | Three auth methods: magic link, password, Google OAuth2 |
| 0006 | CSS-only image resizing, originals stored as-is |
| 0009 | MySQL via PDO (supersedes SQLite) |
| 0010 | Strict TDD for all new code |
| 0011 | Branch-based development, PR to main, must pass tests |

Full ADRs are in `doc/adr/`.
