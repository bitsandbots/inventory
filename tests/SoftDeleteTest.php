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

echo "\n---\nResults: $pass passed, $fail failed\n";
exit($fail > 0 ? 1 : 0);
