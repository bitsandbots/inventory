<?php
require_once '../includes/load.php';
page_require_level(1);

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !verify_csrf()) {
	$session->msg('d', 'Invalid request.');
	redirect('orgs.php', false);
}

$org_id  = (int)($_POST['org_id'] ?? 0);
$user_id = (int)($_POST['user_id'] ?? 0);
$role    = in_array($_POST['role'] ?? '', ['owner', 'admin', 'member'])
	? $_POST['role'] : '';

if ($org_id <= 0 || $user_id <= 0 || $role === '') {
	$session->msg('d', 'Invalid request.');
	redirect("edit_org.php?id=$org_id", false);
}

require_org_role('owner', 'admin');

global $db;

// Guard: cannot demote the last owner.
if ($role !== 'owner') {
	$row = $db->prepare_select_one(
		"SELECT COUNT(*) AS cnt FROM org_members WHERE org_id = ? AND role = 'owner'",
		'i', $org_id
	);
	$current_role = $db->prepare_select_one(
		"SELECT role FROM org_members WHERE org_id = ? AND user_id = ?",
		'ii', $org_id, $user_id
	);
	if ($current_role && $current_role['role'] === 'owner' && (int)$row['cnt'] <= 1) {
		$session->msg('d', 'Cannot demote the last owner. Assign another owner first.');
		redirect("edit_org.php?id=$org_id", false);
	}
}

$db->prepare_query(
	"UPDATE org_members SET role = ? WHERE org_id = ? AND user_id = ?",
	'sii', $role, $org_id, $user_id
);
$session->msg('s', 'Role updated.');
redirect("edit_org.php?id=$org_id", false);
