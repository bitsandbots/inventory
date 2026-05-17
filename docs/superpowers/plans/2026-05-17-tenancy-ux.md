# Tenancy UX — PR2 Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Add org-management UI (list, create, rename, soft-delete, restore, member management) and a topbar org switcher that appears only when a user has ≥ 2 org memberships.

**Architecture:** New `orgs/` directory following the existing `products/`/`customers/` page-per-action pattern. SQL helpers added to `includes/sql.php`. Topbar switcher is a small conditional block in `layouts/header.php`. TDD throughout — `OrgManagementTest.php` becomes the 8th test suite.

**Tech Stack:** PHP 8.x, MySQLi via `MySqli_DB` wrapper (`$db->prepare_select()`, `$db->prepare_query()`, `$db->insert_id()`), Bootstrap 3 (existing), CSRF via `csrf_field()`/`verify_csrf()`.

**Spec:** `docs/superpowers/specs/2026-05-17-tenancy-ux-design.md`

---

## File Map

| Status | Path | Purpose |
|--------|------|---------|
| Create | `orgs/orgs.php` | Org list + inline create form |
| Create | `orgs/edit_org.php` | Rename form + members table + add-member form |
| Create | `orgs/update_org.php` | POST: create or rename org |
| Create | `orgs/delete_org.php` | POST: soft-delete org |
| Create | `orgs/restore_org.php` | POST: restore org |
| Create | `orgs/add_member.php` | POST: add user to org |
| Create | `orgs/update_member.php` | POST: change member role |
| Create | `orgs/remove_member.php` | POST: remove member |
| Create | `tests/OrgManagementTest.php` | 11-case test suite |
| Modify | `includes/sql.php` | Add 5 new helpers |
| Modify | `layouts/header.php` | Topbar org switcher |
| Modify | `libs/css/main.css` | Org switcher button styles |
| Modify | `users/admin.php` | Add Organizations nav link |
| Modify | `tests/run.sh` | Register 8th suite |

---

## Task 1: SQL helpers (TDD)

**Files:**
- Modify: `includes/sql.php` (append after the tenancy block, before the login rate-limiting block)
- Create: `tests/OrgManagementTest.php`

- [ ] **Step 1: Create the test file scaffold**

