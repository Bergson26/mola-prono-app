<?php
session_start();
require_once __DIR__ . '/config.php';

define('ADMIN_PWD', ADMIN_SMS_PASSWORD);

// Déconnexion
if (isset($_GET['logout'])) {
    session_destroy();
    header('Location: admin_pronos.php');
    exit;
}

// Connexion
$login_error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['password'])) {
    if ($_POST['password'] === ADMIN_PWD) {
        $_SESSION['admin'] = true;
        header('Location: admin_pronos.php');
        exit;
    }
    $login_error = 'Mot de passe incorrect.';
}

// Lancer l'analyse Python en arrière-plan
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['refresh_date']) && isset($_SESSION['admin'])) {
    $script = __DIR__ . '/analyzer.py';
    $log    = __DIR__ . '/data/manual_run.log';
    $cmd    = "/usr/bin/python3 " . escapeshellarg($script) . " --date today >> " . escapeshellarg($log) . " 2>&1";

    if (function_exists('shell_exec') && !in_array('shell_exec', array_map('trim', explode(',', ini_get('disable_functions'))))) {
        shell_exec($cmd . " &");
    } else {
        $pipes = [];
        $h = @proc_open($cmd, [1 => ['file', $log, 'a'], 2 => ['file', $log, 'a']], $pipes);
        if ($h) proc_close($h);
    }

    header("Location: admin_pronos.php?tab=today&refreshing=1");
    exit;
}

$logged = isset($_SESSION['admin']);

// ── Données ──────────────────────────────────────────────────────
$today = date('Y-m-d');

function loadJson($path) {
    return file_exists($path) ? json_decode(file_get_contents($path), true) : null;
}

$today_data = loadJson(__DIR__ . '/data/admin_matches_' . $today . '.json');
$pred_data  = loadJson(__DIR__ . '/data/predictions.json');
$manual_all = jread(__DIR__ . '/data/manual_pronos.json');

$manual_today   = array_values(array_filter($manual_all, fn($p) => ($p['display_date']??'') === $today));

$auto_count = count($pred_data['top_predictions'] ?? []);
$auto_gen   = $pred_data['generated_at'] ?? null;

// Map activeKey => prono ID  (pour afficher bouton "retirer" sur les lignes déjà sélectionnées)
function buildActiveMap(array $pronos): array {
    $map = [];
    foreach ($pronos as $p) {
        $key = ($p['fixture_id']??'') . '|' . ($p['prediction']['label']??'');
        $map[$key] = $p['id'] ?? '';
    }
    return $map;
}
$active_map_today = buildActiveMap($manual_today);

// ── Helpers de rendu ─────────────────────────────────────────────
function h(string $s): string { return htmlspecialchars($s, ENT_QUOTES, 'UTF-8'); }

function renderMatchSection(?array $day_data, array $active_map, string $tab = 'today'): void {
    if (!$day_data || empty($day_data['matches'])) {
        echo '<div class="no-data-box"><div class="nd-icon">📭</div><div class="nd-title">Aucune donnée disponible</div><div class="nd-desc">Cliquez sur "Recharger" pour lancer l\'analyse.</div></div>';
        return;
    }
    $matches = $day_data['matches'];
    if (empty($matches)) {
        echo '<div class="no-data-box"><div class="nd-icon">🏖️</div><div class="nd-title">Aucun match</div><div class="nd-desc">Pas de matchs dans les ligues configurées.</div></div>';
        return;
    }
    // Grouper par ligue
    $by_league = [];
    foreach ($matches as $m) {
        $by_league[$m['league']??'Autre'][] = $m;
    }
    foreach ($by_league as $league_name => $lms) {
        $logo = $lms[0]['league_logo'] ?? '';
        echo '<div class="league-block">';
        echo '<div class="lb-hdr" onclick="toggleLeague(this)" style="cursor:pointer">';
        if ($logo) echo '<img src="'.h($logo).'" class="lb-logo" onerror="this.style.display=\'none\'">';
        echo '<span class="lb-name">'.h($league_name).'</span>';
        echo '<span class="lb-cnt">'.count($lms).' match'.(count($lms)>1?'s':'').'</span>';
        echo '<span class="lb-arrow">▼</span>';
        echo '</div>';
        echo '<div class="lb-body">';
        foreach ($lms as $m) {
            renderMatch($m, $active_map, $tab);
        }
        echo '</div>';
        echo '</div>';
    }
}

