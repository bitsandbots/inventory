<?php
/**
 * orders.php
 *
 * @package default
 */


$page_title = 'All Orders';
require_once '../includes/load.php';
// Checkin What level user has permission to view this page
page_require_level(1);

$all_orders = find_all('orders');
$orders = array_reverse($all_orders);
?>
<?php include_once '../layouts/header.php'; ?>
  <div class="row">
     <div class="col-md-12">
       <?php echo display_msg($msg); ?>
     </div>
  </div>

  <div class="row">
    <div class="col-md-12">
      <div class="panel panel-default">
        <div class="panel-heading clearfix">
          <strong>
            <span class="glyphicon glyphicon-th"></span>
            <span>All Orders</span>
          </strong>
          <div class="pull-right">
            <a href="../sales/add_order.php" class="btn btn-primary">Add Order</a>
          </div>
        </div>
        <div class="panel-body">

          <table class="table table-bordered table-striped">
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


              <?php foreach ($orders as $order):?>
                <tr>
                    <td class="text-center">
					<a href="../sales/sales_by_order.php?id=<?php echo (int)$order['id'];?>">
					<?php echo $order['id'];?>
					</a>
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

  <?php $customer = find_by_name('customers', $order['customer']); ?>

                    <td class="text-center">
                      <div class="btn-group">
                        <a href="../sales/edit_order.php?id=<?php echo (int)$order['id'];?>"  class="btn btn-xs btn-warning" data-toggle="tooltip" title="Edit">
                          <span class="glyphicon glyphicon-edit"></span>
                        </a>
                        <a href="../customers/edit_customer.php?id=<?php echo (int)$customer['id'];?>"  class="btn btn-xs btn-info" data-toggle="tooltip" title="Customer Details">
                          <span class="glyphicon glyphicon-user"></span>
                        </a>
                        <a href="../sales/delete_order.php?id=<?php echo (int)$order['id'];?>" onClick="return confirm('Are you sure you want to delete?')" class="btn btn-xs btn-danger" data-toggle="tooltip" title="Remove">
                          <span class="glyphicon glyphicon-trash"></span>
                        </a>
                      </div>
                    </td>

                </tr>
              <?php endforeach; ?>


            </tbody>
          </table>
       </div>
    </div>
    </div>
   </div>
  </div>
  <?php include_once '../layouts/footer.php'; ?>
