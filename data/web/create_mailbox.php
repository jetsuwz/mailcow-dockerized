<?php
$_SERVER['DOCUMENT_ROOT'] = '/web';
require_once '/web/inc/prerequisites.inc.php';

$email = $argv[1] ?? '';
if (empty($email)) die("Missing email");

$parts = explode('@', $email);
$local_part = $parts[0];
$domain = $parts[1];

$stmt = $pdo->prepare("SELECT username FROM mailbox WHERE username = ?");
$stmt->execute([$email]);
if ($stmt->fetchColumn()) {
    die("Mailbox exists");
}

$pw_tmp = bin2hex(random_bytes(16));

// 1. Create mailbox using native Mailcow function
mailbox('add', 'mailbox', [
    'local_part'      => $local_part,
    'domain'          => $domain,
    'name'            => $local_part,
    'quota'           => 3072,
    'password'        => $pw_tmp,
    'password2'       => $pw_tmp,
    'active'          => '1',
    'force_pw_update' => '0',
    'tls_enforce_in'  => '0',
    'tls_enforce_out' => '0',
    'sogo_access'     => '1',
    'imap_access'     => '1',
    'pop3_access'     => '1',
    'smtp_access'     => '1',
    'sieve_access'    => '1',
]);

// 2. Force ldap authsource
$pdo->prepare("UPDATE mailbox SET authsource='ldap', password='' WHERE username=?")
    ->execute([$email]);

// 3. Update SOGo static view with SSO hash
$sogo_sso_pass = trim(file_get_contents('/etc/sogo-sso/sogo-sso.pass'));
$sogo_password_hash = '{BLF-CRYPT}' . password_hash($sogo_sso_pass, PASSWORD_BCRYPT, ['cost' => 10]);

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

if (function_exists('flush_memcached')) {
    flush_memcached();
}

// 4. Trigger doveadm to create the maildir
$maildir_base = '/var/vmail/' . $domain . '/' . $local_part;
if (!is_dir($maildir_base)) {
    shell_exec("doveadm mailbox create -u " . escapeshellarg($email) . " INBOX 2>&1");
    shell_exec("doveadm mailbox create -u " . escapeshellarg($email) . " Sent 2>&1");
    shell_exec("doveadm mailbox create -u " . escapeshellarg($email) . " Trash 2>&1");
    shell_exec("doveadm mailbox create -u " . escapeshellarg($email) . " Junk 2>&1");
    shell_exec("doveadm mailbox create -u " . escapeshellarg($email) . " Drafts 2>&1");
}

echo "Created successfully\n";
