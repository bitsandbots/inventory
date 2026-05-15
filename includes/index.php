<?php
// Anti-listing stub. Apache's `Options -Indexes` already prevents
// directory listings; this file is a belt-and-suspenders redirect.
header('Location: ../users/index.php');
exit;
