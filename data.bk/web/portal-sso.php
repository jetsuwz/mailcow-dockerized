<?php
// Script to bridge Next.js Portal SSO with Mailcow SOGo SSO
// Uses Mailcow REST API for mailbox provisioning, then sets session for auth_request

require_once $_SERVER['DOCUMENT_ROOT'] . '/inc/prerequisites.inc.php';

// Configuration
$SECRET_TOKEN   = 'IDCH_SUITE_PORTAL_SOGO_SSO_SECRET_XYZ_2026';
$MAILCOW_API    = 'http://nginx-mailcow/api/v1';
$MAILCOW_APIKEY = '4185c6c0706936619c1c1b2338bc326b200f5c83be36ea09';
$MAIL_DOMAIN    = 'idchsuite.web.id';

// Validate token
$token = $_GET['token'] ?? '';
if ($token !== $SECRET_TOKEN) {
    http_response_code(403);
    die('Forbidden: Invalid token');
}

// Validate email
$email = $_GET['email'] ?? '';
if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    http_response_code(400);
    die('Bad Request: Invalid email format');
}

// Only allow emails on our mail domain
$parts      = explode('@', $email);
$local_part = $parts[0];
$domain     = $parts[1];
if ($domain !== $MAIL_DOMAIN) {
    http_response_code(400);
    die('Bad Request: Invalid domain');
}

// Read sogo-sso password and generate correct BLF-CRYPT hash
$sogo_sso_pass  = trim(file_get_contents('/etc/sogo-sso/sogo-sso.pass'));
$sogo_password_hash = '{BLF-CRYPT}' . password_hash($sogo_sso_pass, PASSWORD_BCRYPT, ['cost' => 10]);

// --- Helper: call Mailcow internal API ---
function mailcow_api($method, $path, $data = null) {
    global $MAILCOW_API, $MAILCOW_APIKEY;
    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL            => $MAILCOW_API . $path,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_CUSTOMREQUEST  => $method,
        CURLOPT_HTTPHEADER     => [
            'X-Api-Key: ' . $MAILCOW_APIKEY,
            'Content-Type: application/json',
        ],
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_SSL_VERIFYHOST => false,
        CURLOPT_TIMEOUT        => 10,
    ]);
    if ($data !== null) {
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
    }
    $response = curl_exec($ch);
    $httpcode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    return ['code' => $httpcode, 'body' => json_decode($response, true)];
}

// --- Check if mailbox exists ---
$check = mailcow_api('GET', '/get/mailbox/' . urlencode($email));

if ($check['code'] !== 200 || empty($check['body']['username'])) {
    // Mailbox does not exist – create it via Mailcow API
    $pw_tmp = bin2hex(random_bytes(16));
    mailcow_api('POST', '/add/mailbox', [
        'local_part'          => $local_part,
        'domain'              => $domain,
        'name'                => $local_part,
        'quota'               => 3072, // MiB
        'password'            => $pw_tmp,
        'password2'           => $pw_tmp,
        'active'              => '1',
        'force_pw_update'     => '0',
        'tls_enforce_in'      => '0',
        'tls_enforce_out'     => '0',
        'sogo_access'         => '1',
        'imap_access'         => '1',
        'pop3_access'         => '1',
        'smtp_access'         => '1',
        'sieve_access'        => '1',
    ]);
    // Force authsource = ldap (API doesn't expose this)
    $pdo->prepare("UPDATE mailbox SET authsource='ldap', password='' WHERE username=:u")
        ->execute([':u' => $email]);
}

// Ensure quota2 / alias rows exist (idempotent)
$pdo->prepare("INSERT IGNORE INTO quota2 (username, bytes, messages) VALUES (:u, 0, 0)")
    ->execute([':u' => $email]);
$pdo->prepare("INSERT IGNORE INTO quota2replica (username, bytes, messages) VALUES (:u, 0, 0)")
    ->execute([':u' => $email]);
$pdo->prepare("INSERT IGNORE INTO alias (address, goto, domain, active) VALUES (:u, :u, :d, 1)")
    ->execute([':u' => $email, ':d' => $domain]);

// Ensure user is in _sogo_static_view with CORRECT BLF-CRYPT password
$pdo->prepare("INSERT INTO _sogo_static_view (c_uid, domain, c_name, c_password, c_cn, mail, aliases, ad_aliases, ext_acl, kind, multiple_bookings)
    SELECT username, domain, username, :pw_hash, name, username, '', '', '', '', -1
    FROM mailbox WHERE username = :u AND active = 1
    ON DUPLICATE KEY UPDATE c_password = VALUES(c_password), domain = VALUES(domain), c_cn = VALUES(c_cn)")
    ->execute([':pw_hash' => $sogo_password_hash, ':u' => $email]);

// Set Mailcow SSO session variables
$_SESSION['sogo-sso-user-allowed'] = [$email];
$_SESSION['sogo-sso-pass']         = $sogo_sso_pass;
$_SESSION['mailcow_cc_username']   = $email;
$_SESSION['mailcow_cc_role']       = 'user';
$_SESSION['pending_pw_update']     = false;
$_SESSION['pending_tfa_setup']     = false;
unset($_SESSION['dual-login']);

// Redirect to SOGo (Nginx auth_request will read session via same MCSESSID cookie)
header('Location: /SOGo/so/');
exit;
