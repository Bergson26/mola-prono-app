<?php
session_start();
require_once __DIR__ . '/config.php';

define('ADMIN_PASSWORD', ADMIN_SMS_PASSWORD);
define('LOG_FILE', __DIR__ . '/visitors_log.json');

// Déconnexion
if (isset($_GET['logout'])) {
    session_destroy();
    header('Location: admin.php');
    exit;
}

// Vider le log
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['clear']) && isset($_SESSION['admin'])) {
    file_put_contents(LOG_FILE, json_encode([], JSON_PRETTY_PRINT));
    header('Location: admin.php');
    exit;
}

// Connexion
$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['password'])) {
    if ($_POST['password'] === ADMIN_PASSWORD) {
        $_SESSION['admin'] = true;
        header('Location: admin.php');
        exit;
    } else {
        $error = 'Mot de passe incorrect.';
    }
}

// Lecture des visiteurs
$visitors = [];
$total    = 0;
if (isset($_SESSION['admin']) && file_exists(LOG_FILE)) {
    $raw      = file_get_contents(LOG_FILE);
    $visitors = $raw ? (json_decode($raw, true) ?: []) : [];
    $total    = count($visitors);
    $visitors = array_reverse($visitors); // plus récents en premier
}

$logged = isset($_SESSION['admin']);
?>
<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Admin — Mola Prono</title>
<style>
  *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
  :root {
    --bg:    #f4f6f9;
    --bg2:   #eef0f4;
    --card:  #ffffff;
    --card2: #f0f2f6;
    --blue: #1d4ed8;
    --gdk: #1e3a8a;
    --text:  #111827;
    --muted: #6b7280;
    --brd:   rgba(0,0,0,.1);
    --red:   #dc2626;
  }
  body { font-family: system-ui, sans-serif; background: var(--bg); color: var(--text); min-height: 100vh; }

  /* ── LOGIN ── */
  .login-wrap {
    display: flex; align-items: center; justify-content: center;
    min-height: 100vh; padding: 20px;
  }
  .login-box {
    background: var(--card); border: 1px solid var(--brd);
    border-radius: 16px; padding: 40px 36px; width: 100%; max-width: 380px;
    text-align: center;
  }
  .login-box h1 { font-size: 22px; font-weight: 800; margin-bottom: 6px; }
  .login-box p  { color: var(--muted); font-size: 14px; margin-bottom: 28px; }
  .login-box input[type=password] {
    width: 100%; padding: 12px 16px; border-radius: 10px;
    border: 1px solid var(--brd); background: var(--bg2);
    color: var(--text); font-size: 15px; margin-bottom: 14px; outline: none;
  }
  .login-box input[type=password]:focus { border-color: var(--blue); }
  .btn-blue {
    width: 100%; padding: 12px; border-radius: 10px;
    background: linear-gradient(135deg, var(--blue-dk), var(--blue));
    color: #000; font-weight: 800; font-size: 15px; border: none; cursor: pointer;
    transition: opacity .2s;
  }
  .btn-blue:hover { opacity: .88; }
  .error-msg { color: var(--red); font-size: 13px; margin-top: 10px; }

  /* ── ADMIN PANEL ── */
  .topbar {
    background: var(--card2); border-bottom: 1px solid var(--brd);
    padding: 14px 24px; display: flex; align-items: center;
    justify-content: space-between; gap: 12px; flex-wrap: wrap;
  }
  .topbar h1  { font-size: 18px; font-weight: 800; }
  .topbar h1 span { color: var(--blue); }
  .topbar-right { display: flex; align-items: center; gap: 10px; }
  .badge-total {
    background: var(--blue); color: #000; font-size: 12px; font-weight: 800;
    padding: 3px 12px; border-radius: 999px;
  }
  .btn-sm {
    padding: 7px 16px; border-radius: 8px; font-size: 13px; font-weight: 700;
    border: none; cursor: pointer; text-decoration: none; display: inline-block;
  }
  .btn-clear  { background: rgba(239,68,68,.15); color: var(--red);  border: 1px solid rgba(239,68,68,.3); }
  .btn-logout { background: rgba(0,0,0,.05); color: var(--muted); border: 1px solid var(--brd); }
  .btn-clear:hover  { background: rgba(239,68,68,.25); }
  .btn-logout:hover { background: rgba(0,0,0,.09); color: var(--text); }

  .content { padding: 24px 20px; max-width: 1300px; margin: 0 auto; }

  /* ── TABLE ── */
  .table-wrap { overflow-x: auto; border-radius: 14px; border: 1px solid var(--brd); }
  table { width: 100%; border-collapse: collapse; font-size: 13px; }
  thead th {
    background: var(--card2); padding: 12px 14px; text-align: left;
    color: var(--muted); font-weight: 700; text-transform: uppercase;
    letter-spacing: .05em; font-size: 11px; white-space: nowrap;
    border-bottom: 1px solid var(--brd);
  }
  tbody tr { border-bottom: 1px solid var(--brd); }
  tbody tr:last-child { border-bottom: none; }
  tbody tr:nth-child(odd)  { background: var(--card); }
  tbody tr:nth-child(even) { background: var(--bg2); }
  tbody tr:hover { background: var(--card2); }
  td { padding: 10px 14px; vertical-align: middle; }
  td.ip    { font-family: monospace; color: var(--blue); font-weight: 600; white-space: nowrap; }
  td.date  { white-space: nowrap; color: var(--muted); }
  td.ua    { max-width: 280px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; color: var(--muted); font-size: 12px; }
  td.num   { color: var(--muted); font-size: 11px; text-align: right; }
  .flag    { font-size: 16px; margin-right: 4px; }

  .empty-state { text-align: center; padding: 64px 20px; color: var(--muted); }
  .empty-state p { font-size: 32px; margin-bottom: 12px; }

  /* ── NAV DASHBOARD ── */
  .dash-nav {
    background: var(--bg2); border-bottom: 1px solid var(--brd);
    padding: 0 24px; display: flex; gap: 0;
  }
  .dash-nav a {
    padding: 14px 22px; font-size: 14px; font-weight: 700;
    color: var(--muted); text-decoration: none;
    border-bottom: 3px solid transparent;
    display: flex; align-items: center; gap: 8px;
    transition: color .2s;
  }
  .dash-nav a:hover { color: var(--text); }
  .dash-nav a.active { color: var(--blue); border-bottom-color: var(--blue); }

  @media(max-width:600px) {
    .topbar { flex-direction: column; align-items: flex-start; }
    .dash-nav a { padding: 12px 14px; font-size: 13px; }
  }
