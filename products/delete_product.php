<?php
/**
 * delete_product.php
 *
 * @package default
 */


require_once '../includes/load.php';
// Checkin What level user has permission to view this page
page_require_level(ROLE_SUPERVISOR);
if (!verify_get_csrf()) { $session->msg('d', 'Invalid or missing security token.'); redirect($_SERVER['HTTP_REFERER'] ?? 'index.php', false); }
?>
<?php
$product = find_by_id('products', (int)$_GET['id']);
if (!$product) {
	$session->msg("d", "Missing Product ID.");
	redirect('../products/products.php');
}

$all_stock = find_all('stock');
foreach ( $all_stock as $stock ) {
	if ( $stock['product_id'] == $product['id'] ) {
		$session->msg("d", "Please delete entries OR add negative quantity stock.");
		redirect('../products/stock.php');
	}
}

$delete_id = delete_by_id('products', (int)$product['id']);
if ($delete_id) {
	$session->msg("s", "Product deleted.");
	redirect('../products/products.php');
} else {
	$session->msg("d", "Failed to delete Product.");
	redirect('../products/products.php');
}
?>
