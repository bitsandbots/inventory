<?php
/**
 * view_product.php
 *
 * @package default
 */


$page_title = 'All Product';
require_once '../includes/load.php';
// Checkin What level user has permission to view this page
page_require_level(2);

if ( isset( $_GET['id'] ) ) {
	$product = find_by_id('products', (int)$_GET['id']);
	$all_categories = find_all('categories');
	$all_photo = find_all('media');
	if ( ! $product ) {
		$session->msg("d", "Missing product id.");
		redirect('../products/products.php');
	}
} else {
	$session->msg("d", "Missing product id.");
	redirect('../products/products.php');
}


?>

<?php include_once '../layouts/header.php'; ?>
<div class="row">
  <div class="col-md-6">
    <?php echo display_msg($msg); ?>
</div>
</div>

  <div class="row">
    <div class="col-md-12">
      <div class="panel panel-default">

        <div class="panel-heading clearfix">
         <strong>
          <span class="glyphicon glyphicon-th"></span>
          <span>Product Detail</span></strong>
		</div>

        <div class="panel-body">

  <div class="row">
    <div class="col-md-1">
    </div>
    <div class="col-md-6">
<h4><?php echo first_character($product['name']);?></h4>

<div><label>Description:</label></div>
<div><?php echo $product['description'];?></div>

    </div>



    <div class="col-md-1">
    </div>
    <div class="col-md-4">
<?php
foreach ($all_photo as $photo) {
	if ( $product['media_id'] == $photo['id'] ) {
?>
	<img class="img-thumbnail" src="../uploads/products/<?php echo $photo['file_name']; ?>" alt="">
<?php
	}
}
?>
    </div>
</div>



  <div class="row">
    <div class="col-md-1">
	</div>
    <div class="col-md-10">
<div class="text-center"><label></label></div>
    </div>
</div>



  <div class="row">
    <div class="col-md-1">
	</div>
    <div class="col-md-10">

        <div class="panel-body">

          <table class="table table-bordered">
            <thead>
              <tr>
<!--     *************************     -->
                <th class="text-center" style="width: 10%;"> Category </th>
                <th class="text-center" style="width: 10%;"> Location </th>
                <th class="text-center" style="width: 10%;"> SKU </th>
                <th class="text-center" style="width: 10%;"> Stock </th>
                <th class="text-center" style="width: 15%;"> Cost Price </th>
                <th class="text-center" style="width: 15%;"> Sale Price </th>
                <th class="text-center" style="width: 15%;"> Product Added </th>
                <th class="text-center" style="width: 50px;"> Actions </th>
              </tr>
<!--     *************************     -->
            </thead>
            <tbody>
<!--     *************************     -->

              <tr>
<?php
foreach ($all_categories as $category ) {
	if ( $product['category_id'] == $category['id'] ) {
		break;
	}
}
?>
 			    <td class="text-center"> <?php echo $category['name']; ?></td>
                <td class="text-center"> <?php echo $product['location']; ?></td>
                <td class="text-center"> <?php echo $product['sku']; ?></td>
                <td class="text-center"> <?php echo $product['quantity']; ?></td>
                <td class="text-center"> <?php echo formatcurrency( $product['buy_price'], $CURRENCY_CODE); ?></td>
                <td class="text-center"> <?php echo formatcurrency( $product['sale_price'], $CURRENCY_CODE); ?></td>
                <td class="text-center"> <?php echo read_date($product['date']); ?></td>
<!--     *************************     -->
                <td class="text-center">
                  <div class="btn-group">
					<a href="../products/add_stock.php?id=<?php echo (int)$product['id'];?>"  class="btn btn-xs btn-warning" data-toggle="tooltip" title="Add">
					  <span class="glyphicon glyphicon-th-large"></span>
					</a>
                    <a href="../products/edit_product.php?id=<?php echo (int)$product['id'];?>" class="btn btn-info btn-xs"  title="Edit" data-toggle="tooltip">
                      <span class="glyphicon glyphicon-edit"></span>
                    </a>
                    <a href="../products/delete_product.php?id=<?php echo (int)$product['id'];?>" onClick="return confirm('Are you sure you want to delete?')" class="btn btn-danger btn-xs"  title="Delete" data-toggle="tooltip">
                      <span class="glyphicon glyphicon-trash"></span>
                    </a>
                  </div>
                </td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>


    </div>
  </div>

<?php
// print "<pre>";
// print_r($product);
// print "</pre>\n";
?>

    </div>
  </div>
    </div>
  </div>

  <?php include_once '../layouts/footer.php'; ?>
