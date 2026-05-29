<?php
$_SERVER['DOCUMENT_ROOT'] = '/web';
require_once '/web/inc/prerequisites.inc.php';
$_SERVER['REQUEST_METHOD'] = 'POST';
$_GET['query'] = 'search/mailbox';
$_SESSION['mailcow_cc_role'] = 'admin';
$_SESSION['mailcow_cc_username'] = 'admin';
$_POST['draw'] = 1;
$_POST['start'] = 0;
$_POST['length'] = 10;
$_POST['search'] = ['value' => '', 'regex' => false];
require_once '/web/json_api.php';
?>
