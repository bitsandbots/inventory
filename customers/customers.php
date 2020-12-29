<?php
/**
 * customers.php
 *
 * @package default
 */


?>

<?php
$page_title = 'All Customers';
require_once '../includes/load.php';
// Checkin What level user has permission to view this page
page_require_level(1);

$all_customers = find_all('customers');

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
            <span class="glyphicon glyphicon-user"></span>
            <span>All Customers</span>
          </strong>
          <div class="pull-right">
            <a href="add_customer.php" class="btn btn-primary">Add Customer</a>
          </div>
        </div>
        <div class="panel-body">


          <table class="table table-bordered table-striped">
          <thead>
                <tr>
                    <th class="text-center" style="width: 100px;">Customer</th>
                    <th class="text-center" style="width: 100px;">City</th>
                    <th class="text-center" style="width: 50px;">Region</th>
                    <th class="text-center" style="width: 50px;">Code</th>
                    <th class="text-center" style="width: 50px;">Telephone</th>
                    <th class="text-center" style="width: 50px;">Email</th>
                    <th class="text-center" style="width: 50px;">Pay Method</th>
                    <th class="text-center" style="width: 50px;">Actions</th>
                </tr>
            </thead>
            <tbody>


              <?php foreach ($all_customers as $customer):?>
                <tr>
                    <td class="text-center">
						<?php echo ucfirst($customer['name']);?>
					</td>
                    <td class="text-center">
						<?php echo $customer['city'];?>
					</td>
                    <td class="text-center">
						<?php echo $customer['region'];?>
					</td>

                    <td class="text-center">
						<?php echo $customer['postcode'];?>
					</td>
                   <td class="text-center">
          					<a href="tel:<?php echo $customer['telephone'];?>"><?php echo $customer['telephone'];?></a>
          				</td>

                    <td class="text-center">
          					<a href="mailto:<?php echo $customer['email'];?>"><?php echo $customer['email'];?></a>
          				</td>

                    <td class="text-center">
						<?php echo ucfirst($customer['paymethod']);?>
					</td>

                    <td class="text-center">
                      <div class="btn-group">
                        <a href="../customers/edit_customer.php?id=<?php echo (int)$customer['id'];?>"  class="btn btn-xs btn-warning" data-toggle="tooltip" title="Edit">
                          <span class="glyphicon glyphicon-edit"></span>
                        </a>
                        <a href="../customers/delete_customer.php?id=<?php echo (int)$customer['id'];?>" onClick="return confirm('Are you sure you want to delete?')" class="btn btn-xs btn-danger" data-toggle="tooltip" title="Remove">
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
