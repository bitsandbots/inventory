<?php
/**
 * sales_by_order.php
 *
 * @package default
 */


$page_title = 'Sales by Order';
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
?>



<?php include_once '../layouts/header.php'; ?>
  <div class="row">
     <div class="col-md-12">
       <?php echo display_msg($msg); ?>
     </div>
  </div>

    <div class="col-md-12">
    <div class="panel panel-default">
      <div class="panel-heading">
        <strong>
          <span class="glyphicon glyphicon-th"></span>

            <span>Order #<?php echo $order_id; ?></span>

       </strong>
      </div>
        <div class="panel-body">
          <table class="table table-bordered table-striped table-hover">
            <thead>
                <tr>
                    <th class="text-center" style="width: 50px;">#</th>
                    <th class="text-center" style="width: 50px;">Customer</th>
                    <th class="text-center" style="width: 50px;">Pay Method</th>
                    <th class="text-center" style="width: 50px;">Notes</th>
                    <th class="text-center" style="width: 50px;">Date</th>
                    <th class="text-center" style="width: 100px;">Actions</th>
                </tr>
            </thead>
            <tbody>

                <tr>
                    <td class="text-center"><?php echo $order['id'];?>
					</td>

                    <td class="text-center">
						<?php echo ucfirst($order['customer']);?>
					</td>
                    <td class="text-center">
						<?php echo ucfirst($order['paymethod']);?>
					</td>

                    <td class="text-center">
						<?php echo $order['notes'];?>
					</td>

                    <td class="text-center">
						<?php echo $order['date'];?>
					</td>

                    <td class="text-center">
                      <div class="btn-group">
                        <a href="../sales/edit_order.php?id=<?php echo (int)$order['id'];?>"  class="btn btn-xs btn-warning" data-toggle="tooltip" title="Edit">
                          <span class="glyphicon glyphicon-edit"></span>
                        </a>
                        <a href="../sales/order_picklist.php?id=<?php echo (int)$order['id'];?>"  class="btn btn-xs btn-primary" data-toggle="tooltip" title="Picklist">
                          <span class="glyphicon glyphicon-hand-up"></span>
                        </a>
                        <a href="../sales/sales_invoice.php?id=<?php echo (int)$order['id'];?>"  class="btn btn-xs btn-success" data-toggle="tooltip" title="Invoice">
                          <span class="glyphicon glyphicon-export"></span>
                        </a>
                      </div>
                    </td>

                </tr>

            </tbody>
          </table>
       </div>
    </div>



  <div class="row">
    <div class="col-md-12">
      <div class="panel panel-default">
        <div class="panel-heading clearfix">
          <strong>
            <span class="glyphicon glyphicon-th"></span>
            <span>Sales</span>
          </strong>
          <div class="pull-right">
            <a href="../sales/add_sale_by_search.php?id=<?php echo $order_id; ?>" class="btn btn-primary">Add sale</a>
          </div>
        </div>
        <div class="panel-body">

          <table class="table table-bordered table-striped">
            <thead>
              <tr>
                <th class="text-center" style="width: 50px;">#</th>
                <th> Product </th>
                <th class="text-center" style="width: 15%;"> SKU </th>
                <th class="text-center" style="width: 15%;"> Location </th>
                <th class="text-center" style="width: 15%;"> Quantity </th>
                <th class="text-center" style="width: 15%;"> Total </th>
                <th class="text-center" style="width: 100px;"> Actions </th>
             </tr>
            </thead>

           <tbody>

             <?php foreach ($sales as $sale):?>

             <tr>
               <td class="text-center"><?php echo count_id();?></td>
               <td><?php echo $sale['name']; ?></td>
               <td class="text-center"><?php echo $sale['sku']; ?></td>
               <td class="text-center"><?php echo $sale['location']; ?></td>
               <td class="text-center"><?php echo (int)$sale['qty']; ?></td>
               <td class="text-center"><?php echo formatcurrency($sale['price'], $CURRENCY_CODE); ?></td>
               <td class="text-center">
                  <div class="btn-group">
                     <a href="../sales/edit_sale.php?id=<?php echo (int)$sale['id'];?>" class="btn btn-warning btn-xs"  title="Edit" data-toggle="tooltip">
                       <span class="glyphicon glyphicon-edit"></span>
                     </a>
                     <a href="../sales/delete_sale.php?id=<?php echo (int)$sale['id'];?>" class="btn btn-danger btn-xs"  title="Delete" data-toggle="tooltip">
                       <span class="glyphicon glyphicon-trash"></span>
                     </a>
                  </div>
               </td>
             </tr>

             <?php endforeach;?>

             <tr>
               <td class="text-center"></td>
               <td class="text-center"></td>
               <td class="text-center"></td>
               <td class="text-center"></td>
               <td class="text-center"></td>
<?php
$order_total = 0;
foreach ($sales as $sale) {
	$order_total = $order_total + $sale['price'];
}
?>
               <td class="text-center"><?php echo formatcurrency($order_total, $CURRENCY_CODE); ?></td>
               <td class="text-center"></td>


			</tr>


           </tbody>
         </table>
<!--     *************************     -->
        </div>
      </div>


    </div>
  </div>
<?php include_once '../layouts/footer.php'; ?>
