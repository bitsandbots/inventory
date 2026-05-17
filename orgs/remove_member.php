<?php
require_once '../includes/load.php';
page_require_level(ROLE_ADMIN);

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !verify_csrf()) {
	$session->msg('d', 'Invalid request.');
	redirect('orgs.php', false);
}

$org_id  = (int)($_POST['org_id'] ?? 0);
$user_id = (int)($_POST['user_id'] ?? 0);

if ($org_id <= 0 || $user_id <= 0) {
	$session->msg('d', 'Invalid request.');
	redirect("edit_org.php?id=$org_id", false);
}

require_org_role('owner', 'admin');

global $db;
// Guard: cannot remove the last owner.
$member = $db->prepare_select_one(
	"SELECT role FROM org_members WHERE org_id = ? AND user_id = ?",
	'ii', $org_id, $user_id
);
if ($member && $member['role'] === 'owner') {
	$row = $db->prepare_select_one(
		"SELECT COUNT(*) AS cnt FROM org_members WHERE org_id = ? AND role = 'owner'",
		'i', $org_id
	);
	if ((int)$row['cnt'] <= 1) {
		$session->msg('d', 'Cannot remove the last owner.');
		redirect("edit_org.php?id=$org_id", false);
	}
}

$db->prepare_query(
	"DELETE FROM org_members WHERE org_id = ? AND user_id = ?",
	'ii', $org_id, $user_id
);
$session->msg('s', 'Member removed.');
redirect("edit_org.php?id=$org_id", false);
