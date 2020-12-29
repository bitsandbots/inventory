<?php
/**
 * home.php
 *
 * @package default
 */


$page_title = 'Home Page';
require_once '../includes/load.php';
if (!$session->isUserLoggedIn(true)) { redirect('index.php', false);}
page_require_level(3);

$products_sold   = find_highest_selling_product('10');
$recent_products = find_recent_product_added('5');
$recent_sales    = find_recent_sale_added('5')
?>
<?php include_once '../layouts/header.php'; ?>

<script>
function closePanel()
{
  var x = document.getElementById("myDIV");
  if (x.style.display === "none")
  {
    x.style.display = "block";
  } else {
    x.style.display = "none";
  }
}
</script>


<div class="row">
   <div class="col-md-12">
     <?php echo display_msg($msg); ?>
   </div>
</div>


  <div class="row" id="myDIV">
   <div class="col-md-12">
      <div class="panel">
        <div class="pull-right">
		<a href="#" onclick="closePanel();" class="btn btn-xs btn-danger" data-toggle="tooltip" title="Close"><i class="glyphicon glyphicon-remove"></i></a>
        </div>

        <div class="jumbotron text-center">
           <h3>Welcome!</h3>Contact support for additional assistance.
        </div>

      </div>
   </div>
  </div>

  <div class="row">
   <div class="col-md-4">
     <div class="panel panel-default">
       <div class="panel-heading">
         <strong>
           <span class="glyphicon glyphicon-th"></span>
           <span>Highest Selling Products</span>
         </strong>
       </div>

       <div class="panel-body">
         <table class="table table-striped table-bordered table-condensed">

          <thead>
           <tr>
             <th>Product</th>
             <th>Total Sold</th>
             <th>Total Quantity</th>
           <tr>
          </thead>


          <tbody>

            <?php foreach ($products_sold as  $product_sold): ?>

              <tr>
                <td><?php echo first_character($product_sold['name']); ?></td>
                <td><?php echo (int)$product_sold['totalSold']; ?></td>
                <td><?php echo (int)$product_sold['totalQty']; ?></td>
              </tr>

            <?php endforeach; ?>


          <tbody>
         </table>
       </div>
     </div>
   </div>
<!--     *************************     -->
   <div class="col-md-4">
      <div class="panel panel-default">
        <div class="panel-heading">
          <strong>
            <span class="glyphicon glyphicon-th"></span>
            <span>LATEST SALES</span>
          </strong>
        </div>
        <div class="panel-body">
          <table class="table table-striped table-bordered table-condensed">
       <thead>
         <tr>
           <th class="text-center" style="width: 50px;">#</th>
           <th>Product</th>
           <th>Date</th>
           <th>Total Sale</th>
         </tr>
       </thead>
       <tbody>


         <?php foreach ($recent_sales as  $recent_sale): ?>
         <tr>
           <td class="text-center"><?php echo count_id();?></td>
           <td>
            <a href="../sales/edit_sale.php?id=<?php echo (int)$recent_sale['id']; ?>">
             <?php echo first_character($recent_sale['name']); ?>
           </a>
           </td>
           <td><?php echo ucfirst($recent_sale['date']); ?></td>
           <td><?php echo formatcurrency( first_character($recent_sale['price']), $CURRENCY_CODE); ?></td>
        </tr>

       <?php endforeach; ?>


       </tbody>
     </table>
    </div>
   </div>
  </div>
<!--     *************************     -->

  <div class="col-md-4">
    <div class="panel panel-default">
      <div class="panel-heading">
        <strong>
          <span class="glyphicon glyphicon-th"></span>
          <span>Recently Added Products</span>
        </strong>
      </div>
      <div class="panel-body">

        <div class="list-group">


      <?php foreach ($recent_products as  $recent_product): ?>
            <a class="list-group-item clearfix" href="../products/view_product.php?id=<?php echo    (int)$recent_product['id'];?>">
                <h4 class="list-group-item-heading">
                 <?php if ($recent_product['media_id'] === '0'): ?>
                    <img class="img-avatar img-circle" src="../uploads/products/no_image.jpg" alt="">
                  <?php else: ?>
                  <img class="img-avatar img-circle" src="../uploads/products/<?php echo $recent_product['image'];?>" alt="" />
                <?php endif;?>
                <?php echo first_character($recent_product['name']);?>
                  <span class="label label-warning pull-right">
                 <?php echo formatcurrency( (int)$recent_product['sale_price'], $CURRENCY_CODE); ?>
                  </span>
                </h4>
                <span class="list-group-item-text pull-right">
                <?php echo first_character($recent_product['category']); ?>
              </span>
          </a>

      <?php endforeach; ?>


    </div>
  </div>
 </div>
</div>
 </div>
  <div class="row">

  </div>


<?php include_once '../layouts/footer.php'; ?>
