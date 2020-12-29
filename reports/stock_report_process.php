<?php
/**
 * stock_report_process.php
 *
 * @package default
 */


$page_title = 'Stock Report';
require_once '../includes/load.php';
// Checkin What level user has permission to view this page
page_require_level(3);

if (isset($_POST['submit'])) {
	$req = array('product-category');
	validate_fields($req);

	if (empty($errors)) {
		$products = find_products_by_category((int)$_POST['product-category']);
	} else {
		$products = join_product_table();
	}

} else {
	$session->msg("d", "Sorry no products have been found.");
	redirect('stock_report.php', false);
}
?>
<!doctype html>
<html lang="en-US">
 <head>
   <meta http-equiv="Content-Type" content="text/html; charset=utf-8">
   <title>Stock Report</title>
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
  <?php if ($products): ?>
    <div class="page-break">
       <div class="sale-head pull-right">
           <h1>Stock Report</h1>
       </div>
      <table class="table table-border">
        <thead>
          <tr>
              <th>Product SKU</th>
              <th>Product </th>
              <th>Category</th>
              <th>Quantity</th>
              <th>Cost Price</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($products as $product): ?>
           <tr>
              <td class="text-center"><?php echo $product['sku'];?></td>
              <td class="text-center"><?php echo ucfirst($product['name']);?></td>
              <td class="text-center"><?php echo $product['category'];?></td>
              <td class="text-center"><?php echo $product['quantity'];?></td>
              <td class="text-center"><?php echo formatcurrency($product['buy_price'], $CURRENCY_CODE);?></td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  <?php
else:
	$session->msg("d", "Sorry no products have been found. ");
redirect('stock_report.php', false);
endif;
?>
</body>
</html>
<?php if (isset($db)) { $db->db_disconnect(); } ?>
