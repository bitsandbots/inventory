<?php
/**
 * edit_stock.php
 *
 * @package default
 */


$page_title = 'Edit category';
require_once '../includes/load.php';
// Checkin What level user has permission to view this page
page_require_level(1);
?>
<?php
//Display all catgories.
$stock = find_by_id('stock', (int)$_GET['id']);
$product = find_by_id('products', (int)$stock['product_id']);

if (!$stock) {
	$session->msg("d", "Missing order id.");
	redirect('../products/stock.php');
}
?>

<?php
if (isset($_POST['edit_stock'])) {
	$req_field = array('product_id', 'quantity');
	validate_fields($req_field);
	$product_id = remove_junk($db->escape($_POST['product_id']));
	$quantity = remove_junk($db->escape($_POST['quantity']));

	// check if the quantity has changed
	$s_qty_diff = 0;
	if ( $quantity != $stock['quantity'] ) {
		// there has been an increase in quantity
		if ( $quantity > $stock['quantity'] ) {
			// difference between previous quantity and new value
			$s_qty_diff = $quantity - $stock['quantity'];
			$decrease_quantity_flag = false;
		}
		// there has been a decrease in quantity
		else if ( $quantity < $stock['quantity'] ) {
			// difference between previous quantity and new value
			$s_qty_diff = $stock['quantity'] - $quantity;
			$decrease_quantity_flag = true;
		}
	}

	$comments = remove_junk($db->escape($_POST['comments']));
	$date = remove_junk($db->escape($_POST['date']));
	$current_date    = make_date();

	if (empty($errors)) {
		$sql = "UPDATE stock SET";
		$sql .= " product_id='{$product_id}', quantity='{$quantity}', comments='{$comments}', date='{$current_date}'";
		$sql .= " WHERE id='{$stock['id']}'";

		$result = $db->query($sql);
		if ($result && $db->affected_rows() === 1) {
			if ( $s_qty_diff > 0 ) {
				if ( $decrease_quantity_flag ) {
					decrease_product_qty($s_qty_diff, $product_id);
				} else {
					increase_product_qty($s_qty_diff, $product_id);
				}
			}
			$session->msg("s", "Successfully updated");
			redirect('../products/stock.php', false);
		} else {
			$session->msg("d", "Sorry! Failed");
			redirect('../products/edit_stock.php', false);
		}

	} else {
		$session->msg("d", $errors);
		redirect('../products/edit_stock.php', false);
	}
}
?>
<?php include_once '../layouts/header.php'; ?>

<div class="row">
   <div class="col-md-12">
     <?php echo display_msg($msg); ?>
   </div>
   <div class="col-md-5">
     <div class="panel panel-default">
       <div class="panel-heading">
         <strong>
           <span class="glyphicon glyphicon-th"></span>
           <span>Editing <?php echo $stock['product_id'];?></span>
        </strong>
       </div>
       <div class="panel-body">
         <form method="post" action="">

           <div class="form-group">
              <label for="name" class="control-label"><?php echo $product['name'];?></label>
			 <input type="hidden" class="form-control" name="product_id" value="<?php echo $stock['product_id'] ;?>">
           </div>

           <div class="form-group">
		   <div class="input-group">
			 <span class="input-group-addon">
			  <i class="glyphicon glyphicon-shopping-cart"></i>
			 </span>
			 <input type="number" class="form-control" name="quantity" value="<?php echo $stock['quantity'] ;?>" placeholder="Product Quantity">
		  </div>
           </div>

           <div class="form-group">
               <input type="text" class="form-control" name="comments" value="<?php echo $stock['comments'];?>" placeholder="Notes">
           </div>

           <button type="submit" name="edit_stock" class="btn btn-primary">Update Inventory</button>
       </form>
       </div>
     </div>
 </div>
</div>

<?php include_once '../layouts/footer.php'; ?>
