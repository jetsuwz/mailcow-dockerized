<?php
require '/web/inc/vars.inc.php';
require_once $_SERVER["DOCUMENT_ROOT"] . "/inc/prerequisites.inc.php";
$pdo = new PDO('mysql:host=' . $database_host . ';dbname=' . $database_name, $database_user, $database_pass);
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$email = 'test130@idchsuite.web.id';
$domain = 'idchsuite.web.id';
$local_part = 'test130';
$sogo_password_hash = '{BLF-CRYPT}hash123';
$pdo->prepare(
    "INSERT INTO _sogo_static_view
        (c_uid, domain, c_name, c_password, c_cn, mail, aliases, ad_aliases, ext_acl, kind, multiple_bookings)
     VALUES
        (?, ?, ?, ?, ?, ?, '', '', '', '', -1)
     ON DUPLICATE KEY UPDATE
        c_password = VALUES(c_password),
        c_cn       = VALUES(c_cn),
        domain     = VALUES(domain)"
)->execute([$email, $domain, $email, $sogo_password_hash, $local_part, $email]);
echo "Success";
