<?php
/**
 * tests/AuthTest.php
 *
 * Smoke tests for authentication: login, password verification, and user retrieval.
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

echo "=== AuthTest ===\n\n";

// 1. Admin authentication
test('authenticate() as admin', function () {
    $user = authenticate('admin', 'admin');
    assert($user !== false, 'Admin login failed — wrong password or user missing?');
    assert($user['username'] === 'admin', 'Expected username "admin", got: ' . ($user['username'] ?? 'null'));
    assert($user['user_level'] === '1', 'Admin user_level should be 1');
    echo "       [authenticated as admin, id={$user['id']}]\n";
});

// 2. Special user authentication
test('authenticate() as special user', function () {
    $user = authenticate('special', 'special');
    assert($user !== false, 'Special login failed — wrong password or user missing?');
    assert($user['username'] === 'special', 'Expected username "special"');
    assert($user['user_level'] === '2', 'Special user_level should be 2');
    echo "       [authenticated as special, id={$user['id']}]\n";
});

// 3. Default user authentication
test('authenticate() as user', function () {
    $user = authenticate('user', 'user');
    assert($user !== false, 'User login failed — wrong password or user missing?');
    assert($user['username'] === 'user', 'Expected username "user"');
    assert($user['user_level'] === '3', 'Default user_level should be 3');
    echo "       [authenticated as user, id={$user['id']}]\n";
});

// 4. Invalid password
test('authenticate() rejects wrong password', function () {
    $user = authenticate('admin', 'WRONG_PASSWORD');
    assert($user === false, 'Should return false for wrong password');
});

// 5. Non-existent user
test('authenticate() rejects non-existent user', function () {
    $user = authenticate('nonexistent_user_xyz', 'password');
    assert($user === false, 'Should return false for non-existent user');
});

// 6. find_by_id for users table
test('find_by_id() retrieves user', function () {
    $admins = find_by_sql("SELECT id FROM users WHERE username='admin' LIMIT 1");
    assert(!empty($admins), 'Could not find admin user row');
    $admin = find_by_id('users', (int)$admins[0]['id']);
    assert($admin !== null, 'find_by_id() returned null for valid user');
    assert($admin['username'] === 'admin', 'find_by_id() returned wrong user');
});

// 7. find_by_name — products
test('find_by_name() returns null for unknown product', function () {
    $result = find_by_name('products', 'NONEXISTENT_PRODUCT_XYZ_999');
    assert($result === null, 'Should return null for unknown product');
});

// 8. SHA1 → bcrypt migration: login with legacy SHA1 hash rehashes to bcrypt
test('authenticate() rehashes legacy SHA1 password to bcrypt on login', function () {
    global $db;
    $test_user = 'HARNESS_sha1migrate_' . bin2hex(random_bytes(4));
    $test_pass = 'test_pass_' . bin2hex(random_bytes(4));
    $sha1_hash = sha1($test_pass);

    // Insert a user with a raw SHA1 hash
    $stmt = $db->prepare_query(
        "INSERT INTO users (name, username, password, user_level, status) VALUES (?, ?, ?, '3', '1')",
        "sss", $test_user, $test_user, $sha1_hash
    );
    $inserted_id = $db->insert_id();
    $stmt->close();

    // Authenticate — should succeed and trigger rehash
    $result = authenticate($test_user, $test_pass);
    assert($result !== false, 'Login with SHA1 hash should succeed');

    // Verify the stored hash was upgraded to bcrypt
    $user = find_by_id('users', $inserted_id);
    assert($user !== null, 'Could not retrieve user after login');
    $new_hash = $user['password'];
    assert(strlen($new_hash) !== 40, 'Hash should no longer be 40-char SHA1');
    assert(password_verify($test_pass, $new_hash), 'New hash should be valid bcrypt');
    echo "       [SHA1 hash upgraded to bcrypt for user $test_user]\n";

    // Cleanup
    $db->prepare_query("DELETE FROM users WHERE id = ?", "i", $inserted_id)->close();
});

// 9. session_regenerate_id is called on login (session ID changes)
test('Session::login() regenerates session ID to prevent fixation', function () {
    $old_id = session_id();
    $session_obj = new Session();
    $session_obj->login(1);
    $new_id = session_id();
    assert($old_id !== $new_id, 'Session ID must change after login() to prevent session fixation');
    echo "       [session ID changed: " . substr($old_id, 0, 8) . "... → " . substr($new_id, 0, 8) . "...]\n";
});

echo "\n---\nResults: $pass passed, $fail failed\n";

exit($fail > 0 ? 1 : 0);
