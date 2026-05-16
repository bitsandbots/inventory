<?php
/**
 * tests/SoftDeleteTest.php
 *
 * Integration tests for the soft-delete pattern (migrations 005-009).
 * Skips cleanly when migrations are not yet applied locally — mirrors
 * the SettingsTest skip pattern.
 *
 * All test rows use the HARNESS_ prefix; teardown purges them.
 */

require_once __DIR__ . '/bootstrap.php';

$pass = 0;
$fail = 0;

function test(string $name, callable $fn): void
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

function check(bool $cond, string $msg): void
{
    if (!$cond) {
        throw new RuntimeException($msg);
    }
}

echo "=== SoftDeleteTest ===\n\n";

// Skip gracefully if migrations 005-009 are not yet applied locally.
// Probe one table's deleted_at column — if missing, the whole suite skips.
$soft_delete_ready = false;
try {
    global $db;
    $r = $db->connection()->query("SHOW COLUMNS FROM `users` LIKE 'deleted_at'");
    $soft_delete_ready = ($r !== false && $r->num_rows > 0);
    if ($r) {
        $r->free();
    }
} catch (\Throwable $e) {
    $soft_delete_ready = false;
}
if (!$soft_delete_ready) {
    echo "  SKIPPED: `users.deleted_at` column not present.\n";
    echo "  Apply migrations 005-009 to enable these tests:\n";
    echo "    for n in 005 006 007 008 009; do\n";
    echo "      sudo mysql inventory < migrations/\${n}_*.up.sql\n";
    echo "    done\n";
    echo "\n---\nResults: 0 passed, 0 failed (suite skipped)\n";
    exit(0);
}

// Set up org context for tests that need org_id guards
$_SESSION['current_org_id'] = 1;

// Task 7 — table_has_soft_delete introspection.
test('table_has_soft_delete returns true for in-scope tables', function () {
    check(table_has_soft_delete('users') === true, 'users should be soft-delete-aware');
    check(table_has_soft_delete('customers') === true, 'customers should be soft-delete-aware');
    check(table_has_soft_delete('sales') === true, 'sales should be soft-delete-aware');
    check(table_has_soft_delete('orders') === true, 'orders should be soft-delete-aware');
    check(table_has_soft_delete('stock') === true, 'stock should be soft-delete-aware');
});

test('table_has_soft_delete returns false for out-of-scope tables', function () {
    check(table_has_soft_delete('products') === false, 'products is out of scope');
    check(table_has_soft_delete('categories') === false, 'categories is out of scope');
    check(table_has_soft_delete('log') === false, 'log is out of scope');
    check(table_has_soft_delete('media') === false, 'media is out of scope');
    check(table_has_soft_delete('user_groups') === false, 'user_groups is out of scope');
});

test('table_has_soft_delete returns false for unknown table', function () {
    check(table_has_soft_delete('definitely_not_a_table') === false, 'unknown table should return false');
});

// Task 8 — soft-delete / restore / purge round-trip on a HARNESS_ user.
test('soft_delete_by_id stamps deleted_at and deleted_by', function () {
    global $db;
    // Insert a HARNESS_ user. Use mysqli directly to skip the registration UI.
    $stmt = $db->prepare_query(
        "INSERT INTO users (name, username, password, user_level, status) VALUES (?, ?, ?, ?, ?)",
        "sssii", 'HARNESS_softdel', 'HARNESS_softdel', 'x', 3, 1
    );
    $id = $db->insert_id();
    $stmt->close();
    check($id > 0, 'failed to insert HARNESS_softdel');

    $ok = soft_delete_by_id('users', $id, 1);
    check($ok === true, 'soft_delete_by_id returned false');

    $row = find_by_id_with_deleted('users', $id);
    check($row !== null, 'row not found after soft-delete');
    check($row['deleted_at'] !== null, 'deleted_at not set');
    check((int)$row['deleted_by'] === 1, 'deleted_by not set to actor id');
});

