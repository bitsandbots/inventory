<?php
require_once '../includes/load.php';
page_require_level(ROLE_ADMIN);

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !verify_csrf()) {
	$session->msg('d', 'Invalid request.');
	redirect('orgs.php', false);
}

$org_id   = (int)($_POST['org_id'] ?? 0);
$username = trim(remove_junk($_POST['username'] ?? ''));
$role     = in_array($_POST['role'] ?? '', ['owner', 'admin', 'member'])
	? $_POST['role'] : 'member';

if ($org_id <= 0 || $username === '') {
	$session->msg('d', 'Missing required fields.');
	redirect("edit_org.php?id=$org_id", false);
}

require_org_role('owner', 'admin');

global $db;
$user = $db->prepare_select_one(
	"SELECT id, name FROM users WHERE username = ? AND deleted_at IS NULL LIMIT 1",
	's', $username
);
if (!$user) {
	$session->msg('d', "User '" . h($username) . "' not found.");
	redirect("edit_org.php?id=$org_id", false);
}

$existing = $db->prepare_select_one(
	"SELECT 1 FROM org_members WHERE org_id = ? AND user_id = ?",
	'ii', $org_id, (int)$user['id']
);
if ($existing) {
	$session->msg('d', h($username) . ' is already a member of this organization.');
	redirect("edit_org.php?id=$org_id", false);
}

$db->prepare_query(
	"INSERT INTO org_members (org_id, user_id, role) VALUES (?, ?, ?)",
	'iis', $org_id, (int)$user['id'], $role
);
$session->msg('s', h($user['name']) . ' added as ' . $role . '.');
redirect("edit_org.php?id=$org_id", false);