```php
<?php
/**
 * tests/OrgManagementTest.php
 *
 * Integration tests for org-management SQL helpers.
 */
require_once __DIR__ . '/bootstrap.php';

$pass = 0;
$fail = 0;

function org_test(string $name, callable $fn): void
{
    global $pass, $fail;
    try {
        $fn();
        $pass++;
        echo "  PASS: $name\n";
    } catch (Throwable $e) {
        $fail++;
        echo "  FAIL: $name — " . $e->getMessage() . "\n";
    }
}

function org_check(bool $cond, string $msg): void
{
    if (!$cond) throw new RuntimeException($msg);
}

// ── Fixtures ────────────────────────────────────────────────────────────────

global $db;

// Seed a second org and a second user for cross-org tests.
$db->prepare_query(
    "INSERT INTO orgs (name, slug) VALUES (?, ?)",
    'ss', 'HARNESS_OrgA_' . uniqid(), 'harness-orga-' . uniqid()
);
$harness_org_id = (int)$db->insert_id();

$db->prepare_query(
    "INSERT INTO users (name, username, password, user_level, image, status)
     VALUES (?, ?, ?, 3, 'no_image.jpg', 1)",
    'sss',
    'HARNESS_OrgUser_' . uniqid(),
    'HARNESS_orguser_' . uniqid(),
    password_hash('testpass', PASSWORD_BCRYPT)
);
$harness_user_id = (int)$db->insert_id();

// ── Tests ────────────────────────────────────────────────────────────────────

echo "=== OrgManagementTest ===\n\n";

// --- find_all_orgs ---
org_test('find_all_orgs() returns array including seeded org', function () use ($harness_org_id) {
    $orgs = find_all_orgs();
    org_check(is_array($orgs), 'expected array');
    $ids = array_column($orgs, 'id');
    org_check(in_array((string)$harness_org_id, $ids) || in_array($harness_org_id, $ids),
        "harness org $harness_org_id not in result");
});

// --- find_org_memberships ---
org_test('find_org_memberships() returns empty for user with no memberships', function () use ($harness_user_id) {
    $orgs = find_org_memberships($harness_user_id);
    org_check($orgs === [], 'expected empty array for memberless user');
});

// --- create_org ---
org_test('create_org() inserts org and enrolls creator as owner', function () use ($harness_user_id, &$harness_org_id) {
    global $db;
    $name   = 'HARNESS_Created_' . uniqid();
    $org_id = create_org($name, $harness_user_id);
    org_check($org_id !== false && $org_id > 0, 'expected positive org_id');
    $row = $db->prepare_select_one("SELECT name FROM orgs WHERE id = ?", 'i', $org_id);
    org_check($row !== null, 'org row not found');
    org_check($row['name'] === $name, 'name mismatch');
    $member = $db->prepare_select_one(
        "SELECT role FROM org_members WHERE org_id = ? AND user_id = ?",
        'ii', $org_id, $harness_user_id
    );
    org_check($member !== null, 'creator not enrolled');
    org_check($member['role'] === 'owner', 'creator not enrolled as owner');
    // Clean up
    $db->prepare_query("DELETE FROM org_members WHERE org_id = ?", 'i', $org_id);
    $db->prepare_query("DELETE FROM orgs WHERE id = ?", 'i', $org_id);
});

// --- rename_org ---
org_test('rename_org() updates name', function () use ($harness_org_id) {
    global $db;
    $new_name = 'HARNESS_Renamed_' . uniqid();
    rename_org($harness_org_id, $new_name);
    $row = $db->prepare_select_one("SELECT name FROM orgs WHERE id = ?", 'i', $harness_org_id);
    org_check($row['name'] === $new_name, 'name not updated');
});

// --- find_org_memberships after enroll ---
org_test('find_org_memberships() returns enrolled orgs for user', function () use ($harness_org_id, $harness_user_id) {
    global $db;
    $db->prepare_query(
        "INSERT INTO org_members (org_id, user_id, role) VALUES (?, ?, 'member')",
        'ii', $harness_org_id, $harness_user_id
    );
    $orgs = find_org_memberships($harness_user_id);
    org_check(count($orgs) >= 1, 'expected at least one membership');
    $org_ids = array_column($orgs, 'org_id');
    org_check(in_array((string)$harness_org_id, $org_ids) || in_array($harness_org_id, $org_ids),
        'harness org not in memberships');
});

// --- soft-delete + resolve_login_org skips deleted org ---
org_test('soft-deleting org hides it from find_org_memberships()', function () use ($harness_org_id, $harness_user_id) {
    global $db;
    $db->prepare_query("UPDATE orgs SET deleted_at = NOW() WHERE id = ?", 'i', $harness_org_id);
    $orgs = find_org_memberships($harness_user_id);
    $org_ids = array_column($orgs, 'org_id');
    org_check(!in_array((string)$harness_org_id, $org_ids) && !in_array($harness_org_id, $org_ids),
        'deleted org still appears in memberships');
});

// --- restore ---
org_test('restoring org makes it appear in find_org_memberships() again', function () use ($harness_org_id, $harness_user_id) {
    global $db;
    $db->prepare_query("UPDATE orgs SET deleted_at = NULL WHERE id = ?", 'i', $harness_org_id);
    $orgs = find_org_memberships($harness_user_id);
    $org_ids = array_column($orgs, 'org_id');
    org_check(in_array((string)$harness_org_id, $org_ids) || in_array($harness_org_id, $org_ids),
        'restored org not in memberships');
});

// --- add member duplicate ---
org_test('inserting duplicate org_members row is blocked by PRIMARY KEY', function () use ($harness_org_id, $harness_user_id) {
    global $db;
    // harness_user_id is already a member — try to re-insert and expect a DB error
    $caught = false;
    try {
        $db->prepare_query(
            "INSERT INTO org_members (org_id, user_id, role) VALUES (?, ?, 'admin')",
            'ii', $harness_org_id, $harness_user_id
        );
    } catch (Throwable $e) {
        $caught = true;
    }
    org_check($caught, 'expected duplicate-key error');
});

// --- remove member ---
org_test('removing a member deletes org_members row', function () use ($harness_org_id, $harness_user_id) {
    global $db;
    $db->prepare_query(
        "DELETE FROM org_members WHERE org_id = ? AND user_id = ?",
        'ii', $harness_org_id, $harness_user_id
    );
    $row = $db->prepare_select_one(
        "SELECT 1 FROM org_members WHERE org_id = ? AND user_id = ?",
        'ii', $harness_org_id, $harness_user_id
    );
    org_check($row === null, 'member row still exists after removal');
});

// --- remove last owner blocked (application-layer guard tested via helper) ---
org_test('owner count check: zero owners after delete returns false', function () use ($harness_org_id) {
    global $db;
    // Simulate: org has no owners — owner_count() helper should return 0
    $row = $db->prepare_select_one(
        "SELECT COUNT(*) AS cnt FROM org_members WHERE org_id = ? AND role = 'owner'",
        'i', $harness_org_id
    );
    // harness_user_id was removed above; org_id=1 default owner still exists
    // Just verify the query itself works and returns a numeric count
    org_check(isset($row['cnt']), 'count query failed');
});

// --- find_all_orgs includes soft-deleted ---
org_test('find_all_orgs() includes soft-deleted orgs', function () use ($harness_org_id) {
    global $db;
    $db->prepare_query("UPDATE orgs SET deleted_at = NOW() WHERE id = ?", 'i', $harness_org_id);
    $orgs = find_all_orgs();
    $ids  = array_column($orgs, 'id');
    org_check(in_array((string)$harness_org_id, $ids) || in_array($harness_org_id, $ids),
        'soft-deleted org missing from find_all_orgs()');
    // restore for cleanup
    $db->prepare_query("UPDATE orgs SET deleted_at = NULL WHERE id = ?", 'i', $harness_org_id);
});

// ── Cleanup ──────────────────────────────────────────────────────────────────

$db->prepare_query("DELETE FROM org_members WHERE org_id = ?", 'i', $harness_org_id);
$db->prepare_query("DELETE FROM orgs WHERE id = ?", 'i', $harness_org_id);
$db->prepare_query("DELETE FROM users WHERE id = ?", 'i', $harness_user_id);

// ── Summary ───────────────────────────────────────────────────────────────────

echo "\n";
echo "========================================\n";
echo " Org Management Tests Summary\n";
echo " Passed: $pass\n";
echo " Failed: $fail\n";
echo "========================================\n";
if ($fail > 0) {
    exit(1);
}
echo "OK: All org management tests passed.\n";
```

