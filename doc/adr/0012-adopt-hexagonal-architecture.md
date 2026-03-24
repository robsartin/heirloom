# 12. Adopt hexagonal architecture

Date: 2026-03-24

## Status

Proposed

## Context

The Heirloom codebase has grown to 3 controllers (609, 317, 249 lines), a 368-line Auth class, and 381 tests. Business logic is mixed with infrastructure concerns:

- Controllers directly build SQL queries, read superglobals, and call email services
- Data flows as raw associative arrays — no typed domain entities
- Only one interface exists (Mailer) — all other dependencies are concrete classes
- Testing requires full infrastructure setup (database schema, session state)

ADR 0002 chose "no framework" for simplicity. That decision served the project well through its initial build phase. As the codebase matures, the lack of architectural boundaries is creating:

- **Long controller methods** that mix validation, persistence, and notification
- **Repeated query patterns** scattered across controllers
- **Difficult-to-test business rules** that require HTTP/DB setup

## Decision

We will incrementally adopt hexagonal architecture (ports & adapters) to separate domain logic from infrastructure.

### Core principles

1. **Domain entities** replace raw arrays: `User`, `Painting`, `Interest` as immutable value objects with typed properties
2. **Port interfaces** define contracts: `UserRepository`, `PaintingRepository`, `InterestRepository`, `NotificationGateway`
3. **Use case classes** encapsulate business operations: `AwardPainting`, `ExpressInterest`, `UploadPainting`
4. **Adapter implementations** fulfill ports: `MysqlUserRepository`, `SmtpNotificationGateway`
5. **Controllers become thin** — extract request data, call use case, return response

### Directory structure

```
src/
├── Domain/           # Entities, value objects (no dependencies)
│   ├── User.php
│   ├── Painting.php
│   └── Interest.php
├── Ports/            # Interfaces (contracts)
│   ├── UserRepository.php
│   ├── PaintingRepository.php
│   ├── InterestRepository.php
│   └── NotificationGateway.php
├── UseCases/         # Application logic (depends on Ports only)
│   ├── AwardPainting.php
│   ├── ExpressInterest.php
│   └── UploadPainting.php
├── Adapters/         # Infrastructure (implements Ports)
│   ├── MysqlUserRepository.php
│   ├── MysqlPaintingRepository.php
│   └── SmtpNotificationGateway.php
└── Controllers/      # HTTP adapters (thin, call UseCases)
```

### Migration approach

- **Incremental**: extract one use case at a time, starting with `ExpressInterest` (smallest, well-tested)
- **Parallel structures**: new hexagonal code lives alongside existing code during transition
- **Each extraction is a separate PR** with full test coverage
- **No big-bang rewrite** — existing tests act as safety net

## Consequences

### Benefits
- **Testable domain logic**: use cases testable with mock repositories, no DB or HTTP
- **Explicit domain model**: typed entities catch errors at compile time, not runtime
- **Swappable infrastructure**: easy to change database, email provider, or session store
- **Reduced controller complexity**: controllers become request→use-case→response bridges
- **Clear dependency flow**: domain depends on nothing; infrastructure depends on domain

### Costs
- **More files and abstractions**: roughly doubles the number of source files
- **Learning curve**: contributors must understand ports & adapters pattern
- **Migration effort**: large refactor touching every controller over multiple PRs
- **Potential over-engineering**: for a small gallery app, the abstraction may exceed the complexity

### Mitigations
- Incremental adoption limits risk per PR
- 381 existing tests provide safety net
- ADR 0010 (TDD) ensures new code is tested before merge
- If a particular extraction adds complexity without value, we stop and reassess
