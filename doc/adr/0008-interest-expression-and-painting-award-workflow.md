# 8. Interest expression and painting award workflow

Date: 2026-03-22

## Status

Accepted

## Context

The core workflow: users browse paintings, express interest in ones they want, and the admin selects one interested user to receive each painting. Once awarded, the painting should no longer appear as available.

## Decision

- **Expressing interest:** Logged-in users can toggle interest on a painting. Each interest record is unique per (user, painting) pair, enforced by a database constraint. Users can optionally include a message explaining why they want the painting. Interest can be withdrawn by clicking again (toggle behavior).
- **Admin award:** The admin views the list of interested users for a painting and clicks "Award" next to their chosen recipient. This sets `awarded_to` on the painting record. Awarded paintings are filtered out of the public gallery (`WHERE awarded_to IS NULL`) but remain visible in the admin dashboard under the "Awarded" filter.
- **Unassign:** The admin can unassign an awarded painting, returning it to the available pool.
- **Delete:** The admin can permanently delete a painting, which also removes the image file from disk and cascades to delete interest records.

## Consequences

- **Easier:** Simple toggle UX for users, clear admin workflow, awarded paintings preserved in the database for record-keeping.
- **Harder:** No notification to the awarded user — the admin must contact them separately. No waitlist or priority system.
- **Future option:** Email notification to the awarded user could be added by sending mail in the `award()` action. The user's email is already in the database.
