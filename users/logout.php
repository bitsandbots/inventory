<?php
/**
 * logout.php
 *
 * @package default
 */


require_once '../includes/load.php';
if (!$session->logout()) {redirect("index.php");}
?>
