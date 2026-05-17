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
page_require_level(ROLE_ADMIN);

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
                    <th class="text-center col-w-100">Customer</th>
                    <th class="text-center col-w-100">City</th>
                    <th class="text-center col-w-50">Region</th>
                    <th class="text-center col-w-50">Code</th>
                    <th class="text-center col-w-50">Telephone</th>
                    <th class="text-center col-w-50">Email</th>
                    <th class="text-center col-w-50">Pay Method</th>
                    <th class="text-center col-w-50">Actions</th>
                </tr>
            </thead>
            <tbody>


              <?php foreach ($all_customers as $customer):?>
                <tr>
                    <td class="text-center">
						<?php echo h(ucfirst($customer['name']));?>
					</td>
                    <td class="text-center">
						<?php echo h($customer['city']);?>
					</td>
                    <td class="text-center">
						<?php echo h($customer['region']);?>
					</td>

                    <td class="text-center">
						<?php echo h($customer['postcode']);?>
					</td>
                   <td class="text-center">
          					<a href="tel:<?php echo h($customer['telephone']);?>"><?php echo h($customer['telephone']);?></a>
          				</td>

                    <td class="text-center">
          					<a href="mailto:<?php echo h($customer['email']);?>"><?php echo h($customer['email']);?></a>
          				</td>

                    <td class="text-center">
						<?php echo ucfirst($customer['paymethod']);?>
					</td>

                    <td class="text-center">
                      <div class="btn-group">
                        <a href="../customers/edit_customer.php?id=<?php echo (int)$customer['id'];?>"  class="btn btn-xs btn-warning" data-toggle="tooltip" title="Edit">
                          <span class="glyphicon glyphicon-edit"></span>
                        </a>
                        <a href="../customers/delete_customer.php?id=<?php echo (int)$customer['id'];?>&<?php echo csrf_url_param(); ?>" onClick="return confirm('Are you sure you want to delete?')" class="btn btn-xs btn-danger" data-toggle="tooltip" title="Remove">
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
