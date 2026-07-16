<?php
require_once __DIR__ . '/config.php';

define('WHATSAPP_URL', 'https://whatsapp.com/channel/0029VbBrwdH1noz3OjnU5B2V');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: index.html'); exit;
}

$pays      = trim($_POST['pays']      ?? '');
$indicatif = preg_replace('/[^0-9]/', '', $_POST['indicatif'] ?? '');
$telephone = preg_replace('/[^0-9]/', '', $_POST['telephone'] ?? '');

// Sauvegarde si données valides
if ($pays && $indicatif && $telephone && strlen($telephone) >= 5) {
    $full = '+' . $indicatif . $telephone;
    $subs = jread(FILE_SUBSCRIBERS);

    $already = false;
    foreach ($subs as &$s) {
        if ($s['telephone_full'] === $full) {
            $s['actif'] = true;
            $already    = true;
            break;
        }
    }
    unset($s);

    if (!$already) {
        $subs[] = [
            'id'             => count($subs) + 1,
            'pays'           => $pays,
            'ville'          => '',
            'indicatif'      => '+' . $indicatif,
            'telephone'      => $telephone,
            'telephone_full' => $full,
            'date'           => date('Y-m-d H:i:s'),
            'actif'          => true,
        ];
    }

    jwrite(FILE_SUBSCRIBERS, $subs);
}

// Toujours rediriger vers WhatsApp
header('Location: ' . WHATSAPP_URL);
exit;
