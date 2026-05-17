<?php
/**
 * users/switch_org.php
 *
 * POST: switch the session's active org. Validates CSRF and membership
 * before updating session and last_active_org_id.
 */
require_once '../includes/load.php';
page_require_level(3);

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !verify_csrf()) {
	$session->msg('d', 'Invalid security token. Please try again.');
	redirect('home.php', false);
}

$new_org_id = (int)($_POST['org_id'] ?? 0);
if ($new_org_id <= 0) {
	$session->msg('d', 'Invalid organization.');
	redirect('home.php', false);
}

// Verify the user actually has an active membership in the requested org.
global $db;
$user_id = (int)$_SESSION['user_id'];
$row = $db->prepare_select_one(
	"SELECT m.org_id FROM org_members m
	   JOIN orgs o ON o.id = m.org_id
	  WHERE m.user_id = ? AND m.org_id = ? AND o.deleted_at IS NULL",
	'ii', $user_id, $new_org_id
);

if (!$row) {
	$session->msg('d', 'You do not have access to that organization.');
	redirect('home.php', false);
}

// Switch the session and persist the preference.
$_SESSION['current_org_id'] = $new_org_id;
$db->prepare_query(
	"UPDATE users SET last_active_org_id = ? WHERE id = ?",
	'ii', $new_org_id, $user_id
);

$session->msg('s', 'Organization switched.');
redirect('home.php', false);
