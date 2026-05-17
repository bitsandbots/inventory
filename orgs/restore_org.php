<?php
require_once '../includes/load.php';
page_require_level(1);

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !verify_csrf()) {
	$session->msg('d', 'Invalid request.');
	redirect('orgs.php', false);
}

$org_id = (int)($_POST['org_id'] ?? 0);
$org    = $org_id > 0 ? find_org_by_id($org_id) : null;
if (!$org || !$org['deleted_at']) {
	$session->msg('d', 'Organization not found or not deleted.');
	redirect('orgs.php', false);
}

global $db;
$db->prepare_query(
	"UPDATE orgs SET deleted_at = NULL WHERE id = ?",
	'i', $org_id
);
$session->msg('s', 'Organization restored.');
redirect('orgs.php', false);
