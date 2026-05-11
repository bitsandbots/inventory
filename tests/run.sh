#!/bin/bash
#
# tests/run.sh
#
# Run all smoke tests for the Inventory Management System.
# Usage: bash tests/run.sh
#
# Requires a valid .env file with database credentials for full suite.
# When .env is missing or DB is unreachable, only unit tests run (CSRF, helpers).

set -e
cd "$(dirname "$0")/.."

TOTAL=0
PASSED=0
FAILED=0
SKIPPED=0

echo "========================================="
echo " Inventory Management System — Test Suite"
echo " Date: $(date)"
echo "========================================="
echo ""

run_test() {
    local test_file="$1"
    local name="$2"
    local nodb="${3:-}"
    TOTAL=$((TOTAL + 1))
    echo "--- $name ---"
    if [ -n "$nodb" ]; then
        export TESTS_NO_DB=1
    fi
    if php "$test_file" 2>&1; then
        PASSED=$((PASSED + 1))
        echo ""
    else
        local code=$?
        FAILED=$((FAILED + 1))
        echo "  (exit code: $code)"
        echo ""
    fi
    unset TESTS_NO_DB
}

# Unit tests (no database required) — always run
run_test "tests/CSRFTest.php" "CSRF & Helpers (unit)" nodb

# Database-dependent tests — only if DB is reachable
DB_OK=0
if php -r "
define('URL_SEPARATOR', '/');
define('DS', DIRECTORY_SEPARATOR);
define('SITE_ROOT', realpath('includes'));
define('LIB_PATH_INC', SITE_ROOT . DS);
require_once 'includes/config.php';
try {
    \$c = @mysqli_connect(DB_HOST, DB_USER, DB_PASS, DB_NAME);
    if (\$c) { echo 'OK'; exit(0); }
} catch (Exception \$e) {}
exit(1);
" 2>/dev/null; then
    DB_OK=1
fi

if [ "$DB_OK" -eq 1 ]; then
    run_test "tests/AuthTest.php" "Authentication (integration)"
    run_test "tests/CRUDTest.php" "CRUD Operations (integration)"
else
    echo "--- Authentication (integration) ---"
    echo "  SKIPPED: Database not accessible."
    echo "  Configure .env with valid credentials to enable integration tests."
    echo ""
    SKIPPED=$((SKIPPED + 2))
    TOTAL=$((TOTAL + 2))
fi

echo "========================================="
echo " Summary: $PASSED/$TOTAL suites passed"
if [ "$SKIPPED" -gt 0 ]; then
    echo " ($SKIPPED skipped — configure database)"
fi
echo "========================================="

if [ "$FAILED" -gt 0 ]; then
    echo "FAILED: $FAILED test suite(s)"
    exit 1
fi

echo "OK: All available test suites passed."
exit 0
