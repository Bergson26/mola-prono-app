<?php
$apk_path = __DIR__ . '/app/mola-prono-latest.apk';

if (!file_exists($apk_path)) {
    http_response_code(404);
    header('Content-Type: text/html; charset=UTF-8');
    echo '<!DOCTYPE html><html lang="fr"><head><meta charset="UTF-8"><title>Mola Prono</title>
    <style>body{font-family:sans-serif;background:#07101e;color:#e8f0fe;display:flex;align-items:center;justify-content:center;min-height:100vh;text-align:center;padding:20px;}
    h1{color:#22c55e;font-size:22px;margin-bottom:10px;}p{color:#5a7394;}</style></head>
    <body><div><h1>⚽ Mola Prono</h1><p>L\'APK sera disponible très prochainement.<br>Revenez dans quelques heures !</p></div></body></html>';
    exit;
}

// ── Tracking du téléchargement ──────────────────────────────
$dl_file = __DIR__ . '/server/data/downloads.json';
$dl_dir  = dirname($dl_file);
if (!is_dir($dl_dir)) @mkdir($dl_dir, 0755, true);

$list = file_exists($dl_file) ? (json_decode(file_get_contents($dl_file), true) ?: []) : [];

$ip           = trim(explode(',', $_SERVER['HTTP_X_FORWARDED_FOR'] ?? $_SERVER['REMOTE_ADDR'] ?? '')[0]);
$country      = '';
$country_code = '';

if ($ip && $ip !== '127.0.0.1' && filter_var($ip, FILTER_VALIDATE_IP)) {
    $geo = @file_get_contents("http://ip-api.com/json/{$ip}?fields=country,countryCode");
    if ($geo) {
        $g            = json_decode($geo, true) ?: [];
        $country      = $g['country']              ?? '';
        $country_code = strtolower($g['countryCode'] ?? '');
    }
}

$list[] = [
    'date'         => date('Y-m-d'),
    'time'         => date('Y-m-d H:i:s'),
    'ip'           => $ip,
    'country'      => $country,
    'country_code' => $country_code,
];

$fp = @fopen($dl_file, 'c+');
if ($fp) {
    flock($fp, LOCK_EX);
    ftruncate($fp, 0); rewind($fp);
    fwrite($fp, json_encode($list, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
    flock($fp, LOCK_UN); fclose($fp);
}

// ── Envoi du fichier APK ────────────────────────────────────
$size = filesize($apk_path);
header('Content-Type: application/vnd.android.package-archive');
header('Content-Disposition: attachment; filename="mola-prono.apk"');
header('Content-Length: ' . $size);
header('Cache-Control: no-cache, no-store, must-revalidate');
header('Pragma: no-cache');
header('Expires: 0');
readfile($apk_path);
exit;
