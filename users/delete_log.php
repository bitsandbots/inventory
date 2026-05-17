<?php
/**
 * delete_log.php
 *
 * @package default
 */


require_once '../includes/load.php';
// Checkin What level user has permission to view this page
page_require_level(ROLE_SUPERVISOR);
if (!verify_get_csrf()) { $session->msg('d', 'Invalid or missing security token.'); redirect($_SERVER['HTTP_REFERER'] ?? 'index.php', false); }

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
