<?php
session_start();
require_once __DIR__ . '/config.php';

if (!isset($_SESSION['admin'])) {
    http_response_code(403);
    die(json_encode(['error' => 'Non autorisé']));
}

header('Content-Type: application/json');
$raw  = file_get_contents('php://input');
$data = json_decode($raw, true);

if (!$data || !isset($data['prediction'])) {
    die(json_encode(['error' => 'Données manquantes']));
}

$today    = date('Y-m-d');
$tomorrow = date('Y-m-d', strtotime('+1 day'));

$schedule     = $data['schedule'] ?? 'today';
$display_date = ($schedule === 'tomorrow') ? $tomorrow : $today;

$prono = [
    'id'           => uniqid('mp_', true),
    'selected_at'  => date('Y-m-d H:i:s'),
    'display_date' => $display_date,
    'schedule'     => $schedule,
    'fixture_id'   => $data['fixture_id']   ?? 0,
    'league'       => $data['league']       ?? '',
    'league_logo'  => $data['league_logo']  ?? '',
    'home_team'    => $data['home_team']    ?? '',
    'home_logo'    => $data['home_logo']    ?? '',
    'away_team'    => $data['away_team']    ?? '',
    'away_logo'    => $data['away_logo']    ?? '',
    'kick_off'     => $data['kick_off']     ?? '',
    'markets'      => $data['markets']      ?? (object)[],
    'prediction'   => $data['prediction'],
    'display_mode' => $data['display_mode'] ?? 'yn',
];

$file   = __DIR__ . '/data/manual_pronos.json';
$pronos = jread($file);

// Auto-nettoyage : supprimer les pronos affichés avant aujourd'hui (plus d'une semaine)
$cutoff = date('Y-m-d', strtotime('-7 days'));
$pronos = array_values(array_filter($pronos, function($p) use ($cutoff) {
    return ($p['display_date'] ?? '') >= $cutoff;
}));

$pronos[] = $prono;
jwrite($file, $pronos);

echo json_encode(['ok' => true, 'id' => $prono['id']], JSON_UNESCAPED_UNICODE);
