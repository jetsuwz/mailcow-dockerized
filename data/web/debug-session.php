<?php
require_once $_SERVER['DOCUMENT_ROOT'] . '/inc/prerequisites.inc.php';
header("Content-Type: text/plain");
echo "MCSESSID: " . $_COOKIE['MCSESSID'] . "\n";
echo "mailcow_cc_username: " . $_SESSION['mailcow_cc_username'] . "\n";
echo "sogo-sso-user-allowed: " . print_r($_SESSION['sogo-sso-user-allowed'], true) . "\n";
echo "pending_pw_update: " . var_export($_SESSION['pending_pw_update'], true) . "\n";
echo "pending_tfa_setup: " . var_export($_SESSION['pending_tfa_setup'], true) . "\n";
?>