function renderMatch(array $m, array $active_map, string $tab = 'today'): void {
    $fid     = (int)($m['fixture_id'] ?? 0);
    $hname   = $m['home_team'] ?? '';
    $aname   = $m['away_team'] ?? '';
    $ko      = $m['kick_off']  ?? '--:--';
    $status  = $m['status']    ?? 'ok';
    $markets = $m['markets']   ?? [];

    $match_js = h(json_encode([
        'fixture_id'  => $fid,
        'league'      => $m['league']      ?? '',
        'league_logo' => $m['league_logo'] ?? '',
        'home_team'   => $hname,
        'home_logo'   => $m['home_logo']   ?? '',
        'away_team'   => $aname,
        'away_logo'   => $m['away_logo']   ?? '',
        'kick_off'    => $ko,
        'markets'     => $markets,
    ], JSON_UNESCAPED_UNICODE));

    echo '<div class="match-card">';
    echo '<div class="mc-hdr" onclick="toggleMatch(this)" style="cursor:pointer">';
    echo '<div class="mc-teams">';
    if (!empty($m['home_logo'])) echo '<img src="'.h($m['home_logo']).'" class="mc-tlogo" onerror="this.style.display=\'none\'">';
    echo '<span class="mc-tn">'.h($hname).'</span>';
    echo '<span class="mc-vs">VS</span>';
    echo '<span class="mc-tn">'.h($aname).'</span>';
    if (!empty($m['away_logo'])) echo '<img src="'.h($m['away_logo']).'" class="mc-tlogo" onerror="this.style.display=\'none\'">';
    echo '</div>';
    echo '<span class="mc-ko">⏱ '.h($ko).'</span>';
    echo '<span class="mc-arrow">▼</span>';
    echo '</div>';
    echo '<div class="mc-body">';

    if ($status === 'no_data') {
        echo '<div class="mc-nodata">Données insuffisantes</div>';
        echo '</div></div>';
        return;
    }

    // Séparer les pronostics : autres vs Over/Under
    $other_items = [];
    $plus_items  = [];
    $moins_items = [];

    if (!empty($markets['1n2'])) {
        $p1 = (float)($markets['1n2']['1'] ?? 0);
        $pN = (float)($markets['1n2']['N'] ?? 0);
        $p2 = (float)($markets['1n2']['2'] ?? 0);
        $other_items[] = ['1N2', "Victoire $hname",              $p1];
        $other_items[] = ['1N2', "Victoire $aname",              $p2];
        $other_items[] = ['1N2', 'Match nul',                     $pN];
        $other_items[] = ['DC',  "Victoire ou nul $hname (1X)",  round($p1+$pN,1)];
        $other_items[] = ['DC',  "Victoire ou nul $aname (2X)",  round($pN+$p2,1)];
    }
    if (!empty($markets['btts'])) {
        $other_items[] = ['BTTS', 'Les deux équipes vont marquer', (float)($markets['btts']['yes'] ?? 0)];
    }
    if (!empty($markets['over_under'])) {
        $ou = $markets['over_under'];
        foreach (['1_5'=>'1,5','2_5'=>'2,5','3_5'=>'3,5'] as $k=>$l) {
            $suf = ($l !== '1,5') ? 's' : '';
            if (isset($ou["over_$k"]))  $plus_items[]  = ['BUTS', "Plus de $l but$suf",  (float)$ou["over_$k"]];
            if (isset($ou["under_$k"])) $moins_items[] = ['BUTS', "Moins de $l but$suf", (float)$ou["under_$k"]];
        }
    }

    // Rendu : 1N2 / DC / BTTS (avec Oui/Non + bouton coloré)
    foreach ($other_items as [$type, $label, $prob]) {
        $prob    = round((float)$prob, 1);
        $yn      = $prob >= 50 ? 'Oui' : 'Non';
        $yn_cls  = $prob >= 50 ? 'fp-yes' : 'fp-no';
        $btn_cls = $prob >= 50 ? 'btn-add-yes' : 'btn-add-no';
        $okey    = $fid.'|'.$label;
        $is_sel  = isset($active_map[$okey]);
        $sel_id  = $is_sel ? $active_map[$okey] : '';
        $pred_js = h(json_encode(['type'=>$type,'label'=>$label,'probability'=>$prob], JSON_UNESCAPED_UNICODE));

        echo '<div class="fixed-row'.($is_sel?' selected':'').'">';
        echo '<span class="fp-lbl">'.h($label).'</span>';
        echo '<span class="fp-pct">'.h((string)$prob).'%</span>';
        echo '<span class="fp-yn '.$yn_cls.'">'.$yn.'</span>';
        if ($is_sel) {
            echo '<button class="btn-unsel" onclick="removePred(\''.h($sel_id).'\')">✕</button>';
        } else {
            echo '<button class="btn-add '.$btn_cls.'" onclick="addPred('.$match_js.','.$pred_js.',\'today\')">+</button>';
        }
        echo '</div>';
    }

    // Rendu : Over/Under en grille 2 colonnes (sans Oui/Non, bouton coloré)
    if (!empty($plus_items) || !empty($moins_items)) {
        echo '<div class="ou-grid">';
        $count = max(count($plus_items), count($moins_items));
        for ($i = 0; $i < $count; $i++) {
            foreach ([['plus', $plus_items[$i] ?? null], ['moins', $moins_items[$i] ?? null]] as [$side, $item]) {
                if ($item === null) { echo '<div class="ou-cell ou-empty"></div>'; continue; }
                [$type, $label, $prob] = $item;
                $prob    = round((float)$prob, 1);
                $btn_cls = $side === 'plus' ? 'btn-add-yes' : 'btn-add-no';
                $okey    = $fid.'|'.$label;
                $is_sel  = isset($active_map[$okey]);
                $sel_id  = $is_sel ? $active_map[$okey] : '';
                $pred_js = h(json_encode(['type'=>$type,'label'=>$label,'probability'=>$prob], JSON_UNESCAPED_UNICODE));

                echo '<div class="ou-cell'.($is_sel?' selected':'').'">';
                echo '<span class="ou-lbl">'.h($label).'</span>';
                echo '<span class="ou-pct">'.h((string)$prob).'%</span>';
                if ($is_sel) {
                    echo '<button class="btn-unsel" onclick="removePred(\''.h($sel_id).'\')">✕</button>';
                } else {
                    echo '<button class="btn-add '.$btn_cls.'" onclick="addPred('.$match_js.','.$pred_js.',\'today\')">+</button>';
                }
                echo '</div>';
            }
        }
        echo '</div>';
    }

    echo '</div></div>'; // ferme .mc-body et .match-card
}

