<?php
/**
 * group.php
 *
 * @package default
 */


$page_title = 'All Group';
require_once '../includes/load.php';
// Checkin What level user has permission to view this page
page_require_level(1);

$all_groups = find_all('user_groups');

?>

<?php include_once '../layouts/header.php'; ?>

<div class="row">
   <div class="col-md-12">
     <?php echo display_msg($msg); ?>
   </div>
</div>
<!--     *************************     -->
<div class="row">
  <div class="col-md-12">
    <div class="panel panel-default">
    <div class="panel-heading clearfix">
      <strong>
        <span class="glyphicon glyphicon-th"></span>
        <span>Groups</span>
     </strong>
          <div class="pull-right">
             <!-- <a href="../users/add_group.php"class="btn btn-primary">Add New Group</a> -->
          </div>
    </div>
<!--     *************************     -->
     <div class="panel-body">
      <table class="table table-bordered">
        <thead>
          <tr>
            <th class="text-center" style="width: 50px;">#</th>
<!--     *************************     -->
            <th>Group Name</th>
<!--     *************************     -->
            <th class="text-center" style="width: 20%;">Group Level</th>
            <th class="text-center" style="width: 15%;">Status</th>
            <th class="text-center" style="width: 100px;">Actions</th>
          </tr>
        </thead>
        <tbody>


        <?php foreach ($all_groups as $a_group): ?>

          <tr>
           <td class="text-center"><?php echo count_id();?></td>
           <td><?php echo ucwords($a_group['group_name'])?></td>
           <td class="text-center">
             <?php echo ucwords($a_group['group_level'])?>
           </td>
<!--     *************************     -->
           <td class="text-center">
           <?php if ($a_group['group_status'] === '1'): ?>
            <span class="label label-success"><?php echo "Active"; ?></span>
          <?php else: ?>
            <span class="label label-danger"><?php echo "Deactive"; ?></span>
          <?php endif;?>
           </td>
<!--     *************************     -->
           <td class="text-center">
             <div class="btn-group">
                <a href="../users/edit_group.php?id=<?php echo (int)$a_group['id'];?>" class="btn btn-xs btn-warning" data-toggle="tooltip" title="Edit">
                  <i class="glyphicon glyphicon-pencil"></i>
               </a>
<!--
                <a href="../users/delete_group.php?id=<?php echo (int)$a_group['id'];?>" onClick=\"return confirm('Are you sure you want to delete?')\" class="btn btn-xs btn-danger" data-toggle="tooltip" title="Remove">
                  <i class="glyphicon glyphicon-remove"></i>
                </a>
-->
                </div>
           </td>
<!--     *************************     -->
          </tr>


        <?php endforeach;?>


       </tbody>
     </table>
     </div>
    </div>
  </div>
</div>
  <?php include_once '../layouts/footer.php'; ?>
