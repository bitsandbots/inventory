<?php
/**
 * tests/SecurityHeadersTest.php
 *
 * Asserts that the security response headers emitted by includes/load.php
 * are present on a representative set of pages.
 *
 * Requires an HTTP server reachable at the URL in env var $INVENTORY_BASE_URL
 * (default http://localhost:8080). If the server is not reachable, the suite
 * skips with exit 0 rather than failing — same convention run.sh uses for the
 * DB-dependent tests.
 */

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

/**
 * Fetch headers from $url. Returns associative array of header name (lower)
 * to header value (string). Returns null if the server is unreachable.
 */
function fetch_headers(string $url): ?array
{
    $ctx = stream_context_create([
        'http' => [
            'method' => 'GET',
            'timeout' => 3,
            'ignore_errors' => true,
        ],
    ]);
    $body = @file_get_contents($url, false, $ctx);
    if ($body === false || !isset($http_response_header)) {
        return null;
    }
    $headers = [];
    foreach ($http_response_header as $line) {
        if (strpos($line, ':') !== false) {
            [$name, $value] = explode(':', $line, 2);
            $headers[strtolower(trim($name))] = trim($value);
        }
    }
    return $headers;
}

$base = getenv('INVENTORY_BASE_URL') ?: 'http://localhost:8080';

echo "=== SecurityHeadersTest ===\n";
echo "Base URL: $base\n\n";

// Probe reachability first; skip cleanly if the dev server is not running.
$probe = fetch_headers($base . '/users/index.php');
if ($probe === null) {
    echo "  SKIPPED: server at $base is not reachable.\n";
    echo "  Start Apache (or set INVENTORY_BASE_URL) to run this suite.\n";
    exit(0);
}

// 1. CSP present on login page
test('CSP header on login page', function () use ($probe) {
    check(isset($probe['content-security-policy']), 'content-security-policy missing');
    $csp = $probe['content-security-policy'];
    check(strpos($csp, "default-src 'self'") !== false, "CSP should include default-src 'self'");
    check(strpos($csp, "frame-ancestors 'none'") !== false, "CSP should include frame-ancestors 'none'");
});

// 2. Clickjacking defense
test('X-Frame-Options: DENY on login page', function () use ($probe) {
    check(isset($probe['x-frame-options']), 'x-frame-options missing');
    check(strtoupper($probe['x-frame-options']) === 'DENY', 'x-frame-options should be DENY');
});

// 3. MIME-sniffing defense
test('X-Content-Type-Options: nosniff on login page', function () use ($probe) {
    check(isset($probe['x-content-type-options']), 'x-content-type-options missing');
    check(strtolower($probe['x-content-type-options']) === 'nosniff', 'should be nosniff');
});

// 4. Referrer policy
test('Referrer-Policy on login page', function () use ($probe) {
    check(isset($probe['referrer-policy']), 'referrer-policy missing');
    check($probe['referrer-policy'] !== '', 'referrer-policy must not be empty');
});

// 5. Permissions policy
test('Permissions-Policy on login page', function () use ($probe) {
    check(isset($probe['permissions-policy']), 'permissions-policy missing');
});

// 6. Headers present on a deeper, authenticated-only path as well.
//    A 302 redirect to login still passes through load.php so headers
//    should be set.
test('CSP also emitted on protected path (302 to login)', function () use ($base) {
    $h = fetch_headers($base . '/users/home.php');
    check($h !== null, 'home.php unreachable');
    check(isset($h['content-security-policy']), 'CSP missing on home.php');
});

// 7. CSP must NOT include 'unsafe-eval' (script-src tightening guard).
//    'unsafe-inline' is accepted today because of bundled Bootstrap/jQuery.
test('CSP does not allow unsafe-eval', function () use ($probe) {
    $csp = $probe['content-security-policy'] ?? '';
    check(strpos($csp, "'unsafe-eval'") === false, "CSP must not include 'unsafe-eval'");
});

echo "\n---\nResults: $pass passed, $fail failed\n";
exit($fail > 0 ? 1 : 0);