function renderSelPanel(array $pronos, string $empty_msg): void {
    if (empty($pronos)) {
        echo '<div class="sel-empty">'.$empty_msg.'</div>';
        return;
    }
    foreach ($pronos as $p) {
        $id        = h($p['id'] ?? '');
        $match_str = h(($p['home_team']??'') . ' vs ' . ($p['away_team']??''));
        $lbl       = h($p['prediction']['label'] ?? '');
        $prob      = (float)($p['prediction']['probability'] ?? 0);
        $dm        = $p['display_mode'] ?? 'yn';
        $yn_str    = $prob >= 50 ? 'Oui' : 'Non';
        $pct_str   = round($prob,1).'%';
        if ($dm === 'pct')    $disp = $pct_str;
        elseif ($dm === 'pct_yn') $disp = $pct_str.' '.$yn_str;
        else                  $disp = $yn_str;
        $ko        = h($p['kick_off'] ?? '');
        $at        = substr($p['selected_at']??'', 11, 5);
        echo '<div class="sel-item" id="si-'.$id.'">';
        echo '<div class="si-match">'.$match_str.' <em>'.$ko.'</em></div>';
        echo '<div class="si-pred"><span>'.$lbl.'</span><strong>'.h($disp).'</strong></div>';
        echo '<div class="si-meta">Ajouté à '.$at.'</div>';
        echo '<button class="btn-remove" onclick="removePred(\''.$id.'\')">✕ Retirer</button>';
        echo '</div>';
    }
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Admin Pronostics — Mola Prono</title>
<style>
*, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
:root {
  --bg:    #f4f6f9; --bg2: #eef0f4; --card: #ffffff; --card2: #f0f2f6;
  --blue: #1d4ed8; --gdk: #1e3a8a; --blue: #2563eb;
  --yel:   #d97706; --red: #dc2626;
  --text:  #111827; --muted: #6b7280; --brd: rgba(0,0,0,.1);
}
body { font-family: system-ui, sans-serif; background: var(--bg); color: var(--text); min-height: 100vh; }

/* LOGIN */
.login-wrap { display:flex; align-items:center; justify-content:center; min-height:100vh; padding:20px; }
.login-box  { background:var(--card); border:1px solid var(--brd); border-radius:16px; padding:40px 36px; width:100%; max-width:380px; text-align:center; }
.login-box h1 { font-size:22px; font-weight:800; margin-bottom:6px; }
.login-box p  { color:var(--muted); font-size:14px; margin-bottom:28px; }
.login-box input[type=password] { width:100%; padding:12px 16px; border-radius:10px; border:1px solid var(--brd); background:var(--bg2); color:var(--text); font-size:15px; margin-bottom:14px; outline:none; }
.login-box input:focus { border-color:var(--blue); }
.btn-login { width:100%; padding:12px; border-radius:10px; background:linear-gradient(135deg,var(--blue-dk),var(--blue)); color:#000; font-weight:800; font-size:15px; border:none; cursor:pointer; }
.err-msg { color:var(--red); font-size:13px; margin-top:10px; }

/* TOPBAR & NAV */
.topbar { background:var(--card2); border-bottom:1px solid var(--brd); padding:14px 24px; display:flex; align-items:center; justify-content:space-between; flex-wrap:wrap; gap:10px; }
.topbar h1 { font-size:18px; font-weight:800; }
.topbar h1 span { color:var(--blue); }
.btn-logout { padding:7px 16px; border-radius:8px; font-size:13px; font-weight:700; border:1px solid var(--brd); background:rgba(0,0,0,.05); color:var(--muted); cursor:pointer; text-decoration:none; }
.btn-logout:hover { color:var(--text); background:rgba(0,0,0,.09); }
.dash-nav { background:var(--bg2); border-bottom:1px solid var(--brd); padding:0 20px; display:flex; overflow-x:auto; }
.dash-nav a { padding:13px 18px; font-size:14px; font-weight:700; color:var(--muted); text-decoration:none; border-bottom:3px solid transparent; white-space:nowrap; transition:color .2s; }
.dash-nav a:hover { color:var(--text); }
.dash-nav a.active { color:var(--blue); border-bottom-color:var(--blue); }

/* STATUS CARDS */
.stats-row { display:grid; grid-template-columns:repeat(3,1fr); gap:14px; padding:20px 20px 0; max-width:1440px; margin:0 auto; }
.scard { background:var(--card); border:1px solid var(--brd); border-radius:14px; padding:16px 20px; }
.scard .sc-lbl { font-size:11px; color:var(--muted); text-transform:uppercase; letter-spacing:.05em; margin-bottom:6px; }
.scard .sc-n   { font-size:30px; font-weight:900; line-height:1; }
.scard .sc-sub { font-size:12px; color:var(--muted); margin-top:5px; }
.scard.g .sc-n { color:var(--blue); }
.scard.b .sc-n { color:var(--blue); }
.scard.y .sc-n { color:var(--yel); }

/* NOTIF */
.notifs { max-width:1440px; margin:14px auto 0; padding:0 20px; }
.notif { display:flex; align-items:center; gap:10px; padding:13px 16px; border-radius:12px; font-size:14px; font-weight:600; margin-bottom:8px; }
.notif.ok   { background:rgba(29,78,216,.1); border:1px solid rgba(29,78,216,.3); color:#1e3a8a; }
.notif.warn { background:rgba(245,158,11,.1); border:1px solid rgba(245,158,11,.3); color:#92400e; }
.notif.info { background:rgba(59,130,246,.1); border:1px solid rgba(59,130,246,.3); color:#1d4ed8; }

/* PRONOSTICS EN LIGNE */
.online-box { max-width:1440px; margin:14px auto 0; padding:0 20px; }
.online-title { font-size:13px; font-weight:800; color:var(--blue); margin-bottom:8px; letter-spacing:.03em; }
.online-row { background:rgba(29,78,216,.07); border:1px solid rgba(29,78,216,.25); border-radius:12px; padding:12px 16px; margin-bottom:8px; display:flex; flex-direction:column; gap:8px; }
.online-match { display:flex; align-items:center; gap:8px; flex-wrap:wrap; font-size:14px; }
.online-league { font-size:11px; color:var(--muted); margin-left:6px; }
.online-ko { font-size:11px; color:var(--muted); }
.online-pred { display:flex; align-items:center; gap:8px; }

/* REFRESH */
.rrow { max-width:1440px; margin:12px auto 0; padding:0 20px; display:flex; gap:10px; flex-wrap:wrap; }
.btn-ref { display:flex; align-items:center; gap:6px; padding:8px 14px; border-radius:8px; font-size:13px; font-weight:700; border:1px solid var(--brd); background:var(--card); color:var(--text); cursor:pointer; }
.btn-ref:hover { background:var(--card2); }

/* MAIN LAYOUT */
.layout { display:grid; grid-template-columns:1fr 300px; gap:20px; padding:20px; max-width:1440px; margin:0 auto; align-items:start; }

/* TABS */

/* LEAGUE BLOCK */
.league-block { margin-bottom:22px; }
.lb-hdr { display:flex; align-items:center; gap:8px; padding:9px 12px; background:var(--card2); border-radius:8px; margin-bottom:8px; user-select:none; }
.lb-hdr.collapsed { margin-bottom:0; border-radius:8px; }
.lb-logo { width:20px; height:20px; object-fit:contain; }
.lb-name { font-size:12px; font-weight:800; text-transform:uppercase; letter-spacing:.05em; flex:1; }
.lb-cnt  { font-size:11px; color:var(--muted); background:rgba(0,0,0,.07); padding:2px 8px; border-radius:999px; }
.lb-arrow { font-size:10px; color:var(--muted); transition:transform .2s; display:inline-block; }
.lb-hdr.collapsed .lb-arrow { transform:rotate(-90deg); }

/* MATCH CARD */
.match-card { background:var(--card); border:1px solid var(--brd); border-radius:12px; margin-bottom:10px; overflow:hidden; }
.mc-hdr     { display:flex; align-items:center; justify-content:space-between; padding:11px 13px; border-bottom:1px solid var(--brd); flex-wrap:wrap; gap:6px; user-select:none; }
.mc-hdr.mc-collapsed { border-bottom:none; }
.mc-teams   { display:flex; align-items:center; gap:6px; flex:1; min-width:0; flex-wrap:wrap; }
.mc-tlogo   { width:20px; height:20px; object-fit:contain; flex-shrink:0; }
.mc-tn      { font-size:13px; font-weight:700; }
.mc-vs      { font-size:10px; color:var(--muted); font-weight:800; }
.mc-ko      { font-size:11px; color:var(--muted); background:var(--bg2); padding:3px 9px; border-radius:20px; white-space:nowrap; flex-shrink:0; }
.mc-arrow   { font-size:10px; color:var(--muted); transition:transform .2s; display:inline-block; flex-shrink:0; }
.mc-hdr.mc-collapsed .mc-arrow { transform:rotate(-90deg); }
.mc-nodata  { padding:12px 14px; color:var(--muted); font-size:12px; font-style:italic; }

/* FIXED PRED ROWS */
.fixed-row { display:flex; align-items:center; gap:8px; padding:9px 13px; border-bottom:1px solid var(--brd); }
.fixed-row:last-child { border-bottom:none; }
.fixed-row.selected { background:rgba(29,78,216,.07); border-left:3px solid var(--blue); }
.fp-yn  { font-size:11px; font-weight:900; min-width:28px; text-align:center; flex-shrink:0; }
.fp-yes { color:var(--blue); }
.fp-no  { color:var(--red); }
.fp-lbl { flex:1; font-size:12px; color:var(--text); min-width:0; }
.fp-pct { font-size:12px; font-weight:700; color:var(--muted); flex-shrink:0; min-width:38px; text-align:right; }
.btn-add          { font-size:13px; font-weight:900; width:26px; height:26px; border:none; border-radius:50%; cursor:pointer; flex-shrink:0; line-height:1; }
.btn-add.btn-add-yes { background:linear-gradient(135deg,#1d4ed8,#1d4ed8); color:#000; }
.btn-add.btn-add-no  { background:linear-gradient(135deg,#b91c1c,#ef4444); color:#fff; }
.btn-add:hover    { opacity:.85; }

/* OVER/UNDER GRID */
.ou-grid { display:grid; grid-template-columns:1fr 1fr; gap:6px; padding:8px 13px; }
.ou-cell { display:flex; align-items:center; gap:6px; padding:7px 10px; background:var(--bg2); border-radius:8px; border:1px solid var(--brd); }
.ou-cell.selected { background:rgba(29,78,216,.1); border-color:var(--blue); }
.ou-cell.ou-empty { background:transparent; border:none; }
.ou-lbl  { flex:1; font-size:11.5px; color:var(--text); min-width:0; }
.ou-pct  { font-size:11px; font-weight:700; color:var(--muted); flex-shrink:0; }
.btn-unsel { background:rgba(239,68,68,.12); color:var(--red); border:1px solid rgba(239,68,68,.25); font-size:11px; padding:3px 8px; border-radius:6px; cursor:pointer; margin-left:auto; flex-shrink:0; }
.btn-suppress { background:rgba(239,68,68,.1); color:var(--red); border:1px solid rgba(239,68,68,.2); font-size:11px; font-weight:700; padding:3px 9px; border-radius:5px; cursor:pointer; white-space:nowrap; flex-shrink:0; }
.btn-suppress:hover { background:rgba(239,68,68,.2); }

/* SEL PANEL */
.sel-panel { background:var(--card); border:1px solid var(--brd); border-radius:14px; position:sticky; top:12px; }
.sel-hdr  { display:flex; align-items:center; gap:8px; padding:14px 16px; border-bottom:1px solid var(--brd); user-select:none; }
.sel-hdr.collapsed { border-bottom:none; }
.sel-hdr.collapsed .lb-arrow { transform:rotate(-90deg); }
.sel-hdr h3 { font-size:14px; font-weight:800; flex:1; }
.sel-badge { background:var(--blue); color:#000; font-size:11px; font-weight:900; padding:2px 8px; border-radius:999px; }
.sel-tabs  { display:flex; border-bottom:1px solid var(--brd); }
.sel-tab   { flex:1; padding:9px; font-size:12px; font-weight:700; color:var(--muted); background:none; border:none; border-bottom:2px solid transparent; cursor:pointer; transition:color .2s; }
.sel-tab.active { color:var(--blue); border-bottom-color:var(--blue); }
.sel-body  { padding:10px; max-height:450px; overflow-y:auto; }
.sel-empty { text-align:center; padding:24px 12px; color:var(--muted); font-size:13px; }
.sel-item  { background:var(--bg2); border:1px solid rgba(29,78,216,.25); border-left:3px solid var(--blue); border-radius:9px; padding:12px 14px; margin-bottom:10px; }
.si-match  { font-size:13px; font-weight:800; margin-bottom:6px; color:var(--text); }
.si-match em { color:var(--muted); font-style:normal; margin-left:6px; font-size:11px; }
.si-pred   { display:flex; justify-content:space-between; align-items:center; margin-bottom:4px; background:rgba(29,78,216,.07); border-radius:6px; padding:6px 10px; }
.si-pred span { font-size:13px; font-weight:600; color:var(--text); flex:1; }
.si-pred strong { font-size:14px; color:var(--blue); font-weight:900; flex-shrink:0; margin-left:8px; }
.si-meta   { font-size:11px; color:var(--muted); margin-bottom:8px; }
.btn-remove { background:rgba(239,68,68,.1); color:var(--red); border:1px solid rgba(239,68,68,.2); font-size:11px; font-weight:700; padding:4px 10px; border-radius:5px; cursor:pointer; width:100%; }
.btn-remove:hover { background:rgba(239,68,68,.2); }


/* HISTORY */
.td-date { color:var(--muted); white-space:nowrap; }
.td-pred { color:var(--blue); font-weight:600; }
.td-pct  { font-weight:900; color:var(--blue); text-align:right; }

/* NO DATA */
.no-data-box { text-align:center; padding:50px 20px; }
.nd-icon  { font-size:48px; margin-bottom:12px; }
.nd-title { font-size:17px; font-weight:700; margin-bottom:6px; }
.nd-desc  { color:var(--muted); font-size:13px; }

/* TOAST */
#toast { position:fixed; bottom:24px; left:50%; transform:translateX(-50%) translateY(80px); background:#071223; border:1px solid var(--blue); color:#93c5fd; padding:11px 22px; border-radius:10px; font-size:14px; font-weight:700; z-index:9999; transition:transform .3s; pointer-events:none; }
#toast.show { transform:translateX(-50%) translateY(0); }
#toast.err  { background:#3a1e1e; border-color:var(--red); color:#fca5a5; }

/* RESPONSIVE */
@media(max-width:960px) {
  .layout { grid-template-columns:1fr; }
  .sel-panel { position:static; }
  .stats-row { grid-template-columns:1fr 1fr; }
}
@media(max-width:580px) {
  .stats-row { grid-template-columns:1fr; }
  .pactions { flex-wrap:wrap; }
}
</style>
</head>
<body>

<?php if (!$logged): ?>
<div class="login-wrap">
  <div class="login-box">
    <h1>⚽ Pronostics Admin</h1>
    <p>Mola Prono — Gestion des pronostics</p>
    <form method="POST">
      <input type="password" name="password" placeholder="Mot de passe" autofocus autocomplete="current-password">
      <button type="submit" class="btn-login">Se connecter</button>
      <?php if ($login_error): ?><p class="err-msg">⚠️ <?= h($login_error) ?></p><?php endif; ?>
    </form>
  </div>
</div>
<?php else: ?>

<div class="topbar">
  <h1>⚽ Admin — <span>Mola Prono</span></h1>
  <a href="admin_pronos.php?logout=1" class="btn-logout">Déconnexion</a>
</div>
<nav class="dash-nav">
  <a href="admin.php">📊 Visiteurs</a>
  <a href="admin_pronos.php" class="active">⚽ Pronostics</a>
  <a href="server/admin_notif.php">🔔 Notifications</a>
</nav>

<!-- Stats cards -->
<div class="stats-row">
  <div class="scard g">
    <div class="sc-lbl">Pronostics auto — Aujourd'hui</div>
    <div class="sc-n"><?= $auto_count ?></div>
    <?php if ($auto_gen): ?>
    <div class="sc-sub">Mis à jour <?= date('H:i', strtotime($auto_gen)) ?></div>
    <?php else: ?><div class="sc-sub">Pas encore lancé</div><?php endif; ?>
  </div>
  <div class="scard b">
    <div class="sc-lbl">Sélections manuelles — Aujourd'hui</div>
    <div class="sc-n"><?= count($manual_today) ?></div>
    <div class="sc-sub">Total index : <?= $auto_count + count($manual_today) ?> pronostic<?= ($auto_count+count($manual_today))>1?'s':'' ?></div>
  </div>
</div>

<!-- Notifications -->
<div class="notifs">
  <?php if ($auto_count > 0): ?>
  <div class="notif ok">
    ✅ La page index affiche <strong><?= $auto_count ?> pronostic<?= $auto_count>1?'s':'' ?> automatique<?= $auto_count>1?'s':'' ?></strong> aujourd'hui
    <?php if (count($manual_today)>0): ?> + <strong><?= count($manual_today) ?> manuel<?= count($manual_today)>1?'s':'' ?></strong><?php endif; ?>
  </div>
  <?php else: ?>
  <div class="notif warn">
    ⚠️ <strong>Aucun pronostic automatique</strong> pour aujourd'hui. Lancez l'analyse ou ajoutez des sélections manuelles.
  </div>
  <?php endif; ?>
  <?php if (isset($_GET['refreshing'])): ?>
  <div class="notif info">🔄 Analyse lancée en arrière-plan. Rechargez dans ~30 secondes.</div>
  <?php endif; ?>
</div>

<!-- Pronostics AUTO en ligne -->
<?php
$top_preds = $pred_data['top_predictions'] ?? [];
if (!empty($top_preds)):
?>
<div class="online-box">
  <div class="online-title">🟢 En ligne sur l'index (<?= count($top_preds) ?>)</div>
  <?php foreach ($top_preds as $tp):
    $pred_tp = $tp['prediction'] ?? null;
  ?>
  <div class="online-row">
    <div class="online-match">
      <?php if (!empty($tp['home_logo'])): ?><img src="<?= h($tp['home_logo']) ?>" class="mc-tlogo" onerror="this.style.display='none'"><?php endif; ?>
      <strong><?= h($tp['home_team'] ?? '') ?></strong>
      <span style="color:var(--muted);margin:0 6px">vs</span>
      <strong><?= h($tp['away_team'] ?? '') ?></strong>
      <?php if (!empty($tp['away_logo'])): ?><img src="<?= h($tp['away_logo']) ?>" class="mc-tlogo" onerror="this.style.display='none'"><?php endif; ?>
      <span class="online-league"><?= h($tp['league'] ?? '') ?></span>
      <span class="online-ko">⏱ <?= h($tp['kick_off'] ?? '--:--') ?></span>
    </div>
    <?php if ($pred_tp): ?>
    <div class="online-pred">
      <span class="fp-lbl"><?= h($pred_tp['label'] ?? '') ?></span>
      <span class="fp-pct"><?= h((string)($pred_tp['probability'] ?? '')) ?>%</span>
      <button class="btn-suppress" onclick="suppressAuto(<?= (int)($tp['fixture_id']??0) ?>)">✕ Retirer</button>
    </div>
    <?php endif; ?>
  </div>
  <?php endforeach; ?>
</div>
<?php endif; ?>

<!-- Refresh buttons -->
<div class="rrow">
  <form method="POST">
    <input type="hidden" name="refresh_date" value="today">
    <button type="submit" class="btn-ref">🔄 Recharger Aujourd'hui</button>
  </form>
</div>

<!-- Main layout -->
<div class="layout">

  <!-- LEFT: Matchs par jour -->
  <div>
    <?php renderMatchSection($today_data, $active_map_today, 'today'); ?>
  </div>

  <!-- RIGHT: Panel sélections actives -->
  <div class="sel-panel">
    <div class="sel-hdr" onclick="toggleSel(this)" style="cursor:pointer">
      <h3>Sélections actives</h3>
      <span class="sel-badge"><?= count($manual_today) ?></span>
      <span class="lb-arrow" style="margin-left:4px">▼</span>
    </div>
    <div class="sel-body">
      <?php renderSelPanel($manual_today, 'Aucune sélection manuelle aujourd\'hui'); ?>
    </div>
  </div>

</div><!-- .layout -->

<div id="toast"></div>

<script>
function toggleSel(hdr) {
  var body = hdr.nextElementSibling;
  var open = body.style.display !== 'none';
  body.style.display = open ? 'none' : '';
  hdr.classList.toggle('collapsed', open);
}
function toggleLeague(hdr) {
  var body = hdr.nextElementSibling;
  var open = body.style.display !== 'none';
  body.style.display = open ? 'none' : '';
  hdr.classList.toggle('collapsed', open);
}
function toggleMatch(hdr) {
  var body = hdr.nextElementSibling;
  var open = body.style.display !== 'none';
  body.style.display = open ? 'none' : '';
  hdr.classList.toggle('mc-collapsed', open);
}

var _tt;
function toast(msg, err) {
  var t = document.getElementById('toast');
  t.textContent = msg;
  t.className = (err ? 'err ' : '') + 'show';
  clearTimeout(_tt);
  _tt = setTimeout(function(){ t.className=''; }, 3000);
}
function addPred(matchData, prediction, schedule) {
  var body = Object.assign({}, matchData, { prediction: prediction, schedule: schedule, display_mode: 'yn' });
  fetch('save_manual_prono.php', {
    method: 'POST',
    headers: {'Content-Type':'application/json'},
    body: JSON.stringify(body)
  })
  .then(r => r.json())
  .then(d => {
    if (d.ok) {
      toast('✅ Ajouté sur l\'index !');
      setTimeout(() => location.reload(), 900);
    } else toast('Erreur : ' + (d.error||'?'), true);
  })
  .catch(() => toast('Erreur réseau', true));
}
function suppressAuto(fixtureId) {
  fetch('suppress_auto.php', {
    method:'POST', headers:{'Content-Type':'application/json'},
    body: JSON.stringify({fixture_id: fixtureId, action:'add'})
  })
  .then(r => r.json())
  .then(d => { if(d.ok){toast('Retiré de l\'index'); setTimeout(()=>location.reload(),900);} else toast('Erreur',true); });
}
function removePred(id) {
  fetch('delete_manual_prono.php', {
    method: 'POST',
    headers: {'Content-Type':'application/json'},
    body: JSON.stringify({id: id})
  })
  .then(r => r.json())
  .then(d => {
    if (d.ok) { toast('🗑 Sélection retirée'); setTimeout(() => location.reload(), 900); }
    else toast('Erreur', true);
  });
}
</script>

<?php endif; ?>
</body>
</html>
