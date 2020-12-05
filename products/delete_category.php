<?php
/**
 * delete_category.php
 *
 * @package default
 */


require_once '../includes/load.php';
// Checkin What level user has permission to view this page
page_require_level(1);
?>
<?php
$category = find_by_id('categories', (int)$_GET['id']);
if (!$category) {
	$session->msg("d", "Missing category id.");
	redirect('../products/category.php');
}
?>
<?php
$delete_id = delete_by_id('categories', (int)$category['id']);
if ($delete_id) {
	$session->msg("s", "category deleted.");
	redirect('../products/category.php');
} else {
	$session->msg("d", "category deletion failed.");
	redirect('../products/category.php');
}
?>
