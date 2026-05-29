<?php
require_once $_SERVER['DOCUMENT_ROOT'] . '/inc/prerequisites.inc.php';
header("Content-Type: application/json");
$_SESSION['mailcow_cc_role'] = 'admin';
$_SESSION['mailcow_cc_username'] = 'admin';
$result = mailbox('get', 'mailboxes');
echo json_encode($result);
?>
