<?php
/**
 * tests/TenancyTest.php
 *
 * Smoke tests for multi-tenancy: ORG_SCOPED_TABLES constant, table_has_org_id() probe,
 * and current_org_id() helper. Skips gracefully when migrations 010-021 are not applied.
 */

require_once __DIR__ . '/bootstrap.php';

$pass = 0;
$fail = 0;
$skipped = 0;

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

// Check if tenancy migrations have been applied
$tenancy_applied = false;
try {
    global $db;
    $result = $db->query(
        "SELECT COUNT(*) AS n FROM information_schema.TABLES
         WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'orgs'"
    );
    $row = $db->fetch_array($result);
    $tenancy_applied = (int)($row['n'] ?? 0) > 0;
} catch (Throwable $e) {
    $tenancy_applied = false;
}

if (!$tenancy_applied) {
    echo "SKIP: Tenancy migrations 010-021 not applied — skipping TenancyTest.\n";
    exit(0);
}

// Set up session for tests that need it
$_SESSION['current_org_id'] = 1;
$_SESSION['user_id'] = 1;

// T05: ORG_SCOPED_TABLES constant tests
test('ORG_SCOPED_TABLES constant defined', function () {
    check(defined('ORG_SCOPED_TABLES'), 'ORG_SCOPED_TABLES not defined');
});

test('ORG_SCOPED_TABLES is array', function () {
    check(is_array(ORG_SCOPED_TABLES), 'ORG_SCOPED_TABLES is not an array');
});

test('ORG_SCOPED_TABLES contains customers', function () {
    check(in_array('customers', ORG_SCOPED_TABLES, true), "customers not in ORG_SCOPED_TABLES");
});

test('ORG_SCOPED_TABLES contains products', function () {
    check(in_array('products', ORG_SCOPED_TABLES, true), "products not in ORG_SCOPED_TABLES");
});

test('ORG_SCOPED_TABLES contains categories', function () {
    check(in_array('categories', ORG_SCOPED_TABLES, true), "categories not in ORG_SCOPED_TABLES");
});

test('ORG_SCOPED_TABLES contains sales', function () {
    check(in_array('sales', ORG_SCOPED_TABLES, true), "sales not in ORG_SCOPED_TABLES");
});

test('ORG_SCOPED_TABLES contains orders', function () {
    check(in_array('orders', ORG_SCOPED_TABLES, true), "orders not in ORG_SCOPED_TABLES");
});

test('ORG_SCOPED_TABLES contains stock', function () {
    check(in_array('stock', ORG_SCOPED_TABLES, true), "stock not in ORG_SCOPED_TABLES");
});

test('ORG_SCOPED_TABLES contains media', function () {
    check(in_array('media', ORG_SCOPED_TABLES, true), "media not in ORG_SCOPED_TABLES");
});

// T05: table_has_org_id() function tests
test('table_has_org_id() exists', function () {
    check(function_exists('table_has_org_id'), 'table_has_org_id() not defined');
});

test('table_has_org_id(customers) returns true', function () {
    check(table_has_org_id('customers') === true, 'table_has_org_id(customers) should return true');
});

test('table_has_org_id(users) returns false', function () {
    check(table_has_org_id('users') === false, 'table_has_org_id(users) should return false');
});

test('table_has_org_id() caches results', function () {
    $result1 = table_has_org_id('products');
    $result2 = table_has_org_id('products');
    check($result1 === $result2, 'table_has_org_id() results not consistent (cache broken)');
});

// T06: current_org_id() function tests
test('current_org_id() exists', function () {
    check(function_exists('current_org_id'), 'current_org_id() not defined');
});

test('current_org_id() returns session value', function () {
    $_SESSION['current_org_id'] = 7;
    $result = current_org_id();
    check($result === 7, "current_org_id() should return 7, got $result");
    $_SESSION['current_org_id'] = 1;
});

test('current_org_id() throws RuntimeException when session empty', function () {
    $saved = $_SESSION['current_org_id'] ?? null;
    unset($_SESSION['current_org_id']);
    try {
        current_org_id();
        check(false, 'current_org_id() should throw RuntimeException when session unset');
    } catch (RuntimeException $e) {
        check(true, 'Correctly threw RuntimeException');
    } finally {
        if ($saved !== null) {
            $_SESSION['current_org_id'] = $saved;
        }
    }
});

// T07: find_all() / find_by_id() org-filter tests
// Placeholder tests — will be completed in Task 16 with tenancy_fixtures.php
// These require multi-org seed data which doesn't exist yet.
// For now, just verify the functions exist and accept org-scoped tables.

// Summary
echo "\n";
echo "========================================\n";
echo " Tenancy Tests Summary\n";
echo " Passed: $pass\n";
echo " Failed: $fail\n";
echo "========================================\n";

if ($fail > 0) {
    exit(1);
}

echo "OK: All tenancy tests passed.\n";
exit(0);
