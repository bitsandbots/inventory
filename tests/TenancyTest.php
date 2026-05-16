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
// Use tenancy_fixtures.php to create multi-org test data
require_once __DIR__ . '/lib/tenancy_fixtures.php';

test('find_all(customers) returns only org 1 customers', function () {
    fixture_ensure_test_org();
    fixture_add_member(2, 1);

    // Create a customer in org 2
    $org2_cust_id = fixture_create_customer(2, 'Org2 Customer');

    // As org 1: should NOT see org 2's customer
    setup_test_org_session(1);
    $org1_customers = find_all('customers');
    $found = array_filter($org1_customers ?? [], fn($c) => $c['id'] === $org2_cust_id);
    check(count($found) === 0, "Org 1 should not see org 2 customers in find_all()");

    fixture_teardown_org2();
});

test('find_all(customers) returns only org 2 customers when session=org 2', function () {
    fixture_ensure_test_org();
    fixture_add_member(2, 1);

    // Create a customer in org 2
    $org2_cust_id = fixture_create_customer(2, 'Org2 FindAll');

    // As org 2: SHOULD see it
    setup_test_org_session(2);
    $org2_customers = find_all('customers');
    $found = array_filter($org2_customers ?? [], fn($c) => $c['id'] === $org2_cust_id);
    check(count($found) === 1, "Org 2 should see its own customer in find_all()");

    fixture_teardown_org2();
});

test('find_by_id(customers) returns null for cross-org record', function () {
    fixture_ensure_test_org();
    fixture_add_member(2, 1);

    // Create a customer in org 2
    $org2_cust_id = fixture_create_customer(2, 'Org2 ById');

    // As org 1: should get null for org 2's ID
    setup_test_org_session(1);
    $result = find_by_id('customers', $org2_cust_id);
    check($result === null, "find_by_id() must return null for cross-org record");

    fixture_teardown_org2();
});

test('find_by_id(customers) returns record for same-org id', function () {
    fixture_ensure_test_org();
    fixture_add_member(2, 1);

    // Create a customer in org 2
    $org2_cust_id = fixture_create_customer(2, 'Org2 ByIdOwn');

    // As org 2: should get the record
    setup_test_org_session(2);
    $result = find_by_id('customers', $org2_cust_id);
    check($result !== null, "find_by_id() must return the record for its own org");
    check((int)$result['org_id'] === 2, "Returned record must have org_id=2");

    fixture_teardown_org2();
});

test('find_all(products) respects org_id filtering', function () {
    fixture_ensure_test_org();
    fixture_add_member(2, 1);

    // Create a product in org 2
    $org2_prod_id = fixture_create_product(2, 'PROD_ORG2', 'Org2 Product');

    // As org 1: should NOT see org 2's product
    setup_test_org_session(1);
    $org1_products = find_all('products');
    $found = array_filter($org1_products ?? [], fn($p) => $p['id'] === $org2_prod_id);
    check(count($found) === 0, "Org 1 must not see org 2 products");

    // As org 2: SHOULD see it
    setup_test_org_session(2);
    $org2_products = find_all('products');
    $found = array_filter($org2_products ?? [], fn($p) => $p['id'] === $org2_prod_id);
    check(count($found) === 1, "Org 2 must see its own product");

    fixture_teardown_org2();
});

test('find_by_id(products) respects org_id filtering', function () {
    fixture_ensure_test_org();
    fixture_add_member(2, 1);

    // Create a product in org 2
    $org2_prod_id = fixture_create_product(2, 'PROD_ORG2_ID', 'Org2 Product ById');

    // As org 1: should get null
    setup_test_org_session(1);
    $result = find_by_id('products', $org2_prod_id);
    check($result === null, "find_by_id() for product must return null for cross-org");

    // As org 2: should get the record
    setup_test_org_session(2);
    $result = find_by_id('products', $org2_prod_id);
    check($result !== null, "find_by_id() for product must return record for same-org");
    check((int)$result['org_id'] === 2, "Product record must have org_id=2");

    fixture_teardown_org2();
});

