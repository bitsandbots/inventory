<?php
/**
 * delete_customer.php
 *
 * @package default
 */


require_once '../includes/load.php';
// Checkin What level user has permission to view this page
page_require_level(2);
?>
<?php
$d_customer = find_by_id('customers', (int)$_GET['id']);

if (!$d_customer) {
	$session->msg("d", "Missing Customer ID.");
	redirect('../customers/customers.php');
}


$delete_id = delete_by_id('customers', (int)$d_customer['id']);

if ($delete_id) {
	$session->msg("s", "Customer Deleted.");
	redirect('../customers/customers.php');
} else {
	$session->msg("d", "Customer Deletion Failed.");
	redirect('../customers/customers.php');
}

?>
