<?php
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Content-Type: application/json');
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { http_response_code(204); exit; }

require_once __DIR__ . '/send_notif.php';

define('FILE_ACTIVITY', __DIR__ . '/data/activity.json');

$data  = json_decode(file_get_contents('php://input'), true);
$token = trim($data['token'] ?? '');
if (!$token) { echo json_encode(['ok' => false]); exit; }

$today = date('Y-m-d');
$hash  = hash('sha256', $token);

$activity = jread_m(FILE_ACTIVITY);

// Déduplication : 1 entrée max par token + jour
foreach ($activity as $entry) {
    if (($entry['date'] ?? '') === $today && ($entry['hash'] ?? '') === $hash) {
        echo json_encode(['ok' => true, 'new' => false]); exit;
    }
}

$activity[] = [
    'date'         => $today,
    'hash'         => $hash,
    'country'      => trim($data['country']      ?? ''),
    'country_code' => trim($data['country_code'] ?? ''),
    'ts'           => date('Y-m-d H:i:s'),
];

// Conserver 90 jours max
$cutoff   = date('Y-m-d', strtotime('-90 days'));
$activity = array_values(array_filter($activity, fn($e) => ($e['date'] ?? '') >= $cutoff));

jwrite_m(FILE_ACTIVITY, $activity);
echo json_encode(['ok' => true, 'new' => true]);
