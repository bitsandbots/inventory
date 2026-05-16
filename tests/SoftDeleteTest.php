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

// Test cases are added in later tasks.

echo "\n---\nResults: $pass passed, $fail failed\n";
exit($fail > 0 ? 1 : 0);
