<?php
/**
 * add_stock.php
 *
 * @package default
 */


$page_title = 'All stock';
require_once '../includes/load.php';
// Checkin What level user has permission to view this page
page_require_level(1);

$selected_product = 0;
if ( isset($_GET['id'] ) ) {
	$selected_product = (int)$_GET['id'];
}


$all_stock = find_all('stock');
$all_products = find_all('products');

?>

<!--     *************************     -->

<?php
if (isset($_POST['add_stock'])) {
	$req_field = array('product_id', 'quantity');
	validate_fields($req_field);
	$product_id = remove_junk($db->escape($_POST['product_id']));
	$quantity = remove_junk($db->escape($_POST['quantity']));
	$comments = remove_junk($db->escape($_POST['comments']));
	$current_date    = make_date();
	if (empty($errors)) {
		$sql  = "INSERT INTO stock (product_id,quantity,comments,date)";
		$sql .= " VALUES ('{$product_id}','{$quantity}','{$comments}','{$current_date}')";
		$result = $db->query($sql);
		if ( $result && $db->affected_rows() === 1) {
			increase_product_qty($quantity, $product_id);
			$session->msg("s", "Successfully Added");
			redirect( ( '../products/stock.php' ) , false);
		} else {
			$session->msg("d", "Sorry Failed to insert.");
			redirect( '../products/add_stock.php' , false);
		}
	} else {
		$session->msg("d", $errors);
		redirect( '../products/add_stock.php' , false);
	}
}

/**
 * print "<pre>";
 * print_r($all_stock);
 * print "</pre>\n";
 *
 */
?>

<!--     *************************     -->

<?php include_once '../layouts/header.php'; ?>


<div class="login-page">
    <div class="text-center">
<!--     *************************     -->
       <h2>Add Stock</h3>
<!--     *************************     -->
     </div>
        <div class="form-group">
     <?php echo display_msg($msg); ?>
        </div>

      <form method="post" action="" class="clearfix">
        <div class="form-group">
<!--
<label for="name" class="control-label">Product</label>
-->
<select class="form-control" name="product_id">
<option value="0">Select Product</option>
<?php
foreach ( $all_products as $product ) {
	if ( $selected_product == $product['id'] ) {
		echo "<option value=\"" . $product['id'] . "\" selected>" . $product['name'] . "</option>";
	} else {
		echo "<option value=\"" . $product['id'] . "\">" . $product['name'] . "</option>";
	}
}
?>
</select>
           </div>

           <div class="form-group">
		   <div class="input-group">
			 <span class="input-group-addon">
			  <i class="glyphicon glyphicon-shopping-cart"></i>
			 </span>
			 <input type="number" class="form-control" name="quantity" placeholder="Product Quantity">
		  </div>
           </div>

           <div class="form-group">
               <input type="text" class="form-control" name="comments" value="" placeholder="Comments">
           </div>

<!--     *************************     -->
        <div class="form-group clearfix">
         <div class="pull-right">
                <button type="submit" name="add_stock" class="btn btn-info">Add</button>
        </div>
        </div>
    </form>
</div>

<?php include_once '../layouts/footer.php'; ?>