</style>
</head>
<body>

<?php if (!$logged): ?>

<!-- ═══ LOGIN ═══ -->
<div class="login-wrap">
  <div class="login-box">
    <h1>⚙️ Admin Panel</h1>
    <p>Mola Prono — Accès administrateur</p>
    <form method="POST">
      <input type="password" name="password" placeholder="Mot de passe" autofocus autocomplete="current-password">
      <button type="submit" class="btn-blue">Se connecter</button>
      <?php if ($error): ?>
        <p class="error-msg">⚠️ <?= htmlspecialchars($error) ?></p>
      <?php endif; ?>
    </form>
  </div>
</div>

<?php else: ?>

<!-- ═══ ADMIN PANEL ═══ -->
<div class="topbar">
  <h1>⚙️ Admin — <span>Mola Prono</span></h1>
  <div class="topbar-right">
    <span class="badge-total"><?= $total ?> visite<?= $total > 1 ? 's' : '' ?></span>
    <form method="POST" style="display:inline" onsubmit="return confirm('Vider le journal des visiteurs ?')">
      <button type="submit" name="clear" class="btn-sm btn-clear">🗑 Vider</button>
    </form>
    <a href="admin.php?logout=1" class="btn-sm btn-logout">Déconnexion</a>
  </div>
</div>

<!-- ═══ NAVIGATION DASHBOARD ═══ -->
<nav class="dash-nav">
  <a href="admin.php" class="active">📊 Visiteurs</a>
  <a href="admin_pronos.php">⚽ Pronostics</a>
  <a href="server/admin_notif.php">🔔 Notifications</a>
</nav>

<div class="content">
  <?php if (empty($visitors)): ?>
    <div class="empty-state">
      <p>👥</p>
      <div>Aucun visiteur enregistré pour l'instant.</div>
    </div>
  <?php else: ?>
  <div class="table-wrap">
    <table>
      <thead>
        <tr>
          <th>#</th>
          <th>Adresse IP</th>
          <th>Pays</th>
          <th>Ville</th>
          <th>Région</th>
          <th>Date et heure</th>
          <th>Navigateur</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($visitors as $i => $v): ?>
        <tr>
          <td class="num"><?= $total - $i ?></td>
          <td class="ip"><?= htmlspecialchars($v['ip'] ?? '—') ?></td>
          <td><?= htmlspecialchars($v['country'] ?? '—') ?></td>
          <td><?= htmlspecialchars($v['city']    ?? '—') ?></td>
          <td><?= htmlspecialchars($v['region']  ?? '—') ?></td>
          <td class="date"><?= htmlspecialchars($v['date'] ?? '—') ?></td>
          <td class="ua" title="<?= htmlspecialchars($v['ua'] ?? '') ?>"><?= htmlspecialchars($v['ua'] ?? '—') ?></td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
  <?php endif; ?>
</div>

<?php endif; ?>

</body>
</html>