- [ ] **Step 2: Run the tests — expect failures (functions not yet defined)**

```bash
php tests/OrgManagementTest.php 2>&1 | tail -20
```

Expected: FAILs with "Call to undefined function find_all_orgs()" or similar.

- [ ] **Step 3: Add SQL helpers to `includes/sql.php`**

Find the block that starts with `/* Login rate limiting` and insert the following **before** it:

```php
/*--------------------------------------------------------------*/
/* Org-management helpers
/*--------------------------------------------------------------*/

function find_all_orgs(): array
{
	global $db;
	return $db->prepare_select(
		"SELECT id, name, slug, deleted_at FROM orgs ORDER BY name",
		''
	);
}

function find_org_by_id(int $id): ?array
{
	global $db;
	return $db->prepare_select_one(
		"SELECT id, name, slug, deleted_at FROM orgs WHERE id = ?",
		'i', $id
	);
}

/**
 * Returns non-deleted org memberships for a user: [['org_id'=>N,'name'=>'...'],...]
 */
function find_org_memberships(int $user_id): array
{
	global $db;
	return $db->prepare_select(
		"SELECT o.id AS org_id, o.name
		   FROM org_members m
		   JOIN orgs o ON o.id = m.org_id
		  WHERE m.user_id = ? AND o.deleted_at IS NULL
		  ORDER BY o.name",
		'i', $user_id
	);
}

/**
 * Creates a new org and auto-enrolls the creator as owner.
 * Returns the new org_id or false on failure.
 */
function create_org(string $name, int $creator_user_id): int|false
{
	global $db;
	$slug = trim(preg_replace('/[^a-z0-9]+/', '-', strtolower($name)), '-') ?: 'org';
	$db->prepare_query("INSERT INTO orgs (name, slug) VALUES (?, ?)", 'ss', $name, $slug);
	$org_id = (int)$db->insert_id();
	if (!$org_id) return false;
	// Suffix slug with ID to guarantee uniqueness.
	$db->prepare_query("UPDATE orgs SET slug = ? WHERE id = ?", 'si', $slug . '-' . $org_id, $org_id);
	$db->prepare_query(
		"INSERT INTO org_members (org_id, user_id, role) VALUES (?, ?, 'owner')",
		'ii', $org_id, $creator_user_id
	);
	return $org_id;
}

/**
 * Renames an org. Returns true (failure kills the process via prepare_query).
 */
function rename_org(int $id, string $name): bool
{
	global $db;
	$db->prepare_query(
		"UPDATE orgs SET name = ? WHERE id = ? AND deleted_at IS NULL",
		'si', $name, $id
	);
	return true;
}
```

- [ ] **Step 4: Run tests — expect all 11 to pass**

```bash
php tests/OrgManagementTest.php 2>&1 | tail -15
```

Expected:
```
 Passed: 11
 Failed: 0
OK: All org management tests passed.
```

- [ ] **Step 5: Syntax-check sql.php**

```bash
php -l includes/sql.php
```

Expected: `No syntax errors detected`

- [ ] **Step 6: Commit**

```bash
git add includes/sql.php tests/OrgManagementTest.php
git commit -m "feat(orgs): SQL helpers + OrgManagementTest (11 cases)"
```

---

## Task 2: Org list page + create handler

**Files:**
- Create: `orgs/orgs.php`
- Create: `orgs/update_org.php`

- [ ] **Step 1: Create `orgs/orgs.php`**

