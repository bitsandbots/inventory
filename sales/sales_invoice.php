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
     <link rel="stylesheet" href="https://maxcdn.bootstrapcdn.com/bootstrap/3.3.4/css/bootstrap.min.css"/>
   <style>
   @media print {
     html,body{
        font-size: 9.5pt;
        margin: 0;
        padding: 0;
     }.page-break {
       page-break-before:always;
       width: auto;
       margin: auto;
      }
    }
    .page-break{
      width: 980px;
      margin: 0 auto;
    }
     .sale-head{
       margin: 40px 0;
       text-align: center;
     }.sale-head h1,.sale-head strong{
       padding: 10px 20px;
       display: block;
     }.sale-head h1{
       margin: 0;
       border-bottom: 1px solid #212121;
     }.table>thead:first-child>tr:first-child>th{
       border-top: 1px solid #000;
      }
      table thead tr th {
       text-align: center;
       border: 1px solid #ededed;
     }table tbody tr td{
       vertical-align: middle;
     }.sale-head,table.table thead tr th,table tbody tr td,table tfoot tr td{
       border: 1px solid #212121;
       white-space: nowrap;
     }.sale-head h1,table thead tr th,table tfoot tr td{
       background-color: #f8f8f8;
     }tfoot{
       color:#000;
       text-transform: uppercase;
       font-weight: 500;
     }
   </style>
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
echo ucfirst($order['customer']);
echo "</h1>";
echo $customer['address'];
echo "<br>";
echo $customer['city'];
echo "&nbsp;&nbsp;";
echo $customer['region'];
echo "&nbsp;&nbsp;";
echo $customer['postcode'];
echo "<br>";

echo "&nbsp;&nbsp;";
echo $customer['telephone']; echo "&nbsp; | &nbsp;"; echo $customer['email'];
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
              <td class="text-center"><?php echo ucfirst($sale['name']);?></td>
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
