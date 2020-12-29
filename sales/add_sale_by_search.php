<?php
/**
 * add_sale_by_search.php
 *
 * @package default
 */


$page_title = 'Add Sale by Search';
require_once '../includes/load.php';
// Checkin What level user has permission to view this page
page_require_level(3);
$order_id = 0;

if (isset($_GET['id'])) {
	$order_id = (int)$_GET['id'];
} else {
	$session->msg("d", "Missing order id.");
	redirect( ( '../sales/sales_by_order.php?id=' . $order_id ) , false);
}



if (isset($_POST['add_sale'])) {
	$req_fields = array('s_id', 'order_id', 'quantity', 'sale_price');
	validate_fields($req_fields);
	if (empty($errors)) {
		$p_id      = $db->escape((int)$_POST['s_id']);
		$o_id      = $db->escape((int)$_POST['order_id']);
		$s_qty     = $db->escape((int)$_POST['quantity']);

		$product = find_by_id("products", $p_id);
		if ( (int)$product['quantity'] < $s_qty ) {
			$session->msg('d', ' Insufficient Quantity for Sale!');
			redirect( ( 'sales_by_order.php?id=' . $order_id ) , false);
		}

		$s_price      = $db->escape($_POST['sale_price']);
		$s_total   = $s_qty * $s_price;

		$date    = make_date();

		$sql  = "INSERT INTO sales (";
		$sql .= " product_id,order_id,qty,price,date";
		$sql .= ") VALUES (";
		//$sql .= "'{$p_id}','{$s_qty}','{$s_total}','{$s_date}'";
		$sql .= "'{$p_id}','{$o_id}','{$s_qty}','{$s_total}','{$date}'";
		$sql .= ")";

		if ($db->query($sql)) {
			decrease_product_qty($s_qty, $p_id);
			$session->msg('s', "Sale added. ");
			redirect( ( '../sales/sales_by_order.php?id=' . $order_id ) , false);
		} else {
			$session->msg('d', ' Sorry failed to add!');
			redirect( ( '../sales/sales_by_order.php?id=' . $order_id ) , false);
		}
	} else {
		$session->msg("d", $errors);
		redirect( ( '../sales/sales_by_order.php?id=' . $order_id ) , false);
	}
}

$all_categories = find_all('categories');


?>

<?php include_once '../layouts/header.php'; ?>
<div class="row">
  <div class="col-md-6">
    <?php echo display_msg($msg); ?>
    <form method="post" action="../sales/add_sale_by_search.php?id=<?php echo $order_id; ?>">
        <div class="form-group">
          <div class="input-group">
            <span class="input-group-btn">
              <button type="submit" class="btn btn-primary">Search</button>
            </span>
            <input type="text" class="form-control" name="search"  placeholder="Product Name / SKU / Description">
         </div>
        </div>
    </form>
  </div>

  <div class="col-md-6">
    <div class="panel">
      <div class="jumbotron text-center">
<a href="../sales/sales_by_order.php?id=<?php echo $order_id;?>">
<h3>Order #<?php echo $order_id; ?></h3></a>

      </div>
    </div>
</div>

<div class="row">
  <div class="col-md-12">
    <div class="panel panel-default">
      <div class="panel-heading clearfix">
        <strong>
          <span class="glyphicon glyphicon-th"></span>
          <span>Add Sales to Order #<?php echo $order_id; ?></span>
       </strong>
          <div class="pull-right">
            <a href="../sales/add_sale_to_order.php?id=<?php echo $order_id; ?>" class="btn btn-success">Add Sales by Category</a>
          </div>
      </div>
      <div class="panel-body">
         <table class="table table-bordered">
           <thead>
            <th class="text-center" style="width: 15px;"> Product </th>
            <th class="text-center" style="width: 50px;"> Photo </th>
            <th class="text-center" style="width: 15px;"> SKU </th>
            <th class="text-center" style="width: 50px;"> Location </th>
            <th class="text-center" style="width: 15px;"> Available </th>
            <th class="text-center" style="width: 15px;"> Quantity </th>
            <th class="text-center" style="width: 50px;"> Price </th>
            <th class="text-center" style="width: 50px;"> Action</th>
           </thead>

<?php

$sales = find_sales_by_order_id( $order_id );

// find all product
if (isset($_POST['search']) && strlen($_POST['search'])) {
	$product_search = remove_junk($db->escape($_POST['search']));
	$products_available = find_all_product_info_by_search($product_search);

	foreach ( $products_available as $product ) {
		$added_to_order = false;
		foreach ( $sales as $sale ) {
			if ( $product['name'] == $sale['name'] ) { // already added to order
				$added_to_order = true;
			}
		}

		if ( $added_to_order == false ) {

?>
        <form method="post" action="../sales/add_sale_by_search.php?id=<?php echo $order_id; ?>">

<tr>
<td id="s_name">
<?php echo $product['name'];?>
</td>
                <td>
                  <?php if ($product['media_id'] === '0'): ?>
                    <img class="img-avatar img-circle" src="../uploads/products/no_image.jpg" alt="">
                  <?php else: ?>
                  <img class="img-avatar img-circle" src="../uploads/products/<?php echo $product['image']; ?>" alt="">
                <?php endif; ?>
                </td>
<td class="text-center">
<?php echo $product['sku']; ?>
</td>
<td class="text-center">
<?php echo $product['location']; ?>
</td>
<input type="hidden" name="s_id" value="<?php echo $product['id']; ?>">
<input type="hidden" name="order_id" value="<?php echo $order_id; ?>">
<input type="hidden" class="form-control" name="sale_price" value="<?php echo $product['sale_price']; ?>">

<td class="text-center">
<?php echo $product['quantity']; ?>
</td>
<td id="s_qty">

                   <div class="input-group">
                     <span class="input-group-addon">
                      <i class="glyphicon glyphicon-shopping-cart"></i>
                     </span>
                     <input type="number" class="form-control" name="quantity" placeholder="Product Quantity">
                  </div>
                 </div>

</td>
<td id="s_price">
<?php echo formatcurrency( $product['sale_price'], $CURRENCY_CODE); ?>
</td>
<td>
<button type="submit" name="add_sale" class="btn btn-primary">
Add Sale
</button>
</td>
</tr>
       </form>
<?php
		}
	}
}

?>



		     </tbody>
         </table>
      </div>
    </div>

  </div>
</div>

<?php include_once '../layouts/footer.php'; ?>
