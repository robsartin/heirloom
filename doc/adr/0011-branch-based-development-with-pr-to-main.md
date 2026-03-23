# 11. Branch-based development with PR to main

Date: 2026-03-22

## Status

Accepted

## Context

The project now has multiple contributors and a test suite. Direct commits to `main` bypass review and risk introducing regressions. We need a workflow that keeps `main` releasable at all times.

## Decision

All development work MUST happen on a feature or fix branch, then be merged to `main` via a pull request. A PR can only be merged when it:

1. **Merges cleanly** — no merge conflicts with the current `main`. The author is responsible for rebasing or merging `main` into their branch to resolve conflicts before the PR can be accepted.
2. **Passes all tests** — the full test suite (`composer check`, which runs both PHPUnit and PHPSpec) must pass on the branch. A PR with failing tests cannot be merged.

### Branch naming

Use descriptive branch names: `feature/short-description`, `fix/short-description`, or `chore/short-description`.

### No direct commits to main

No one pushes directly to `main`. All changes — features, bug fixes, refactors, documentation — go through a PR. The only exception is initial project setup (already done).

## Consequences

- **Easier:** `main` is always in a working state. Regressions are caught before merge. Changes are reviewable. Git history on `main` is a sequence of reviewed, tested merges.
- **Harder:** Small changes still require a branch and PR, which adds overhead. Contributors must keep branches up to date with `main`.
- **Enforcement:** This is a convention for now. GitHub branch protection rules can enforce it automatically (require PR reviews, require status checks to pass) if desired in the future.
