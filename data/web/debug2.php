<?php
require_once $_SERVER['DOCUMENT_ROOT'] . '/inc/prerequisites.inc.php';
header("Content-Type: text/plain");
$_SESSION['mailcow_cc_username'] = 'ecep@idchsuite.web.id';
session_write_close();
echo "Wrote ecep to session\n";
?>
