<?php
/**
 * delete_group.php
 *
 * @package default
 */


require_once '../includes/load.php';
// Checkin What level user has permission to view this page
page_require_level(ROLE_ADMIN);
if (!verify_get_csrf()) { $session->msg('d', 'Invalid or missing security token.'); redirect($_SERVER['HTTP_REFERER'] ?? 'index.php', false); }
?>
<?php
$delete_id = delete_by_id('user_groups', (int)$_GET['id']);
if ($delete_id) {
	$session->msg("s", "Group has been deleted.");
	redirect('../users/group.php');
} else {
	$session->msg("d", "Group deletion failed Or Missing Prm.");
	redirect('../users/group.php');
}
?>
