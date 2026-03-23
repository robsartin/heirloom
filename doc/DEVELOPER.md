# Heirloom Gallery - Developer Documentation

## System Overview

```mermaid
graph TB
    subgraph Client
        Browser[Browser]
    end

    subgraph Server["PHP Application"]
        FC[public/index.php<br/>Front Controller]
        Router[Router]
        Auth[Auth]
        FC --> Router
        Router --> GC[GalleryController]
        Router --> AC[AuthController]
        Router --> ADC[AdminController]
        GC --> Auth
        AC --> Auth
        ADC --> Auth
        GC --> DB[Database PDO]
        AC --> DB
        ADC --> DB
        Auth --> DB
    end

    subgraph External
        MySQL[(MySQL)]
        Google[Google OAuth2]
        SMTP[SMTP Server]
        Uploads[public/uploads/]
    end

    Browser -->|HTTP| FC
    DB --> MySQL
    AC -->|OAuth2| Google
    Auth -->|PHPMailer| SMTP
    ADC -->|move_uploaded_file| Uploads
    Browser -->|static files| Uploads
```

## Request Lifecycle

Every request flows through a single entry point. This diagram shows the complete path from browser to response.

```mermaid
sequenceDiagram
    participant B as Browser
    participant I as index.php
    participant R as Router
    participant C as Controller
    participant A as Auth
    participant D as Database
    participant T as Template

    B->>I: HTTP Request
    I->>I: Config::load(.env)
    I->>I: session_start()
    I->>D: Database::getInstance()
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

The application supports three authentication methods that converge on a single user record matched by email.

### Magic Link Registration and Login

```mermaid
sequenceDiagram
    participant U as User
    participant App as Application
    participant DB as Database
    participant Mail as SMTP / Error Log

    U->>App: POST /register {email, name}
    App->>DB: findOrCreateUserByEmail(email, name)
    App->>App: createMagicLink(email)
    App->>DB: INSERT magic_links {email, token}
    App->>Mail: Send email with link
    Mail-->>U: Email: "Click to log in"
    Note over Mail: Link: /auth/magic/{token}<br/>Expires: 1 hour<br/>Single use

    U->>App: GET /auth/magic/{token}
    App->>DB: SELECT magic_links WHERE token AND used=0 AND <1hr old
    alt Valid token
        App->>DB: UPDATE magic_links SET used=1
        App->>DB: findOrCreateUserByEmail(email)
        App->>App: loginUser(userId)
        App->>App: session_regenerate_id()
        alt No password set
            App-->>U: Redirect to /set-password
        else Has password
            App-->>U: Redirect to saved URL or /
        end
    else Invalid/expired token
        App-->>U: "Invalid or expired login link"
    end
```

### Password Login

```mermaid
sequenceDiagram
    participant U as User
    participant App as Application
    participant DB as Database

    U->>App: POST /login {email, password}
    App->>DB: SELECT user WHERE email = ?
    alt User found with password_hash
        App->>App: password_verify(password, hash)
        alt Password matches
            App->>App: loginUser(userId)
            App-->>U: Redirect to saved URL or /
        else Wrong password
            App-->>U: "Invalid email or password"
        end
    else No user or no password set
        App-->>U: "Invalid email or password"
    end
```

### Google OAuth2 Login

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
    G-->>U: Redirect to /auth/google/callback?code=X&state=Y

    U->>App: GET /auth/google/callback
    App->>App: Verify state matches session
    App->>G: Exchange code for access token
    G-->>App: Access token
    App->>G: Get user profile (email, name)
    G-->>App: {email, name}
    App->>DB: findOrCreateUserByEmail(email, name)
    App->>App: loginUser(userId)
    App-->>U: Redirect to saved URL or /
```

### Magic Link Token Lifecycle

```mermaid
stateDiagram-v2
    [*] --> Created: createMagicLink()
    Created --> Valid: Token exists, used=0, age < 1hr
    Valid --> Consumed: consumeMagicLink() sets used=1
    Valid --> Expired: Age > 1 hour
    Consumed --> [*]: Cannot be reused
    Expired --> [*]: Rejected on next attempt
```

## Painting Lifecycle

```mermaid
stateDiagram-v2
    [*] --> Uploaded: Admin uploads image
    Uploaded --> Available: Visible in public gallery

    Available --> HasInterest: User clicks "I want this"
    HasInterest --> Available: User withdraws interest
    HasInterest --> HasInterest: More users express interest

    HasInterest --> Awarded: Admin clicks "Award"
    Available --> Awarded: Admin clicks "Award" (edge case)
    Awarded --> Available: Admin clicks "Unassign"

    Available --> [*]: Admin deletes
    HasInterest --> [*]: Admin deletes
    Awarded --> [*]: Admin deletes
```

