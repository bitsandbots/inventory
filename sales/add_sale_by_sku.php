<?php
/**
 * add_sale_by_sku.php
 *
 * @package default
 */


$page_title = 'Add Sale by SKU';
require_once '../includes/load.php';
// Checkin What level user has permission to view this page
page_require_level(3);

$order_id = last_id('orders');
$o_id = $order_id['id'];

if (isset($_POST['add_sale'])) {
	$req_fields = array('s_id', 'quantity', 'price', 'total' );
	validate_fields($req_fields);
	if (empty($errors)) {
		$p_id      = $db->escape((int)$_POST['s_id']);
		$s_qty     = $db->escape((int)$_POST['quantity']);

		$product = find_by_id("products", $p_id);
		if ( (int)$product['quantity'] < $s_qty ) {
			$session->msg('d', ' Insufficient Quantity for Sale!');
			redirect('../sales/add_sale_by_sku.php', false);
		}
		$s_total   = $db->escape($_POST['total']);
		$s_date    = make_date();

		$sql  = "INSERT INTO sales (";
		$sql .= " product_id,order_id,qty,price,date";
		$sql .= ") VALUES (";
		$sql .= "'{$p_id}','{$o_id}','{$s_qty}','{$s_total}','{$s_date}'";
		$sql .= ")";

		if ($db->query($sql)) {
			decrease_product_qty($s_qty, $p_id);
			$session->msg('s', "Sale added. ");
			redirect('../sales/add_sale_by_sku.php', false);
		} else {
			$session->msg('d', ' Sorry failed to add!');
			redirect('../sales/add_sale_by_sku.php', false);
		}
	} else {
		$session->msg("d", $errors);
		redirect('../sales/add_sale_by_sku.php', false);
	}
}

?>
<?php include_once '../layouts/header.php'; ?>
<div class="row">
  <div class="col-md-6">
    <?php echo display_msg($msg); ?>
    <form method="post" action="../sales/ajax_sku.php" autocomplete="off" id="sug-sku-form">
        <div class="form-group">
          <div class="input-group">
            <span class="input-group-btn">
              <button type="submit" class="btn btn-primary">Search</button>
            </span>
            <input type="text" id="sug_sku_input" class="form-control" name="sku"  placeholder="Product SKU">
         </div>
         <div id="result" class="list-group"></div>
        </div>
    </form>
  </div>

  <div class="col-md-6">
    <div class="panel">
      <div class="jumbotron text-center">
<a href="../sales/sales_by_order.php?id=<?php echo $o_id;?>">
<h3>Order #<?php echo $o_id; ?></h3></a>

      </div>
    </div>
</div>


</div>
<div class="row">

  <div class="col-md-12">
    <div class="panel panel-default">
      <div class="panel-heading clearfix">
        <strong>
          <span class="glyphicon glyphicon-th"></span>
          <span>Add Sale</span>
       </strong>
      </div>
      <div class="panel-body">
        <form method="post" action="../sales/add_sale_by_sku.php">
         <table class="table table-bordered">
           <thead>
            <th class="text-center" style="width: 100px;">Product </th>
            <th class="text-center" style="width: 50px;"> SKU </th>
            <th class="text-center" style="width: 50px;"> Location </th>
            <th class="text-center" style="width: 15px;"> Available </th>
            <th class="text-center" style="width: 15px;"> Quantity </th>
            <th class="text-center" style="width: 50px;"> Price </th>
            <th class="text-center" style="width: 50px;"> Total </th>
            <th class="text-center" style="width: 50px;"> Action</th>
           </thead>
             <tbody  id="product_info"> </tbody>
         </table>
       </form>
      </div>
    </div>
  </div>

</div>

<?php include_once '../layouts/footer.php'; ?>
