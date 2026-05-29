<?php
require_once __DIR__ . '/inc/prerequisites.inc.php';

$_SESSION['mailcow_cc_username'] = 'admin';
$_SESSION['mailcow_cc_role'] = 'admin';
$_SESSION['access_all_exception'] = '1';

$email = 'ecep@idchsuite.web.id';
$domain = 'idchsuite.web.id';
$local_part = 'ecep';

echo "Attempting to create mailbox...\n";
update_sogo_static_view('ecep@idchsuite.web.id');
echo "Updated Sogo view for ecep\n";

echo "Result: " . ($res ? 'true' : 'false') . "\n";
echo "Session return: \n";
print_r($_SESSION['return']);
