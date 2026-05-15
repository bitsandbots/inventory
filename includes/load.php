<?php
/**
 * includes/load.php
 *
 * @package default
 * @see index.php
 */


// -----------------------------------------------------------------------
// DEFINE SEPERATOR ALIASES
// -----------------------------------------------------------------------
define("URL_SEPARATOR", '/');
define("DS", DIRECTORY_SEPARATOR);

// -----------------------------------------------------------------------
// DEFINE ROOT PATHS
// -----------------------------------------------------------------------
defined('SITE_ROOT')? null: define('SITE_ROOT', realpath(dirname(__FILE__)));
define("LIB_PATH_INC", SITE_ROOT.DS);

// -----------------------------------------------------------------------
// Session security hardening — must run before session_start()
// -----------------------------------------------------------------------
ini_set('session.cookie_httponly', 1);
ini_set('session.cookie_samesite', 'Lax');
ini_set('session.use_strict_mode', 1);
if (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') {
    ini_set('session.cookie_secure', 1);
}

// -----------------------------------------------------------------------
// Security response headers — emitted on every request.
//
// CSP is intentionally permissive ('self' + inline styles/scripts) because
// the project bundles Bootstrap 5 and jQuery with inline onclick handlers.
// Tightening this requires refactoring inline JS out of the templates;
// tracked in docs/gap-analysis.md.
// -----------------------------------------------------------------------
if (PHP_SAPI !== 'cli' && !headers_sent()) {
    // script-src: 'self' only — no 'unsafe-inline'. All inline JS has been
    //   moved to libs/js/functions.js. New inline scripts must use a nonce
    //   or be moved to an external file.
    // style-src: keeps 'unsafe-inline' because Bootstrap and jQuery
    //   plugins (dropdowns, tooltips, popovers) set inline style attributes
    //   at runtime. Removing it would break common UI controls. Tracked in
    //   docs/gap-analysis.md as an accepted constraint.
    header("Content-Security-Policy: "
        . "default-src 'self'; "
        . "script-src 'self'; "
        . "style-src 'self' 'unsafe-inline'; "
        . "img-src 'self' data:; "
        . "font-src 'self'; "
        . "object-src 'none'; "
        . "base-uri 'self'; "
        . "form-action 'self'; "
        . "frame-ancestors 'none'");
    header('X-Frame-Options: DENY');
    header('X-Content-Type-Options: nosniff');
    header('Referrer-Policy: same-origin');
    header('Permissions-Policy: geolocation=(), microphone=(), camera=()');
}

require_once LIB_PATH_INC.'config.php';
require_once LIB_PATH_INC.'functions.php';
require_once LIB_PATH_INC.'session.php';
require_once LIB_PATH_INC.'upload.php';
require_once LIB_PATH_INC.'database.php';
require_once LIB_PATH_INC.'sql.php';
require_once LIB_PATH_INC.'formatcurrency.php';

/*--------------------------------------------------------------*/
/* Change format of currency used throughout the system
/*--------------------------------------------------------------*/
$CURRENCY_CODE = 'USD';
//$CURRENCY_CODE = 'EUR';


/*--------------------------------------------------------------*/
/* Initialize CSRF token
/*--------------------------------------------------------------*/
csrf_token();


/*--------------------------------------------------------------*/
/* Log user actions (skip static asset requests)
/*--------------------------------------------------------------*/
$user_id = 0;
$remote_ip = 0;
$action =  '';

// Determine if this is a page request (not a static asset)
$is_page_request = true;
if (isset($_SERVER['REQUEST_URI'])) {
    $path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
    $ext = strtolower(pathinfo($path, PATHINFO_EXTENSION));
    $static_extensions = ['css', 'js', 'jpg', 'jpeg', 'png', 'gif', 'svg', 'ico', 'woff', 'woff2', 'ttf', 'eot', 'map'];
    if (in_array($ext, $static_extensions)) {
        $is_page_request = false;
    }
}

if ($is_page_request) {
    if (isset( $_SESSION['user_id'] )) {
        $user_id = $_SESSION['user_id'];
    }

    // Use REMOTE_ADDR as the authoritative IP; only fall back to
    // X-Forwarded-For when running behind a trusted reverse proxy, and
    // always validate the extracted value is a real IP address.
    $remote_ip = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
    if ( isset($_SERVER['HTTP_X_FORWARDED_FOR']) ) {
        $forwarded_ips = explode(',', $_SERVER['HTTP_X_FORWARDED_FOR']);
        $candidate = trim($forwarded_ips[0]);
        if (filter_var($candidate, FILTER_VALIDATE_IP)) {
            $remote_ip = $candidate;
        }
    }

    if (isset( $_SERVER['REQUEST_URI'] )) {
        $action = $_SERVER['REQUEST_URI'];
        $action = preg_replace('/^.+[\\\\\\/]/', '', $action);
    }

    if (!logAction( $user_id, $remote_ip, $action )) {
        error_log("WARNING: logAction failed for user_id=$user_id ip=$remote_ip action=$action");
    }

    // ~1% of page requests: prune stale failed-login rows so the table
    // can't grow unbounded. Stale rows have no effect on rate limiting.
    if (random_int(1, 100) === 1 && function_exists('prune_failed_logins')) {
        prune_failed_logins();
    }
}
