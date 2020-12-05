<?php
/**
 * delete_product.php
 *
 * @package default
 */


require_once '../includes/load.php';
// Checkin What level user has permission to view this page
page_require_level(2);
?>
<?php
$product = find_by_id('products', (int)$_GET['id']);
if (!$product) {
	$session->msg("d", "Missing Product id.");
	redirect('../products/products.php');
}
?>
<?php
$delete_id = delete_by_id('products', (int)$product['id']);
if ($delete_id) {
	$session->msg("s", "Products deleted.");
	redirect('../products/products.php');
} else {
	$session->msg("d", "Products deletion failed.");
	redirect('../products/products.php');
}
?>
