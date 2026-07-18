<?php
session_start();
require_once __DIR__ . '/send_notif.php';
require_once __DIR__ . '/../config.php';

define('NOTIF_PASS', ADMIN_SMS_PASSWORD); // même mot de passe que admin.php et admin_pronos.php

$PAYS_MAP = [
    'bj' => 'Bénin', 'bf' => 'Burkina Faso', 'cm' => 'Cameroun',
    'ci' => "Côte d'Ivoire", 'gn' => 'Guinée', 'cd' => 'RDC',
    'sn' => 'Sénégal', 'tg' => 'Togo',
];

// ── Logout ────────────────────────────────────────────────────
if (isset($_GET['logout'])) {
    unset($_SESSION['admin']);
    header('Location: admin_notif.php'); exit;
}

// ── Auth ──────────────────────────────────────────────────────
$login_error = '';
if (!isset($_SESSION['admin'])) {
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['password'])) {
        if ($_POST['password'] === NOTIF_PASS) {
            $_SESSION['admin'] = true;
            header('Location: admin_notif.php'); exit;
        }
        $login_error = 'Mot de passe incorrect.';
    }
}

// ── Envoi notification ────────────────────────────────────────
$flash = ''; $flash_type = 'ok';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_SESSION['admin']) && isset($_POST['title'])) {
    $title  = trim($_POST['title'] ?? '');
    $body   = trim($_POST['body']  ?? '');
    $target = $_POST['target']     ?? 'tous';

    if ($title && $body) {
        $notif_id = uniqid('n_');
        $ok       = false;
        $label    = $target;

        if ($target === 'tous') {
            $ok = fcm_send_topic($title, $body, 'tous', $notif_id);

        } elseif ($target === 'pays') {
            $codes = array_filter(array_map('trim', (array)($_POST['countries'] ?? [])));
            $ok    = !empty($codes);
            foreach ($codes as $code) {
                $r  = fcm_send_topic($title, $body, 'pays_' . $code, $notif_id);
                if (!$r) $ok = false;
            }
            $names  = array_map(fn($c) => $PAYS_MAP[$c] ?? $c, $codes);
            $label  = implode(', ', $names);

        } elseif ($target === 'user') {
            $dev_token = trim($_POST['user_token'] ?? '');
            if ($dev_token) {
                $ok    = fcm_send_token($dev_token, $title, $body, $notif_id);
                $label = 'Utilisateur spécifique';
            }
        }

        save_notification_record($notif_id, $title, $body, $label, $ok);
        $_SESSION['notif_flash']      = $ok ? '✅ Notification envoyée !' : '❌ Erreur Firebase — vérifiez firebase-service-account.json';
        $_SESSION['notif_flash_type'] = $ok ? 'ok' : 'err';
        header('Location: admin_notif.php'); exit;
    }
}

if (!empty($_SESSION['notif_flash'])) {
    $flash      = $_SESSION['notif_flash'];
    $flash_type = $_SESSION['notif_flash_type'] ?? 'ok';
    unset($_SESSION['notif_flash'], $_SESSION['notif_flash_type']);
}

// ── Data ──────────────────────────────────────────────────────
define('FILE_DOWNLOADS_M', __DIR__ . '/data/downloads.json');
define('FILE_ACTIVITY_M',  __DIR__ . '/data/activity.json');

$tokens    = jread_m(FILE_TOKENS_M);
$notifs    = array_reverse(jread_m(FILE_NOTIFS_M));
$downloads = jread_m(FILE_DOWNLOADS_M);
$activity  = jread_m(FILE_ACTIVITY_M);

$total_installs = count($tokens);
$country_stats  = [];
foreach ($tokens as $t) {
    $cc = $t['country_code'] ?? '??';
    $cn = $t['country']      ?? $cc;
    if (!isset($country_stats[$cc])) $country_stats[$cc] = ['name' => $cn, 'count' => 0];
    $country_stats[$cc]['count']++;
}
uasort($country_stats, fn($a, $b) => $b['count'] - $a['count']);

// Stats téléchargements APK
$total_downloads = count($downloads);
$dl_by_day       = [];
$dl_by_country   = [];
foreach ($downloads as $d) {
    $date = $d['date'] ?? '';
    if ($date) $dl_by_day[$date] = ($dl_by_day[$date] ?? 0) + 1;
    $c = $d['country'] ?: ($d['country_code'] ?: '?');
    $dl_by_country[$c] = ($dl_by_country[$c] ?? 0) + 1;
}
krsort($dl_by_day);
arsort($dl_by_country);
$dl_today = $dl_by_day[date('Y-m-d')] ?? 0;

