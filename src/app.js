import { FirebaseMessaging } from '@capacitor-firebase/messaging';

const SERVER   = 'https://mola-prono.online';
const WA_LINK  = 'https://www.whatsapp.com/channel/0029VbBrwdH1noz3OjnU5B2V';

const COUNTRIES = [
  { name: "Bénin",         ind: "229", code: "bj", ph: "01 23 45 67 89" },
  { name: "Burkina Faso",  ind: "226", code: "bf", ph: "70 12 34 56"    },
  { name: "Cameroun",      ind: "237", code: "cm", ph: "6 70 00 00 00"  },
  { name: "Côte d'Ivoire", ind: "225", code: "ci", ph: "07 12 34 56"    },
  { name: "Guinée",        ind: "224", code: "gn", ph: "62 12 34 56"    },
  { name: "RDC",           ind: "243", code: "cd", ph: "81 234 5678"    },
  { name: "Sénégal",       ind: "221", code: "sn", ph: "77 123 45 67"   },
  { name: "Togo",          ind: "228", code: "tg", ph: "90 12 34 56"    },
];

const TYPE_BADGE = {
  "1N2":     ["tb-1n2",     "⚽", "Résultat 1N2"],
  "BUTS":    ["tb-buts",    "🎯", "Nombre de buts"],
  "CARTONS": ["tb-cartons", "🟨", "Cartons jaunes"],
  "BTTS":    ["tb-btts",    "🔵", "Les deux équipes marquent"],
};

let selectedCountry = null;
let bannerTimer = null;

// ── INIT ─────────────────────────────────────────────────────
document.addEventListener('DOMContentLoaded', () => {
  buildCountrySelect();
  loadPronostics();
  initNotifications();
});

// ── COUNTRY SELECT ────────────────────────────────────────────
function buildCountrySelect() {
  const list = document.getElementById('cs-list');
  COUNTRIES.forEach((c, i) => {
    const d = document.createElement('div');
    d.className = 'cs-option';
    d.innerHTML = `<img class="cs-flag" src="https://flagcdn.com/20x15/${c.code}.png" alt=""> ${c.name}`;
    d.addEventListener('click', () => pickCountry(i));
    list.appendChild(d);
  });

  document.addEventListener('click', (e) => {
    if (!document.getElementById('cs-wrap').contains(e.target))
      document.getElementById('cs-list').classList.remove('open');
  });

  document.getElementById('phone-input').addEventListener('input', function () {
    this.value = this.value.replace(/[^0-9]/g, '');
    this.style.borderColor = '';
  });
}

window.toggleCS = function () {
  document.getElementById('cs-list').classList.toggle('open');
};

function pickCountry(idx) {
  const c = COUNTRIES[idx];
  selectedCountry = c;

  const flag = document.getElementById('cs-flag');
  flag.src = `https://flagcdn.com/20x15/${c.code}.png`;
  flag.style.display = '';
  document.getElementById('cs-name').textContent = c.name;
  document.getElementById('cs-name').style.color = '';
  document.getElementById('cs-selected').style.borderColor = '';

  const pref = document.getElementById('phone-prefix');
  pref.textContent = '+' + c.ind;
  pref.style.display = '';

  document.getElementById('phone-input').placeholder = 'Ex: ' + c.ph;
  document.getElementById('phone-input').value = '';
  document.querySelectorAll('.cs-option').forEach((o, i) => o.classList.toggle('sel', i === idx));
  document.getElementById('cs-list').classList.remove('open');
}

// ── WHATSAPP JOIN ─────────────────────────────────────────────
window.joinWhatsApp = function () {
  let valid = true;
  if (!selectedCountry) {
    document.getElementById('cs-selected').style.borderColor = '#f87171';
    valid = false;
  }
  const phone = document.getElementById('phone-input').value.trim();
  if (!phone) {
    document.getElementById('phone-input').style.borderColor = '#f87171';
    valid = false;
  }
  if (!valid) return;

  // Save subscriber (same as website)
  const fd = new FormData();
  fd.append('pays', selectedCountry.name);
  fd.append('indicatif', selectedCountry.ind);
  fd.append('telephone', phone);
  fetch(`${SERVER}/subscribe.php`, { method: 'POST', body: fd }).catch(() => {});

  // Open WhatsApp channel
  window.open(WA_LINK, '_system');
};

