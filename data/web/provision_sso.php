<?php
$email = $argv[1] ?? '';
if (empty($email)) die("Missing email");

require '/web/inc/vars.inc.php';

$pdo = new PDO('mysql:host=' . $database_host . ';dbname=' . $database_name, $database_user, $database_pass);
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

$parts = explode('@', $email);
$local_part = $parts[0];
$domain = $parts[1];

$sogo_sso_pass = trim(file_get_contents('/etc/sogo-sso/sogo-sso.pass'));
$sogo_password_hash = '{BLF-CRYPT}' . password_hash($sogo_sso_pass, PASSWORD_BCRYPT, ['cost' => 10]);

// 1. SOGo Static View
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

// 2. Mailbox
$stmt = $pdo->prepare("SELECT username FROM mailbox WHERE username = ?");
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
    
    $pdo->prepare("INSERT INTO mailbox 
        (username, password, name, local_part, domain, quota, attributes, custom_attributes, kind, multiple_bookings, authsource, active)
        VALUES 
        (?, '', ?, ?, ?, 3072, ?, '{}', '', -1, 'ldap', 1)")
        ->execute([$email, $local_part, $local_part, $domain, $attributes]);
}

// 3. Quotas, Aliases, ACL
$pdo->prepare("INSERT IGNORE INTO quota2 (username, bytes, messages) VALUES (?, 0, 0)")
    ->execute([$email]);
$pdo->prepare("INSERT IGNORE INTO quota2replica (username, bytes, messages) VALUES (?, 0, 0)")
    ->execute([$email]);
$pdo->prepare("INSERT IGNORE INTO alias (address, goto, domain, active) VALUES (?, ?, ?, 1)")
    ->execute([$email, $email, $domain]);
$pdo->prepare("INSERT IGNORE INTO user_acl
    (username, spam_alias, tls_policy, spam_score, spam_policy, delimiter_action,
     syncjobs, eas_reset, sogo_profile_reset, pushover, quarantine,
     quarantine_attachments, quarantine_notification, quarantine_category, app_passwds, pw_reset)
    VALUES (?,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0)")
    ->execute([$email]);

// 4. Maildir creation via doveadm
$maildir_base = '/var/vmail/' . $domain . '/' . $local_part;
if (!is_dir($maildir_base)) {
    shell_exec("doveadm mailbox create -u " . escapeshellarg($email) . " INBOX 2>&1");
    shell_exec("doveadm mailbox create -u " . escapeshellarg($email) . " Sent 2>&1");
    shell_exec("doveadm mailbox create -u " . escapeshellarg($email) . " Trash 2>&1");
    shell_exec("doveadm mailbox create -u " . escapeshellarg($email) . " Junk 2>&1");
    shell_exec("doveadm mailbox create -u " . escapeshellarg($email) . " Drafts 2>&1");
}
