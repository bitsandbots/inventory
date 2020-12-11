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
	redirect('../products/categories.php');
}

$products = find_products_by_category((int)$_GET['id']);
if ($products) {
	$session->msg("d", "Products assigned to category id.");
	redirect('../products/categories.php');
}


$delete_id = delete_by_id('categories', (int)$category['id']);
if ($delete_id) {
	$session->msg("s", "Category deleted.");
	redirect('../products/categories.php');
} else {
	$session->msg("d", "Category deletion failed.");
	redirect('../products/categories.php');
}
?>
