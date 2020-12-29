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
/* Log user actions
/*--------------------------------------------------------------*/
$user_id = 0;
$remote_ip = 0;
$action =  '';
if (isset( $_SESSION['user_id'] )) {
	$user_id = $_SESSION['user_id'];
}
if ( isset($_SERVER['HTTP_X_FORWARDED_FOR']) ) {
	$remote_ip = $_SERVER['HTTP_X_FORWARDED_FOR'];
	if ( strpos( $remote_ip, "," ) > 0 ) {
		$remote_ip_for = explode( ",", $remote_ip );
		$remote_ip = $remote_ip_for[0];
	}
} else {

	if (isset( $_SERVER['REMOTE_ADDR'] )) {
		$remote_ip = $_SERVER['REMOTE_ADDR'];
	}

}

if (isset( $_SERVER['REQUEST_URI'] )) {
	$action = $_SERVER['REQUEST_URI'];
	$action = preg_replace('/^.+[\\\\\\/]/', '', $action);
	//$action = preg_replace('/^\/inventory/', '', $action);
}

logAction( $user_id, $remote_ip, $action );
?>
