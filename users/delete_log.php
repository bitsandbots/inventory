<?php
/**
 * delete_log.php
 *
 * @package default
 */


require_once '../includes/load.php';
// Checkin What level user has permission to view this page
page_require_level(2);

$id = filter_var($_GET['id'], FILTER_VALIDATE_INT);
$log = find_by_id('log', $id);
if ( ! $log ) {
	$session->msg("d", "Missing log id.");
	redirect('../users/log.php');
}

$delete_id = delete_by_id('log', (int)$log['id']);
if ( $delete_id ) {
	$session->msg("s", "logs deleted.");
	redirect('../users/log.php');
} else {
	$session->msg("d", "log deletion failed.");
	redirect('../users/log.php');
}
