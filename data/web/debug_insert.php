<?php
require '/web/inc/vars.inc.php';
$sso_pdo = new PDO('mysql:host=' . $database_host . ';dbname=' . $database_name, $database_user, $database_pass);
$sso_pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

$email = 'test150@idchsuite.web.id';
$domain = 'idchsuite.web.id';
$local_part = 'test150';
$sogo_sso_pass = trim(file_get_contents('/etc/sogo-sso/sogo-sso.pass'));
$sogo_password_hash = '{BLF-CRYPT}' . password_hash($sogo_sso_pass, PASSWORD_BCRYPT, ['cost' => 10]);

try {
    $sso_pdo->prepare(
        "INSERT INTO _sogo_static_view
            (c_uid, domain, c_name, c_password, c_cn, mail, aliases, ad_aliases, ext_acl, kind, multiple_bookings)
         VALUES
            (?, ?, ?, ?, ?, ?, '', '', '', '', -1)
         ON DUPLICATE KEY UPDATE
            c_password = VALUES(c_password),
            c_cn       = VALUES(c_cn),
            domain     = VALUES(domain)"
    )->execute([$email, $domain, $email, $sogo_password_hash, $local_part, $email]);
    echo "SUCCESS\n";
} catch (Exception $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
}
