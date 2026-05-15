<?php
/**
 * order_picklist.php
 *
 * @package default
 */


$page_title = 'Order Picklist';

require_once '../includes/load.php';
// Checkin What level user has permission to view this page
page_require_level(3);
$order_id  = 0;

if (isset($_GET['id'])) {
	$order_id = (int) $_GET['id'];
} else {
	$session->msg("d", "Missing order id.");
}

$sales = find_sales_by_order_id( $order_id );
$order = find_by_id("orders", $order_id);

$products_available = join_product_table();
?>

<!doctype html>
<html lang="en-US">
 <head>
   <meta http-equiv="Content-Type" content="text/html; charset=utf-8">
   <title>Order Picklist</title>
     <link rel="stylesheet" href="../libs/bootstrap/css/bootstrap.min.css"/>
   <link rel="stylesheet" href="../libs/css/print.css"/>
</head>
<body>
  <?php if ($sales): ?>
    <div class="page-break">
       <div class="sale-head pull-right">
           <h1>Order #<?php echo ucfirst($order['id']);?></h1>
           <strong><?php echo $order['date'];?> </strong>
       </div>
       <div class="sale-head pull-left">
           <h1><?php echo h(ucfirst($order['customer']));?> </h1>
       </div>

      <table class="table table-border">
        <thead>
          <tr>
              <th>Product SKU</th>
              <th>Product Title</th>
              <th>Product Location</th>
              <th>Stock Available</th>
              <th>Quantity Ordered</th>
              <th>Available</th>
              <th>Picked</th>
              <th>Dispatched</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($sales as $sale): ?>
           <tr>
              <td class="text-center">
              <?php
	foreach ( $products_available as $product ) {
		if ( $product['name'] == $sale['name'] ) {
			echo h($product['sku']);
		}
	}
?>
              </td>
              <td class="text-center"><?php echo h(ucfirst($sale['name']));?></td>
              <td class="text-center"><?php echo h($sale['location']);?></td>
              <td class="text-center">
              <?php
foreach ( $products_available as $product ) {
	if ( $product['name'] == $sale['name'] ) {
		echo $product['quantity'] + $sale['qty'];
	}
}
?>
              </td>
              <td class="text-center"><?php echo $sale['qty'];?></td>
              <td class="text-center"></td>
              <td class="text-center"></td>
              <td class="text-center"></td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  <?php
else:
	$session->msg("d", "Sorry no sales has been found. ");
redirect('../sales/orders.php', false);
endif;
?>
</body>
</html>
<?php if (isset($db)) { $db->db_disconnect(); } ?>
