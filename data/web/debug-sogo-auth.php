<?php
$_SERVER['DOCUMENT_ROOT'] = '/web';
$_SERVER['HTTP_X_ORIGINAL_URI'] = '/SOGo/so/';
$_SERVER['HTTP_X_REAL_IP'] = '1.1.1.1';
$_GET['login'] = 'noreply@idchsuite.web.id';
require_once '/web/inc/prerequisites.inc.php';
$_SESSION['mailcow_cc_username'] = 'noreply@idchsuite.web.id';
$_SESSION['mailcow_cc_role'] = 'user';
$_SESSION['dual-login'] = ['username' => 'admin', 'role' => 'admin'];
ob_start();
include '/web/sogo-auth.php';
$output = ob_get_clean();
echo "HTTP Status: " . http_response_code() . "\n";
echo "Headers: \n";
print_r(headers_list());
echo "Output: \n" . $output;
?>
