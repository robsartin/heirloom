# 10. Test-driven development workflow for all new code

Date: 2026-03-22

## Status

Accepted

## Context

The initial codebase was built without tests. As the project evolves, we need confidence that changes don't break existing behavior and that new features are well-specified before implementation. A disciplined TDD workflow provides this.

## Decision

All future development MUST follow strict Test-Driven Development using the red-green-refactor cycle:

1. **Red** — Write exactly one failing test that specifies the next small piece of desired behavior. Run it. Confirm it fails for the expected reason.
2. **Green** — Write the minimum code required to make that one test pass. No more. Run the test. Confirm it passes.
3. **Refactor** — Clean up the code (and the test if needed) while keeping all tests green. Remove duplication, improve naming, extract methods — but do not add new behavior.
4. **Repeat** — Refine the goal, pick the next behavior to specify, write the next failing test.

### Rules for existing tests

When implementation changes require revisiting existing tests, apply careful judgment:

- **Keep the test** if it specifies behavior that is still correct and desired. The implementation must satisfy it.
- **Change the test** if the specified behavior has genuinely changed (new requirements, refined understanding). Document why in the commit.
- **Delete the test** if it tests behavior that is no longer part of the system, or if it was testing an implementation detail rather than a meaningful behavior. Dead tests are noise.
- **Never change a test just to make it pass.** If a test fails, first understand what it is protecting. The failure is information.

### Scope

- Applies to all new features, bug fixes, and refactors going forward.
- Existing untested code gets tests added retroactively (characterization tests) to establish a safety net before modification.
- One test at a time. Do not write a batch of tests then implement — the feedback loop is the point.

### Tooling

- PHPUnit 10+ for test execution.
- Tests live in `tests/` mirroring `src/` structure.
- `composer test` runs the full suite.

## Consequences

- **Easier:** Every behavior is specified by a test before it exists. Regressions are caught immediately. Refactoring is safe. Tests serve as living documentation of intended behavior.
- **Harder:** Development feels slower at first — writing a test before each small change adds overhead. Requires discipline not to skip ahead. Some code (e.g., template rendering, file uploads) is harder to unit test and may need architectural changes to become testable.
- **Risk:** Poorly written tests that test implementation details rather than behavior create friction during refactoring. Mitigated by the rule to prefer testing observable behavior over internal structure.
