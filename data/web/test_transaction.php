<?php
$_SERVER['DOCUMENT_ROOT'] = '/web';
require_once $_SERVER['DOCUMENT_ROOT'] . '/inc/prerequisites.inc.php';
require '/web/inc/vars.inc.php';
$sso_pdo = new PDO('mysql:host=' . $database_host . ';dbname=' . $database_name, $database_user, $database_pass);
echo "Default PDO in transaction: " . ($pdo->inTransaction() ? "YES" : "NO") . "\n";
echo "SSO PDO in transaction: " . ($sso_pdo->inTransaction() ? "YES" : "NO") . "\n";
