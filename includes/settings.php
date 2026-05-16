<?php
/**
 * includes/settings.php
 *
 * Per-org settings backed by the `settings(org_id, setting_key, setting_value)`
 * table. Values are loaded once per request and cached in a static, so
 * repeated Settings::get() calls hit memory, not the DB.
 *
 * Falls back to the supplied default if the row (or table) is missing — the
 * latter case covers the brief window between a deploy and migrations being
 * applied.
 */

class Settings {

    private static $cache = [];
    private static $loaded = false;
    private static $loaded_org_id = null;

    /**
     * Read a setting value. Lazy-loads the table on first call.
     *
     * @param string $key
     * @param string|null $default
     * @return string|null
     */
    public static function get($key, $default = null) {
        $org_id = current_org_id_safe() ?? 1;
        if (!self::$loaded || self::$loaded_org_id !== $org_id) {
            self::load($org_id);
        }
        return self::$cache[$key] ?? $default;
    }

    /**
     * Upsert a setting value for the current org. Updates the in-process
     * cache so subsequent Settings::get() calls in the same request see
     * the new value.
     *
     * @param string $key
     * @param string $value
     * @return bool true on success
     */
    public static function set($key, $value) {
        global $db;
        $org_id = current_org_id_safe() ?? 1;
        $stmt = $db->prepare_query(
            'INSERT INTO `settings` (`org_id`, `setting_key`, `setting_value`) VALUES (?, ?, ?) '
            . 'ON DUPLICATE KEY UPDATE `setting_value` = VALUES(`setting_value`)',
            'iss', $org_id, $key, $value
        );
        $stmt->close();
        if (self::$loaded_org_id === $org_id) {
            self::$cache[$key] = $value;
        }
        return true;
    }

    /**
     * Reset the in-process cache. Tests call this between cases.
     */
    public static function clear_cache() {
        self::$cache = [];
        self::$loaded = false;
        self::$loaded_org_id = null;
    }

    /**
     * Populate the cache from the settings table for the given org.
     * Swallows a missing-table error so a half-migrated deploy still
     * serves traffic on defaults.
     *
     * @param int $org_id
     */
    private static function load(int $org_id) {
        global $db;
        self::$cache = [];
        self::$loaded = true;
        self::$loaded_org_id = $org_id;
        try {
            $result = $db->prepare_select(
                'SELECT `setting_key`, `setting_value` FROM `settings` WHERE `org_id` = ?',
                'i', $org_id
            );
            if ($result === null) {
                return;
            }
            foreach ($result as $row) {
                self::$cache[$row['setting_key']] = $row['setting_value'];
            }
        } catch (\Throwable $e) {
            error_log('Settings::load() — settings table unavailable, using defaults: ' . $e->getMessage());
        }
    }
}
