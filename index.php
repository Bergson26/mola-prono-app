<!DOCTYPE html>
<html lang="fr">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Mola Prono — Pronostics Football</title>
  <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700;800;900&display=swap" rel="stylesheet">
  <style>
    *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

    :root {
      --bg:         #07101e;
      --bg2:        #0d1a30;
      --card:       #101c2e;
      --card2:      #162440;
      --green:      #22c55e;
      --green-dk:   #16a34a;
      --green-glow: rgba(34,197,94,.25);
      --yellow:     #f59e0b;
      --text:       #e8f0fe;
      --muted:      #5a7394;
      --border:     rgba(255,255,255,.07);
      --shadow:     0 24px 64px rgba(0,0,0,.55);
    }

    body { font-family:'Outfit',sans-serif; background:#ffffff; color:#111827; min-height:100vh; }

    /* ── HEADER ── */
    .header {
      background: linear-gradient(160deg,#0d1a30 0%,#0c2416 60%,#07101e 100%);
      border-bottom:1px solid var(--border);
      padding:32px 24px 0; text-align:center; position:relative; overflow:hidden;
    }
    .header::before {
      content:''; position:absolute; inset:0;
      background:radial-gradient(ellipse 80% 60% at 50% -10%,rgba(34,197,94,.12) 0%,transparent 70%);
      pointer-events:none;
    }
    .logo-wrap { display:flex; align-items:center; justify-content:center; gap:16px; margin-bottom:10px; }
    .logo-img  { width:64px; height:64px; filter:drop-shadow(0 4px 12px rgba(34,197,94,.35)); }
    .site-title { font-size:clamp(30px,6vw,54px); font-weight:900; letter-spacing:-.03em; line-height:1; }
    .site-title em { color:var(--green); font-style:normal; }
    .header-sub  { color:var(--muted); font-size:13px; text-transform:uppercase; letter-spacing:.08em; margin-bottom:6px; }
    .header-date { color:var(--text); font-size:15px; margin-bottom:28px; opacity:.7; }

    /* ── BADGE MANUEL ── */
    .manual-badge {
      display: inline-block; font-size: 10px; font-weight: 800;
      padding: 2px 7px; border-radius: 4px;
      background: rgba(59,130,246,.15); color: #60a5fa;
      margin-left: 6px; vertical-align: middle;
    }

    /* ── APP DOWNLOAD BANNER ── */
    .app-banner {
      background: linear-gradient(135deg,#0a1628 0%,#0d1f3c 50%,#071020 100%);
      border-bottom: 2px solid var(--yellow);
      padding: 16px 20px;
      display: flex; align-items: center; justify-content: center;
      gap: 16px; flex-wrap: wrap; text-align: center;
      text-decoration: none;
    }
    .app-banner-text { font-size: 14px; font-weight: 600; color: var(--text); line-height: 1.4; }
    .app-banner-text strong { color: var(--yellow); }
    .app-dl-btn {
      display: inline-flex; align-items: center; gap: 8px;
      background: linear-gradient(135deg,#d97706,var(--yellow));
      color: #000; font-weight: 800; font-size: 14px;
      padding: 10px 20px; border-radius: 999px;
      text-decoration: none; white-space: nowrap;
      box-shadow: 0 4px 16px rgba(245,158,11,.35);
      transition: transform .15s, box-shadow .15s;
    }
    .app-dl-btn:hover { transform: scale(1.05); }
    .app-dl-btn svg { width:18px; height:18px; flex-shrink:0; }

    /* ── MAIN ── */
    .main { max-width:860px; margin:0 auto; padding:36px 20px 60px; }
    .toolbar { display:flex; align-items:center; justify-content:space-between; margin-bottom:24px; flex-wrap:wrap; gap:12px; }
    .section-heading { display:flex; align-items:center; gap:12px; }
    .section-heading h2 { font-size:20px; font-weight:800; color:#0a0f1e; }
    .count-badge { background:var(--green); color:#000; font-size:12px; font-weight:800; padding:2px 10px; border-radius:999px; }
    .update-info { display:flex; align-items:center; gap:7px; font-size:13px; color:#9ca3af; }
    .update-dot { width:8px; height:8px; border-radius:50%; background:var(--green); animation:blink 1.8s ease-in-out infinite; }
    @keyframes blink { 0%,100%{opacity:1} 50%{opacity:.3} }

    /* ── CARDS ── */
    .cards-grid { display:grid; gap:24px; }
    .pred-card {
      background:#ffffff;
      border:1px solid #e5e7eb;
      border-radius:18px; overflow:hidden;
      box-shadow:0 4px 20px rgba(0,0,0,.18);
      transition:transform .25s, box-shadow .25s;
    }
    .pred-card:hover { transform:translateY(-4px); box-shadow:0 8px 32px rgba(0,0,0,.25); }

    .c-head {
      display:flex; align-items:center; justify-content:space-between;
      padding:12px 18px; background:#ffffff; border-bottom:1px solid #e5e7eb;
    }
    .league-row { display:flex; align-items:center; gap:8px; }
    .league-logo { width:26px; height:26px; object-fit:contain; border-radius:4px; }
    .league-name { font-size:12px; font-weight:700; text-transform:uppercase; letter-spacing:.06em; color:#6b7280; }
    .ko-pill {
      display:flex; align-items:center; gap:5px;
      background:#111827; color:#e8f0fe;
      padding:4px 12px; border-radius:20px; font-size:12px; font-weight:600;
    }

    .c-match {
      display:grid; grid-template-columns:1fr auto 1fr;
      align-items:center; padding:22px 18px 16px; gap:8px;
      background:#ffffff;
    }
    .team { display:flex; flex-direction:column; align-items:center; gap:8px; text-align:center; }
    .team-logo-wrap {
      width:64px; height:64px; border-radius:50%;
      background:#f1f5f9;
      display:flex; align-items:center; justify-content:center;
      overflow:hidden; border:2px solid #e5e7eb;
    }
    .team-logo { width:46px; height:46px; object-fit:contain; }
    .team-name { font-size:14px; font-weight:700; line-height:1.2; color:#111827; }
    .vs-badge {
      width:36px; height:36px; border-radius:50%;
      background:#f1f5f9; border:1px solid #e5e7eb;
      display:flex; align-items:center; justify-content:center;
      font-size:11px; font-weight:800; color:#9ca3af;
    }

    .c-pred { padding:0 18px 18px; background:#ffffff; }
    .main-pred-box {
      background:#dcfce7;
      border:1px solid #86efac;
      border-radius:12px;
      padding:14px 16px;
      display:flex; align-items:center; justify-content:space-between; gap:12px;
      margin-bottom:10px;
    }
    .main-pred-left { display:flex; align-items:center; gap:10px; flex:1; min-width:0; }
    .pred-dot { width:10px; height:10px; border-radius:50%; background:#16a34a; flex-shrink:0; }
    .main-pred-label { font-size:15px; font-weight:800; line-height:1.2; margin-bottom:3px; color:#111827; }
    .main-pred-conf { font-size:12px; font-weight:600; color:#16a34a; }
    .main-pred-pct { font-size:22px; font-weight:900; color:#16a34a; line-height:1; flex-shrink:0; }

    .stat-row {
      display:flex; align-items:center; gap:10px;
      padding:9px 0; border-top:1px solid #f3f4f6;
      font-size:14px; background:#ffffff;
    }
    .stat-circle {
      width:22px; height:22px; border-radius:50%;
      display:flex; align-items:center; justify-content:center;
      font-size:12px; font-weight:900; flex-shrink:0; line-height:1;
    }
    .stat-circle.yes { background:#16a34a; color:#fff; }
    .stat-circle.no  { background:#ef4444; color:#fff; }
    .stat-circle.neu { background:#f59e0b; color:#fff; }
    .stat-lbl { flex:1; color:#374151; font-weight:500; }
    .stat-pct { font-size:14px; font-weight:700; flex-shrink:0; }
    .stat-pct.yes { color:#16a34a; }
    .stat-pct.no  { color:#ef4444; }
    .stat-pct.neu { color:#f59e0b; }

    /* ── EMPTY ── */
    .empty-state { text-align:center; padding:64px 20px; }
    .empty-icon  { font-size:60px; margin-bottom:16px; }
    .empty-title { font-size:22px; font-weight:700; margin-bottom:8px; color:#111827; }
    .empty-desc  { color:#9ca3af; font-size:15px; line-height:1.6; }

    /* ── FOOTER ── */
    footer {
      text-align:center; padding:28px 20px 24px;
      border-top:2px solid #e5e7eb;
      background: linear-gradient(135deg,#0a0f1e 0%,#111827 100%);
    }
    .promo-logos {
      display:flex; align-items:center; justify-content:center; gap:18px;
      flex-wrap:wrap; margin-bottom:16px;
    }
    .promo-logo-item {
      display:flex; flex-direction:column; align-items:center; gap:6px;
    }
    .promo-logo-item img {
      height:38px; width:auto; max-width:90px; object-fit:contain;
      border-radius:8px; background:#fff; padding:4px 8px;
      box-shadow:0 2px 8px rgba(0,0,0,.4);
    }
    .promo-logo-item span {
      font-size:10px; font-weight:800; color:#9ca3af; letter-spacing:.06em;
    }
    .promo-text {
      font-size:14px; font-weight:600; color:#e5e7eb; line-height:1.7;
      max-width:520px; margin:0 auto;
    }
    .promo-code {
      display:inline-block; background:#facc15; color:#000;
      font-size:17px; font-weight:900; padding:3px 12px; border-radius:6px;
      letter-spacing:.05em; margin:0 3px;
    }
    .promo-bonus {
      color:#4ade80; font-weight:900; font-size:15px;
    }
    @media(max-width:480px){
      .promo-text { font-size:13px; }
      .promo-logo-item img { height:30px; }
    }

    /* ── MANUAL ROWS ── */
    .c-sep { display:none; }
    .manual-row {
      display:flex; align-items:center; gap:10px;
      padding:9px 0; border-top:1px solid #f3f4f6;
      font-size:14px; background:#ffffff;
    }
    .manual-label { flex:1; color:#374151; font-weight:500; }
    .manual-val   { font-size:14px; font-weight:700; flex-shrink:0; }
    .manual-val.yes { color:#16a34a; }
    .manual-val.no  { color:#ef4444; }
    .manual-val.neu { color:#6b7280; }

    /* ── WHATSAPP BANNER ── */
    @keyframes wa-pulse {
      0%,100% { box-shadow: 0 0 10px #25d366, 0 0 30px #25d366, 0 0 60px #128c7e; opacity:1; }
      50%      { box-shadow: 0 0 20px #25d366, 0 0 60px #25d366, 0 0 100px #25d366; opacity:.85; }
    }
    @keyframes wa-text-glow {
      0%,100% { text-shadow: 0 0 6px #fff, 0 0 14px #25d366; }
      50%      { text-shadow: 0 0 14px #fff, 0 0 30px #25d366, 0 0 50px #25d366; }
    }
    @keyframes wa-badge-blink {
      0%,49%  { background: #ff0; color: #000; }
      50%,100%{ background: #ff6f00; color: #fff; }
    }
    .wa-banner {
      background: linear-gradient(135deg, #012c1e 0%, #014d35 50%, #012c1e 100%);
      border-top: 2px solid #25d366; border-bottom: 2px solid #25d366;
      padding: 14px 20px;
      display: flex; align-items: center; justify-content: center; gap: 14px;
      flex-wrap: wrap; text-align: center;
      animation: wa-pulse 2s ease-in-out infinite;
      cursor: pointer;
    }
    .wa-main-text {
      font-size: 15px; font-weight: 600; color: #e0ffe8; line-height: 1.5;
      animation: wa-text-glow 2s ease-in-out infinite;
    }
    .wa-main-text strong {
      display: block; font-size: 18px; font-weight: 900;
      color: #fff; letter-spacing: .04em;
      animation: wa-text-glow 1.2s ease-in-out infinite;
    }
    .wa-join-btn {
      display: inline-flex; align-items: center; gap: 8px;
      padding: 10px 20px; border-radius: 999px;
      background: #25d366; color: #000;
      font-weight: 900; font-size: 13px; text-decoration: none;
      white-space: nowrap; flex-shrink: 0;
      animation: wa-badge-blink 1s step-start infinite;
      border: 2px solid #fff;
    }
    .wa-join-btn svg { width: 18px; height: 18px; flex-shrink: 0; }
    .wa-join-btn:hover { transform: scale(1.06); }
    @media(max-width:600px){
      .wa-main-text { font-size: 13px; }
      .wa-main-text strong { font-size: 15px; }
    }

    /* ── RESPONSIVE ── */
    @media(max-width:600px){
      .c-match { padding:16px 12px; }
      .team-logo-wrap { width:52px; height:52px; }
      .team-logo { width:36px; height:36px; }
      .team-name { font-size:12px; }
      .main-pred-label { font-size:13px; }
      .main-pred-pct { font-size:20px; }
      .app-banner { flex-direction:column; gap:10px; }
    }
  </style>
</head>
<body>

<!-- ═══ HEADER ═══ -->
<header class="header">
  <div class="logo-wrap">
    <img src="logo.svg" alt="Mola Prono" class="logo-img">
    <h1 class="site-title">Mola <em>Prono</em></h1>
  </div>
  <div class="header-sub">Pronostics Football</div>
  <div class="header-date" id="header-date">Chargement…</div>
</header>

<!-- ═══ WHATSAPP BANNER ═══ -->
<a href="https://whatsapp.com/channel/0029VbBrwdH1noz3OjnU5B2V" target="_blank" style="text-decoration:none;display:block">
<div class="wa-banner">
  <div class="wa-main-text">
    Appuyez ici pour avoir le coupon du jour 👉
    <strong>COUPON SCORE EXACT DU JOUR</strong>
  </div>
  <span class="wa-join-btn">
    <svg viewBox="0 0 24 24" fill="currentColor"><path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51-.173-.008-.371-.01-.57-.01-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347m-5.421 7.403h-.004a9.87 9.87 0 01-5.031-1.378l-.361-.214-3.741.982.998-3.648-.235-.374a9.86 9.86 0 01-1.51-5.26c.001-5.45 4.436-9.884 9.888-9.884 2.64 0 5.122 1.03 6.988 2.898a9.825 9.825 0 012.893 6.994c-.003 5.45-4.437 9.884-9.885 9.884m8.413-18.297A11.815 11.815 0 0012.05 0C5.495 0 .16 5.335.157 11.892c0 2.096.547 4.142 1.588 5.945L.057 24l6.305-1.654a11.882 11.882 0 005.683 1.448h.005c6.554 0 11.89-5.335 11.893-11.893a11.821 11.821 0 00-3.48-8.413z"/></svg>
    Rejoindre
  </span>
</div>
</a>

<!-- ═══ BANNIÈRE TÉLÉCHARGEMENT APK ═══ -->
<a href="/download.php" class="app-banner">
  <div class="app-banner-text">
    📲 Téléchargez notre application Android<br>
    <strong>Recevez vos coupons chaque jour par notification push — Gratuit</strong>
  </div>
  <span class="app-dl-btn">
    <svg viewBox="0 0 24 24" fill="currentColor"><path d="M12 4v12m0 0l-4-4m4 4l4-4M4 20h16" stroke="currentColor" stroke-width="2.5" fill="none" stroke-linecap="round" stroke-linejoin="round"/></svg>
    Télécharger l'application mobile
  </span>
</a>

<!-- ═══ MAIN ═══ -->
<main class="main">
  <div class="toolbar">
    <div class="section-heading">
      <h2>Pronostics du Jour</h2>
      <span class="count-badge" id="pred-count">0</span>
    </div>
    <div class="update-info">
      <span class="update-dot"></span>
      <span id="update-ts">—</span>
    </div>
  </div>
  <div class="cards-grid" id="cards"></div>
</main>

<footer>
  <div class="promo-logos">
    <div class="promo-logo-item">
      <img src="images/1xBet-logo.png" alt="1XBET">
    </div>
    <div class="promo-logo-item">
      <img src="images/Melbet-Logo.webp" alt="MELBET">
    </div>
    <div class="promo-logo-item">
      <img src="images/Linebet.logo.jpg" alt="LINEBET">
    </div>
  </div>
  <div class="promo-text">
    Utilisez le meilleur code promo 👉 <span class="promo-code">KS15</span> pour vous inscrire sur
    <strong style="color:#fff">1XBET</strong>, <strong style="color:#fff">MELBET</strong> ou <strong style="color:#fff">LINEBET</strong>
    et recevez un bonus de bienvenu de <span class="promo-bonus">150.000F gratuit et retirable !!!</span>
  </div>
</footer>

<script>
  (function() {
    var s = document.createElement('script');
    s.src = 'https://mola-prono.online/data.js?v=' + new Date().toISOString().slice(0, 10);
    s.onload = function() { if (typeof render === 'function') render(); };
    s.onerror = function() {
      fetch('get_manual_pronos.php')
        .then(function(r) { return r.json(); })
        .catch(function() { return []; })
        .then(function(m) { renderCards([], m || [], null); });
    };
    document.head.appendChild(s);
  })();
</script>

<script>
  function fmtDate(iso) {
    try { return new Date(iso).toLocaleDateString("fr-FR",{weekday:"long",year:"numeric",month:"long",day:"numeric"}); }
    catch(e) { return iso; }
  }
  function fmtTime(iso) {
    try { return new Date(iso).toLocaleTimeString("fr-FR",{hour:"2-digit",minute:"2-digit"}); }
    catch(e) { return "—"; }
  }

  function teamLogoHtml(url, alt) {
    var inner = url
      ? '<img src="'+url+'" alt="'+alt+'" class="team-logo" onerror="this.src=\'data:image/svg+xml,<svg xmlns=%22http://www.w3.org/2000/svg%22 viewBox=%220 0 100 100%22><text y=%22.9em%22 font-size=%2290%22>⚽</text></svg>\'">'
      : '<span style="font-size:34px">⚽</span>';
    return '<div class="team-logo-wrap">'+inner+'</div>';
  }

  function main1N2Label(pred) {
    var mk = pred.markets || {};
    var ht = pred.home_team, at = pred.away_team;
    if (!mk['1n2']) return { label: pred.prediction.label, pct: pred.prediction.probability };
    var n = mk['1n2'];
    var p1 = n['1'] || 0, pN = n['N'] || 0, p2 = n['2'] || 0;
    if (p1 >= p2) {
      return { label: 'Victoire ' + ht + ' ou Nul (1X)', pct: p1 + pN };
    } else {
      return { label: 'Match nul ou Victoire ' + at + ' (2X)', pct: pN + p2 };
    }
  }

  function statRows(pred) {
    var mk = pred.markets || {};
    var rows = '';

    if (mk.btts) {
      var isYes = mk.btts.yes >= mk.btts.no;
      var cls   = isYes ? 'yes' : 'no';
      var icon  = isYes ? '✓' : '✗';
      rows += '<div class="stat-row"><div class="stat-circle '+cls+'">'+icon+'</div><span class="stat-lbl">Les deux équipes vont marquer</span><span class="stat-pct '+cls+'">'+(isYes?'Oui':'Non')+'</span></div>';
    }

    if (mk.over_under) {
      var ou = mk.over_under;
      var thresholds = ['1_5','2_5','3_5'];
      var bestLbl = '', bestPct = 0, bestCls = 'yes';
      for (var i=0; i<thresholds.length; i++) {
        var t = thresholds[i];
        var ov = ou['over_'+t] || 0;
        var un = ou['under_'+t] || 0;
        var winner = ov >= un ? ov : un;
        if (winner > bestPct) {
          bestPct = winner;
          var td = t.replace('_','.');
          bestLbl = ov >= un ? 'Plus de '+td+' buts' : 'Moins de '+td+' buts';
          bestCls = ov >= un ? 'yes' : 'no';
        }
      }
      if (bestLbl) {
        rows += '<div class="stat-row"><div class="stat-circle '+bestCls+'">'+(bestCls==='yes'?'✓':'✗')+'</div><span class="stat-lbl">'+bestLbl+'</span><span class="stat-pct '+bestCls+'">'+(bestCls==='yes'?'Oui':'Non')+'</span></div>';
      }
    }

    return rows;
  }

  function render() {
    var data = (typeof PREDICTIONS_DATA !== 'undefined') ? PREDICTIONS_DATA : null;

    if (data && data.date)
      document.getElementById('header-date').textContent = fmtDate(data.date + 'T12:00:00');
    if (data && data.generated_at)
      document.getElementById('update-ts').textContent = 'Mis à jour à ' + fmtTime(data.generated_at);

    var autoPreds = [];
    if (data && !data.error) {
      autoPreds = (data.top_predictions || []).filter(function(p) {
        if (p.prediction.type === 'CARTONS') {
          var prob = p.prediction.probability;
          return prob >= 50 && prob <= 70;
        }
        return true;
      });
    }

    Promise.all([
      fetch('get_manual_pronos.php').then(function(r){return r.json();}).catch(function(){return [];}),
      fetch('get_suppressed.php').then(function(r){return r.json();}).catch(function(){return [];})
    ]).then(function(results) {
      var manualPreds = results[0] || [];
      var suppressed  = results[1] || [];
      var suppSet = {};
      suppressed.forEach(function(id){ suppSet[String(id)] = true; });
      var filteredAuto = autoPreds.filter(function(p){ return !suppSet[String(p.fixture_id)]; });
      renderCards(filteredAuto, manualPreds, data);
    });
  }

  function renderCards(autoPreds, manualPreds, data) {
    var container = document.getElementById('cards');

    if (!data && !autoPreds.length && !manualPreds.length) {
      container.innerHTML = '<div class="empty-state"><div class="empty-icon">⚠️</div><div class="empty-title">Données non disponibles</div><div class="empty-desc">Revenez après 07h00.</div></div>';
      document.getElementById('pred-count').textContent = '0';
      return;
    }

    var manualMap = {};
    manualPreds.forEach(function(p) {
      var fid = String(p.fixture_id);
      if (!manualMap[fid]) manualMap[fid] = [];
      manualMap[fid].push(p);
    });

    var seen = {};
    var cards = [];
    autoPreds.forEach(function(p) {
      var fid = String(p.fixture_id);
      seen[fid] = true;
      cards.push({ auto: p, manuals: manualMap[fid] || [] });
    });
    manualPreds.forEach(function(p) {
      var fid = String(p.fixture_id);
      if (!seen[fid]) {
        seen[fid] = true;
        cards.push({ auto: null, manuals: manualMap[fid] });
      }
    });

    cards.sort(function(a, b) {
      var ta = ((a.auto || (a.manuals[0]||{})).kick_off || '');
      var tb = ((b.auto || (b.manuals[0]||{})).kick_off || '');
      return ta.localeCompare(tb);
    });

    document.getElementById('pred-count').textContent = cards.length;

    if (!cards.length) {
      container.innerHTML = '<div class="empty-state"><div class="empty-icon">😔</div><div class="empty-title">Aucun pronostic disponible aujourd\'hui</div><div class="empty-desc">Revenez demain !</div></div>';
      return;
    }

    container.innerHTML = cards.map(function(c) { return buildCard(c); }).join('');
  }

  function buildCard(c) {
    var auto    = c.auto;
    var manuals = c.manuals || [];
    var ref     = auto || manuals[0];
    if (!ref) return '';

    var html = '<div class="pred-card">';

    html += '<div class="c-head">'
      +'<div class="league-row">'
      +(ref.league_logo ? '<img src="'+ref.league_logo+'" alt="" class="league-logo" onerror="this.style.display=\'none\'">' : '')
      +'<span class="league-name">'+ref.league+'</span>'
      +'</div>'
      +'<div class="ko-pill">⏱ '+ref.kick_off+'</div>'
      +'</div>';

    html += '<div class="c-match">'
      +'<div class="team">'+teamLogoHtml(ref.home_logo, ref.home_team)+'<span class="team-name">'+ref.home_team+'</span></div>'
      +'<div class="vs-badge">VS</div>'
      +'<div class="team">'+teamLogoHtml(ref.away_logo, ref.away_team)+'<span class="team-name">'+ref.away_team+'</span></div>'
      +'</div>';

    html += '<div class="c-pred">';

    if (auto) {
      var main1n2 = main1N2Label(auto);
      html += '<div class="main-pred-box">'
        +'<div class="main-pred-left">'
        +'<div class="pred-dot"></div>'
        +'<div class="main-pred-label">'+main1n2.label+'</div>'
        +'</div>'
        +'<div class="main-pred-pct">Oui</div>'
        +'</div>'
        +statRows(auto);
    }

    if (auto && manuals.length) {
      html += '<div class="c-sep"></div>';
    }

    manuals.forEach(function(p) {
      var label  = p.prediction.label || '';
      var type   = p.prediction.type  || '';
      var prob   = parseFloat(p.prediction.probability) || 0;
      var mode   = p.display_mode || 'yn';
      var isYes  = prob >= 50;
      var yn     = isYes ? 'Oui' : 'Non';
      var cls    = isYes ? 'yes' : 'no';
      var icon   = isYes ? '✓' : '✗';
      var pct    = Math.round(prob * 10) / 10 + '%';
      var valHtml = '';
      if (type !== 'BUTS') {
        var dispVal, dispCls;
        if (mode === 'pct')         { dispVal = pct;          dispCls = 'neu'; }
        else if (mode === 'pct_yn') { dispVal = pct+' '+yn;   dispCls = cls; }
        else                        { dispVal = yn;            dispCls = cls; }
        valHtml = '<span class="manual-val '+dispCls+'">'+dispVal+'</span>';
      }
      html += '<div class="manual-row">'
        +'<div class="stat-circle '+cls+'">'+icon+'</div>'
        +'<span class="manual-label">'+label+'</span>'
        +valHtml
        +'</div>';
    });

    html += '</div></div>';
    return html;
  }

  fetch('tracker.php').catch(function(){});

  var _loadedDate = new Date().toISOString().slice(0, 10);
  setInterval(function() {
    if (new Date().toISOString().slice(0, 10) !== _loadedDate) window.location.reload(true);
  }, 3600000);
</script>
</body>
</html>
