<?php
/**
 * sales_invoice.php
 *
 * @package default
 */


$page_title = 'Sales Invoice';
require_once '../includes/load.php';
// Checkin What level user has permission to view this page
page_require_level(3);
$order_id  = 0;
$order_total = 0;

if (isset($_GET['id'])) {
	$order_id = (int) $_GET['id'];
} else {
	$session->msg("d", "Missing order id.");
}

$sales = find_sales_by_order_id( $order_id );
$order = find_by_id("orders", $order_id);
$customer = find_by_name('customers', $order['customer']);
$products_available = join_product_table();

?>
<!doctype html>
<html lang="en-US">
 <head>
   <meta http-equiv="Content-Type" content="text/html; charset=utf-8">
   <title>Sales Invoice</title>
     <link rel="stylesheet" href="../libs/bootstrap/css/bootstrap.min.css"/>
   <link rel="stylesheet" href="../libs/css/print.css"/>
</head>
<body>
  <?php if ($sales): ?>
    <div class="page-break">
       <div class="sale-head pull-right">
           <h1>Invoice #<?php echo ucfirst($order['id']);?></h1>
           <strong><?php echo $order['date'];?> </strong>
       </div>
       <div class="sale-head pull-left">
       <?php
echo "<h1>";
echo h(ucfirst($order['customer']));
echo "</h1>";
echo h($customer['address']);
echo "<br>";
echo h($customer['city']);
echo "&nbsp;&nbsp;";
echo h($customer['region']);
echo "&nbsp;&nbsp;";
echo h($customer['postcode']);
echo "<br>";

echo "&nbsp;&nbsp;";
echo h($customer['telephone']); echo "&nbsp; | &nbsp;"; echo h($customer['email']);
echo "&nbsp;&nbsp;";
?>
       </div>
      <table class="table table-border">
        <thead>
          <tr>
              <th>Quantity</th>
              <th>Product</th>
              <th>Price</th>
              <th>Total</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($sales as $sale): ?>
           <tr>
              <td class="text-center"><?php echo $sale['qty'];?></td>
              <td class="text-center"><?php echo h(ucfirst($sale['name']));?></td>
              <td class="text-center">
               <?php
foreach ( $products_available as $product ) {
	if ( $product['name'] == $sale['name'] ) {
		echo formatcurrency( $product['sale_price'], $CURRENCY_CODE);
	}
}
?>
              </td>
              <td class="text-center"><?php echo formatcurrency( $sale['price'], $CURRENCY_CODE); ?></td>
<?php
$order_total = $order_total + $sale['price'];
?>
          </tr>
        <?php endforeach; ?>
        </tbody>
        <tfoot>
         <tr class="text-right">
           <td colspan="2"></td>
           <td colspan="1">Grand Total</td>
               <td class="text-center"><?php echo formatcurrency($order_total, $CURRENCY_CODE); ?></td>
        </tfoot>
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
