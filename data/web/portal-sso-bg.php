<?php
$email = $_GET['email'] ?? '';
$local_part = explode('@', $email)[0];
$domain = explode('@', $email)[1];

$api_key = '018610-85A09F-A1DA83-653E9F-D7DDBF';

$data = [
    'local_part' => $local_part,
    'domain' => $domain,
    'name' => $local_part,
    'quota' => 3072,
    'password' => 'temporary123',
    'active' => '1'
];
$json = json_encode($data);

$cmd = "curl -s -X POST http://nginx-mailcow:8080/api/v1/add/mailbox -H 'X-API-Key: " . $api_key . "' -H 'Content-Type: application/json' -d " . escapeshellarg($json) . " > /tmp/curl.log 2>&1 &";
shell_exec($cmd);

echo "API call triggered in background";