// Total ouvertures de notifications
$total_opens = 0;
foreach (jread_m(FILE_NOTIFS_M) as $n) { $total_opens += intval($n['open_count'] ?? 0); }

// Utilisateurs actifs par jour (14 derniers)
$au_by_day     = [];
$today_str     = date('Y-m-d');
foreach ($activity as $e) {
    $d = $e['date'] ?? '';
    if ($d) $au_by_day[$d] = ($au_by_day[$d] ?? 0) + 1;
}
krsort($au_by_day);
$au_today = $au_by_day[$today_str] ?? 0;
?>
<!DOCTYPE html>
<html lang="fr">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Mola Prono — Admin Notifications</title>
  <style>
    :root {
      --bg: #ffffff; --bg2: #f9fafb; --bg3: #f3f4f6;
      --blue: #1d4ed8; --blue-dk: #1e3a8a;
      --text: #111827; --muted: #6b7280;
      --brd: #e5e7eb; --red: #ef4444; --yellow: #f59e0b;
      --blue: #3b82f6; --radius: 12px;
    }
    * { box-sizing: border-box; margin: 0; padding: 0; }
    body { font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif; background: var(--bg); color: var(--text); min-height: 100vh; }

    /* ── LOGIN ── */
    .login-page { display: flex; align-items: center; justify-content: center; min-height: 100vh; padding: 20px; }
    .login-box { background: var(--bg2); border: 1px solid var(--brd); border-radius: 20px; padding: 40px 32px; width: 100%; max-width: 380px; text-align: center; }
    .login-box h1 { font-size: 22px; font-weight: 800; margin-bottom: 4px; }
    .login-box p  { color: var(--muted); font-size: 14px; margin-bottom: 28px; }
    .login-box input[type=password] { width: 100%; padding: 12px 16px; border-radius: 10px; border: 1px solid var(--brd); background: var(--bg3); color: var(--text); font-size: 15px; margin-bottom: 12px; outline: none; font-family: inherit; }
    .login-box input[type=password]:focus { border-color: var(--blue); }
    .btn-blue { width: 100%; padding: 12px; border-radius: 10px; background: linear-gradient(135deg, var(--blue-dk), var(--blue)); color: #000; font-weight: 800; font-size: 15px; border: none; cursor: pointer; font-family: inherit; }
    .login-error { color: var(--red); font-size: 13px; margin-top: 8px; }

    /* ── HEADER PLEIN ÉCRAN ── */
    .admin-header {
      display: flex; align-items: center; justify-content: space-between;
      padding: 14px 28px; background: #fff;
      border-bottom: 2px solid var(--brd); position: sticky; top: 0; z-index: 50;
    }
    .admin-title { font-size: 18px; font-weight: 900; display: flex; align-items: center; gap: 8px; }
    .admin-title em { color: var(--blue); font-style: normal; }
    .logout-link { font-size: 13px; color: var(--muted); text-decoration: none; padding: 7px 14px; border: 1px solid var(--brd); border-radius: 8px; transition: color .15s; }
    .logout-link:hover { color: var(--red); border-color: var(--red); }

    /* ── NAV TABS ── */
    .admin-nav { display: flex; border-bottom: 1px solid var(--brd); padding: 0 28px; background: #fff; }
    .admin-nav a {
      display: flex; align-items: center; gap: 6px; padding: 13px 18px;
      font-size: 14px; font-weight: 700; color: var(--muted); text-decoration: none;
      border-bottom: 3px solid transparent; margin-bottom: -1px; transition: color .15s;
    }
    .admin-nav a:hover { color: var(--text); }
    .admin-nav a.active { color: var(--blue); border-bottom-color: var(--blue); }

    /* ── CONTENT ── */
    .admin-content { padding: 28px 28px 60px; }

    /* Flash */
    .flash { padding: 14px 18px; border-radius: var(--radius); margin-bottom: 20px; font-size: 14px; font-weight: 600; }
    .flash.ok  { background: rgba(29,78,216,.1);  border: 1px solid rgba(29,78,216,.3);  color: var(--blue); }
    .flash.err { background: rgba(248,113,113,.1); border: 1px solid rgba(248,113,113,.3); color: var(--red); }

    /* Stats */
    .stats-row { display: grid; gap: 14px; margin-bottom: 24px; }
    .stats-6 { grid-template-columns: repeat(6, 1fr); }
    .stats-3 { grid-template-columns: repeat(3, 1fr); }
    @media(max-width:900px){ .stats-6 { grid-template-columns: repeat(3,1fr); } }
    @media(max-width:600px){ .stats-6,.stats-3 { grid-template-columns: repeat(2,1fr); } }

    .stat-card { background: var(--bg2); border: 1px solid var(--brd); border-radius: var(--radius); padding: 18px 14px; text-align: center; }
    .stat-num  { font-size: 30px; font-weight: 900; color: var(--blue); line-height: 1.1; }
    .stat-num.blue { color: var(--blue); }
    .stat-lbl  { font-size: 11px; color: var(--muted); margin-top: 5px; text-transform: uppercase; letter-spacing: .06em; }

    /* Panels */
    .panel { background: var(--bg2); border: 1px solid var(--brd); border-radius: var(--radius); padding: 24px; margin-bottom: 24px; }
    .panel-title { font-size: 13px; font-weight: 800; color: var(--muted); text-transform: uppercase; letter-spacing: .07em; margin-bottom: 18px; }
    .panel-grid-2 { display: grid; grid-template-columns: 1fr 1fr; gap: 24px; }
    @media(max-width:700px){ .panel-grid-2 { grid-template-columns: 1fr; } }

    /* Bar chart rows */
    .bar-row { display: flex; align-items: center; gap: 8px; margin-bottom: 7px; }
    .bar-label { font-size: 12px; color: var(--muted); width: 82px; flex-shrink: 0; }
    .bar-track { flex: 1; background: rgba(0,0,0,.06); border-radius: 4px; height: 14px; overflow: hidden; }
    .bar-fill  { height: 100%; border-radius: 4px; }
    .bar-fill.blue { background: var(--blue); }
    .bar-fill.blue  { background: var(--blue); }
    .bar-fill.orange{ background: #f97316; }
    .bar-val  { font-size: 12px; font-weight: 700; width: 26px; text-align: right; flex-shrink: 0; }
    .bar-sublabel { font-size: 12px; color: var(--muted); font-weight: 700; margin-bottom: 10px; text-transform: uppercase; letter-spacing: .05em; }

    /* Form */
    .form-group { margin-bottom: 16px; }
    .form-group label { display: block; font-size: 13px; color: var(--muted); margin-bottom: 6px; font-weight: 600; }
    .form-group input[type=text], .form-group textarea {
      width: 100%; padding: 11px 14px; border-radius: 10px; border: 1px solid var(--brd);
      background: var(--bg3); color: var(--text); font-size: 14px; font-family: inherit; outline: none; transition: border-color .2s;
    }
    .form-group input:focus, .form-group textarea:focus { border-color: var(--blue); }
    .form-group textarea { resize: vertical; min-height: 80px; }
    .char-count { font-size: 11px; color: var(--muted); margin-top: 4px; text-align: right; }

    .target-tabs { display: flex; gap: 8px; margin-bottom: 14px; flex-wrap: wrap; }
    .target-tab { padding: 8px 16px; border-radius: 20px; border: 1px solid var(--brd); background: var(--bg3); color: var(--muted); font-size: 13px; cursor: pointer; transition: all .2s; font-family: inherit; font-weight: 600; }
    .target-tab.active { background: rgba(29,78,216,.15); border-color: var(--blue); color: var(--blue); }

    .country-grid { display: flex; flex-wrap: wrap; gap: 8px; margin-top: 8px; }
    .country-check { display: flex; align-items: center; gap: 6px; padding: 7px 12px; border-radius: 8px; border: 1px solid var(--brd); background: var(--bg3); cursor: pointer; font-size: 13px; transition: all .15s; }
    .country-check:has(input:checked) { border-color: var(--blue); background: rgba(29,78,216,.1); color: var(--blue); }
    .country-check input { accent-color: var(--blue); }

    #user-token-field { display: none; }
    .form-hint { font-size: 12px; color: var(--muted); margin-top: 6px; font-style: italic; }
    .send-btn { display: block; width: 100%; padding: 14px; background: linear-gradient(135deg, var(--blue-dk), var(--blue)); color: #000; font-weight: 800; font-size: 15px; border: none; border-radius: 10px; cursor: pointer; font-family: inherit; margin-top: 6px; transition: opacity .2s; }
    .send-btn:hover { opacity: .9; }

    /* Table */
    .table-wrap { overflow-x: auto; }
    table { width: 100%; border-collapse: collapse; font-size: 13px; }
    th { padding: 10px 14px; text-align: left; color: var(--muted); font-weight: 700; text-transform: uppercase; font-size: 11px; letter-spacing: .05em; border-bottom: 2px solid var(--brd); white-space: nowrap; }
    td { padding: 11px 14px; border-bottom: 1px solid var(--brd); }
    tr:last-child td { border-bottom: none; }
    tr:hover td { background: var(--bg2); }
    .badge-blue { background: rgba(29,78,216,.15); color: var(--blue-dk); padding: 3px 9px; border-radius: 6px; font-size: 11px; font-weight: 700; }
    .badge-red   { background: rgba(248,113,113,.15); color: var(--red); padding: 3px 9px; border-radius: 6px; font-size: 11px; font-weight: 700; }
    .badge-muted { background: var(--bg3); color: var(--muted); padding: 3px 9px; border-radius: 6px; font-size: 11px; }
    .token-short { font-family: monospace; font-size: 11px; color: var(--muted); cursor: pointer; }
    .open-rate { font-weight: 700; color: var(--blue); }
    .empty-row td { text-align: center; color: var(--muted); padding: 32px; }
  </style>
</head>
<body>

<?php if (!isset($_SESSION['admin'])): ?>
<!-- ═══ LOGIN ═══ -->
<div class="login-page">
  <div class="login-box">
    <h1>🔔 Notifications</h1>
    <p>Mola Prono — Panneau Admin</p>
    <form method="POST">
      <input type="password" name="password" placeholder="Mot de passe" autofocus autocomplete="current-password">
      <button type="submit" class="btn-blue">Se connecter</button>
      <?php if ($login_error): ?><p class="login-error"><?= htmlspecialchars($login_error) ?></p><?php endif; ?>
    </form>
  </div>
</div>

<?php else: ?>
<!-- ═══ ADMIN PANEL ═══ -->

<!-- Header plein écran -->
<header class="admin-header">
  <div class="admin-title">⚙️ Admin — Mola <em>Prono</em></div>
  <a href="?logout=1" class="logout-link">Déconnexion →</a>
</header>

<!-- Navigation -->
<nav class="admin-nav">
  <a href="../admin.php">📊 Visiteurs</a>
  <a href="../admin_pronos.php">⚽ Pronostics</a>
  <a href="admin_notif.php" class="active">🔔 Notifications</a>
</nav>

<div class="admin-content">

  <?php if ($flash): ?>
  <div class="flash <?= $flash_type ?>"><?= htmlspecialchars($flash) ?></div>
  <?php endif; ?>

  <!-- ── KPIs ── -->
  <div class="stats-row stats-6">
    <div class="stat-card">
      <div class="stat-num blue"><?= $au_today ?></div>
      <div class="stat-lbl">Actifs aujourd'hui</div>
    </div>
    <div class="stat-card">
      <div class="stat-num"><?= $total_installs ?></div>
      <div class="stat-lbl">Appareils enregistrés</div>
    </div>
    <div class="stat-card">
      <div class="stat-num"><?= $total_downloads ?></div>
      <div class="stat-lbl">Téléchargements application mobile</div>
    </div>
    <div class="stat-card">
      <div class="stat-num"><?= $dl_today ?></div>
      <div class="stat-lbl">DL aujourd'hui</div>
    </div>
    <div class="stat-card">
      <div class="stat-num"><?= count($notifs) ?></div>
      <div class="stat-lbl">Notifs envoyées</div>
    </div>
    <div class="stat-card">
      <div class="stat-num"><?= $total_opens ?></div>
      <div class="stat-lbl">Ouvertures notifs</div>
    </div>
  </div>

  <!-- ── GRAPHIQUES ── -->
  <div class="panel">
    <div class="panel-title">📈 Activité</div>
    <div class="panel-grid-2">

      <!-- Utilisateurs actifs par jour -->
      <div>
        <p class="bar-sublabel">👤 Utilisateurs actifs (14 derniers jours)</p>
        <?php if (empty($au_by_day)): ?>
          <p style="font-size:13px;color:var(--muted)">Aucune donnée (données disponibles dès la première ouverture de l'app)</p>
        <?php else:
          $max_au = max(array_values($au_by_day));
          foreach (array_slice($au_by_day, 0, 14, true) as $date => $cnt): ?>
        <div class="bar-row">
          <span class="bar-label"><?= htmlspecialchars($date) ?></span>
          <div class="bar-track"><div class="bar-fill blue" style="width:<?= $max_au ? round($cnt/$max_au*100) : 0 ?>%"></div></div>
          <span class="bar-val"><?= $cnt ?></span>
        </div>
        <?php endforeach; endif; ?>
      </div>

      <!-- Téléchargements par jour -->
      <div>
        <p class="bar-sublabel">📥 Téléchargements application mobile (14 derniers jours)</p>
        <?php if (empty($dl_by_day)): ?>
          <p style="font-size:13px;color:var(--muted)">Aucun téléchargement</p>
        <?php else:
          $max_dl = max(array_values($dl_by_day));
          foreach (array_slice($dl_by_day, 0, 14, true) as $date => $cnt): ?>
        <div class="bar-row">
          <span class="bar-label"><?= htmlspecialchars($date) ?></span>
          <div class="bar-track"><div class="bar-fill blue" style="width:<?= $max_dl ? round($cnt/$max_dl*100) : 0 ?>%"></div></div>
          <span class="bar-val"><?= $cnt ?></span>
        </div>
        <?php endforeach; endif; ?>
      </div>

    </div>
  </div>

  <!-- ── ENVOYER ── -->
  <div class="panel">
    <div class="panel-title">📨 Envoyer une notification</div>
    <form method="POST" onsubmit="return confirmSend()">
      <input type="hidden" name="title" id="h-title">
      <input type="hidden" name="body"  id="h-body">
      <input type="hidden" name="target" id="h-target" value="tous">
      <input type="hidden" name="user_token" id="h-user-token">

      <div class="form-group">
        <label>Titre</label>
        <input type="text" id="f-title" maxlength="80" placeholder="⚽ Nouveaux coupons disponibles !" oninput="updateCount('f-title','cc-title',80)">
        <div class="char-count"><span id="cc-title">0</span>/80</div>
      </div>

      <div class="form-group">
        <label>Message</label>
        <textarea id="f-body" maxlength="200" placeholder="Les pronostics du jour sont disponibles…" oninput="updateCount('f-body','cc-body',200)"></textarea>
        <div class="char-count"><span id="cc-body">0</span>/200</div>
      </div>

      <div class="form-group">
        <label>Destinataires</label>
        <div class="target-tabs">
          <button type="button" class="target-tab active" onclick="setTarget('tous',this)">🌍 Tous les utilisateurs</button>
          <button type="button" class="target-tab" onclick="setTarget('pays',this)">🗺️ Par pays</button>
          <button type="button" class="target-tab" onclick="setTarget('user',this)">👤 Utilisateur précis</button>
        </div>

        <!-- Par pays -->
        <div id="pays-field" style="display:none">
          <div class="country-grid">
            <?php foreach ($PAYS_MAP as $code => $name):
              $cnt = $country_stats[$code]['count'] ?? 0; ?>
            <label class="country-check">
              <input type="checkbox" name="countries[]" value="<?= $code ?>">
              <?= htmlspecialchars($name) ?> <?php if ($cnt): ?><span style="color:var(--muted)">(<?= $cnt ?>)</span><?php endif; ?>
            </label>
            <?php endforeach; ?>
          </div>
        </div>

        <!-- Par utilisateur -->
        <div id="user-token-field">
          <div class="table-wrap" style="margin-bottom:12px;max-height:200px;overflow-y:auto;">
            <table>
              <thead><tr><th>Token</th><th>Pays</th><th>Ville</th><th>Date</th><th></th></tr></thead>
              <tbody>
                <?php if (empty($tokens)): ?>
                <tr class="empty-row"><td colspan="5">Aucune installation enregistrée</td></tr>
                <?php else: foreach (array_reverse($tokens) as $t): ?>
                <tr>
                  <td><span class="token-short" title="<?= htmlspecialchars($t['token']) ?>"><?= htmlspecialchars(substr($t['token'], 0, 20)) ?>…</span></td>
                  <td><?= htmlspecialchars($t['country'] ?: ($t['country_code'] ?: '—')) ?></td>
                  <td><?= htmlspecialchars($t['city'] ?: '—') ?></td>
                  <td><?= htmlspecialchars(substr($t['installed_at'] ?? '', 0, 10)) ?></td>
                  <td><button type="button" onclick="selectUser('<?= htmlspecialchars($t['token']) ?>')" style="font-size:11px;padding:3px 8px;border-radius:6px;border:1px solid var(--blue);background:transparent;color:var(--blue);cursor:pointer;">Choisir</button></td>
                </tr>
                <?php endforeach; endif; ?>
              </tbody>
            </table>
          </div>
          <input type="text" id="f-user-token" placeholder="Token FCM de l'utilisateur" style="font-size:12px" oninput="document.getElementById('h-user-token').value=this.value">
          <p class="form-hint">Cliquez sur « Choisir » dans le tableau ou collez directement le token.</p>
        </div>
      </div>

      <button type="submit" class="send-btn">📤 Envoyer la notification</button>
    </form>
  </div>

  <!-- ── UTILISATEURS ── -->
  <div class="panel">
    <div class="panel-title">📱 Utilisateurs installés (<?= $total_installs ?>)</div>
    <div class="table-wrap">
      <table>
        <thead>
          <tr><th>Pays</th><th>Ville</th><th>Installé le</th><th>Dernière activité</th></tr>
        </thead>
        <tbody>
          <?php if (empty($tokens)): ?>
          <tr class="empty-row"><td colspan="4">Aucune installation pour le moment</td></tr>
          <?php else: foreach (array_reverse($tokens) as $t): ?>
          <tr>
            <td><?= htmlspecialchars($t['country'] ?: ($t['country_code'] ?: '—')) ?></td>
            <td><?= htmlspecialchars($t['city'] ?: '—') ?></td>
            <td><?= htmlspecialchars($t['installed_at'] ?? '—') ?></td>
            <td><?= htmlspecialchars($t['last_seen'] ?? '—') ?></td>
          </tr>
          <?php endforeach; endif; ?>
        </tbody>
      </table>
    </div>
  </div>

  <!-- ── HISTORIQUE ── -->
  <div class="panel">
    <div class="panel-title">📊 Historique des notifications</div>
    <div class="table-wrap">
      <table>
        <thead>
          <tr><th>Titre</th><th>Destinataires</th><th>Envoyée le</th><th>Ouvertures</th><th>Statut</th></tr>
        </thead>
        <tbody>
          <?php if (empty($notifs)): ?>
          <tr class="empty-row"><td colspan="5">Aucune notification envoyée</td></tr>
          <?php else: foreach ($notifs as $n): ?>
          <tr>
            <td style="max-width:200px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap" title="<?= htmlspecialchars($n['body'] ?? '') ?>"><?= htmlspecialchars($n['title'] ?? '—') ?></td>
            <td><span class="badge-muted"><?= htmlspecialchars($n['target'] ?? '—') ?></span></td>
            <td><?= htmlspecialchars($n['sent_at'] ?? '—') ?></td>
            <td><span class="open-rate"><?= intval($n['open_count'] ?? 0) ?></span> ouvertures</td>
            <td><?= ($n['success'] ?? false) ? '<span class="badge-blue">✓ Livré</span>' : '<span class="badge-red">✗ Erreur</span>' ?></td>
          </tr>
          <?php endforeach; endif; ?>
        </tbody>
      </table>
    </div>
  </div>

</div><!-- /.admin-content -->

<script>
function updateCount(fid, cid, max) {
  document.getElementById(cid).textContent = document.getElementById(fid).value.length;
}

function setTarget(val, btn) {
  document.getElementById('h-target').value = val;
  document.querySelectorAll('.target-tab').forEach(t => t.classList.remove('active'));
  btn.classList.add('active');
  document.getElementById('pays-field').style.display        = (val === 'pays')  ? '' : 'none';
  document.getElementById('user-token-field').style.display  = (val === 'user')  ? '' : 'none';
}

function selectUser(token) {
  document.getElementById('f-user-token').value   = token;
  document.getElementById('h-user-token').value   = token;
}

function confirmSend() {
  const title  = document.getElementById('f-title').value.trim();
  const body   = document.getElementById('f-body').value.trim();
  if (!title || !body) { alert('Remplissez le titre et le message.'); return false; }
  document.getElementById('h-title').value = title;
  document.getElementById('h-body').value  = body;
  const target = document.getElementById('h-target').value;
  let dest = 'tous les utilisateurs';
  if (target === 'pays') {
    const checked = [...document.querySelectorAll('input[name="countries[]"]:checked')].map(i => i.parentElement.textContent.trim());
    if (!checked.length) { alert('Sélectionnez au moins un pays.'); return false; }
    dest = checked.join(', ');
  }
  if (target === 'user') {
    const tok = document.getElementById('h-user-token').value.trim();
    if (!tok) { alert('Sélectionnez un utilisateur.'); return false; }
    dest = 'un utilisateur précis';
  }
  return confirm(`Envoyer "${title}" à : ${dest} ?`);
}
</script>
<?php endif; ?>
</body>
</html>