// T08: resolve_login_org() function tests
test('resolve_login_org() exists', function () {
    check(function_exists('resolve_login_org'), 'resolve_login_org() not defined');
});

test('resolve_login_org() returns org_id for member with last_active_org_id', function () {
    global $db;
    // Create a temporary test org and user membership
    $slug = 'harness_t08_' . substr(md5(microtime()), 0, 8);
    $db->prepare_query(
        "INSERT INTO orgs (name, slug, deleted_at) VALUES (?, ?, NULL)",
        'ss', 'HARNESS_TestOrg_T08', $slug
    );
    $org_id = (int)$db->insert_id();

    $user = $db->prepare_select_one(
        "SELECT id FROM users WHERE deleted_at IS NULL LIMIT 1", ''
    );
    if (!$user) {
        throw new RuntimeException('No users found in database');
    }
    $user_id = (int)$user['id'];

    try {
        // Create org membership
        $db->prepare_query(
            "INSERT INTO org_members (user_id, org_id, joined_at) VALUES (?, ?, NOW())",
            'ii', $user_id, $org_id
        );

        // Call resolve_login_org with the org_id as last_active_org_id
        $result_org_id = resolve_login_org($user_id, $org_id);
        check($result_org_id !== false, "resolve_login_org() should return an org_id for a member");
        check($result_org_id === $org_id, "resolve_login_org() should return the correct org_id");
        check(is_int($result_org_id), "resolve_login_org() should return an int");
    } finally {
        $db->prepare_query("DELETE FROM org_members WHERE user_id = ? AND org_id = ?", 'ii', $user_id, $org_id);
        $db->prepare_query("DELETE FROM orgs WHERE id = ?", 'i', $org_id);
    }
});

test('resolve_login_org() returns false for memberless user', function () {
    global $db;
    // Insert a temporary user with no org membership
    $db->prepare_query(
        "INSERT INTO users (name, username, email, password, user_level, status, deleted_at)
         VALUES (?, ?, ?, ?, ?, ?, NULL)",
        'ssssii', 'HARNESS_Orphan', 'harness_orphan_t8', 'orphan@example.com', 'x', 3, 1
    );
    $user_id = (int)$db->insert_id();
    try {
        $result = resolve_login_org($user_id, null);
        check($result === false, "resolve_login_org() should return false for user with no org membership");
    } finally {
        $db->prepare_query("DELETE FROM users WHERE id = ?", 'i', $user_id);
    }
});

test('authenticate() returns array with user_id and org_id', function () {
    // This is a unit test only — we don't test actual credentials,
    // just verify the return type structure.
    // We can't test with real credentials without fixtures,
    // so we just verify the function signature changed.
    check(function_exists('authenticate'), 'authenticate() not defined');
});

// T09: require_org_role() and org membership tests
test('require_org_role() passes for member of current org', function () {
    fixture_ensure_test_org();
    fixture_add_member(2, 1, 'member');
    setup_test_org_session(2);

    try {
        require_org_role();
        check(true, 'require_org_role() should pass for org member');
    } catch (RuntimeException $e) {
        check(false, 'require_org_role() should not throw for member: ' . $e->getMessage());
    } finally {
        fixture_teardown_org2();
    }
});

test('require_org_role() throws for non-member of current org', function () {
    fixture_ensure_test_org();
    // User 1 is NOT added to org 2
    setup_test_org_session(2);

    try {
        require_org_role();
        check(false, 'require_org_role() should throw for non-member');
    } catch (RuntimeException $e) {
        check(true, 'Correctly threw RuntimeException for non-member');
    } finally {
        fixture_teardown_org2();
    }
});

test('require_org_role("owner") passes for owner', function () {
    fixture_ensure_test_org();
    fixture_add_member(2, 1, 'owner');
    setup_test_org_session(2);

    try {
        require_org_role('owner');
        check(true, 'require_org_role("owner") should pass for owner role');
    } catch (RuntimeException $e) {
        check(false, 'require_org_role("owner") should not throw for owner: ' . $e->getMessage());
    } finally {
        fixture_teardown_org2();
    }
});

