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
if (!verify_csrf()) { $session->msg('d', 'Invalid or missing security token.'); redirect($_SERVER['HTTP_REFERER'] ?? 'index.php', false); }

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
     <link rel="stylesheet" href="../libs/bootstrap/css/bootstrap.min.css"/>
   <link rel="stylesheet" href="../libs/css/print.css"/>
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
              <td class="text-center"><?php echo h($product['sku']);?></td>
              <td class="text-center"><?php echo ucfirst(h($product['name']));?></td>
              <td class="text-center"><?php echo h($product['category']);?></td>
              <td class="text-center"><?php echo h($product['quantity']);?></td>
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
