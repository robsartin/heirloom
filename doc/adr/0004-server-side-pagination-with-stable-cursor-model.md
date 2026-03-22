# 4. Server-side pagination with stable cursor model

Date: 2026-03-22

## Status

Accepted

## Context

The gallery may contain up to ~1000 paintings. Loading all at once would be slow and overwhelming. We need pagination that doesn't shift items between pages when paintings are awarded/removed (the "stable model" requirement).

## Decision

Use server-side offset/limit pagination with 12 paintings per page on the public gallery and 20 per page in the admin dashboard. Paintings are ordered by `created_at DESC` (newest first). The query filters `WHERE awarded_to IS NULL` for the public gallery, so awarded paintings disappear from the listing. Page numbers are passed via `?page=N` query parameter. The pagination UI shows prev/next links, numbered pages with ellipsis for large ranges, and highlights the current page.

## Consequences

- **Easier:** Simple to implement, universally understood UX, bookmarkable page URLs, works with standard SQL `LIMIT/OFFSET`.
- **Harder:** When a painting is awarded and removed from the available list, items shift — a user on page 3 might miss a painting that moved from page 4 to page 3. This is acceptable because removals are infrequent admin actions, not user-driven.
- **Trade-off:** Offset pagination is O(N) for large offsets, but with ~1000 rows this is negligible. Cursor-based pagination was considered but adds complexity without meaningful benefit at this scale.
