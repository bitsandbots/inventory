<?php
/**
 * delete_sale.php
 *
 * @package default
 */


require_once '../includes/load.php';
// Checkin What level user has permission to view this page
page_require_level(2);
?>
<?php
$d_sale = find_by_id('sales', (int)$_GET['id']);

if (!$d_sale) {
	$session->msg("d", "Missing sale id.");
	redirect('../sales/sales.php');
}

// Check if the associated product still exists before restoring stock
$product = find_by_id('products', $d_sale['product_id']);
if ($product) {
	// Product exists — restore stock
	increase_product_qty( $d_sale['qty'], $d_sale['product_id'] );
} else {
	// Product was deleted (CASCADE) — log and continue with sale deletion
	error_log("Sale #{$d_sale['id']} deleted but product #{$d_sale['product_id']} no longer exists. Stock not restored.");
}

$delete_id = delete_by_id('sales', (int)$d_sale['id']);

if ($delete_id) {
	$session->msg("s", "sale deleted.");
	redirect('../sales/sales.php');
} else {
	$session->msg("d", "sale deletion failed.");
	redirect('../sales/sales.php');
}
