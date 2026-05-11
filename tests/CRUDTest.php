<?php
/**
 * tests/CRUDTest.php
 *
 * Smoke tests for product CRUD: create, read, update, delete.
 */

require_once __DIR__ . '/bootstrap.php';

$pass = 0;
$fail = 0;
$created_product_id = null;

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

echo "=== CRUDTest ===\n\n";

// 1. List products (join_product_table)
test('join_product_table() returns products', function () {
    global $db;
    $products = join_product_table();
    assert(is_array($products), 'join_product_table() should return an array');
    echo "       [" . count($products) . " products in database]\n";
});

// 2. Create a test product
test('Create product via INSERT', function () use (&$created_product_id) {
    global $db;
    $name = 'HARNESS_TestProduct_' . uniqid();
    $date = date('Y-m-d H:i:s');
    $e_name = $db->escape($name);
    $sql = "INSERT INTO products (name, quantity, buy_price, sale_price, categ_id, media_id, date)
            VALUES ('{$e_name}', 100, 5.00, 10.00, 1, 0, '{$date}')";
    $db->query($sql);
    $created_product_id = $db->insert_id();
    assert($created_product_id > 0, 'Failed to create product');
    echo "       [created product id=$created_product_id name=$name]\n";
});

// 3. Read the created product
test('find_by_id() retrieves created product', function () use ($created_product_id) {
    $product = find_by_id('products', $created_product_id);
    assert($product !== null, 'find_by_id() returned null');
    assert(
        strpos($product['name'], 'HARNESS_TestProduct_') === 0,
        'Product name mismatch: ' . ($product['name'] ?? 'null')
    );
    echo "       [retrieved: {$product['name']}]\n";
});

// 4. Update product quantity
test('increase_product_qty() updates quantity', function () use ($created_product_id) {
    global $db;
    $before = find_by_id('products', $created_product_id);
    $old_qty = (int) $before['quantity'];
    increase_product_qty(50, $created_product_id);
    $after = find_by_id('products', $created_product_id);
    assert((int)$after['quantity'] === $old_qty + 50, 'Quantity not increased');
    echo "       [quantity: $old_qty -> {$after['quantity']}]\n";
});

// 5. Test decrease_product_qty
test('decrease_product_qty() updates quantity', function () use ($created_product_id) {
    global $db;
    $before = find_by_id('products', $created_product_id);
    $old_qty = (int) $before['quantity'];
    decrease_product_qty(25, $created_product_id);
    $after = find_by_id('products', $created_product_id);
    assert((int)$after['quantity'] === $old_qty - 25, 'Quantity not decreased');
    echo "       [quantity: $old_qty -> {$after['quantity']}]\n";
});

// 6. Delete the test product
test('Delete product via delete_by_id', function () use ($created_product_id) {
    global $db;
    $result = delete_by_id('products', $created_product_id);
    assert($result !== false, 'delete_by_id() failed');
    $gone = find_by_id('products', $created_product_id);
    assert($gone === null, 'Product still exists after delete');
    echo "       [product id=$created_product_id deleted]\n";
});

// 7. Verify INT quantity column (migration 001)
test('Quantity column is INT type', function () {
    global $db;
    $result = $db->query("SHOW COLUMNS FROM products LIKE 'quantity'");
    $row = $db->fetch_assoc($result);
    assert($row !== false, 'Could not inspect quantity column');
    $type = strtolower($row['Type']);
    assert(strpos($type, 'int') !== false, "quantity column is $type, expected INT");
    echo "       [quantity column type: {$row['Type']}]\n";
});

// 8. Sales functions exist
test('find_sale_by_dates() executes', function () {
    $sales = find_sale_by_dates('2020-01-01', '2030-12-31');
    assert(is_array($sales), 'find_sale_by_dates() should return an array');
    echo "       [" . count($sales) . " sales in range]\n";
});

echo "\n---\nResults: $pass passed, $fail failed\n";

exit($fail > 0 ? 1 : 0);
