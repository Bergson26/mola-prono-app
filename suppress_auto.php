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

$fixture_id = (int)($data['fixture_id'] ?? 0);
if (!$fixture_id) die(json_encode(['error' => 'fixture_id manquant']));

$file = __DIR__ . '/data/suppressed_auto.json';
$list = file_exists($file) ? json_decode(file_get_contents($file), true) : [];
if (!is_array($list)) $list = [];

$action = $data['action'] ?? 'add';
if ($action === 'remove') {
    $list = array_values(array_filter($list, fn($id) => $id !== $fixture_id));
} else {
    if (!in_array($fixture_id, $list)) $list[] = $fixture_id;
}

file_put_contents($file, json_encode($list, JSON_UNESCAPED_UNICODE));
echo json_encode(['ok' => true]);
