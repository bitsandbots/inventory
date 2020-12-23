<?php
/**
 * auth.php
 *
 * @package default
 */


include_once '../includes/load.php';

$req_fields = array('username', 'password' );
validate_fields($req_fields);
$username = remove_junk($_POST['username']);
$password = remove_junk($_POST['password']);

if (empty($errors)) {
	$user_id = authenticate($username, $password);
	if ($user_id) {
		//create session with id
		$session->login($user_id);
		//Update Sign in time
		updateLastLogIn($user_id);
		$session->msg("s", "Welcome to Inventory.");
		redirect('../users/home.php', false);

	} else {
		$session->msg("d", "Sorry Username/Password Incorrect.");
		redirect('index.php', false);
	}

} else {
	$session->msg("d", $errors);
	redirect('index.php', false);
}

?>
