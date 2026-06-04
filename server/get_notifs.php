<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

$file = __DIR__ . '/data/notifs.json';
clearstatcache(true, $file);
if (!file_exists($file)) { echo '[]'; exit; }

$notifs = json_decode(file_get_contents($file), true) ?: [];
$public = array_map(fn($n) => [
    'id'      => $n['id']      ?? '',
    'title'   => $n['title']   ?? '',
    'body'    => $n['body']    ?? '',
    'sent_at' => $n['sent_at'] ?? '',
], array_slice(array_reverse($notifs), 0, 50));

echo json_encode($public);
