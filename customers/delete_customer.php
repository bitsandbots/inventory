<?php
/**
 * delete_customer.php
 *
 * @package default
 */


require_once '../includes/load.php';
// Checkin What level user has permission to view this page
page_require_level(2);
if (!verify_get_csrf()) { $session->msg('d', 'Invalid or missing security token.'); redirect($_SERVER['HTTP_REFERER'] ?? 'index.php', false); }
?>
<?php
$d_customer = find_by_id('customers', (int)$_GET['id']);

if (!$d_customer) {
	$session->msg("d", "Missing Customer ID.");
	redirect('../customers/customers.php');
}


$delete_id = soft_delete_by_id('customers', (int)$d_customer['id']);

if ($delete_id) {
	$session->msg("s", "Customer Deleted.");
	redirect('../customers/customers.php');
} else {
	$session->msg("d", "Customer Deletion Failed.");
	redirect('../customers/customers.php');
}

?>
