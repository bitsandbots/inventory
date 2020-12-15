<?php
/**
 * edit_sale.php
 *
 * @package default
 */


$page_title = 'Edit sale';
require_once '../includes/load.php';
// Checkin What level user has permission to view this page
page_require_level(3);
?>


<?php
$sale = find_by_id('sales', (int)$_GET['id']);
if (!$sale) {
	$session->msg("d", "Missing product id.");
	redirect('../sales/sales.php');
}
?>

<?php $product = find_by_id('products', $sale['product_id']); ?>
<?php $order = find_by_id('orders', $sale['order_id']); ?>

<?php

if (isset($_POST['update_sale'])) {
	$req_fields = array('title', 'order_id', 'quantity', 'price', 'total', 'date' );
	validate_fields($req_fields);
	if (empty($errors)) {
		$o_id      = $db->escape((int)$_POST['order_id']);
		$p_id      = $db->escape((int)$product['id']);
		$quantity     = $db->escape((int)$_POST['quantity']);

		$s_qty_diff = 0;
		if ( $quantity != $sale['qty'] ) {
			// there has been a change in quantity
			if ( $quantity > $sale['qty'] ) {
				// increase in quantity sold & check for availability in stock
				// difference between previous quantity and new value
				$s_qty_diff = $quantity - $sale['qty'];
				//check for availability in stock
				if ( (int)$product['quantity'] < $s_qty_diff ) {
					$session->msg('d', ' Insufficient Quantity for Sale!');
					redirect('../sales/add_sale.php', false);
				} else {
					$decrease_quantity_flag = true;
				}
			}
			// decrease - increase in sold stock
			else if ( $quantity < $sale['qty'] ) {
				// difference between previous quantity and new value
				$s_qty_diff = $sale['qty'] - $quantity;
				$decrease_quantity_flag = false;
			}
		}

		$s_total   = $db->escape($_POST['total']);
		$date      = $db->escape($_POST['date']);
		$s_date    = date("Y-m-d", strtotime($date));

		$sql  = "UPDATE sales SET";
		$sql .= " order_id= '{$o_id}', product_id= '{$p_id}',qty={$quantity},price='{$s_total}',date='{$s_date}'";
		$sql .= " WHERE id ='{$sale['id']}'";
		$result = $db->query($sql);

		if ( $result && $db->affected_rows() === 1) {
			if ( $s_qty_diff > 0 ) {
				if ( $decrease_quantity_flag ) {
					decrease_product_qty($s_qty_diff, $p_id);
				} else {
					increase_product_qty($s_qty_diff, $p_id);
				}
			}

			$session->msg('s', "Sale updated.");
			redirect('../sales/edit_sale.php?id='.$sale['id'], false);
		} else {
			$session->msg('d', ' Sorry failed to updated!');
			redirect('../sales/sales.php', false);
		}
	} else {
		$session->msg("d", $errors);
		redirect('../sales/edit_sale.php?id='.(int)$sale['id'], false);
	}
}

?>
<?php include_once '../layouts/header.php'; ?>

<div class="row">
  <div class="col-md-6">
    <?php echo display_msg($msg); ?>
  </div>
</div>
<div class="row">

  <div class="col-md-12">
  <div class="panel">
    <div class="panel-heading clearfix">
      <strong>
        <span class="glyphicon glyphicon-th"></span>
<!--     *************************     -->
        <span>Edit Sale</span>
<!--     *************************     -->
     </strong>
     <div class="pull-right">
<!--     *************************     -->
       <a href="../sales/sales.php" class="btn btn-primary">Show All Sales</a>
<!--     *************************     -->
     </div>
    </div>
    <div class="panel-body">
<!--     *************************     -->
       <table class="table table-bordered">
         <thead>
          <th> Order # </th>
          <th> Product </th>
          <th> Qty </th>
          <th> Price </th>
          <th> Total </th>
          <th> Date</th>
          <th> Action</th>
         </thead>
           <tbody  id="product_info">
              <tr>
              <form method="post" action="../sales/edit_sale.php?id=<?php echo (int)$sale['id']; ?>">

                <td>
                  <input type="text" class="form-control" name="order_id" value="<?php echo $order['id']; ?>">
                </td>


                <td id="s_name">
                  <input type="text" class="form-control" id="sug_input" name="title" value="<?php echo $product['name']; ?>">

                  <div id="result" class="list-group"></div>

                </td>
                <td id="s_qty">
                  <input type="text" class="form-control" name="quantity" value="<?php echo (int)$sale['qty']; ?>">
                </td>
                <td id="s_price">
                  <input type="text" class="form-control" name="price" value="<?php echo $product['sale_price']; ?>" >
                </td>
                <td>
                  <input type="text" class="form-control" name="total" value="<?php echo $sale['price']; ?>">
                </td>
                <td id="s_date">
                  <input type="date" class="form-control datepicker" name="date" data-date-format="" value="<?php echo $sale['date']; ?>">
                </td>
                <td>
                  <button type="submit" name="update_sale" class="btn btn-primary">Update Sale</button>
                </td>
              </form>
              </tr>
           </tbody>
       </table>
<!--     *************************     -->

    </div>
  </div>
  </div>
</div>

<?php include_once '../layouts/footer.php'; ?>
