<?php
// ── Fichiers données notifications ─────────────────────────
define('FILE_NOTIFS_M', __DIR__ . '/data/notifs.json');
define('FILE_TOKENS_M', __DIR__ . '/data/tokens.json');

if (!function_exists('jread_m')) {
    function jread_m(string $file): array {
        clearstatcache(true, $file);
        if (!file_exists($file)) return [];
        $c = file_get_contents($file);
        return $c ? (json_decode($c, true) ?: []) : [];
    }
    function jwrite_m(string $file, $data): void {
        $dir = dirname($file);
        if (!is_dir($dir)) mkdir($dir, 0755, true);
        $fp = fopen($file, 'c+');
        if (!$fp) return;
        flock($fp, LOCK_EX);
        ftruncate($fp, 0);
        rewind($fp);
        fwrite($fp, json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
        flock($fp, LOCK_UN);
        fclose($fp);
    }
}

// ── JWT helper ─────────────────────────────────────────────
function b64url_m(string $d): string {
    return rtrim(strtr(base64_encode($d), '+/', '-_'), '=');
}

// ── Récupère le access_token OAuth2 depuis le service account ─
function get_fcm_access_token(): string {
    $path = __DIR__ . '/firebase-service-account.json';
    if (!file_exists($path)) return '';
    $sa = json_decode(file_get_contents($path), true);
    if (!$sa || empty($sa['private_key']) || empty($sa['client_email'])) return '';

    $now = time();
    $h   = b64url_m(json_encode(['alg' => 'RS256', 'typ' => 'JWT']));
    $p   = b64url_m(json_encode([
        'iss'   => $sa['client_email'],
        'scope' => 'https://www.googleapis.com/auth/firebase.messaging',
        'aud'   => 'https://oauth2.googleapis.com/token',
        'iat'   => $now,
        'exp'   => $now + 3600,
    ]));
    $si  = "$h.$p";
    $key = openssl_pkey_get_private($sa['private_key']);
    if (!$key) return '';
    openssl_sign($si, $sig, $key, 'SHA256');
    $jwt = $si . '.' . b64url_m($sig);

    $ch = curl_init('https://oauth2.googleapis.com/token');
    curl_setopt_array($ch, [
        CURLOPT_POST           => true,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_POSTFIELDS     => http_build_query([
            'grant_type' => 'urn:ietf:params:oauth:grant-type:jwt-bearer',
            'assertion'  => $jwt,
        ]),
    ]);
    $res = json_decode(curl_exec($ch), true);
    curl_close($ch);
    return $res['access_token'] ?? '';
}

function get_fcm_project_id(): string {
    $path = __DIR__ . '/firebase-service-account.json';
    if (!file_exists($path)) return '';
    $sa = json_decode(file_get_contents($path), true);
    return $sa['project_id'] ?? '';
}

// ── Envoie vers un TOPIC FCM (tous / pays_bj / pays_tg ...) ──
function fcm_send_topic(string $title, string $body, string $topic, string $notif_id = ''): bool {
    $token      = get_fcm_access_token();
    $project_id = get_fcm_project_id();
    if (!$token || !$project_id) return false;

    $payload = json_encode([
        'message' => [
            'topic'        => $topic,
            'notification' => ['title' => $title, 'body' => $body],
            'android'      => [
                'priority'     => 'high',
                'notification' => [
                    'channel_id'              => 'mola_prono_channel',
                    'sound'                   => 'default',
                    'visibility'              => 'PUBLIC',
                    'notification_priority'   => 'PRIORITY_MAX',
                    'default_sound'           => true,
                    'default_vibrate_timings' => true,
                ],
            ],
            'data' => [
                'notif_id' => $notif_id ?: uniqid(),
                'title'    => $title,
                'body'     => $body,
            ],
        ],
    ]);

    return _fcm_post($token, $project_id, $payload);
}

// ── Envoie vers un TOKEN spécifique ──────────────────────────
function fcm_send_token(string $device_token, string $title, string $body, string $notif_id = ''): bool {
    $token      = get_fcm_access_token();
    $project_id = get_fcm_project_id();
    if (!$token || !$project_id) return false;

    $payload = json_encode([
        'message' => [
            'token'        => $device_token,
            'notification' => ['title' => $title, 'body' => $body],
            'android'      => [
                'priority'     => 'high',
                'notification' => [
                    'channel_id'              => 'mola_prono_channel',
                    'sound'                   => 'default',
                    'visibility'              => 'PUBLIC',
                    'notification_priority'   => 'PRIORITY_MAX',
                    'default_sound'           => true,
                    'default_vibrate_timings' => true,
                ],
            ],
            'data' => [
                'notif_id' => $notif_id ?: uniqid(),
                'title'    => $title,
                'body'     => $body,
            ],
        ],
    ]);

    return _fcm_post($token, $project_id, $payload);
}

function _fcm_post(string $access_token, string $project_id, string $payload): bool {
    $ch = curl_init("https://fcm.googleapis.com/v1/projects/{$project_id}/messages:send");
    curl_setopt_array($ch, [
        CURLOPT_POST           => true,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_SSL_VERIFYHOST => 0,
        CURLOPT_USERAGENT      => 'MolaProno/1.0',
        CURLOPT_HTTPHEADER     => [
            'Content-Type: application/json',
            'Authorization: Bearer ' . $access_token,
        ],
        CURLOPT_POSTFIELDS     => $payload,
    ]);
    curl_exec($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    return $code === 200;
}

// ── Sauvegarde une notification dans l'historique ────────────
function save_notification_record(string $id, string $title, string $body, string $target, bool $success): void {
    $notifs = jread_m(FILE_NOTIFS_M);
    $notifs[] = [
        'id'         => $id,
        'title'      => $title,
        'body'       => $body,
        'target'     => $target,
        'sent_at'    => date('Y-m-d H:i:s'),
        'open_count' => 0,
        'success'    => $success,
    ];
    if (count($notifs) > 500) $notifs = array_slice($notifs, -500);
    jwrite_m(FILE_NOTIFS_M, $notifs);
}