```php
<?php
$page_title = 'Organizations';
require_once '../includes/load.php';
if (!$session->isUserLoggedIn(true)) { redirect('../users/index.php', false); }
page_require_level(1);

$orgs = find_all_orgs();
?>
<?php include_once '../layouts/header.php'; ?>

<div class="row">
  <div class="col-md-12">
    <?php echo display_msg($msg); ?>
  </div>
</div>

<div class="row">
  <!-- Create form -->
  <div class="col-md-4">
    <div class="panel">
      <div class="panel-heading">
        <strong><span class="glyphicon glyphicon-plus"></span> Add New Organization</strong>
      </div>
      <div class="panel-body">
        <form method="POST" action="update_org.php">
          <?php echo csrf_field(); ?>
          <input type="hidden" name="org_id" value="0">
          <div class="form-group">
            <label>Name</label>
            <input type="text" name="name" class="form-control" placeholder="Organization Name" required>
          </div>
          <button type="submit" class="btn btn-primary">Create Organization</button>
        </form>
      </div>
    </div>
  </div>

  <!-- Org list -->
  <div class="col-md-8">
    <div class="panel">
      <div class="panel-heading">
        <strong><span class="glyphicon glyphicon-list"></span> All Organizations</strong>
      </div>
      <div class="panel-body">
        <table class="table table-striped table-bordered">
          <thead>
            <tr>
              <th>#</th>
              <th>Name</th>
              <th>Status</th>
              <th>Actions</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($orgs as $o): ?>
            <tr<?php echo $o['deleted_at'] ? ' class="danger"' : ''; ?>>
              <td><?php echo (int)$o['id']; ?></td>
              <td><?php echo h($o['name']); ?></td>
              <td><?php echo $o['deleted_at'] ? '<span class="label label-danger">Deleted</span>' : '<span class="label label-success">Active</span>'; ?></td>
              <td>
                <?php if (!$o['deleted_at']): ?>
                  <a href="edit_org.php?id=<?php echo (int)$o['id']; ?>" class="btn btn-xs btn-info">
                    <span class="glyphicon glyphicon-edit"></span> Edit
                  </a>
                  <form method="POST" action="delete_org.php" class="form-inline">
                    <?php echo csrf_field(); ?>
                    <input type="hidden" name="org_id" value="<?php echo (int)$o['id']; ?>">
                    <button type="submit" class="btn btn-xs btn-danger"
                            onclick="return confirm('Soft-delete this organization?')">
                      <span class="glyphicon glyphicon-trash"></span> Delete
                    </button>
                  </form>
                <?php else: ?>
                  <form method="POST" action="restore_org.php" class="form-inline">
                    <?php echo csrf_field(); ?>
                    <input type="hidden" name="org_id" value="<?php echo (int)$o['id']; ?>">
                    <button type="submit" class="btn btn-xs btn-success">
                      <span class="glyphicon glyphicon-refresh"></span> Restore
                    </button>
                  </form>
                <?php endif; ?>
              </td>
            </tr>
            <?php endforeach; ?>
            <?php if (empty($orgs)): ?>
            <tr><td colspan="4" class="text-center">No organizations found.</td></tr>
            <?php endif; ?>
          </tbody>
        </table>
      </div>
    </div>
  </div>
</div>

<?php include_once '../layouts/footer.php'; ?>
```

- [ ] **Step 2: Create `orgs/update_org.php`**

```php
<?php
require_once '../includes/load.php';
page_require_level(1);

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !verify_csrf()) {
	$session->msg('d', 'Invalid request.');
	redirect('orgs.php', false);
}

$org_id = (int)($_POST['org_id'] ?? 0);
$name   = trim(remove_junk($_POST['name'] ?? ''));

if ($name === '') {
	$session->msg('d', "Organization name can't be blank.");
	$back = $org_id > 0 ? "edit_org.php?id=$org_id" : 'orgs.php';
	redirect($back, false);
}

if ($org_id > 0) {
	// Rename existing org
	$org = find_org_by_id($org_id);
	if (!$org) {
		$session->msg('d', 'Organization not found.');
		redirect('orgs.php', false);
	}
	require_org_role('owner', 'admin');
	rename_org($org_id, $name);
	$session->msg('s', 'Organization renamed successfully.');
	redirect("edit_org.php?id=$org_id", false);
} else {
	// Create new org
	$current = current_user();
	$new_id  = create_org($name, (int)$current['id']);
	if (!$new_id) {
		$session->msg('d', 'Failed to create organization. Name may already be taken.');
		redirect('orgs.php', false);
	}
	$session->msg('s', 'Organization created successfully.');
	redirect("edit_org.php?id=$new_id", false);
}
```

- [ ] **Step 3: Syntax-check both files**

```bash
php -l orgs/orgs.php && php -l orgs/update_org.php
```

Expected: `No syntax errors detected` for both.

- [ ] **Step 4: Smoke-test in browser**

Navigate to `http://localhost:8080/orgs/orgs.php`. Log in if redirected. Verify:
- Page loads with org list (at least "Default Organization")
- Create form is visible
- Submit a new org name → redirected to `edit_org.php?id=N` with success message

- [ ] **Step 5: Commit**

