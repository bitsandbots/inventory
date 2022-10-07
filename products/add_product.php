<?php
/**
 * add_product.php
 *
 * @package default
 */


$page_title = 'Add Product';
require_once '../includes/load.php';
// Checkin What level user has permission to view this page
page_require_level(2);

$all_categories = find_all('categories');
$all_photo = find_all('media');
			
if (isset($_POST['add_product'])) {
	$req_fields = array('product-title', 'product-category', 'product-quantity', 'cost-price', 'sale-price' );
	validate_fields($req_fields);
	if (empty($errors)) {
		$p_name  = $db->escape(remove_junk($_POST['product-title']));
		$p_desc  = $db->escape(remove_junk($_POST['product-desc']));
		$p_sku  = $db->escape(remove_junk($_POST['product-sku']));
		$p_loc  = $db->escape(remove_junk($_POST['product-location']));
		$p_cat   = $db->escape(remove_junk($_POST['product-category']));
		$p_qty   = $db->escape(remove_junk($_POST['product-quantity']));
		$p_buy   = $db->escape(remove_junk($_POST['cost-price']));
		$p_sale  = $db->escape(remove_junk($_POST['sale-price']));
		if (is_null($_POST['product-photo']) || $_POST['product-photo'] === "") {
			$media_id = '0';
		} else {
			$media_id = $db->escape(remove_junk($_POST['product-photo']));
		}
		$date    = make_date();
		$query  = "INSERT INTO products (";
		$query .=" name,description,sku,location,quantity,buy_price,sale_price,category_id,media_id,date";
		$query .=") VALUES (";
		$query .=" '{$p_name}', '{$p_desc}', '{$p_sku}', '{$p_loc}', '{$p_qty}', '{$p_buy}', '{$p_sale}', '{$p_cat}', '{$media_id}', '{$date}'";
		$query .=")";
		$query .=" ON DUPLICATE KEY UPDATE name='{$p_name}'";
		if ($db->query($query)) {

			$product = last_id("products");
			$product_id = $product['id'];
			if ( $product_id == 0 ) {
				$session->msg('d', ' Sorry, Failed to Add!');
				redirect('../products/add_product.php', false);
			}

			$quantity = $p_qty;
			$cost = $p_buy;
			$comments = "initial stock";

			$sql  = "INSERT INTO stock (product_id,quantity,comments,date)";
			$sql .= " VALUES ('{$product_id}','{$quantity}','{$comments}','{$date}')";
			$result = $db->query($sql);
			if ( $result && $db->affected_rows() === 1) {
				$session->msg('s', "Product Added ");
				redirect('../products/products.php', false);
			}
		} else {
			$session->msg('d', ' Sorry, Failed to Add!');
			redirect('../products/add_product.php', false);
		}

	} else {
		$session->msg("d", $errors);
		redirect('../products/add_product.php', false);
	}

}

?>


<?php include_once '../layouts/header.php'; ?>
<div class="row">
  <div class="col-md-12">
    <?php echo display_msg($msg); ?>
  </div>
</div>
<!--     *************************     -->
  <div class="row">
  <div class="col-md-8">
      <div class="panel panel-default">
        <div class="panel-heading">
          <strong>
            <span class="glyphicon glyphicon-th"></span>
<!--     *************************     -->
            <span>Add New Product</span>
<!--     *************************     -->
         </strong>
        </div>
        <div class="panel-body">
         <div class="col-md-12">
<!--     *************************     -->
          <form method="post" action="../products/add_product.php" class="clearfix">
              <div class="form-group">
                <div class="input-group">
                  <span class="input-group-addon">
                   <i class="glyphicon glyphicon-th-large"></i>
                  </span>
                  <input type="text" class="form-control" name="product-title" value="" placeholder="Name">
               </div>
              </div>


              <div class="form-group">
                <div class="input-group">
                  <span class="input-group-addon">
                   <i class="glyphicon glyphicon-th-large"></i>
                  </span>
                  <input type="text" class="form-control" name="product-desc" value="" placeholder="Description">
               </div>
              </div>
              <div class="form-group">
                <div class="input-group">
                  <span class="input-group-addon">
                   <i class="glyphicon glyphicon-th-large"></i>
                  </span>
                  <input type="text" class="form-control" name="product-sku" value="" placeholder="SKU">
               </div>
              </div>
              <div class="form-group">
                <div class="input-group">
                  <span class="input-group-addon">
                   <i class="glyphicon glyphicon-th-large"></i>
                  </span>
                  <input type="text" class="form-control" name="product-location" value="" placeholder="Location">
               </div>
              </div>


              <div class="form-group">
                <div class="row">
                  <div class="col-md-6">
                    <select class="form-control" name="product-category">
                    <option value=""> Select a category</option>
                   <?php  foreach ($all_categories as $cat): ?>
                     <option value="<?php echo (int)$cat['id']; ?>">
                       <?php echo remove_junk($cat['name']); ?></option>
                   <?php endforeach; ?>
                 </select>
                  </div>
                  <div class="col-md-6">
                    <select class="form-control" name="product-photo">
                      <option value=""> No image</option>
                      <?php  foreach ($all_photo as $photo): ?>
                        <option value="<?php echo (int)$photo['id'];?>">
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
                      <input type="number" class="form-control" name="product-quantity" value="">
                   </div>
                  </div>
                 </div>
                 <div class="col-md-4">
                  <div class="form-group">
                    <label for="cost-price">Cost Price</label>
                    <div class="input-group">
                      <span class="input-group-addon">
                        <i class="glyphicon glyphicon-piggy-bank"></i>
                      </span>
                      <input type="number" min="0" step="any" class="form-control" name="cost-price" value="">
                   </div>
                  </div>
                 </div>
                  <div class="col-md-4">
                   <div class="form-group">
                     <label for="sale-price">Sale Price</label>
                     <div class="input-group">
                       <span class="input-group-addon">
                         <i class="glyphicon glyphicon-piggy-bank"></i>
                       </span>
                       <input type="number" min="0" step="any" class="form-control" name="sale-price" value="">
                    </div>
                   </div>
                  </div>
               </div>

         <div class="pull-right">
              <button type="submit" name="add_product" class="btn btn-info">Add Product</button>
         </div>

<!--     *************************     -->
          </form>

         </div>
        </div>
      </div>

    </div>
  </div>

<?php include_once '../layouts/footer.php'; ?>
