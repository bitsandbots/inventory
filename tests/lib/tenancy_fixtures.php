<?php
/**
 * tests/lib/tenancy_fixtures.php
 *
 * Helpers for creating and tearing down two-org test fixtures.
 * All data created here uses org_id = 2 (test org) to avoid touching
 * org_id = 1 (Default Organization, used by all other tests).
 */

/**
 * Create a second test org if it doesn't exist. Returns the org_id (always 2).
 * Safe to call multiple times — INSERT IGNORE prevents duplicates.
 */
function fixture_ensure_test_org(): int {
    global $db;
    $db->prepare_query(
        "INSERT IGNORE INTO orgs (id, name, slug, created_at) VALUES (2, 'Test Org B', 'test-org-b', NOW())",
        ''
    );
    return 2;
}

/**
 * Add a user to an org with the given role.
 */
function fixture_add_member(int $org_id, int $user_id, string $role = 'member'): void {
    global $db;
    $db->prepare_query(
        "INSERT IGNORE INTO org_members (org_id, user_id, role) VALUES (?, ?, ?)",
        'iis', $org_id, $user_id, $role
    );
}

/**
 * Create a test customer in a specific org. Returns the inserted ID.
 */
function fixture_create_customer(int $org_id, string $name = 'Test Customer'): int {
    global $db;
    $stmt = $db->prepare_query(
        "INSERT INTO customers (org_id, name, address, city, region, postcode, telephone, email, paymethod)
         VALUES (?, ?, '', '', '', '', '', '', 'Cash')",
        'is', $org_id, $name . ' ' . uniqid()
    );
    $id = $stmt->insert_id;
    $stmt->close();
    return $id;
}

/**
 * Create a test category in a specific org. Returns the inserted ID.
 */
function fixture_create_category(int $org_id, string $name = 'Test Category'): int {
    global $db;
    $stmt = $db->prepare_query(
        "INSERT INTO categories (org_id, name) VALUES (?, ?)",
        'is', $org_id, $name . ' ' . uniqid()
    );
    $id = $stmt->insert_id;
    $stmt->close();
    return $id;
}

/**
 * Create a test product in a specific org. Returns the inserted ID.
 */
function fixture_create_product(int $org_id, string $sku = 'TEST_SKU', string $name = 'Test Product'): int {
    global $db;
    // Ensure a category exists for this org
    $category = $db->prepare_select_one(
        "SELECT id FROM categories WHERE org_id = ? LIMIT 1",
        'i', $org_id
    );
    $category_id = $category ? (int)$category['id'] : fixture_create_category($org_id);

    $stmt = $db->prepare_query(
        "INSERT INTO products (org_id, name, description, sku, location, quantity, buy_price, sale_price, category_id, media_id, date)
         VALUES (?, ?, '', ?, '', 100, 10.00, 20.00, ?, 1, NOW())",
        'issi', $org_id, $name . ' ' . uniqid(), $sku . '_' . uniqid(), $category_id
    );
    $id = $stmt->insert_id;
    $stmt->close();
    return $id;
}

/**
 * Remove all test data for org_id=2 from org-scoped tables.
 * Call in teardown to keep tests isolated.
 */
function fixture_teardown_org2(): void {
    global $db;
    // Delete in dependency order (children before parents)
    foreach (['sales', 'stock', 'orders', 'products', 'customers', 'media', 'categories'] as $table) {
        $db->prepare_query("DELETE FROM `$table` WHERE org_id = 2", '');
    }
    $db->prepare_query("DELETE FROM org_members WHERE org_id = 2", '');
    $db->prepare_query("DELETE FROM orgs WHERE id = 2", '');
}