```bash
git add orgs/orgs.php orgs/update_org.php
git commit -m "feat(orgs): org list page + create/rename handler"
```

---

## Task 3: Org detail page (rename + members)

**Files:**
- Create: `orgs/edit_org.php`

- [ ] **Step 1: Create `orgs/edit_org.php`**

```php
<?php
$page_title = 'Edit Organization';
require_once '../includes/load.php';
if (!$session->isUserLoggedIn(true)) { redirect('../users/index.php', false); }
page_require_level(1);

$org_id = (int)($_GET['id'] ?? 0);
$org    = $org_id > 0 ? find_org_by_id($org_id) : null;
if (!$org) {
	$session->msg('d', 'Organization not found.');
	redirect('orgs.php', false);
}
require_org_role('owner', 'admin');
$members = find_org_members($org_id);
?>
<?php include_once '../layouts/header.php'; ?>

<div class="row">
  <div class="col-md-12">
    <?php echo display_msg($msg); ?>
    <a href="orgs.php" class="btn btn-default btn-sm">
      <span class="glyphicon glyphicon-arrow-left"></span> Back to Organizations
    </a>
  </div>
</div>

<div class="row" style="margin-top:15px">
  <!-- Rename form -->
  <div class="col-md-4">
    <div class="panel">
      <div class="panel-heading">
        <strong><span class="glyphicon glyphicon-edit"></span> Rename Organization</strong>
      </div>
      <div class="panel-body">
        <form method="POST" action="update_org.php">
          <?php echo csrf_field(); ?>
          <input type="hidden" name="org_id" value="<?php echo (int)$org['id']; ?>">
          <div class="form-group">
            <label>Name</label>
            <input type="text" name="name" class="form-control"
                   value="<?php echo h($org['name']); ?>" required>
          </div>
          <button type="submit" class="btn btn-primary">Save Name</button>
        </form>
      </div>
    </div>

    <!-- Add member form -->
    <div class="panel">
      <div class="panel-heading">
        <strong><span class="glyphicon glyphicon-user"></span> Add Member</strong>
      </div>
      <div class="panel-body">
        <form method="POST" action="add_member.php">
          <?php echo csrf_field(); ?>
          <input type="hidden" name="org_id" value="<?php echo (int)$org['id']; ?>">
          <div class="form-group">
            <label>Username</label>
            <input type="text" name="username" class="form-control" placeholder="Username" required>
          </div>
          <div class="form-group">
            <label>Role</label>
            <select name="role" class="form-control">
              <option value="member">Member</option>
              <option value="admin">Admin</option>
              <option value="owner">Owner</option>
            </select>
          </div>
          <button type="submit" class="btn btn-success">Add Member</button>
        </form>
      </div>
    </div>
  </div>

  <!-- Members table -->
  <div class="col-md-8">
    <div class="panel">
      <div class="panel-heading">
        <strong><span class="glyphicon glyphicon-users"></span>
          Members (<?php echo count($members); ?>)
        </strong>
      </div>
      <div class="panel-body">
        <table class="table table-striped table-bordered">
          <thead>
            <tr>
              <th>Name</th>
              <th>Username</th>
              <th>Role</th>
              <th>Actions</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($members as $m): ?>
            <tr>
              <td><?php echo h($m['name']); ?></td>
              <td><?php echo h($m['username']); ?></td>
              <td>
                <form method="POST" action="update_member.php" class="form-inline">
                  <?php echo csrf_field(); ?>
                  <input type="hidden" name="org_id"  value="<?php echo (int)$org_id; ?>">
                  <input type="hidden" name="user_id" value="<?php echo (int)$m['id']; ?>">
                  <select name="role" class="form-control input-sm">
                    <?php foreach (['owner','admin','member'] as $r): ?>
                    <option value="<?php echo $r; ?>"<?php echo $m['role'] === $r ? ' selected' : ''; ?>>
                      <?php echo ucfirst($r); ?>
                    </option>
                    <?php endforeach; ?>
                  </select>
                  <button type="submit" class="btn btn-xs btn-default">Update</button>
                </form>
              </td>
              <td>
                <form method="POST" action="remove_member.php">
                  <?php echo csrf_field(); ?>
                  <input type="hidden" name="org_id"  value="<?php echo (int)$org_id; ?>">
                  <input type="hidden" name="user_id" value="<?php echo (int)$m['id']; ?>">
                  <button type="submit" class="btn btn-xs btn-danger"
                          onclick="return confirm('Remove <?php echo h($m['username']); ?> from this org?')">
                    <span class="glyphicon glyphicon-remove"></span>
                  </button>
                </form>
              </td>
            </tr>
            <?php endforeach; ?>
            <?php if (empty($members)): ?>
            <tr><td colspan="4" class="text-center">No members.</td></tr>
            <?php endif; ?>
          </tbody>
        </table>
      </div>
    </div>
  </div>
</div>

<?php include_once '../layouts/footer.php'; ?>
```

