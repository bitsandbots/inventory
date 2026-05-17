<?php
/**
 * monthly_sales.php
 *
 * @package default
 */


$page_title = 'Monthly Sales';
require_once '../includes/load.php';
// Checkin What level user has permission to view this page
page_require_level(ROLE_USER);
?>
<?php
$year = date('Y');
$sales = monthlySales($year);
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
            <span>Monthly Sales</span>
          </strong>
        </div>
        <div class="panel-body">
          <table class="table table-bordered table-striped">
            <thead>
              <tr>
                <th class="text-center col-w-50">#</th>
                <th> Product </th>
                <th class="text-center col-w-15p"> Quantity Sold</th>
                <th class="text-center col-w-15p"> Total </th>
                <th class="text-center col-w-15p"> Date </th>
             </tr>
            </thead>
           <tbody>
             <?php foreach ($sales as $sale):?>
             <tr>
               <td class="text-center"><?php echo count_id();?></td>
               <td><?php echo h($sale['name']); ?></td>
               <td class="text-center"><?php echo (int)$sale['qty']; ?></td>
               <td class="text-center"><?php echo formatcurrency($sale['total_selling_price'],  $CURRENCY_CODE); ?></td>
               <td class="text-center"><?php echo h($sale['date']); ?></td>
             </tr>
             <?php endforeach;?>
           </tbody>
         </table>
        </div>
      </div>
    </div>
  </div>

<?php include_once '../layouts/footer.php'; ?>
