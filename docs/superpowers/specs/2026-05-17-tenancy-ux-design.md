# Tenancy UX — PR2 Design Spec
**Date:** 2026-05-17
**Branch:** `feature/tenancy-ux`
**Depends on:** PR1 (`feat/tenancy` — merged at `3b68595`)

---

## Scope

Add the org-management UI and topbar org switcher. The `page_require_level()` shim introduced in PR1 is **not** removed in this PR — that is deferred to PR3.

---

## Files & URL Structure

```
orgs/
  orgs.php               # List all orgs + inline create form
  edit_org.php?id=N      # Rename + members table + add-member form
  update_org.php         # POST: create or rename org
  delete_org.php         # POST: soft-delete org
  restore_org.php        # POST: restore soft-deleted org
  add_member.php         # POST: add user to org with role
  update_member.php      # POST: change a member's role
  remove_member.php      # POST: remove member from org
```

All POST handlers: CSRF-check → validate → DB write → `$session->msg()` → `redirect()`.

`layouts/header.php` — small addition: topbar org switcher (renders only when user has ≥ 2 memberships).

`users/admin.php` — adds "Organizations" sub-link to User Management nav pointing to `orgs/orgs.php`.

---

## Access Control

- All `orgs/` pages: `page_require_level(1)` (admin-only).
- `edit_org.php`, mutating POST handlers: also `require_org_role('owner', 'admin')` to prevent regular members from editing.
- Soft-delete blocked if it would remove the last active org a user belongs to is not enforced server-side — `resolve_login_org()` already handles memberless users gracefully at login.

---

## Data Flow

### Org list (`orgs.php`)
- New helper `find_all_orgs()`: returns all orgs including soft-deleted (admins need to restore them). **Not** org-scoped — this is a system-admin view.
- Inline create form POSTs to `update_org.php` (no `id` param = create). Creator auto-enrolled as `owner`.
- Soft-deleted orgs shown with a Restore button; active orgs show an Edit link and soft-delete button.

### Org detail (`edit_org.php?id=N`)
- Rename form POSTs to `update_org.php` (with `id` param = update).
- Members table: role-change dropdown per row → `update_member.php`; Remove button → `remove_member.php`.
- Add-member form: username text input + role selector → `add_member.php`. Server resolves username → `user_id`; fails gracefully if unknown or already enrolled.
- At least one `owner` must remain — `remove_member.php` and `update_member.php` enforce this: reject if the operation would leave zero owners.

### Topbar org switcher (`layouts/header.php`)
- New helper `find_org_memberships(int $user_id): array` — returns `[['org_id' => N, 'name' => '...'], ...]` for non-deleted orgs where the user is a member.
- Renders only when count ≥ 2. Each option is a mini POST form targeting `users/switch_org.php` (existing, CSRF-protected).
- Current org highlighted in the dropdown.

---

## New SQL Helpers (`includes/sql.php`)

| Function | Description |
|---|---|
| `find_all_orgs(): array` | All orgs including soft-deleted, ordered by name |
| `find_org_by_id(int $id): array\|null` | Single org row |
| `find_org_memberships(int $user_id): array` | Active org memberships for a user |
| `create_org(string $name): int\|false` | INSERT org + enroll caller as owner; returns new org_id |
| `rename_org(int $id, string $name): bool` | UPDATE org name |

Member operations use direct DB calls in the POST handlers (simple enough to not warrant helpers).

---

## Testing (`tests/OrgManagementTest.php`)

New suite added to `tests/run.sh`. Target: 8/8 suites.

| # | Test |
|---|---|
| 1 | `find_all_orgs()` returns orgs including soft-deleted |
| 2 | `find_org_memberships()` returns only non-deleted orgs for user |
| 3 | Create org — inserts row, auto-enrolls creator as owner |
| 4 | Rename org — updates name, leaves members intact |
| 5 | Soft-delete org — sets `deleted_at`; `resolve_login_org()` skips it |
| 6 | Restore org — clears `deleted_at` |
| 7 | Add member — inserts `org_members` row with correct role |
| 8 | Add member duplicate — returns error, no duplicate row |
| 9 | Update member role — changes role in `org_members` |
| 10 | Remove member — deletes `org_members` row |
| 11 | Remove last owner blocked — error returned, row untouched |

All fixtures use `HARNESS_` prefix and are cleaned up after each test.

---

## Out of Scope (PR2)

- Removing `page_require_level()` shim — deferred to PR3
- Playwright UI tests for org management — sub-project 3
- Org-level permission matrix UI — future work
- Billing / plan limits per org — not applicable to this deployment