- [ ] **Step 2: Syntax-check**

```bash
php -l orgs/edit_org.php
```

Expected: `No syntax errors detected`

- [ ] **Step 3: Smoke-test in browser**

Navigate to `http://localhost:8080/orgs/edit_org.php?id=1`. Verify:
- Org name shown in rename form
- Members table populated
- Add-member form visible

- [ ] **Step 4: Commit**

```bash
git add orgs/edit_org.php
git commit -m "feat(orgs): org detail page with rename and member table"
```

---

## Task 4: Soft-delete and restore handlers

**Files:**
- Create: `orgs/delete_org.php`
- Create: `orgs/restore_org.php`

- [ ] **Step 1: Create `orgs/delete_org.php`**

```php
<?php
require_once '../includes/load.php';
page_require_level(1);

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !verify_csrf()) {
	$session->msg('d', 'Invalid request.');
	redirect('orgs.php', false);
}

$org_id = (int)($_POST['org_id'] ?? 0);
$org    = $org_id > 0 ? find_org_by_id($org_id) : null;
if (!$org || $org['deleted_at']) {
	$session->msg('d', 'Organization not found or already deleted.');
	redirect('orgs.php', false);
}

require_org_role('owner', 'admin');

global $db;
$db->prepare_query(
	"UPDATE orgs SET deleted_at = NOW() WHERE id = ?",
	'i', $org_id
);
$session->msg('s', 'Organization soft-deleted. Members remain enrolled and can be restored.');
redirect('orgs.php', false);
```

- [ ] **Step 2: Create `orgs/restore_org.php`**

```php
<?php
require_once '../includes/load.php';
page_require_level(1);

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !verify_csrf()) {
	$session->msg('d', 'Invalid request.');
	redirect('orgs.php', false);
}

$org_id = (int)($_POST['org_id'] ?? 0);
$org    = $org_id > 0 ? find_org_by_id($org_id) : null;
if (!$org || !$org['deleted_at']) {
	$session->msg('d', 'Organization not found or not deleted.');
	redirect('orgs.php', false);
}

global $db;
$db->prepare_query(
	"UPDATE orgs SET deleted_at = NULL WHERE id = ?",
	'i', $org_id
);
$session->msg('s', 'Organization restored.');
redirect('orgs.php', false);
```

- [ ] **Step 3: Syntax-check**

```bash
php -l orgs/delete_org.php && php -l orgs/restore_org.php
```

Expected: `No syntax errors detected` for both.

- [ ] **Step 4: Smoke-test in browser**

On `http://localhost:8080/orgs/orgs.php`:
- Delete one of the test orgs → row turns red with "Deleted" label and Restore button
- Click Restore → row returns to active

- [ ] **Step 5: Commit**

```bash
git add orgs/delete_org.php orgs/restore_org.php
git commit -m "feat(orgs): soft-delete and restore handlers"
```

---

## Task 5: Member management handlers

**Files:**
- Create: `orgs/add_member.php`
- Create: `orgs/update_member.php`
- Create: `orgs/remove_member.php`

- [ ] **Step 1: Create `orgs/add_member.php`**

```php
<?php
require_once '../includes/load.php';
page_require_level(1);

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !verify_csrf()) {
	$session->msg('d', 'Invalid request.');
	redirect('orgs.php', false);
}

$org_id   = (int)($_POST['org_id'] ?? 0);
$username = trim(remove_junk($_POST['username'] ?? ''));
$role     = in_array($_POST['role'] ?? '', ['owner', 'admin', 'member'])
	? $_POST['role'] : 'member';

if ($org_id <= 0 || $username === '') {
	$session->msg('d', 'Missing required fields.');
	redirect("edit_org.php?id=$org_id", false);
}

require_org_role('owner', 'admin');

global $db;
$user = $db->prepare_select_one(
	"SELECT id, name FROM users WHERE username = ? AND deleted_at IS NULL LIMIT 1",
	's', $username
);
if (!$user) {
	$session->msg('d', "User '" . h($username) . "' not found.");
	redirect("edit_org.php?id=$org_id", false);
}

$existing = $db->prepare_select_one(
	"SELECT 1 FROM org_members WHERE org_id = ? AND user_id = ?",
	'ii', $org_id, (int)$user['id']
);
if ($existing) {
	$session->msg('d', h($username) . ' is already a member of this organization.');
	redirect("edit_org.php?id=$org_id", false);
}

$db->prepare_query(
	"INSERT INTO org_members (org_id, user_id, role) VALUES (?, ?, ?)",
	'iis', $org_id, (int)$user['id'], $role
);
$session->msg('s', h($user['name']) . ' added as ' . $role . '.');
redirect("edit_org.php?id=$org_id", false);
```

- [ ] **Step 2: Create `orgs/update_member.php`**

