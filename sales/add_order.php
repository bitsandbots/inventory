<?php
/**
 * add_order.php
 *
 * @package default
 */


$page_title = 'Add Order';
require_once '../includes/load.php';
// Checkin What level user has permission to view this page
page_require_level(ROLE_ADMIN);

?>
<?php
if (!verify_csrf()) { $session->msg('d', 'Invalid or missing security token.'); redirect($_SERVER['HTTP_REFERER'] ?? 'index.php', false); }
  if (isset($_POST['add_order'])) {
$req_fields = array('customer-name', 'paymethod' );
	validate_fields($req_fields);
	$customer_name = $db->escape($_POST['customer-name']);
	$paymethod = $db->escape($_POST['paymethod']);
	$notes = '';
	if (isset($_POST['notes'])) {
$notes = $db->escape($_POST['notes']); }
	$c_address = "";
	$c_city = "";
	$c_region = "";
	$c_postcode = "";
	$c_telephone = "";
	$c_email = "";

	if (empty($errors)) {

		if ( ! find_by_name('customers', $customer_name) ) {
			$query  = "INSERT INTO customers (";
			$query .=" name,address,city,region,postcode,telephone,email,paymethod,org_id";
			$query .=") VALUES (";
			$query .=" '{$customer_name}', '{$c_address}','{$c_city}', '{$c_region}', '{$c_postcode}', '{$c_telephone}', '{$c_email}', '{$paymethod}', '" . current_org_id() . "'";
			$query .=")";
			$result = $db->query($query);
			if ($result && $db->affected_rows() === 1) {
				$session->msg('s', "Customer Added! ");
			} else {
				$session->msg('d', ' Sorry, Failed to Add!');
			}
		}

		$current_date    = make_date();
		$sql  = "INSERT INTO orders (customer,paymethod,notes,date,org_id)";
		$sql .= " VALUES ('{$customer_name}','{$paymethod}','{$notes}','{$current_date}','" . current_org_id() . "')";
		if ($db->query($sql)) {
			$new_order_id = $db->insert_id();
			$session->msg("s", "Successfully Added Order");
			redirect( ( '../sales/add_sale_by_search.php?id=' . $new_order_id ) , false);
		} else {
			$session->msg("d", "Sorry, Failed to Add Order!");
			redirect( '../sales/add_order.php' , false);
		}
	} else {
		$session->msg("d", $errors);
		redirect( '../sales/add_order.php' , false);
	}
}
?>

<?php include_once '../layouts/header.php'; ?>

<div class="login-page">
    <div class="text-center">
<!--     *************************     -->
       <h2>Add Order</h3>
<!--     *************************     -->
     </div>
     <?php echo display_msg($msg); ?>

      <form method="post" action="" class="clearfix">
              <?php echo csrf_field(); ?>
<!--     *************************     -->
        <div class="form-group">
        </div>

        <div class="form-group">
              <label for="name" class="control-label">Customer Name</label>
              <input type="text" class="form-control" name="customer-name" value="" placeholder="Customer">
        </div>

           <div class="form-group">
              <label for="paymethod" class="control-label">Pay Method</label>

                    <select class="form-control" name="paymethod">
                      <option value="">Select Payment Method</option>
                      <option value="Cash">Cash</option>
                      <option value="Check">Check</option>
                      <option value="Credit">Credit</option>
                      <option value="Charge">Charge to Account</option>
                    </select>
           </div>

           <div class="form-group">
              <label for="notes" class="control-label">Notes</label>
               <input type="text" class="form-control" name="notes" value="" placeholder="Notes">
           </div>

<!--     *************************     -->
        <div class="form-group clearfix">
         <div class="pull-right">
                <button type="submit" name="add_order" class="btn btn-info">Start Order</button>
        </div>
        </div>
    </form>
</div>

<?php include_once '../layouts/footer.php'; ?>
