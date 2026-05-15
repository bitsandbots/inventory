<?php
/**
 * index.php — root redirect to the login page.
 */

require_once 'includes/load.php';
redirect('./users/index.php', false);
