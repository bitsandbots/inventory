<?php
/**
 * includes/config.php
 *
 * Loads configuration from .env file with fallback to constants/globals.
 *
 * @package default
 */

/**
 * Resolve the project root based on this file's location.
 * Works regardless of whether the app is served from the project root or a subdirectory.
 */
define('CONFIG_ROOT', realpath(__DIR__ . '/..'));

/**
 * Load .env file if it exists.
 * Returns parsed array or empty array if file doesn't exist.
 */
function load_env(string $env_path): array
{
    if (!file_exists($env_path)) {
        return [];
    }
    $lines = file($env_path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    $env = [];
    foreach ($lines as $line) {
        $line = trim($line);
        if ($line === '' || $line[0] === '#' || $line[0] === ';') {
            continue;
        }
        if (strpos($line, '=') !== false) {
            [$key, $value] = explode('=', $line, 2);
            $key = trim($key);
            $value = trim($value);
            $env[$key] = $value;
        } else {
            error_log("WARN: Malformed .env line skipped: " . substr($line, 0, 80));
        }
    }
    return $env;
}

$_ENV_CFG = load_env(CONFIG_ROOT . '/.env');

/*
|--------------------------------------------------------------------------
| Inventory Database Configuration
|--------------------------------------------------------------------------
*/
define('DB_HOST', $_ENV_CFG['DB_HOST'] ?? 'localhost');
define('DB_USER', $_ENV_CFG['DB_USER'] ?? 'webuser');
define('DB_PASS', $_ENV_CFG['DB_PASS'] ?? '');
define('DB_NAME', $_ENV_CFG['DB_NAME'] ?? 'inventory');

/*
|--------------------------------------------------------------------------
| Application Secret (for CSRF tokens and other security features)
|--------------------------------------------------------------------------
*/
define('APP_SECRET', $_ENV_CFG['APP_SECRET'] ?? '');
