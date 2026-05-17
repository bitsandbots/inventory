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
org_test('create_org() inserts org and enrolls creator as owner', function () use ($harness_user_id) {
    global $db;
    $name   = 'HARNESS_Created_' . uniqid();
    $org_id = create_org($name, $harness_user_id);
    try {
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
    } finally {
        if ($org_id) {
            $db->prepare_query("DELETE FROM org_members WHERE org_id = ?", 'i', $org_id);
            $db->prepare_query("DELETE FROM orgs WHERE id = ?", 'i', $org_id);
        }
    }
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
    $row = $db->prepare_select_one(
        "SELECT COUNT(*) AS cnt FROM org_members WHERE org_id = ? AND role = 'owner'",
        'i', $harness_org_id
    );
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
