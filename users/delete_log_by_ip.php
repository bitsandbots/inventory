<?php
/**
 * delete_log_by_ip.php
 *
 * @package default
 */


require_once '../includes/load.php';
// Checkin What level user has permission to view this page
page_require_level(ROLE_SUPERVISOR);
if (!verify_get_csrf()) { $session->msg('d', 'Invalid or missing security token.'); redirect($_SERVER['HTTP_REFERER'] ?? 'index.php', false); }

if ( isset($_GET['ip']) ) {
	$remote_ip = filter_var($_GET['ip'], FILTER_VALIDATE_IP);

	$delete_id = delete_by_ip('log', $remote_ip);
	if ( $delete_id ) {
		$session->msg("s", "logs deleted.");
		redirect('../users/log.php');
	} else {
		$session->msg("d", "log deletion failed.");
		redirect('../users/log.php');
	}

} else {
	$session->msg("d", "Missing log id.");
	redirect('../users/log.php');
}
