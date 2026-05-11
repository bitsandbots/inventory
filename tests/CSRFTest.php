<?php
/**
 * tests/CSRFTest.php
 *
 * Tests for CSRF token generation and validation.
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

echo "=== CSRFTest ===\n\n";

if (!isset($_SESSION)) {
    $_SESSION = [];
}

// 1. Token generation
test('csrf_token() generates a hex token', function () {
    $token = csrf_token();
    assert(!empty($token), 'Token is empty');
    assert(strlen($token) === 64, 'Token should be 64 hex chars (32 bytes), got ' . strlen($token));
    assert(isset($_SESSION['csrf_token']), 'Token not stored in session');
    echo "       [token: " . substr($token, 0, 16) . "...]\n";
});

// 2. Token persistence (same session = same token)
test('csrf_token() returns same token on second call', function () {
    $token1 = csrf_token();
    $token2 = csrf_token();
    assert($token1 === $token2, 'Token changed between calls');
});

// 3. csrf_field() produces hidden input
test('csrf_field() returns hidden input HTML', function () {
    $field = csrf_field();
    assert(strpos($field, '<input') !== false, 'Missing <input> tag');
    assert(strpos($field, 'type="hidden"') !== false, 'Missing type="hidden"');
    assert(strpos($field, 'name="csrf_token"') !== false, 'Missing name="csrf_token"');
    assert(strpos($field, csrf_token()) !== false, 'Token value not in field');
});

// 4. verify_csrf() accepts valid token
test('verify_csrf() passes with correct token', function () {
    $token = csrf_token();
    $_SERVER['REQUEST_METHOD'] = 'POST';
    $_POST['csrf_token'] = $token;
    $result = verify_csrf();
    assert($result === true, 'verify_csrf() should return true for valid token');
    // Cleanup
    unset($_SERVER['REQUEST_METHOD']);
    unset($_POST['csrf_token']);
});

// 5. verify_csrf() rejects wrong token
test('verify_csrf() rejects wrong token', function () {
    csrf_token(); // ensure token exists
    $_SERVER['REQUEST_METHOD'] = 'POST';
    $_POST['csrf_token'] = '0000000000000000000000000000000000000000000000000000000000000000';
    $result = verify_csrf();
    assert($result === false, 'Should reject wrong token');
    unset($_SERVER['REQUEST_METHOD']);
    unset($_POST['csrf_token']);
});

// 6. verify_csrf() rejects missing token
test('verify_csrf() rejects missing token', function () {
    csrf_token();
    $_SERVER['REQUEST_METHOD'] = 'POST';
    // No csrf_token in $_POST
    $result = verify_csrf();
    assert($result === false, 'Should reject when token is missing');
    unset($_SERVER['REQUEST_METHOD']);
});

// 7. verify_csrf() skips GET requests
test('verify_csrf() returns true for GET requests', function () {
    $_SERVER['REQUEST_METHOD'] = 'GET';
    $result = verify_csrf();
    // Should return true (no CSRF check needed for GET)
    assert($result === true, 'Should skip CSRF check for GET');
    unset($_SERVER['REQUEST_METHOD']);
});

// 8. h() escapes HTML
test('h() escapes HTML special characters', function () {
    $input = '<script>alert("xss")</script>';
    $escaped = h($input);
    assert(strpos($escaped, '<') === false, '< should be escaped');
    assert(strpos($escaped, '>') === false, '> should be escaped');
    assert(strpos($escaped, '"') === false, '" should be escaped');
    assert(strpos($escaped, '&lt;') !== false, 'Should contain &lt;');
});

// 9. remove_junk() strips and escapes
test('remove_junk() strips tags and escapes', function () {
    $input = '<p>Hello <b>World</b></p>';
    $result = remove_junk($input);
    assert(strpos($result, '<') === false, 'HTML tags should be removed');
    assert(strpos($result, 'Hello') !== false, 'Text content should remain');
});

// 10. randString() produces expected length
test('randString() produces correct length', function () {
    $s = randString(10);
    assert(strlen($s) === 10, 'randString(10) should return 10 chars, got ' . strlen($s));
});

echo "\n---\nResults: $pass passed, $fail failed\n";

exit($fail > 0 ? 1 : 0);
