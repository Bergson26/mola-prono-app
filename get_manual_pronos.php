<?php
require_once __DIR__ . '/config.php';

header('Content-Type: application/json');
header('Cache-Control: no-cache');
header('Access-Control-Allow-Origin: *');

$today  = date('Y-m-d');
$file   = __DIR__ . '/data/manual_pronos.json';
$all    = jread($file);

$active = array_values(array_filter($all, function($p) use ($today) {
    return ($p['display_date'] ?? '') === $today;
}));

echo json_encode($active, JSON_UNESCAPED_UNICODE);
