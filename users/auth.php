<?php
/**
 * auth.php
 *
 * @package default
 */


include_once '../includes/load.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !verify_csrf()) {
	$session->msg('d', 'Invalid security token. Please try again.');
	redirect('index.php', false);
}

// Resolve client IP. Trust REMOTE_ADDR; only honor X-Forwarded-For if
// it parses as a real IP (mirrors the audit-log policy in load.php).
$client_ip = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
if (isset($_SERVER['HTTP_X_FORWARDED_FOR'])) {
	$forwarded = trim(explode(',', $_SERVER['HTTP_X_FORWARDED_FOR'])[0]);
	if (filter_var($forwarded, FILTER_VALIDATE_IP)) {
		$client_ip = $forwarded;
	}
}

// Rate limit: block before authenticating, so the password-verify cost
// is not part of the attack surface for credential stuffing.
if (is_login_rate_limited($client_ip)) {
	error_log("Login rate-limited for IP {$client_ip}");
	$session->msg('d', 'Too many failed login attempts. Try again later.');
	redirect('index.php', false);
}

$req_fields = array('username', 'password' );
validate_fields($req_fields);
$username = remove_junk($_POST['username']);
$password = remove_junk($_POST['password']);

if (empty($errors)) {
	$user_id = authenticate($username, $password);
	if ($user_id) {
		// Successful login — clear prior failures from this IP.
		clear_failed_logins($client_ip);
		$session->login($user_id);
		updateLastLogIn($user_id);
		$session->msg("s", "Welcome to Inventory.");
		redirect('../users/home.php', false);

	} else {
		// Record failure for rate-limit accounting. Username is logged
		// for auditing but we don't differentiate "wrong user" vs
		// "wrong password" in the response message (timing/oracle).
		record_failed_login($client_ip, $username);
		$session->msg("d", "Sorry Username/Password Incorrect.");
		redirect('index.php', false);
	}

} else {
	$session->msg("d", $errors);
	redirect('index.php', false);
}

?>
