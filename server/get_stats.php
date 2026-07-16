<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

require_once __DIR__ . '/send_notif.php';

define('FILE_DOWNLOADS', __DIR__ . '/data/downloads.json');

$downloads = jread_m(FILE_DOWNLOADS);
$tokens    = jread_m(FILE_TOKENS_M);
$notifs    = jread_m(FILE_NOTIFS_M);

// ── Téléchargements totaux ──
$total_downloads = count($downloads);

// ── Par jour (14 derniers jours) ──
$by_day = [];
foreach ($downloads as $d) {
    $date = $d['date'] ?? date('Y-m-d');
    $by_day[$date] = ($by_day[$date] ?? 0) + 1;
}
krsort($by_day);
$by_day_arr = [];
foreach (array_slice($by_day, 0, 14, true) as $date => $count) {
    $by_day_arr[] = ['date' => $date, 'count' => $count];
}

// ── Par pays (top 10) ──
$by_country = [];
foreach ($downloads as $d) {
    $c = $d['country'] ?: ($d['country_code'] ?: 'Inconnu');
    $by_country[$c] = ($by_country[$c] ?? 0) + 1;
}
arsort($by_country);
$by_country_arr = [];
foreach (array_slice($by_country, 0, 10, true) as $name => $count) {
    $by_country_arr[] = ['country' => $name, 'count' => $count];
}

// ── Appareils actifs (tokens FCM) ──
$active_tokens = count($tokens);

// ── Ouvertures totales ──
$total_opens = 0;
foreach ($notifs as $n) {
    $total_opens += intval($n['open_count'] ?? 0);
}

// ── Ouvertures par notification (top 5) ──
$notifs_sorted = $notifs;
usort($notifs_sorted, fn($a, $b) => intval($b['open_count'] ?? 0) - intval($a['open_count'] ?? 0));
$top_notifs = array_slice($notifs_sorted, 0, 5);

echo json_encode([
    'total_downloads'      => $total_downloads,
    'downloads_by_day'     => $by_day_arr,
    'downloads_by_country' => $by_country_arr,
    'active_tokens'        => $active_tokens,
    'total_opens'          => $total_opens,
    'total_notifs'         => count($notifs),
    'top_notifs'           => $top_notifs,
], JSON_UNESCAPED_UNICODE);