## Database Schema

```mermaid
erDiagram
    users {
        INT id PK
        VARCHAR email UK
        VARCHAR name
        VARCHAR password_hash
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

    users ||--o{ interests : "expresses"
    paintings ||--o{ interests : "receives"
    users ||--o{ paintings : "awarded_to"
```

## Route Map

```mermaid
graph LR
    subgraph Public
        GET_ROOT["GET /"]
        GET_PAINTING["GET /painting/{id}"]
        POST_INTEREST["POST /painting/{id}/interest"]
    end

    subgraph Auth
        GET_LOGIN["GET /login"]
        POST_LOGIN["POST /login"]
        GET_REGISTER["GET /register"]
        POST_REGISTER["POST /register"]
        GET_MAGIC["GET /auth/magic/{token}"]
        GET_GOOGLE["GET /auth/google"]
        GET_GCALLBACK["GET /auth/google/callback"]
        GET_LOGOUT["GET /logout"]
        GET_SETPW["GET /set-password"]
        POST_SETPW["POST /set-password"]
    end

    subgraph Admin
        GET_ADMIN["GET /admin"]
        GET_UPLOAD["GET /admin/upload"]
        POST_UPLOAD["POST /admin/upload"]
        GET_MANAGE["GET /admin/painting/{id}"]
        POST_EDIT["POST /admin/painting/{id}/edit"]
        POST_AWARD["POST /admin/painting/{id}/award"]
        POST_DELETE["POST /admin/painting/{id}/delete"]
    end

    GET_ROOT --> GalleryController
    GET_PAINTING --> GalleryController
    POST_INTEREST --> GalleryController

    GET_LOGIN --> AuthController
    POST_LOGIN --> AuthController
    GET_REGISTER --> AuthController
    POST_REGISTER --> AuthController
    GET_MAGIC --> AuthController
    GET_GOOGLE --> AuthController
    GET_GCALLBACK --> AuthController
    GET_LOGOUT --> AuthController
    GET_SETPW --> AuthController
    POST_SETPW --> AuthController

    GET_ADMIN --> AdminController
    GET_UPLOAD --> AdminController
    POST_UPLOAD --> AdminController
    GET_MANAGE --> AdminController
    POST_EDIT --> AdminController
    POST_AWARD --> AdminController
    POST_DELETE --> AdminController
```

## Project Structure

```
heirloom/
├── public/                     Web root (server document root)
│   ├── index.php               Front controller - all requests enter here
│   ├── .htaccess               Apache rewrite rules
│   ├── .user.ini               PHP upload/memory limits (production)
│   ├── css/style.css           Stylesheet
│   └── uploads/                Uploaded painting images
├── src/                        Application code (PSR-4: Heirloom\)
│   ├── Config.php              .env file parser
│   ├── Database.php            PDO wrapper (MySQL, injectable for tests)
│   ├── Router.php              Regex-based URL router
│   ├── Auth.php                Session management, login, magic links, OAuth
│   ├── Template.php            View renderer with XSS escaping
│   └── Controllers/
│       ├── GalleryController   Public gallery, painting detail, interest toggle
│       ├── AuthController      Login, register, magic link, Google OAuth, password
│       └── AdminController     Dashboard, upload, edit, award, delete
├── templates/                  PHP view templates
├── tests/                      PHPUnit test suite
├── spec/                       PHPSpec behavioral specifications
├── doc/                        Documentation and ADRs
│   └── adr/                    Architecture Decision Records (0001-0011)
├── .github/
│   ├── workflows/tests.yml     CI: PHPUnit + PHPSpec on every PR
│   ├── workflows/pr-review.yml Automated Claude PR review for external PRs
│   └── CODEOWNERS              @robsartin owns all files
├── migrate.php                 Database schema creation + admin/test user seed
├── seed-test-users.php         Sample users with humorous interest messages
├── php-dev.ini                 PHP config for local dev (large upload limits)
├── phpunit.xml                 PHPUnit configuration
├── phpspec.yml                 PHPSpec configuration
├── composer.json               Dependencies and scripts
└── .env.example                Environment variable template
```

## Development Workflow

All new code follows **strict TDD** (ADR 0010):

```mermaid
graph LR
    R[RED<br/>Write one<br/>failing test] --> G[GREEN<br/>Minimal code<br/>to pass]
    G --> RF[REFACTOR<br/>Clean up<br/>keep green]
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
