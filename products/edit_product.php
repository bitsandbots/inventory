<?php
/**
 * edit_product.php
 *
 * @package default
 */


$page_title = 'Edit product';
require_once '../includes/load.php';
// Checkin What level user has permission to view this page
page_require_level(2);
?>
<?php
$product = find_by_id('products', (int)$_GET['id']);
$all_categories = find_all('categories');
$all_photo = find_all('media');
if (!$product) {
	$session->msg("d", "Missing product id.");
	redirect('../products/products.php');
}
?>
<?php
if (isset($_POST['product'])) {
	$req_fields = array('product-title', 'product-category', 'product-quantity', 'cost-price', 'sale-price' );
	validate_fields($req_fields);

	if (empty($errors)) {
		if (is_null($_POST['product-sku']) || $_POST['product-sku'] === "") {
			$p_sku  =  '';
		} else {
			$p_sku  = remove_junk($db->escape($_POST['product-sku']));
		}

		$p_name  = remove_junk($db->escape($_POST['product-title']));

		if (is_null($_POST['product-desc']) || $_POST['product-desc'] === "") {
			$p_desc  =  'none';
		} else {
			$p_desc  = remove_junk($db->escape($_POST['product-desc']));
		}

		if (is_null($_POST['product-location']) || $_POST['product-location'] === "") {
			$p_loc  =  'NA';
		} else {
			$p_loc  = remove_junk($db->escape($_POST['product-location']));
		}

		$p_cat   = (int)$_POST['product-category'];
		$p_qty   = remove_junk($db->escape($_POST['product-quantity']));
		$p_buy   = remove_junk($db->escape($_POST['cost-price']));
		$p_sale  = remove_junk($db->escape($_POST['sale-price']));
		if (is_null($_POST['product-photo']) || $_POST['product-photo'] === "") {
			$media_id = '0';
		} else {
			$media_id = remove_junk($db->escape($_POST['product-photo']));
		}
		$query   = "UPDATE products SET";
		$query  .=" name ='{$p_name}', description ='{$p_desc}', sku ='{$p_sku}',location ='{$p_loc}', quantity ='{$p_qty}',";
		$query  .=" buy_price ='{$p_buy}',sale_price ='{$p_sale}',category_id ='{$p_cat}',media_id ='{$media_id}'";
		$query  .=" WHERE id ='{$product['id']}'";
		$result = $db->query($query);
		if ($result && $db->affected_rows() === 1) {
			$session->msg('s', "Product Updated ");
			redirect('../products/products.php', false);
		} else {
			$session->msg('d', ' Sorry Failed to Update!');
			redirect('../products/edit_product.php?id='.$product['id'], false);
		}

	} else {
		$session->msg("d", $errors);
		redirect('../products/edit_product.php?id='.$product['id'], false);
	}

}

?>
<?php include_once '../layouts/header.php'; ?>
<div class="row">
  <div class="col-md-12">
    <?php echo display_msg($msg); ?>
  </div>
</div>
  <div class="row">
      <div class="panel panel-default">
        <div class="panel-heading">
          <strong>
            <span class="glyphicon glyphicon-th"></span>
            <span>Edit Product</span>
         </strong>
        </div>
        <div class="panel-body">
         <div class="col-md-7">
           <form method="post" action="../products/edit_product.php?id=<?php echo (int)$product['id'] ?>">
              <div class="form-group">
                <div class="input-group">
                  <span class="input-group-addon">
                   <i class="glyphicon glyphicon-th-large"></i>
                  </span>
                  <input type="text" class="form-control" name="product-title" value="<?php echo remove_junk($product['name']);?>">
               </div>
              </div>


              <div class="form-group">
                <div class="input-group">
                  <span class="input-group-addon">
                   <i class="glyphicon glyphicon-th-large"></i>
                  </span>
                  <input type="text" class="form-control" name="product-desc" value="<?php echo remove_junk($product['description']);?>" placeholder="Product Description">
               </div>
              </div>
              <div class="form-group">
                <div class="input-group">
                  <span class="input-group-addon">
                   <i class="glyphicon glyphicon-th-large"></i>
                  </span>
                  <input type="text" class="form-control" name="product-sku" value="<?php echo remove_junk($product['sku']);?>" placeholder="Product SKU">
               </div>
              </div>
              <div class="form-group">
                <div class="input-group">
                  <span class="input-group-addon">
                   <i class="glyphicon glyphicon-th-large"></i>
                  </span>
                  <input type="text" class="form-control" name="product-location" value="<?php echo remove_junk($product['location']);?>" placeholder="Product Location">
               </div>
              </div>


              <div class="form-group">
                <div class="row">
                  <div class="col-md-6">
                    <select class="form-control" name="product-category">
                    <option value=""> Select a category</option>
                   <?php  foreach ($all_categories as $cat): ?>
                     <option value="<?php echo (int)$cat['id']; ?>" <?php if ($product['category_id'] === $cat['id']): echo "selected"; endif; ?> >
                       <?php echo remove_junk($cat['name']); ?></option>
                   <?php endforeach; ?>
                 </select>
                  </div>
                  <div class="col-md-6">
                    <select class="form-control" name="product-photo">
                      <option value=""> No image</option>
                      <?php  foreach ($all_photo as $photo): ?>
                        <option value="<?php echo (int)$photo['id'];?>" <?php if ($product['media_id'] === $photo['id']): echo "selected"; endif; ?> >
                          <?php echo $photo['file_name'] ?></option>
                      <?php endforeach; ?>
                    </select>
                  </div>
                </div>
              </div>

              <div class="form-group">
               <div class="row">
                 <div class="col-md-4">
                  <div class="form-group">
                    <label for="qty">Quantity</label>
                    <div class="input-group">
                      <span class="input-group-addon">
                       <i class="glyphicon glyphicon-shopping-cart"></i>
                      </span>
                      <input type="number" class="form-control" name="product-quantity" value="<?php echo remove_junk($product['quantity']); ?>">
                   </div>
                  </div>
                 </div>
                 <div class="col-md-4">
                  <div class="form-group">
                    <label for="qty">Cost Price</label>
                    <div class="input-group">
                      <span class="input-group-addon">
                        <i class="glyphicon glyphicon-piggy-bank"></i>
                      </span>
                      <input type="number" min="0" step="any" class="form-control" name="cost-price" value="<?php echo remove_junk($product['buy_price']);?>">
                   </div>
                  </div>
                 </div>
                  <div class="col-md-4">
                   <div class="form-group">
                     <label for="qty">Sell Price</label>
                     <div class="input-group">
                       <span class="input-group-addon">
                         <i class="glyphicon glyphicon-piggy-bank"></i>
                       </span>
                       <input type="number" min="0" step="any" class="form-control" name="sale-price" value="<?php echo remove_junk($product['sale_price']);?>">
                    </div>
                   </div>
                  </div>
               </div>
         <div class="pull-right">
              <button type="submit" name="product" class="btn btn-info">Update</button>
          </form>
         </div>
        </div>
      </div>
  </div>

<?php include_once '../layouts/footer.php'; ?>