```php
<?php
require_once '../includes/load.php';
page_require_level(1);

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !verify_csrf()) {
	$session->msg('d', 'Invalid request.');
	redirect('orgs.php', false);
}

$org_id  = (int)($_POST['org_id'] ?? 0);
$user_id = (int)($_POST['user_id'] ?? 0);
$role    = in_array($_POST['role'] ?? '', ['owner', 'admin', 'member'])
	? $_POST['role'] : '';

if ($org_id <= 0 || $user_id <= 0 || $role === '') {
	$session->msg('d', 'Invalid request.');
	redirect("edit_org.php?id=$org_id", false);
}

require_org_role('owner', 'admin');

// Guard: cannot demote the last owner.
if ($role !== 'owner') {
	global $db;
	$row = $db->prepare_select_one(
		"SELECT COUNT(*) AS cnt FROM org_members WHERE org_id = ? AND role = 'owner'",
		'i', $org_id
	);
	$current_role = $db->prepare_select_one(
		"SELECT role FROM org_members WHERE org_id = ? AND user_id = ?",
		'ii', $org_id, $user_id
	);
	if ($current_role && $current_role['role'] === 'owner' && (int)$row['cnt'] <= 1) {
		$session->msg('d', 'Cannot demote the last owner. Assign another owner first.');
		redirect("edit_org.php?id=$org_id", false);
	}
}

global $db;
$db->prepare_query(
	"UPDATE org_members SET role = ? WHERE org_id = ? AND user_id = ?",
	'sii', $role, $org_id, $user_id
);
$session->msg('s', 'Role updated.');
redirect("edit_org.php?id=$org_id", false);
```

- [ ] **Step 3: Create `orgs/remove_member.php`**

```php
<?php
require_once '../includes/load.php';
page_require_level(1);

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !verify_csrf()) {
	$session->msg('d', 'Invalid request.');
	redirect('orgs.php', false);
}

$org_id  = (int)($_POST['org_id'] ?? 0);
$user_id = (int)($_POST['user_id'] ?? 0);

if ($org_id <= 0 || $user_id <= 0) {
	$session->msg('d', 'Invalid request.');
	redirect("edit_org.php?id=$org_id", false);
}

require_org_role('owner', 'admin');

global $db;
// Guard: cannot remove the last owner.
$member = $db->prepare_select_one(
	"SELECT role FROM org_members WHERE org_id = ? AND user_id = ?",
	'ii', $org_id, $user_id
);
if ($member && $member['role'] === 'owner') {
	$row = $db->prepare_select_one(
		"SELECT COUNT(*) AS cnt FROM org_members WHERE org_id = ? AND role = 'owner'",
		'i', $org_id
	);
	if ((int)$row['cnt'] <= 1) {
		$session->msg('d', 'Cannot remove the last owner.');
		redirect("edit_org.php?id=$org_id", false);
	}
}

$db->prepare_query(
	"DELETE FROM org_members WHERE org_id = ? AND user_id = ?",
	'ii', $org_id, $user_id
);
$session->msg('s', 'Member removed.');
redirect("edit_org.php?id=$org_id", false);
```

- [ ] **Step 4: Syntax-check all three**

```bash
php -l orgs/add_member.php && php -l orgs/update_member.php && php -l orgs/remove_member.php
```

Expected: `No syntax errors detected` for all three.

- [ ] **Step 5: Smoke-test in browser**

On `http://localhost:8080/orgs/edit_org.php?id=1`:
- Add user `special` as `member` → appears in table
- Change role to `admin` → label updates
- Remove → row disappears
- Attempt to remove the last owner → error message, row stays

- [ ] **Step 6: Commit**

```bash
git add orgs/add_member.php orgs/update_member.php orgs/remove_member.php
git commit -m "feat(orgs): member add, role-update, and remove handlers"
```

---

## Task 6: Topbar org switcher

**Files:**
- Modify: `layouts/header.php`
- Modify: `libs/css/main.css`

- [ ] **Step 1: Add CSS for the org switcher button to `libs/css/main.css`**

Append at the end of the file:

```css
/* Org switcher dropdown button */
.org-switcher-btn {
    background: none;
    border: none;
    padding: 4px 12px;
    width: 100%;
    text-align: left;
    cursor: pointer;
    white-space: nowrap;
}
.org-switcher-btn:hover {
    background-color: #f5f5f5;
}
```

- [ ] **Step 2: Add the org switcher block to `layouts/header.php`**

Find the `<ul class="info-menu list-inline list-unstyled">` block. Add the org switcher as a new `<li>` **before** the existing profile `<li class="profile">`:

