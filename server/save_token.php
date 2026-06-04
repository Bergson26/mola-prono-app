<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { exit; }
if ($_SERVER['REQUEST_METHOD'] !== 'POST')    { echo json_encode(['ok' => false]); exit; }

$input = json_decode(file_get_contents('php://input'), true);
$token = trim($input['token'] ?? '');
if (!$token) { echo json_encode(['ok' => false, 'error' => 'no token']); exit; }

require_once __DIR__ . '/send_notif.php';

$tokens = jread_m(FILE_TOKENS_M);

// Mettre à jour last_seen si token déjà connu
foreach ($tokens as &$t) {
    if (($t['token'] ?? '') === $token) {
        $t['last_seen'] = date('Y-m-d H:i:s');
        jwrite_m(FILE_TOKENS_M, $tokens);
        echo json_encode(['ok' => true, 'new' => false]);
        exit;
    }
}

// Récupérer pays/ville depuis le client ou depuis l'IP serveur
$country      = trim($input['country']      ?? '');
$country_code = strtolower(trim($input['country_code'] ?? ''));
$city         = trim($input['city']         ?? '');

if (!$country) {
    $ip = trim(explode(',', $_SERVER['HTTP_X_FORWARDED_FOR'] ?? $_SERVER['REMOTE_ADDR'] ?? '')[0]);
    if ($ip && $ip !== '127.0.0.1' && filter_var($ip, FILTER_VALIDATE_IP)) {
        $geo = @file_get_contents("http://ip-api.com/json/{$ip}?fields=country,countryCode,city");
        if ($geo) {
            $g            = json_decode($geo, true) ?: [];
            $country      = $g['country']     ?? '';
            $country_code = strtolower($g['countryCode'] ?? '');
            $city         = $g['city']        ?? '';
        }
    }
}

$tokens[] = [
    'token'        => $token,
    'country'      => $country,
    'country_code' => $country_code,
    'city'         => $city,
    'installed_at' => date('Y-m-d H:i:s'),
    'last_seen'    => date('Y-m-d H:i:s'),
];

jwrite_m(FILE_TOKENS_M, $tokens);
echo json_encode(['ok' => true, 'new' => true]);
