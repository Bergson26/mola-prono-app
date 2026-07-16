<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

define('LOG_FILE', __DIR__ . '/visitors_log.json');
define('MAX_ENTRIES', 2000);

function getRealIP() {
    $candidates = [
        $_SERVER['HTTP_CF_CONNECTING_IP'] ?? '',
        explode(',', $_SERVER['HTTP_X_FORWARDED_FOR'] ?? '')[0],
        $_SERVER['HTTP_X_REAL_IP'] ?? '',
        $_SERVER['REMOTE_ADDR'] ?? '',
    ];
    foreach ($candidates as $ip) {
        $ip = trim($ip);
        if ($ip && filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
            return $ip;
        }
    }
    return $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
}

$ip  = getRealIP();
$now = date('Y-m-d H:i:s');
$ua  = substr($_SERVER['HTTP_USER_AGENT'] ?? 'Inconnu', 0, 250);
$ref = substr($_SERVER['HTTP_REFERER'] ?? '', 0, 200);

// Geolocalisation IP via ip-api.com (gratuit, 1000 req/min)
$geo = ['country' => '—', 'city' => '—', 'region' => '—'];
$geoUrl = "http://ip-api.com/json/{$ip}?fields=status,country,city,regionName&lang=fr";
$ctx = stream_context_create(['http' => ['timeout' => 4, 'ignore_errors' => true]]);
$raw = @file_get_contents($geoUrl, false, $ctx);
if ($raw) {
    $d = json_decode($raw, true);
    if (is_array($d) && ($d['status'] ?? '') === 'success') {
        $geo['country'] = $d['country']    ?? '—';
        $geo['city']    = $d['city']       ?? '—';
        $geo['region']  = $d['regionName'] ?? '—';
    }
}

// Lecture du fichier log existant
$entries = [];
if (file_exists(LOG_FILE)) {
    $content = file_get_contents(LOG_FILE);
    if ($content) {
        $entries = json_decode($content, true) ?: [];
    }
}

// Ajout de la nouvelle entrée
$entries[] = [
    'ip'      => $ip,
    'country' => $geo['country'],
    'city'    => $geo['city'],
    'region'  => $geo['region'],
    'date'    => $now,
    'ua'      => $ua,
    'ref'     => $ref,
];

// Garder seulement les MAX_ENTRIES dernières
if (count($entries) > MAX_ENTRIES) {
    $entries = array_slice($entries, -MAX_ENTRIES);
}

file_put_contents(LOG_FILE, json_encode($entries, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE), LOCK_EX);

echo json_encode(['ok' => true]);
