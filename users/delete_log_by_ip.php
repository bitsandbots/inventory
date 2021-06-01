<?php
/**
 * delete_log_by_ip.php
 *
 * @package default
 */


require_once '../includes/load.php';
// Checkin What level user has permission to view this page
page_require_level(2);

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
