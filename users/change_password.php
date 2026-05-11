<?php
/**
 * change_password.php
 *
 * @package default
 */


$page_title = 'Change Password';
require_once '../includes/load.php';
// Checkin What level user has permission to view this page
page_require_level(3);
?>
<?php $user = current_user(); ?>
<?php
if (!verify_csrf()) { $session->msg('d', 'Invalid or missing security token.'); redirect($_SERVER['HTTP_REFERER'] ?? 'index.php', false); }
  if (isset($_POST['update'])) {
$req_fields = array('new-password', 'old-password', 'id' );
	validate_fields($req_fields);

	if (empty($errors)) {

		$stored_hash = current_user()['password'];
		$old_password = $_POST['old-password'];
		$old_valid = false;

		// Handle both legacy SHA1 and modern bcrypt hashes
		if (strlen($stored_hash) === 40 && ctype_xdigit($stored_hash)) {
			$old_valid = (sha1($old_password) === $stored_hash);
		} else {
			$old_valid = password_verify($old_password, $stored_hash);
		}

		if (!$old_valid) {
			$session->msg('d', "Your old password not match");
			redirect('../users/change_password.php', false);
		}

		$id = (int)$_POST['id'];
		$new_hash = password_hash($_POST['new-password'], PASSWORD_BCRYPT);
		$stmt = $db->prepare_query(
			"UPDATE users SET password = ? WHERE id = ?",
			"si", $new_hash, $id
		);
		$affected = $stmt->affected_rows;
		$stmt->close();
		if ($affected === 1):
			$session->logout();
			$session->msg('s', "Login with your new password.");
			redirect('index.php', false);
		else:
			$session->msg('d', ' Sorry failed to updated!');
			redirect('../users/change_password.php', false);
		endif;
	} else {
		$session->msg("d", $errors);
		redirect('../users/change_password.php', false);
	}
}
?>
<?php include_once '../layouts/header.php'; ?>
<div class="login-page">
    <div class="text-center">
       <h3>Change your password</h3>
     </div>
     <?php echo display_msg($msg); ?>
      <form method="post" action="../users/change_password.php" class="clearfix">
              <?php echo csrf_field(); ?>
        <div class="form-group">
              <label for="newPassword" class="control-label">New password</label>
              <input type="password" class="form-control" name="new-password" placeholder="New password">
        </div>
        <div class="form-group">
              <label for="oldPassword" class="control-label">Old password</label>
              <input type="password" class="form-control" name="old-password" placeholder="Old password">
        </div>
        <div class="form-group clearfix">
               <input type="hidden" name="id" value="<?php echo (int)$user['id'];?>">
                <button type="submit" name="update" class="btn btn-info">Change</button>
        </div>
    </form>
</div>
<?php include_once '../layouts/footer.php'; ?>