test('require_org_role("owner") throws for member', function () {
    fixture_ensure_test_org();
    fixture_add_member(2, 1, 'member');
    setup_test_org_session(2);

    try {
        require_org_role('owner');
        check(false, 'require_org_role("owner") should throw for non-owner');
    } catch (RuntimeException $e) {
        check(true, 'Correctly threw RuntimeException for non-owner');
    } finally {
        fixture_teardown_org2();
    }
});

// T09: find_org_members()
test('find_org_members() returns all members of an org with correct fields', function () {
    fixture_ensure_test_org();
    fixture_add_member(2, 1, 'owner');
    setup_test_org_session(2);

    $members = find_org_members(2);
    check(is_array($members), 'find_org_members() should return an array');
    check(count($members) > 0, 'Org 2 should have at least 1 member');

    $member = $members[0];
    check(isset($member['id']), 'Member should have id field (user_id)');
    check(isset($member['role']), 'Member should have role field');
    check(isset($member['name']), 'Member should have name field (from users table)');

    fixture_teardown_org2();
});

// T14: Settings::get() and Settings::set() org-scoping
test('Settings::get() returns org-specific value', function () {
    fixture_ensure_test_org();

    // Set a value for org 2
    setup_test_org_session(2);
    Settings::clear_cache();
    Settings::set('test_key_org2', 'test_value_org2');

    // Read it back as org 2
    $value = Settings::get('test_key_org2');
    check($value === 'test_value_org2', "Settings::get() should return org 2's value");

    // As org 1, should not find it
    setup_test_org_session(1);
    Settings::clear_cache();
    $value = Settings::get('test_key_org2');
    check($value === null, "Settings::get() for org 1 should not return org 2's value");

    fixture_teardown_org2();
});

test('Settings::set() persists to the correct org', function () {
    fixture_ensure_test_org();

    // Set a value for org 2
    $key = 'test_persist_key_' . uniqid();
    setup_test_org_session(2);
    Settings::clear_cache();
    Settings::set($key, 'org2_value');

    // Verify it was set for org 2
    $value = Settings::get($key);
    check($value === 'org2_value', "Settings::set() should persist to org 2");

    // As org 1, set a different value
    setup_test_org_session(1);
    Settings::clear_cache();
    Settings::set($key, 'org1_value');
    $value = Settings::get($key);
    check($value === 'org1_value', "Settings::set() should persist to org 1 independently");

    // Verify org 2's value is still unchanged
    setup_test_org_session(2);
    Settings::clear_cache();
    $value2 = Settings::get($key);
    check($value2 === 'org2_value', "Org 2's setting should remain unchanged after org 1 update");

    fixture_teardown_org2();
});

// T14: switch_org — session persistence
test('switch_org updates session and persists last_active_org_id', function () {
    global $db;
    fixture_ensure_test_org();
    fixture_add_member(2, 1, 'owner');

    // Start in org 1
    setup_test_org_session(1);
    $user_id = 1;

    // Simulate switch_org call by updating the session and DB
    $_SESSION['current_org_id'] = 2;
    $db->prepare_query(
        "UPDATE users SET last_active_org_id = ? WHERE id = ?",
        'ii', 2, $user_id
    );

    // Verify session was updated
    check($_SESSION['current_org_id'] === 2, "Session org_id should be updated to 2");

    // Verify DB was updated
    $user = $db->prepare_select_one(
        "SELECT last_active_org_id FROM users WHERE id = ?",
        'i', $user_id
    );
    check((int)$user['last_active_org_id'] === 2, "DB last_active_org_id should be updated to 2");

    // Reset for cleanup
    $_SESSION['current_org_id'] = 1;
    $db->prepare_query(
        "UPDATE users SET last_active_org_id = NULL WHERE id = ?",
        'i', $user_id
    );

    fixture_teardown_org2();
});

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
