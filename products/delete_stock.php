<?php
/**
 * delete_stock.php
 *
 * @package default
 */


require_once '../includes/load.php';
// Checkin What level user has permission to view this page
page_require_level(ROLE_SUPERVISOR);
if (!verify_get_csrf()) { $session->msg('d', 'Invalid or missing security token.'); redirect($_SERVER['HTTP_REFERER'] ?? 'index.php', false); }
?>
<?php
$d_stock = find_by_id('stock', (int)$_GET['id']);

if (!$d_stock) {
	$session->msg("d", "Missing stock id.");
	redirect('../products/stock.php');
}

// for each sale
// decrease inventory
if ( decrease_product_qty( $d_stock['quantity'], $d_stock['product_id']) ) {

	$delete_id = soft_delete_by_id('stock', (int)$d_stock['id']);
}

if ($delete_id) {
	$session->msg("s", "stock deleted.");
	redirect('../products/stock.php');
} else {
	$session->msg("d", "stock deletion failed.");
	redirect('../products/stock.php');
}

?>