// ── PRONOSTICS ────────────────────────────────────────────────
function loadPronostics() {
  const s = document.createElement('script');
  s.src = `${SERVER}/data.js?v=${new Date().toISOString().slice(0, 10)}`;
  s.onload = () => { if (typeof render === 'function') render(); };
  s.onerror = () => showPronoError();
  document.head.appendChild(s);
}

// Called by data.js
window.render = function () {
  const data = typeof PREDICTIONS_DATA !== 'undefined' ? PREDICTIONS_DATA : null;
  const container = document.getElementById('cards');

  if (!data) { showPronoError(); return; }

  if (data.date) {
    document.getElementById('header-date').textContent =
      new Date(data.date + 'T12:00:00').toLocaleDateString('fr-FR', {
        weekday: 'long', day: 'numeric', month: 'long',
      });
  }
  if (data.generated_at) {
    document.getElementById('update-ts').textContent =
      '• Mis à jour ' + new Date(data.generated_at).toLocaleTimeString('fr-FR', { hour: '2-digit', minute: '2-digit' });
  }

  if (data.error && !(data.top_predictions && data.top_predictions.length)) {
    container.innerHTML = emptyState('⚠️', 'Analyse non disponible', data.error);
    return;
  }

  const preds = (data.top_predictions || []).filter(p => {
    if (p.prediction.type === 'CARTONS') {
      const prob = p.prediction.probability;
      return prob >= 45 && prob <= 75;
    }
    return true;
  });

  document.getElementById('pred-count').textContent = preds.length;

  if (!preds.length) {
    container.innerHTML = emptyState('😔', 'Aucun pronostic aujourd\'hui', 'Revenez demain !');
    return;
  }

  container.innerHTML = preds.map((p, i) => buildCard(p, i + 1)).join('');

  setTimeout(() => {
    document.querySelectorAll('.bar-fill').forEach(el => {
      setTimeout(() => { el.style.width = el.dataset.w + '%'; }, 80);
    });
  }, 150);
};

function buildCard(pred, rank) {
  const d  = pred.prediction;
  const tb = TYPE_BADGE[d.type] || ['tb-buts', '🎯', d.type];
  const confCls = d.probability >= 80 ? 'conf-high' : 'conf-medium';
  const confLbl = d.probability >= 80 ? 'Haute' : 'Moyenne';

  return `
  <div class="pred-card">
    <div class="rank-ribbon">#${rank}</div>
    <div class="c-head">
      <div class="league-row">
        ${pred.league_logo ? `<img src="${pred.league_logo}" class="league-logo" alt="" onerror="this.style.display='none'">` : ''}
        <span class="league-name">${pred.league}</span>
      </div>
      <div class="ko-pill">🕐 ${pred.kick_off}</div>
    </div>
    <div class="c-match">
      <div class="team">${teamLogo(pred.home_logo, pred.home_team)}<span class="team-name">${pred.home_team}</span></div>
      <div class="vs-badge">VS</div>
      <div class="team">${teamLogo(pred.away_logo, pred.away_team)}<span class="team-name">${pred.away_team}</span></div>
    </div>
    <div class="c-pred">
      <span class="type-badge ${tb[0]}">${tb[1]} ${tb[2]}</span>
      <div class="pred-row">
        <div class="pred-label">${d.label}<span class="confidence ${confCls}">${confLbl}</span></div>
        <div class="prob-block"><div class="prob-num">${d.probability}%</div><div class="prob-lbl">probabilité</div></div>
      </div>
      <div class="bar-track"><div class="bar-fill" data-w="${d.probability}"></div></div>
      ${detailRows(pred)}
    </div>
  </div>`;
}

function teamLogo(url, alt) {
  if (!url) return `<span style="font-size:40px">⚽</span>`;
  return `<img src="${url}" alt="${alt}" class="team-logo" onerror="this.src='data:image/svg+xml,<svg xmlns=%22http://www.w3.org/2000/svg%22 viewBox=%220 0 100 100%22><text y=%22.9em%22 font-size=%2290%22>⚽</text></svg>'">`;
}

