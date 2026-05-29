<?php
require_once $_SERVER['DOCUMENT_ROOT'] . '/inc/prerequisites.inc.php';
header("Content-Type: text/plain");
echo "Read: " . $_SESSION['mailcow_cc_username'] . "\n";
?>
