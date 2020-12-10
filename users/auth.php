<?php
/**
 * auth.php
 *
 * @package default
 */


include_once '../includes/load.php';
///importing csrf handler
use csrfhandler\csrf as csrf;	
//check for only get & post requests
$isValid = csrf::all();
if(!($isValid)) {
	$session->msg('d', "Invalid Token ");
	redirect('index.php', false);
}
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
