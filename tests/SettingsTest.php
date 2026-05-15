<?php
/**
 * tests/SettingsTest.php
 *
 * Integration tests for the Settings class + supported-currency helpers.
 * Requires a live database with migration 004 applied.
 *
 * Touches the `settings` table directly; restores the seeded
 * currency_code = 'USD' row at the end so subsequent runs are
 * deterministic.
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

echo "=== SettingsTest ===\n\n";

// Skip gracefully if the settings table doesn't exist locally
// (migration 004 not yet applied). CI imports schema.sql which already
// contains the table, so this only ever skips on a dev box where the
// admin hasn't run the migration.
$settings_exists = false;
try {
    global $db;
    $r = $db->connection()->query("SHOW TABLES LIKE 'settings'");
    $settings_exists = ($r !== false && $r->num_rows > 0);
    if ($r) {
        $r->free();
    }
} catch (\Throwable $e) {
    $settings_exists = false;
}
if (!$settings_exists) {
    echo "  SKIPPED: `settings` table not present.\n";
    echo "  Apply migration 004 to enable these tests:\n";
    echo "    sudo mysql inventory < migrations/004_settings_table.up.sql\n";
    echo "\n---\nResults: 0 passed, 0 failed (suite skipped)\n";
    exit(0);
}

// 1. supported_currency_codes() returns a sorted, non-empty list that
//    includes USD.
test('supported_currency_codes() returns sorted list including USD', function () {
    $codes = supported_currency_codes();
    check(is_array($codes) && count($codes) > 50, 'expected >50 supported currencies');
    check(in_array('USD', $codes, true), 'USD missing from supported list');
    $sorted = $codes;
    sort($sorted);
    check($codes === $sorted, 'codes are not in sorted order');
    echo "       [{$codes[0]} ... {$codes[count($codes)-1]}, " . count($codes) . " total]\n";
});

// 2. Default fallback when key absent and table reachable.
test('Settings::get() returns default for unknown key', function () {
    global $db;
    Settings::clear_cache();
    // ensure the test key doesn't exist
    $stmt = $db->prepare_query('DELETE FROM `settings` WHERE `setting_key` = ?', 's', 'HARNESS_unknown_key');
    $stmt->close();
    Settings::clear_cache();
    $v = Settings::get('HARNESS_unknown_key', 'fallback-value');
    check($v === 'fallback-value', 'expected fallback-value, got: ' . var_export($v, true));
});

// 3. set() upserts and updates cache.
test('Settings::set() persists and updates cache', function () {
    Settings::clear_cache();
    Settings::set('HARNESS_test_key', 'first-value');
    check(Settings::get('HARNESS_test_key') === 'first-value', 'first set did not stick');

    // Re-read with a fresh cache to confirm it really hit the DB.
    Settings::clear_cache();
    check(Settings::get('HARNESS_test_key') === 'first-value', 'value did not survive cache flush');

    // Update — same key, new value — should overwrite, not duplicate.
    Settings::set('HARNESS_test_key', 'second-value');
    Settings::clear_cache();
    check(Settings::get('HARNESS_test_key') === 'second-value', 'update did not overwrite');
});

// 4. Round-trip with the production key (currency_code) — sets, reads
//    back, formats a number through formatcurrency() and confirms the
//    output reflects the chosen code's symbol/format.
test('currency_code round-trip changes formatcurrency() output', function () {
    Settings::set('currency_code', 'EUR');
    Settings::clear_cache();
    $code = Settings::get('currency_code', 'USD');
    check($code === 'EUR', "expected EUR, got: $code");

    $eur = formatcurrency(1234.56, $code);
    // formatcurrency() emits HTML entities (e.g. &euro;) for symbols, so
    // decode before substring-matching against the literal char.
    $decoded = html_entity_decode($eur, ENT_QUOTES | ENT_HTML5, 'UTF-8');
    check(str_contains($decoded, 'EUR') || str_contains($decoded, '€'), "EUR output looks wrong: $eur");
    echo "       [EUR sample: $eur]\n";

    // Restore to the canonical seeded value.
    Settings::set('currency_code', 'USD');
    Settings::clear_cache();
});

// 5. formatcurrency() with an unknown code falls back to USD instead of
//    throwing.
test('formatcurrency() with unknown code falls back to USD', function () {
    $out = formatcurrency(100, 'ZZZ');
    check($out === '$100.00', "expected USD fallback, got: $out");
});

// Cleanup: drop the harness row(s) so the table only contains the seed.
test('Cleanup HARNESS rows', function () {
    global $db;
    $stmt = $db->prepare_query('DELETE FROM `settings` WHERE `setting_key` LIKE ?', 's', 'HARNESS_%');
    $stmt->close();
});

echo "\n---\nResults: $pass passed, $fail failed\n";
exit($fail > 0 ? 1 : 0);