test('restore_by_id clears both timestamp columns', function () {
    global $db;
    $row = $db->prepare_select_one(
        "SELECT id FROM users WHERE username = ? LIMIT 1", "s", 'HARNESS_softdel'
    );
    $id = (int)$row['id'];

    $ok = restore_by_id('users', $id);
    check($ok === true, 'restore_by_id returned false');

    $row = find_by_id_with_deleted('users', $id);
    check($row !== null, 'row missing after restore');
    check($row['deleted_at'] === null, 'deleted_at not cleared');
    check($row['deleted_by'] === null, 'deleted_by not cleared');
});

test('purge_by_id refuses to remove an active row', function () {
    global $db;
    $row = $db->prepare_select_one(
        "SELECT id FROM users WHERE username = ? LIMIT 1", "s", 'HARNESS_softdel'
    );
    $id = (int)$row['id'];

    $ok = purge_by_id('users', $id);
    check($ok === false, 'purge should refuse active row');

    $row = find_by_id_with_deleted('users', $id);
    check($row !== null, 'row was purged despite refusal');
});

test('purge_by_id removes a soft-deleted row permanently', function () {
    global $db;
    $row = $db->prepare_select_one(
        "SELECT id FROM users WHERE username = ? LIMIT 1", "s", 'HARNESS_softdel'
    );
    $id = (int)$row['id'];

    check(soft_delete_by_id('users', $id, 1) === true, 'pre-purge soft-delete failed');
    check(purge_by_id('users', $id) === true, 'purge_by_id returned false');

    $row = find_by_id_with_deleted('users', $id);
    check($row === null, 'row still present after purge');
});

// Task 9 — generic helpers auto-filter soft-deleted rows.
test('find_all excludes soft-deleted rows', function () {
    global $db;
    // Create + soft-delete a HARNESS_ user.
    $stmt = $db->prepare_query(
        "INSERT INTO users (name, username, password, user_level, status) VALUES (?, ?, ?, ?, ?)",
        "sssii", 'HARNESS_findfilter', 'HARNESS_findfilter', 'x', 3, 1
    );
    $id = $db->insert_id();
    $stmt->close();
    soft_delete_by_id('users', $id, 1);

    $all = find_all('users');
    $ids = array_column($all, 'id');
    check(!in_array($id, array_map('intval', $ids), true),
        "find_all('users') leaked soft-deleted id=$id");

    // Cleanup
    purge_by_id('users', $id);
});

test('find_by_id returns null for a soft-deleted row', function () {
    global $db;
    $stmt = $db->prepare_query(
        "INSERT INTO users (name, username, password, user_level, status) VALUES (?, ?, ?, ?, ?)",
        "sssii", 'HARNESS_findbyid', 'HARNESS_findbyid', 'x', 3, 1
    );
    $id = $db->insert_id();
    $stmt->close();
    soft_delete_by_id('users', $id, 1);

    $row = find_by_id('users', $id);
    check($row === null, 'find_by_id returned a soft-deleted row');

    $row2 = find_by_id_with_deleted('users', $id);
    check($row2 !== null && (int)$row2['id'] === $id,
        'find_by_id_with_deleted failed to return the soft-deleted row');

    purge_by_id('users', $id);
});

test('find_with_deleted returns ALL rows including soft-deleted', function () {
    global $db;
    $stmt = $db->prepare_query(
        "INSERT INTO users (name, username, password, user_level, status) VALUES (?, ?, ?, ?, ?)",
        "sssii", 'HARNESS_withdel', 'HARNESS_withdel', 'x', 3, 1
    );
    $id = $db->insert_id();
    $stmt->close();
    soft_delete_by_id('users', $id, 1);

    $all = find_with_deleted('users');
    $ids = array_map('intval', array_column($all, 'id'));
    check(in_array($id, $ids, true), 'find_with_deleted did not include soft-deleted id');

    purge_by_id('users', $id);
});

