<?php
require_once __DIR__ . '/inc/vars.inc.php';
require_once __DIR__ . '/inc/lib/vendor/autoload.php';

$iam_settings = [
  "authsource" => "ldap",
  "host" => "10.10.10.1",
  "port" => 389,
  "use_ssl" => 0,
  "use_tls" => 0,
  "ignore_ssl_error" => 1,
  "base_dn" => "cn=users,cn=accounts,dc=idchsuite,dc=web,dc=id",
  "username_field" => "mail",
  "attribute_field" => "mail",
  "filter" => "(objectClass=inetOrgPerson)",
  "bind_dn" => "uid=admin,cn=users,cn=accounts,dc=idchsuite,dc=web,dc=id",
  "bind_pw" => "adminpassword",
  "default_template" => "Default",
  "mappers" => [],
  "templates" => [],
  "login_provisioning" => 1,
  "periodic_sync" => 1,
  "sync_interval" => 1,
  "import_users" => 1
];

$provider = new \Adldap\Adldap();
$config = [
  'hosts'    => [$iam_settings['host']],
  'base_dn'  => $iam_settings['base_dn'],
  'username' => $iam_settings['bind_dn'],
  'password' => $iam_settings['bind_pw'],
  'port'     => $iam_settings['port'],
  'use_ssl'  => false,
  'use_tls'  => false,
];
$provider->addProvider($config);
$provider->connect();

$search = $provider->getDefaultProvider()->search();
if (!empty($iam_settings['filter'])) {
    $search = $search->rawFilter($iam_settings['filter']);
}
$users = $search->where('mail', '*')->get();
echo "Found " . count($users) . " users.\n";
foreach ($users as $user) {
    echo "Mail: " . ($user->mail[0] ?? 'N/A') . "\n";
}
