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
$id   = $data['id'] ?? '';

if (!$id) {
    die(json_encode(['error' => 'ID manquant']));
}

$file   = __DIR__ . '/data/manual_pronos.json';
$pronos = jread($file);
$pronos = array_values(array_filter($pronos, fn($p) => ($p['id'] ?? '') !== $id));
jwrite($file, $pronos);

echo json_encode(['ok' => true], JSON_UNESCAPED_UNICODE);