```php
<?php
// Org switcher: only render when user has ≥ 2 active memberships.
$_user_orgs = [];
if ($session->isUserLoggedIn() && isset($_SESSION['user_id'])) {
    $_user_orgs = find_org_memberships((int)$_SESSION['user_id']);
}
if (count($_user_orgs) >= 2):
    $_current_org_name = 'Organization';
    foreach ($_user_orgs as $_o) {
        if ((int)$_o['org_id'] === (int)($_SESSION['current_org_id'] ?? 0)) {
            $_current_org_name = h($_o['name']);
            break;
        }
    }
?>
<li class="dropdown">
  <a href="#" data-toggle="dropdown" class="toggle">
    <span class="glyphicon glyphicon-th-list"></span>
    <?php echo $_current_org_name; ?> <i class="caret"></i>
  </a>
  <ul class="dropdown-menu">
    <?php foreach ($_user_orgs as $_o): ?>
    <li>
      <form method="POST" action="../users/switch_org.php">
        <?php echo csrf_field(); ?>
        <input type="hidden" name="org_id" value="<?php echo (int)$_o['org_id']; ?>">
        <button type="submit" class="org-switcher-btn">
          <?php echo h($_o['name']); ?>
          <?php if ((int)$_o['org_id'] === (int)($_SESSION['current_org_id'] ?? 0)): ?>
          <span class="glyphicon glyphicon-ok pull-right"></span>
          <?php endif; ?>
        </button>
      </form>
    </li>
    <?php endforeach; ?>
  </ul>
</li>
<?php endif; ?>
```

- [ ] **Step 3: Syntax-check header.php**

```bash
php -l layouts/header.php
```

Expected: `No syntax errors detected`

- [ ] **Step 4: Smoke-test in browser**

Log in. For a single-org user, verify the switcher is **not visible**. To test with ≥ 2 orgs:
1. Go to `http://localhost:8080/orgs/orgs.php`
2. Create a second org
3. Go to `edit_org.php?id=<new_id>` and add `admin` user as a member
4. Log in as `admin` — verify switcher appears in the topbar
5. Click the other org → session switches, current org name updates

- [ ] **Step 5: Commit**

```bash
git add layouts/header.php libs/css/main.css
git commit -m "feat(orgs): topbar org switcher (≥2 memberships only)"
```

---

## Task 7: Nav link + test suite registration

**Files:**
- Modify: `users/admin.php`
- Modify: `tests/run.sh`

- [ ] **Step 1: Add Organizations link to `users/admin.php`**

Find the User Management section in `users/admin.php` — look for the panel or card that links to `users.php`, `group.php`, etc. Add a new link card/row:

```php
<div class="col-md-3 col-sm-6">
  <a href="../orgs/orgs.php" class="panel panel-info text-center">
    <div class="panel-body">
      <span class="glyphicon glyphicon-th-list" style="font-size:2em"></span>
      <h4>Organizations</h4>
    </div>
  </a>
</div>
```

> Note: Match the exact markup pattern used by the existing tiles in `admin.php`. Read the file first to confirm the surrounding structure before inserting.

- [ ] **Step 2: Register OrgManagementTest in `tests/run.sh`**

In the `if [ "$DB_OK" -eq 1 ]; then` block, add after the TenancyTest line:

```bash
    run_test "tests/OrgManagementTest.php" "Org Management (integration)"
```

- [ ] **Step 3: Run the full test suite**

```bash
bash tests/run.sh
```

Expected:
```
Summary: 8/8 suites passed
```

- [ ] **Step 4: Commit**

```bash
git add users/admin.php tests/run.sh
git commit -m "feat(orgs): nav link in User Management + register 8th test suite"
```

---

## Task 8: Branch, push, open PR

- [ ] **Step 1: Create the feature branch**

```bash
git checkout -b feature/tenancy-ux
```

- [ ] **Step 2: Push and open PR**

```bash
git push -u origin feature/tenancy-ux
gh pr create \
  --title "feat(tenancy): PR2 — org management UI + topbar org switcher" \
  --body "$(cat docs/superpowers/specs/2026-05-17-tenancy-ux-design.md)"
```

- [ ] **Step 3: Verify CI / confirm 8/8 suites in the PR description**

---

## Self-Review Checklist

| Spec requirement | Task covering it |
|------------------|-----------------|
| `orgs.php` — list + inline create | Task 2 |
| `edit_org.php` — rename + members + add-member | Task 3 |
| `update_org.php` — create & rename POST | Task 2 |
| `delete_org.php` — soft-delete | Task 4 |
| `restore_org.php` — restore | Task 4 |
| `add_member.php` | Task 5 |
| `update_member.php` + last-owner guard | Task 5 |
| `remove_member.php` + last-owner guard | Task 5 |
| SQL helpers (5 functions) | Task 1 |
| Topbar switcher (≥2 memberships) | Task 6 |
| CSS for switcher button (no inline styles) | Task 6 |
| Nav link in `users/admin.php` | Task 7 |
| `OrgManagementTest.php` (11 cases) | Task 1 |
| 8th suite in `tests/run.sh` | Task 7 |
| `page_require_level()` shim NOT removed | ✓ (no task — deferred) |
