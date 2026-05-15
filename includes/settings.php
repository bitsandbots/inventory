<?php
/**
 * includes/settings.php
 *
 * App-wide single-tenant settings, backed by the `settings(setting_key,
 * setting_value)` table. Values are loaded once per request and cached in a
 * static, so repeated Settings::get() calls hit memory, not the DB.
 *
 * Falls back to the supplied default if the row (or table) is missing — the
 * latter case covers the brief window between a deploy and `migrations/004`
 * being applied.
 */

class Settings {

    private static $cache = [];
    private static $loaded = false;

    /**
     * Read a setting value. Lazy-loads the table on first call.
     *
     * @param string $key
     * @param string|null $default
     * @return string|null
     */
    public static function get($key, $default = null) {
        if (!self::$loaded) {
            self::load();
        }
        return self::$cache[$key] ?? $default;
    }

    /**
     * Upsert a setting value. Updates the in-process cache so subsequent
     * Settings::get() calls in the same request see the new value.
     *
     * @param string $key
     * @param string $value
     * @return bool true on success
     */
    public static function set($key, $value) {
        global $db;
        $stmt = $db->prepare_query(
            'INSERT INTO `settings` (`setting_key`, `setting_value`) VALUES (?, ?) '
            . 'ON DUPLICATE KEY UPDATE `setting_value` = VALUES(`setting_value`)',
            'ss', $key, $value
        );
        $stmt->close();
        self::$cache[$key] = $value;
        self::$loaded = true;
        return true;
    }

    /**
     * Reset the in-process cache. Tests call this between cases.
     */
    public static function clear_cache() {
        self::$cache = [];
        self::$loaded = false;
    }

    /**
     * Populate the cache from the settings table. Swallows a missing-table
     * error so a half-migrated deploy still serves traffic on defaults.
     */
    private static function load() {
        global $db;
        self::$loaded = true;
        try {
            $con = $db->connection();
            $result = $con->query('SELECT `setting_key`, `setting_value` FROM `settings`');
            if ($result === false) {
                return;
            }
            while ($row = $result->fetch_assoc()) {
                self::$cache[$row['setting_key']] = $row['setting_value'];
            }
            $result->free();
        } catch (\mysqli_sql_exception $e) {
            error_log('Settings::load() — settings table unavailable, using defaults: ' . $e->getMessage());
        }
    }
}
