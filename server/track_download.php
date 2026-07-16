<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { exit; }

define('FILE_DOWNLOADS', __DIR__ . '/data/downloads.json');

function dl_jread($f) {
    if (!file_exists($f)) return [];
    $c = file_get_contents($f);
    return $c ? (json_decode($c, true) ?: []) : [];
}

function dl_jwrite($f, $data) {
    $dir = dirname($f);
    if (!is_dir($dir)) mkdir($dir, 0755, true);
    $fp = fopen($f, 'c+');
    if (!$fp) return;
    flock($fp, LOCK_EX);
    ftruncate($fp, 0); rewind($fp);
    fwrite($fp, json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
    flock($fp, LOCK_UN); fclose($fp);
}

// Géolocalisation par IP
$ip = trim(explode(',', $_SERVER['HTTP_X_FORWARDED_FOR'] ?? $_SERVER['REMOTE_ADDR'] ?? '')[0]);
$country = ''; $country_code = '';

if ($ip && $ip !== '127.0.0.1' && filter_var($ip, FILTER_VALIDATE_IP)) {
    $geo = @file_get_contents("http://ip-api.com/json/{$ip}?fields=country,countryCode");
    if ($geo) {
        $g            = json_decode($geo, true) ?: [];
        $country      = $g['country']     ?? '';
        $country_code = strtolower($g['countryCode'] ?? '');
    }
}

$downloads   = dl_jread(FILE_DOWNLOADS);
$downloads[] = [
    'date'         => date('Y-m-d'),
    'time'         => date('Y-m-d H:i:s'),
    'ip'           => $ip,
    'country'      => $country,
    'country_code' => $country_code,
];

dl_jwrite(FILE_DOWNLOADS, $downloads);
echo json_encode(['ok' => true, 'total' => count($downloads)]);
