<?php
/**
 * reports/stock_report.php
 *
 * @package default
 */


?>

<?php
/**
 * stock_report.php
 *
 * @package default
 */
$page_title = 'Stock Report';
require_once '../includes/load.php';
// Checkin What level user has permission to view this page
page_require_level(3);
$all_categories = find_all('categories');
if ( isset($_POST['update_category'] ) ) {
	$products = find_products_by_category((int)$_POST['product-category']);
} else {
	$products = join_product_table();
}

?>

<?php include_once '../layouts/header.php'; ?>
<div class="row">
  <div class="col-md-6">
    <?php echo display_msg($msg); ?>
  </div>
</div>

<div class="row">
  <div class="col-md-6">
    <div class="panel">
      <div class="jumbotron text-center">
      <h3>Stock Report</h3>
          <form class="clearfix" method="post" action="stock_report_process.php">
            <div class="form-group">
              <label class="form-label">Category</label>
                    <select class="form-control" name="product-category">
                      <option value="">All Categories</option>
                    <?php  foreach ($all_categories as $cat): ?>
                      <option value="<?php echo (int)$cat['id'] ?>">
                        <?php echo $cat['name'] ?></option>
                    <?php endforeach; ?>
                    </select>
             </div>
            <div class="form-group">
                 <button type="submit" name="submit" class="btn btn-primary">Generate Report</button>
            </div>
          </div>

          </form>
      </div>
    </div>
  </div>
</div>

<?php include_once '../layouts/footer.php'; ?>
