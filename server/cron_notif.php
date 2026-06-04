<?php
require_once __DIR__ . '/send_notif.php';

$title    = '⚽ Nouveaux coupons disponibles !';
$body     = 'Les pronostics du jour sont prêts. Consultez vos coupons maintenant sur Mola Prono.';
$notif_id = 'auto_' . date('Y-m-d');

$ok = fcm_send_topic($title, $body, 'tous', $notif_id);
if ($ok) save_notification_record($notif_id, $title, $body, 'tous', true);

echo $ok ? 'OK - notification envoyée' : 'ERROR - vérifiez firebase-service-account.json';
