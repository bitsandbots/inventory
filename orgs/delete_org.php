<?php
require_once '../includes/load.php';
page_require_level(1);

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !verify_csrf()) {
	$session->msg('d', 'Invalid request.');
	redirect('orgs.php', false);
}

$org_id = (int)($_POST['org_id'] ?? 0);
$org    = $org_id > 0 ? find_org_by_id($org_id) : null;
if (!$org || $org['deleted_at']) {
	$session->msg('d', 'Organization not found or already deleted.');
	redirect('orgs.php', false);
}

require_org_role('owner', 'admin');

global $db;
$db->prepare_query(
	"UPDATE orgs SET deleted_at = NOW() WHERE id = ?",
	'i', $org_id
);
$session->msg('s', 'Organization soft-deleted. Members remain enrolled and can be restored.');
redirect('orgs.php', false);
