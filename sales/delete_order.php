<?php
/**
 * delete_order.php
 *
 * @package default
 */


require_once '../includes/load.php';
// Checkin What level user has permission to view this page
page_require_level(2);
?>
<?php
$d_order = find_by_id('orders', (int)$_GET['id']);

if (!$d_order) {
	$session->msg("d", "Missing order id.");
	redirect('../sales/orders.php');
}

$sales = find_sales_by_order_id( $d_order['id'] );

// for each sale
foreach ( $sales as $sale ) {
	if ( delete_by_id('sales', (int)$sale['id']) ) {
		increase_product_qty( $sale['qty'], $sale['product_id'] );
	}
}

$delete_id = delete_by_id('orders', (int)$d_order['id']);

if ($delete_id) {
	$session->msg("s", "order deleted.");
	redirect('../sales/orders.php');
} else {
	$session->msg("d", "order deletion failed.");
	redirect('../sales/orders.php');
}

?>
