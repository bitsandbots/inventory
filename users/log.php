<?php
/**
 * log.php
 *
 * @package default
 */


$page_title = 'All logs';
require_once '.././includes/load.php';
// Checkin What level user has permission to view this page
page_require_level(2);

/**
 * CoreConduit Copyright (C) 2016 Cory J. Potter - All Rights Reserved
 * NOT INTENDED FOR COMMERCIAL USE!
 * <coreconduitconsulting@gmail.com>
 *
 * *************************************************************************
 *
 * CORECONDUIT CONFIDENTIAL
 * __________________
 *
 *  [2014] - [2018] CoreConduit A.K.A. Cory J. Potter - All Rights Reserved.
 *
 * NOTICE:  All information contained herein is, and remains the property of
 *          CoreConduit and its suppliers, if any.  The intellectual and
 *          technical concepts contained herein are proprietary to CoreConduit
 *          and its suppliers and may be covered by U.S. and Foreign Patents,
 *          patents in process, and are protected by trade secret or copyright law.
 *          Dissemination of this information or reproduction of this material is
 *          strictly forbidden unless prior written permission is obtained from
 *          CoreConduit.
 *          Unless required by applicable law or agreed to in writing, software
 *          distributed is distributed on an "AS IS" BASIS,
 *          WITHOUT WARRANTIES OF ANY KIND, either express or implied.
 * ***********************************************************************
 */
$logs = find_all('log');

/******************************************************************************/
?>
<?php include_once '../layouts/header.php'; ?>

<div class="row">
  <div class="col-md-6">
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
            <span>Logged Actions</span>
          </strong>

        </div>

        <div class="panel-body">
<!--     *************************     -->
          <table class="table table-bordered table-striped">
            <thead>
              <tr>
	<th class="text-center" style="width: 15%;"> id  </th>
	<th class="text-center" style="width: 15%;"> user_id  </th>
	<th class="text-center" style="width: 15%;"> remote_ip  </th>
	<th class="text-center" style="width: 15%;"> action </th>
	<th class="text-center" style="width: 15%;"> date  </th>
	<th class="text-center" style="width: 15%;"> Actions </th>

</tr>
</thead>
<tbody>


<?php
foreach ($logs as $log ) {
?>
<tr>
<td class="text-center">
<?php echo $log['id']; ?>
</td>

<td class="text-center">
<?php
	$user =  find_by_id( "users", $log['user_id'] );
	echo $user['name'];
?>
</td>
<td class="text-center">
<?php  echo $log['remote_ip']; ?>
</td>
<td class="text-center">
<?php echo $log['action']; ?>
</td>

<td class="text-center">
<?php echo $log['date']; ?>
</td>


               <td class="text-center">
                  <div class="btn-group">
                     <a href="../users/delete_log.php?id=<?php echo $log['id']; ?>" onClick="return confirm('Are you sure you want to delete?')" class="btn btn-warning btn-xs"  title="Delete" data-toggle="tooltip">
                       <span class="glyphicon glyphicon-trash"></span>
                     </a>
                     <a href="../users/delete_log_by_ip.php?ip=<?php echo $log['remote_ip']; ?>" onClick="return confirm('Are you sure you want to delete?')" class="btn btn-danger btn-xs"  title="Delete By IP" data-toggle="tooltip">
                       <span class="glyphicon glyphicon-trash"></span>
                     </a>
                  </div>
               </td>
             </tr>
<?php
}
?>

           </tbody>
         </table>
<!--     *************************     -->
        </div>
      </div>

    </div>
  </div>
<?php include_once '../layouts/footer.php'; ?>
