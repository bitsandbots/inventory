<?php
/**
 * users/restore.php
 *
 * POST endpoint to restore a soft-deleted row. CSRF + admin gated.
 */

require_once '../includes/load.php';
page_require_level(1);

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect('trash.php', false);
}
if (!verify_csrf()) {
    $session->msg('d', 'Invalid or missing security token.');
    redirect('trash.php', false);
}

// SOFT_DELETE_TABLES is defined in includes/sql.php (Task 7).
$table = isset($_POST['table']) ? (string)$_POST['table'] : '';
$id    = isset($_POST['id']) ? (int)$_POST['id'] : 0;

if (!in_array($table, SOFT_DELETE_TABLES, true) || $id <= 0) {
    $session->msg('d', 'Invalid restore request.');
    redirect('trash.php', false);
}

if (restore_by_id($table, $id)) {
    $session->msg('s', ucfirst($table) . " row #{$id} restored.");
} else {
    $session->msg('d', "Restore failed (row #{$id} not soft-deleted, or table not in scope).");
}
redirect('trash.php?table=' . urlencode($table), false);
