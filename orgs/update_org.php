<?php
require_once '../includes/load.php';
page_require_level(1);

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !verify_csrf()) {
	$session->msg('d', 'Invalid request.');
	redirect('orgs.php', false);
}

$org_id = (int)($_POST['org_id'] ?? 0);
$name   = trim(remove_junk($_POST['name'] ?? ''));

if ($name === '') {
	$session->msg('d', "Organization name can't be blank.");
	$back = $org_id > 0 ? "edit_org.php?id=$org_id" : 'orgs.php';
	redirect($back, false);
}

if ($org_id > 0) {
	// Rename existing org
	$org = find_org_by_id($org_id);
	if (!$org) {
		$session->msg('d', 'Organization not found.');
		redirect('orgs.php', false);
	}
	require_org_role('owner', 'admin');
	rename_org($org_id, $name);
	$session->msg('s', 'Organization renamed successfully.');
	redirect("edit_org.php?id=$org_id", false);
} else {
	// Create new org
	$current = current_user();
	$new_id  = create_org($name, (int)$current['id']);
	if (!$new_id) {
		$session->msg('d', 'Failed to create organization. Name may already be taken.');
		redirect('orgs.php', false);
	}
	$session->msg('s', 'Organization created successfully.');
	redirect("edit_org.php?id=$new_id", false);
}
