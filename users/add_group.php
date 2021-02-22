<?php
/**
 * add_group.php
 *
 * @package default
 */


$page_title = 'Add Group';
require_once '../includes/load.php';

// Setting language var
$lang->set('users.php');

// Checkin What level user has permission to view this page
page_require_level(1); 
?>
<?php
if (isset($_POST['add'])) {

	$req_fields = array('group-name', 'group-level');
	validate_fields($req_fields);

	if (find_by_groupName($_POST['group-name']) === false ) {
		$session->msg('d', '<b>Sorry!</b> Entered Group Name already in database!');
		redirect('../users/add_group.php', false);
	}elseif (find_by_groupLevel($_POST['group-level']) === false) {
		$session->msg('d', '<b>Sorry!</b> Entered Group Level already in database!');
		redirect('../users/add_group.php', false);
	}
	if (empty($errors)) {
		$name = remove_junk($db->escape($_POST['group-name']));
		$level = remove_junk($db->escape($_POST['group-level']));
		$status = remove_junk($db->escape($_POST['status']));

		$query  = "INSERT INTO user_groups (";
		$query .="group_name,group_level,group_status";
		$query .=") VALUES (";
		$query .=" '{$name}', '{$level}','{$status}'";
		$query .=")";
		if ($db->query($query)) {
			//sucess
			$session->msg('s', $lang->get('NAME_GROUP_NO_DATABASE'));
			redirect('../users/add_group.php', false);
		} else {
			//failed
			$session->msg('d', $lang->get('LEVEL_GROUP_NO_DATABASE'));
			redirect('../users/add_group.php', false);
		}
	} else {
		$session->msg("d", $errors);
		redirect('../users/add_group.php', false);
	}
}
?>
<?php include_once '../layouts/header.php'; ?>
<div class="login-page">
    <div class="text-center">
       <h3><?php echo $lang->get('ADD_NEW_USER_GROUP') ?></h3>
     </div>
     <?php echo display_msg($msg); ?>
      <form method="post" action="../users/add_group.php" class="clearfix">
        <div class="form-group">
              <label for="name" class="control-label"><?php echo $lang->get('GROUP_NAME') ?></label>
              <input type="name" class="form-control" name="group-name">
        </div>
        <div class="form-group">
              <label for="level" class="control-label"><?php echo $lang->get('GROUP_LEVEL') ?></label>
              <input type="number" class="form-control" name="group-level">
        </div>
        <div class="form-group">
          <label for="status"><?php echo $lang->get('STATUS_NAME') ?></label>
            <select class="form-control" name="status">
              <option value="1"><?php echo $lang->get('STATUS_ACTIVE') ?></option>
              <option value="0"><?php echo $lang->get('STATUS_DESACTIVE') ?></option>
            </select>
        </div>
        <div class="form-group clearfix">
                <button type="submit" name="add" class="btn btn-info"><?php echo $lang->get('UPDATE_NAME') ?></button>
        </div>
    </form>
</div>

<?php include_once '../layouts/footer.php'; ?>