// Task 10 — raw-SQL helpers also filter soft-deleted rows.
test('find_all_user excludes soft-deleted users', function () {
    global $db;
    $stmt = $db->prepare_query(
        "INSERT INTO users (name, username, password, user_level, status) VALUES (?, ?, ?, ?, ?)",
        "sssii", 'HARNESS_rawsql', 'HARNESS_rawsql', 'x', 3, 1
    );
    $id = $db->insert_id();
    $stmt->close();
    soft_delete_by_id('users', $id, 1);

    $rows = find_all_user();
    $ids = array_map('intval', array_column($rows, 'id'));
    check(!in_array($id, $ids, true), 'find_all_user leaked soft-deleted id');

    purge_by_id('users', $id);
});

test('find_all_sales excludes soft-deleted sales (row-local cascade test)', function () {
    global $db;
    // sales.product_id has an FK to products.id (ON DELETE CASCADE). The
    // dev DB may be empty; skip the FK-bound part of this test cleanly
    // when so. find_all_sales' raw-SQL filter is still exercised below
    // even without an insertable row.
    $rows = find_by_sql("SELECT id FROM products LIMIT 1");
    if (empty($rows)) {
        $all = find_all_sales();
        check(is_array($all), 'find_all_sales did not return an array');
        echo "       [skipped FK-bound assertions — no products in dev DB]\n";
        return;
    }
    $pid = (int)$rows[0]['id'];

    $stmt = $db->prepare_query(
        "INSERT INTO sales (order_id, product_id, qty, price, date, org_id) VALUES (?, ?, ?, ?, ?, ?)",
        "iidssi", 999999, $pid, 1, 1.00, date('Y-m-d'), 1
    );
    $id = $db->insert_id();
    $stmt->close();

    $rows = find_all_sales();
    $ids = array_map('intval', array_column($rows, 'id'));
    check(in_array($id, $ids, true), 'find_all_sales did not include the new sale');

    soft_delete_by_id('sales', $id, 1);

    $rows = find_all_sales();
    $ids = array_map('intval', array_column($rows, 'id'));
    check(!in_array($id, $ids, true), 'find_all_sales leaked soft-deleted sale');

    purge_by_id('sales', $id);
});

// Task 10 follow-up — authenticate() must reject soft-deleted users.
test('authenticate() rejects a soft-deleted user', function () {
    global $db;
    // Create a test org first
    $slug = 'harness_softdel_' . substr(md5(microtime()), 0, 8);
    $db->prepare_query(
        "INSERT INTO orgs (name, slug, deleted_at) VALUES (?, ?, NULL)",
        'ss', 'HARNESS_SoftDelOrg', $slug
    );
    $org_id = (int)$db->insert_id();

    // Insert a HARNESS_ user with a known bcrypt-hashed password.
    // Use a unique username to avoid collisions in repeated test runs.
    $username = 'HARNESS_authdel_' . substr(md5(microtime(true) . random_bytes(4)), 0, 8);
    $password = 'HARNESS_pass';
    $hash = password_hash($password, PASSWORD_BCRYPT);
    $stmt = $db->prepare_query(
        "INSERT INTO users (name, username, password, user_level, status) VALUES (?, ?, ?, ?, ?)",
        "sssii", $username, $username, $hash, 3, 1
    );
    $id = $db->insert_id();
    $stmt->close();

    // Create org membership so authenticate() succeeds
    $db->prepare_query(
        "INSERT INTO org_members (user_id, org_id, joined_at) VALUES (?, ?, NOW())",
        'ii', $id, $org_id
    );

    try {
        // Confirm authenticate works while the row is active.
        $auth_ok = authenticate($username, $password);
        check($auth_ok !== false, 'authenticate failed for active ' . $username);
        check(is_array($auth_ok), 'authenticate should return array');
        check((int)$auth_ok['user_id'] === (int)$id, 'authenticate returned wrong user_id');

        // Soft-delete and re-attempt.
        soft_delete_by_id('users', $id, 1);
        $auth_after = authenticate($username, $password);
        check($auth_after === false, 'authenticate ACCEPTED a soft-deleted user (security regression)');
    } finally {
        purge_by_id('users', $id);
        $db->prepare_query("DELETE FROM orgs WHERE id = ?", 'i', $org_id);
    }
});

echo "\n---\nResults: $pass passed, $fail failed\n";
exit($fail > 0 ? 1 : 0);
