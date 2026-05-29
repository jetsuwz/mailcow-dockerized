<?php
require_once $_SERVER['DOCUMENT_ROOT'] . '/inc/prerequisites.inc.php';

$MAIL_DOMAIN    = 'idchsuite.web.id';
$SECRET_TOKEN   = 'IDCH_SUITE_PORTAL_SOGO_SSO_SECRET_XYZ_2026';

try {
    $token = $_GET['token'] ?? '';
    if ($token !== $SECRET_TOKEN) {
        http_response_code(403);
        die('Forbidden: Invalid token');
    }

    $email = trim($_GET['email'] ?? '');
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        http_response_code(400);
        die('Bad Request: Invalid email format');
    }

    $parts      = explode('@', $email);
    $local_part = $parts[0];
    $domain     = $parts[1];

    if ($domain !== $MAIL_DOMAIN) {
        http_response_code(400);
        die('Bad Request: Invalid domain');
    }

    // Connect DB
    require '/web/inc/vars.inc.php';
    $sso_pdo = new PDO('mysql:host=' . $database_host . ';dbname=' . $database_name, $database_user, $database_pass);
    $sso_pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // Hash pass
    $sogo_sso_pass = trim(file_get_contents('/etc/sogo-sso/sogo-sso.pass'));
    $sogo_password_hash = '{BLF-CRYPT}' . password_hash($sogo_sso_pass, PASSWORD_BCRYPT, ['cost' => 10]);

    // Session
    $_SESSION['sogo-sso-user-allowed'] = [$email];
    $_SESSION['sogo-sso-pass']         = $sogo_sso_pass;
    $_SESSION['mailcow_cc_username']   = $email;
    $_SESSION['mailcow_cc_role']       = 'user';
    $_SESSION['pending_pw_update']     = false;
    $_SESSION['pending_tfa_setup']     = false;
    unset($_SESSION['dual-login']);

    // INSERT SOGo via CLI bypass
    $mysql_cmd = sprintf(
        "mysql -h %s -u %s -p'%s' mailcow -e \"INSERT INTO _sogo_static_view (c_uid, domain, c_name, c_password, c_cn, mail, aliases, ad_aliases, ext_acl, kind, multiple_bookings) VALUES ('%s', '%s', '%s', '%s', '%s', '%s', '', '', '', '', -1) ON DUPLICATE KEY UPDATE c_password = VALUES(c_password), c_cn = VALUES(c_cn), domain = VALUES(domain)\" 2>&1",
        escapeshellarg($database_host),
        escapeshellarg($database_user),
        $database_pass,
        addslashes($email),
        addslashes($domain),
        addslashes($email),
        addslashes($sogo_password_hash),
        addslashes($local_part),
        addslashes($email)
    );
    $output = shell_exec($mysql_cmd);
    if ($output) {
        die("MYSQL ERROR: " . $output);
    }

    // INSERT Mailbox
    $stmt = $sso_pdo->prepare("SELECT username FROM mailbox WHERE username = ?");
    $stmt->execute([$email]);
    if (!$stmt->fetchColumn()) {
        $attributes = json_encode([
            'force_pw_update' => '0',
            'tls_enforce_in'  => '0',
            'tls_enforce_out' => '0',
            'sogo_access'     => '1',
            'imap_access'     => '1',
            'pop3_access'     => '1',
            'smtp_access'     => '1',
            'sieve_access'    => '1',
        ]);
        
        $sso_pdo->prepare("INSERT INTO mailbox 
            (username, password, name, local_part, domain, quota, attributes, custom_attributes, kind, multiple_bookings, authsource, active)
            VALUES 
            (?, '', ?, ?, ?, 3072, ?, '{}', '', -1, 'ldap', 1)")
            ->execute([$email, $local_part, $local_part, $domain, $attributes]);
    }

    // Quotas, Aliases, ACL
    $sso_pdo->prepare("INSERT IGNORE INTO quota2 (username, bytes, messages) VALUES (?, 0, 0)")
        ->execute([$email]);
    $sso_pdo->prepare("INSERT IGNORE INTO quota2replica (username, bytes, messages) VALUES (?, 0, 0)")
        ->execute([$email]);
    $sso_pdo->prepare("INSERT IGNORE INTO alias (address, goto, domain, active) VALUES (?, ?, ?, 1)")
        ->execute([$email, $email, $domain]);
    $sso_pdo->prepare("INSERT IGNORE INTO user_acl
        (username, spam_alias, tls_policy, spam_score, spam_policy, delimiter_action,
         syncjobs, eas_reset, sogo_profile_reset, pushover, quarantine,
         quarantine_attachments, quarantine_notification, quarantine_category, app_passwds, pw_reset)
        VALUES (?,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0)")
        ->execute([$email]);

    // Maildir creation
    $maildir_base = '/var/vmail/' . $domain . '/' . $local_part;
    if (!is_dir($maildir_base)) {
        shell_exec("doveadm mailbox create -u " . escapeshellarg($email) . " INBOX 2>&1");
        shell_exec("doveadm mailbox create -u " . escapeshellarg($email) . " Sent 2>&1");
        shell_exec("doveadm mailbox create -u " . escapeshellarg($email) . " Trash 2>&1");
        shell_exec("doveadm mailbox create -u " . escapeshellarg($email) . " Junk 2>&1");
        shell_exec("doveadm mailbox create -u " . escapeshellarg($email) . " Drafts 2>&1");
    }

    // Redirect to SOGo
    header('Location: /SOGo/so/');
    exit;

} catch (Throwable $e) {
    http_response_code(500);
    echo "Exception: " . $e->getMessage();
}

