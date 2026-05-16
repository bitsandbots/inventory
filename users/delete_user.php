<?php
/**
 * delete_user.php
 *
 * @package default
 */


require_once '../includes/load.php';
// Checkin What level user has permission to view this page
page_require_level(1);
if (!verify_get_csrf()) { $session->msg('d', 'Invalid or missing security token.'); redirect($_SERVER['HTTP_REFERER'] ?? 'index.php', false); }
?>
<?php
$delete_id = soft_delete_by_id('users', (int)$_GET['id']);
if ($delete_id) {
	$session->msg("s", "User deleted.");
	redirect('../users/users.php');
} else {
	$session->msg("d", "User deletion failed Or Missing Prm.");
	redirect('../users/users.php');
}
?>
