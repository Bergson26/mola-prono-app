<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') { echo json_encode(['ok' => false]); exit; }

$input    = json_decode(file_get_contents('php://input'), true);
$notif_id = trim($input['notif_id'] ?? '');
if (!$notif_id) { echo json_encode(['ok' => false]); exit; }

require_once __DIR__ . '/send_notif.php';

$notifs = jread_m(FILE_NOTIFS_M);
$found  = false;

foreach ($notifs as &$n) {
    if (($n['id'] ?? '') === $notif_id) {
        $n['open_count'] = ($n['open_count'] ?? 0) + 1;
        $found = true;
        break;
    }
}

if ($found) jwrite_m(FILE_NOTIFS_M, $notifs);
echo json_encode(['ok' => $found]);