function detailRows(pred) {
  const mk = pred.markets || {};
  let rows = '';

  if (mk.btts) {
    const isYes = mk.btts.yes >= mk.btts.no;
    const pct   = isYes ? mk.btts.yes : mk.btts.no;
    rows += `<div class="detail-row"><span class="dr-key">Les deux équipes vont marquer</span><span class="dr-val ${isYes ? 'dr-yes' : 'dr-no'}">${isYes ? '✓ Oui' : '✗ Non'}<span class="dr-pct">(${pct}%)</span></span></div>`;
  }

  if (mk.over_under) {
    const ou     = mk.over_under;
    const isOver = (ou.over_2_5 || 0) >= (ou.under_2_5 || 0);
    const pct    = isOver ? (ou.over_2_5 || 0) : (ou.under_2_5 || 0);
    rows += `<div class="detail-row"><span class="dr-key">Plus de 2.5 buts dans le match</span><span class="dr-val ${isOver ? 'dr-yes' : 'dr-no'}">${isOver ? '✓ Oui' : '✗ Non'}<span class="dr-pct">(${pct}%)</span></span></div>`;
  }

  if (mk['1n2']) {
    const n = mk['1n2'];
    const best = (n['1'] >= n['N'] && n['1'] >= n['2']) ? '1' : (n['N'] >= n['2'] ? 'N' : '2');
    let question, pct, cls;
    if (best === '1')      { question = `1X — Victoire ${pred.home_team} ou Nul`; pct = n['1'] || 0; cls = 'dr-yes'; }
    else if (best === '2') { question = `2X — Victoire ${pred.away_team} ou Nul`; pct = n['2'] || 0; cls = 'dr-yes'; }
    else                   { question = 'Match nul'; pct = n['N'] || 0; cls = 'dr-neu'; }
    rows += `<div class="detail-row"><span class="dr-key">${question}</span><span class="dr-val ${cls}">✓ Oui<span class="dr-pct">(${pct}%)</span></span></div>`;
  }

  return rows;
}

function showPronoError() {
  document.getElementById('cards').innerHTML = emptyState('⚠️', 'Données non disponibles', 'Revenez après 07h00');
}

function emptyState(icon, title, sub) {
  return `<div class="empty-state"><span class="empty-icon">${icon}</span><p>${title}</p><small>${sub}</small></div>`;
}

// ── PUSH NOTIFICATIONS ────────────────────────────────────────
async function initNotifications() {
  try {
    const perm = await FirebaseMessaging.requestPermissions();
    if (perm.receive !== 'granted') return;

    // Subscribe to main topic (all users)
    await FirebaseMessaging.subscribeToTopic({ topic: 'tous' });

    // Detect country via IP and subscribe to country topic
    let geo = {};
    try {
      geo = await fetch('https://ip-api.com/json?fields=country,countryCode,city').then(r => r.json());
      if (geo.countryCode) {
        await FirebaseMessaging.subscribeToTopic({ topic: 'pays_' + geo.countryCode.toLowerCase() });
      }
    } catch (_) {}

    // Get token and save to server
    const { token } = await FirebaseMessaging.getToken();
    if (token) saveDevice(token, geo);

    // Notification received while app is open
    await FirebaseMessaging.addListener('notificationReceived', ({ notification }) => {
      showBanner(notification.title || 'Mola Prono', notification.body || '');
    });

    // User tapped notification (app was closed/background)
    await FirebaseMessaging.addListener('notificationActionPerformed', ({ notification }) => {
      const data = notification.data || {};
      if (data.notif_id) trackOpen(data.notif_id);
      document.getElementById('notif-dot').classList.add('hidden');
    });

  } catch (e) {
    // Silent fail — notifications not critical for app function
  }
}

function saveDevice(token, geo) {
  fetch(`${SERVER}/save_token.php`, {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify({
      token,
      country:      geo.country      || '',
      country_code: geo.countryCode  || '',
      city:         geo.city         || '',
    }),
  }).catch(() => {});
}

function trackOpen(notifId) {
  fetch(`${SERVER}/track_open.php`, {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify({ notif_id: notifId }),
  }).catch(() => {});
}

// ── IN-APP BANNER ─────────────────────────────────────────────
function showBanner(title, body) {
  const banner = document.getElementById('notif-banner');
  document.getElementById('notif-banner-title').textContent = title;
  document.getElementById('notif-banner-body').textContent  = body;

  if (bannerTimer) clearTimeout(bannerTimer);
  banner.classList.add('show');
  document.getElementById('notif-dot').classList.remove('hidden');

  bannerTimer = setTimeout(() => banner.classList.remove('show'), 5000);
}

window.dismissBanner = function () {
  const banner = document.getElementById('notif-banner');
  banner.classList.remove('show');
  if (bannerTimer) clearTimeout(bannerTimer);
};
