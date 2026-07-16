<?php
header('Content-Type: application/json');
header('Cache-Control: no-cache');
header('Access-Control-Allow-Origin: *');

// Proxy vers la production — les pronos manuels sont gérés sur mola-prono.online
// L'admin ajoute UNE SEULE FOIS → apparaît sur le site web ET dans l'app mobile
$url  = 'https://mola-prono.online/get_manual_pronos.php';
$data = @file_get_contents($url);

if ($data === false) {
    // Fallback : lire les données locales si le proxy échoue
    require_once __DIR__ . '/config.php';
    $today  = date('Y-m-d');
    $file   = __DIR__ . '/data/manual_pronos.json';
    $all    = jread($file);
    $active = array_values(array_filter($all, function($p) use ($today) {
        return ($p['display_date'] ?? '') === $today;
    }));
    echo json_encode($active, JSON_UNESCAPED_UNICODE);
    exit;
}

echo $data;
