<?php
header('Content-Type: application/json');
header('Cache-Control: no-cache');
header('Access-Control-Allow-Origin: *');

// Proxy vers la production — liste des pronos auto supprimés gérée sur mola-prono.online
$url  = 'https://mola-prono.online/get_suppressed.php';
$data = @file_get_contents($url);

if ($data === false) {
    // Fallback local si le proxy échoue
    $file = __DIR__ . '/data/suppressed_auto.json';
    $list = file_exists($file) ? json_decode(file_get_contents($file), true) : [];
    echo json_encode(is_array($list) ? $list : []);
    exit;
}

echo $data;
