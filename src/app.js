import { FirebaseMessaging } from '@capacitor-firebase/messaging';
import './style.css';

// Serveur API mobile (tokens FCM, stats, pronos manuels avec CORS)
const API_SERVER    = 'https://sms.mola-prono.online';
// Serveur data : data.js via <script> tag (pas de CORS requis pour les balises script)
const DATA_SERVER   = 'https://mola-prono.online';
// Serveur mobile : get_manual_pronos.php + get_suppressed.php avec header CORS
const MOBILE_SERVER = 'https://sms.mola-prono.online';

const WA_LINK   = 'https://www.whatsapp.com/channel/0029VbBrwdH1noz3OjnU5B2V';
const NOTIF_KEY = 'mola_notifications';

let bannerTimer = null;

// ── INIT ──────────────────────────────────────────────────────
document.addEventListener('DOMContentLoaded', () => {
  // Afficher le bloc téléchargement UNIQUEMENT dans un navigateur (pas dans l'app Capacitor)
  const dlSection = document.getElementById('app-download-section');
  if (dlSection) {
    if (!window.Capacitor) {
      dlSection.style.display = '';
    }
  }

  setHeaderDate();
  logActivityOnStart();
  loadPronostics();
  renderNotifPage();
  initNotifications();
  scheduleAutoClear();
});

// ── ONGLETS ──────────────────────────────────────────────────
window.switchTab = function (tab, btn) {
  document.querySelectorAll('.tab-page').forEach(p => p.classList.remove('active'));
  document.querySelectorAll('.nav-btn').forEach(b => b.classList.remove('active'));
  document.getElementById('page-' + tab).classList.add('active');
  btn.classList.add('active');
  if (tab === 'notifs') renderNotifPage();
};

// ── DATE HEADER ───────────────────────────────────────────────
function setHeaderDate() {
  const el = document.getElementById('header-date');
  if (el) el.textContent = new Date().toLocaleDateString('fr-FR', {
    weekday: 'long', day: 'numeric', month: 'long',
  });
}

// ── CHARGEMENT PRONOSTICS ─────────────────────────────────────
function loadPronostics() {
  const s = document.createElement('script');
  s.src = DATA_SERVER + '/data.js?v=' + new Date().toISOString().slice(0, 10);
  s.onload  = () => mergeAndRender();
  s.onerror = () => mergeAndRender();
  document.head.appendChild(s);
}

function mergeAndRender() {
  // eslint-disable-next-line no-undef
  const data = (typeof PREDICTIONS_DATA !== 'undefined') ? PREDICTIONS_DATA : null;

  if (data && data.date) {
    const el = document.getElementById('header-date');
    if (el) el.textContent = new Date(data.date + 'T12:00:00').toLocaleDateString('fr-FR', {
      weekday: 'long', day: 'numeric', month: 'long',
    });
  }
  if (data && data.generated_at) {
    const el = document.getElementById('update-ts');
    if (el) el.textContent = '• ' + new Date(data.generated_at).toLocaleTimeString('fr-FR', {
      hour: '2-digit', minute: '2-digit',
    });
  }

  const autoPreds = [];
  if (data && !data.error) {
    (data.top_predictions || []).forEach(p => {
      if (p.prediction.type === 'CARTONS') {
        const prob = p.prediction.probability;
        if (prob >= 50 && prob <= 70) autoPreds.push(p);
      } else {
        autoPreds.push(p);
      }
    });
  }

  Promise.all([
    fetch(MOBILE_SERVER + '/get_manual_pronos.php').then(r => r.json()).catch(() => []),
    fetch(MOBILE_SERVER + '/get_suppressed.php').then(r => r.json()).catch(() => []),
  ]).then(([manualPreds, suppressed]) => {
    const suppSet = {};
    (suppressed || []).forEach(id => { suppSet[String(id)] = true; });
    const filteredAuto = autoPreds.filter(p => !suppSet[String(p.fixture_id)]);
    renderCards(filteredAuto, manualPreds || [], data);
  });
}

function renderCards(autoPreds, manualPreds, data) {
  const container = document.getElementById('cards');
  if (!container) return;

  if (!data && !autoPreds.length && !manualPreds.length) {
    container.innerHTML = emptyState('⚠️', 'Données non disponibles', 'Revenez après 07h00');
    document.getElementById('pred-count').textContent = '0';
    return;
  }

  // Grouper les manuels par fixture_id
  const manualMap = {};
  (manualPreds || []).forEach(p => {
    const fid = String(p.fixture_id);
    if (!manualMap[fid]) manualMap[fid] = [];
    manualMap[fid].push(p);
  });

  // Construire une carte par match
  const seen  = {};
  const cards = [];
  (autoPreds || []).forEach(p => {
    const fid = String(p.fixture_id);
    seen[fid] = true;
    cards.push({ auto: p, manuals: manualMap[fid] || [] });
  });
  (manualPreds || []).forEach(p => {
    const fid = String(p.fixture_id);
    if (!seen[fid]) {
      seen[fid] = true;
      cards.push({ auto: null, manuals: manualMap[fid] });
    }
  });

  // Trier par kick_off
  cards.sort((a, b) => {
    const ta = ((a.auto || (a.manuals[0] || {})).kick_off || '');
    const tb = ((b.auto || (b.manuals[0] || {})).kick_off || '');
    return ta.localeCompare(tb);
  });

  document.getElementById('pred-count').textContent = cards.length;

  if (!cards.length) {
    container.innerHTML = emptyState('😔', 'Aucun pronostic aujourd\'hui', 'Revenez demain !');
    return;
  }

  container.innerHTML = cards.map(c => buildCard(c)).join('');

  setTimeout(() => {
    document.querySelectorAll('.bar-fill').forEach(el => {
      setTimeout(() => { el.style.width = el.dataset.w + '%'; }, 80);
    });
  }, 150);
}

// ── CONSTRUCTION D'UNE CARTE ──────────────────────────────────
function buildCard(c) {
  const auto    = c.auto;
  const manuals = c.manuals || [];
  const ref     = auto || manuals[0];
  if (!ref) return '';

  let html = '<div class="pred-card">';

  // Entête ligue + horaire
  html += '<div class="c-head">'
    + '<div class="league-row">'
    + (ref.league_logo ? `<img src="${ref.league_logo}" alt="" class="league-logo" onerror="this.style.display='none'">` : '')
    + `<span class="league-name">${ref.league}</span>`
    + '</div>'
    + `<div class="ko-pill">⏱ ${ref.kick_off}</div>`
    + '</div>';

  // Équipes
  html += '<div class="c-match">'
    + `<div class="team">${teamLogo(ref.home_logo, ref.home_team)}<span class="team-name">${ref.home_team}</span></div>`
    + '<div class="vs-badge">VS</div>'
    + `<div class="team">${teamLogo(ref.away_logo, ref.away_team)}<span class="team-name">${ref.away_team}</span></div>`
    + '</div>';

  html += '<div class="c-pred">';

  // Bloc pronostic AUTO
  if (auto) {
    const lbl = main1N2Label(auto);
    html += '<div class="main-pred-box">'
      + '<div class="main-pred-left"><div class="pred-dot"></div>'
      + `<div class="main-pred-label">${lbl.label}</div></div>`
      + '<div class="main-pred-pct">Oui</div>'
      + '</div>'
      + statRows(auto);
  }

  // Séparateur si auto + manuels
  if (auto && manuals.length) html += '<div class="c-sep"></div>';

  // Lignes manuelles
  manuals.forEach(p => {
    const label = p.prediction.label || '';
    const type  = p.prediction.type  || '';
    const prob  = parseFloat(p.prediction.probability) || 0;
    const mode  = p.display_mode || 'yn';
    const isYes = prob >= 50;
    const yn    = isYes ? 'Oui' : 'Non';
    const cls   = isYes ? 'yes' : 'no';
    const icon  = isYes ? '✓' : '✗';
    const pct   = Math.round(prob * 10) / 10 + '%';
    let valHtml = '';
    if (type !== 'BUTS') {
      let dispVal, dispCls;
      if (mode === 'pct')         { dispVal = pct;          dispCls = 'neu'; }
      else if (mode === 'pct_yn') { dispVal = pct + ' ' + yn; dispCls = cls; }
      else                        { dispVal = yn;             dispCls = cls; }
      valHtml = `<span class="manual-val ${dispCls}">${dispVal}</span>`;
    }
    html += `<div class="manual-row"><div class="stat-circle ${cls}">${icon}</div>`
      + `<span class="manual-label">${label}</span>${valHtml}</div>`;
  });

  html += '</div></div>';
  return html;
}

function main1N2Label(pred) {
  const mk = pred.markets || {};
  if (!mk['1n2']) return { label: pred.prediction.label };
  const n = mk['1n2'];
  const p1 = n['1'] || 0, pN = n['N'] || 0, p2 = n['2'] || 0;
  if (p1 >= p2) {
    return { label: `Victoire ${pred.home_team} ou Nul (1X)` };
  } else {
    return { label: `Match nul ou Victoire ${pred.away_team} (2X)` };
  }
}

function statRows(pred) {
  const mk = pred.markets || {};
  let rows = '';

  if (mk.btts) {
    const isYes = mk.btts.yes >= mk.btts.no;
    const cls   = isYes ? 'yes' : 'no';
    rows += `<div class="stat-row"><div class="stat-circle ${cls}">${isYes ? '✓' : '✗'}</div>`
      + `<span class="stat-lbl">Les deux équipes vont marquer</span>`
      + `<span class="stat-pct ${cls}">${isYes ? 'Oui' : 'Non'}</span></div>`;
  }

  if (mk.over_under) {
    const ou = mk.over_under;
    let bestLbl = '', bestPct = 0, bestCls = 'yes';
    for (const t of ['1_5', '2_5', '3_5']) {
      const ov = ou['over_' + t] || 0;
      const un = ou['under_' + t] || 0;
      const w  = ov >= un ? ov : un;
      if (w > bestPct) {
        bestPct = w;
        const td = t.replace('_', '.');
        bestLbl  = ov >= un ? `Plus de ${td} buts` : `Moins de ${td} buts`;
        bestCls  = ov >= un ? 'yes' : 'no';
      }
    }
    if (bestLbl) {
      rows += `<div class="stat-row"><div class="stat-circle ${bestCls}">${bestCls === 'yes' ? '✓' : '✗'}</div>`
        + `<span class="stat-lbl">${bestLbl}</span>`
        + `<span class="stat-pct ${bestCls}">${bestCls === 'yes' ? 'Oui' : 'Non'}</span></div>`;
    }
  }

  return rows;
}

function teamLogo(url, alt) {
  if (!url) return '<span style="font-size:40px">⚽</span>';
  return `<img src="${url}" alt="${alt}" class="team-logo" `
    + `onerror="this.src='data:image/svg+xml,<svg xmlns=%22http://www.w3.org/2000/svg%22 viewBox=%220 0 100 100%22><text y=%22.9em%22 font-size=%2290%22>⚽</text></svg>'">`;
}

function emptyState(icon, title, sub) {
  return `<div class="empty-state"><span class="empty-icon">${icon}</span><p>${title}</p><small>${sub}</small></div>`;
}

// ── HISTORIQUE NOTIFICATIONS ──────────────────────────────────
function getNotifHistory() {
  try {
    const all    = JSON.parse(localStorage.getItem(NOTIF_KEY) || '[]');
    const cutoff = Date.now() - 24 * 60 * 60 * 1000;
    const fresh  = all.filter(n => new Date(n.date).getTime() > cutoff);
    if (fresh.length !== all.length) localStorage.setItem(NOTIF_KEY, JSON.stringify(fresh));
    return fresh;
  } catch { return []; }
}

function saveNotifToHistory(title, body) {
  const history = getNotifHistory();
  history.unshift({ title, body, date: new Date().toISOString(), read: false });
  if (history.length > 50) history.pop();
  localStorage.setItem(NOTIF_KEY, JSON.stringify(history));
  updateNotifBadge();
}

function updateNotifBadge() {
  const unread = getNotifHistory().filter(n => !n.read).length;
  const badge  = document.getElementById('notif-badge');
  if (!badge) return;
  if (unread > 0) {
    badge.textContent = unread > 9 ? '9+' : String(unread);
    badge.classList.remove('hidden');
  } else {
    badge.classList.add('hidden');
  }
}

function renderNotifPage() {
  const list    = document.getElementById('notif-list');
  const counter = document.getElementById('notif-count');
  if (!list) return;

  const history = getNotifHistory();
  if (counter) counter.textContent = history.length;

  history.forEach(n => { n.read = true; });
  localStorage.setItem(NOTIF_KEY, JSON.stringify(history));
  const badge = document.getElementById('notif-badge');
  if (badge) badge.classList.add('hidden');

  if (!history.length) {
    list.innerHTML = `<div class="empty-state">
      <span class="empty-icon">🔔</span>
      <p>Aucune notification</p>
      <small>Vos notifications apparaîtront ici</small>
    </div>`;
    return;
  }

  list.innerHTML = history.map(n => {
    const dt = new Date(n.date).toLocaleString('fr-FR', {
      day: '2-digit', month: '2-digit', year: 'numeric',
      hour: '2-digit', minute: '2-digit',
    });
    return `<div class="notif-item">
      <div class="notif-item-icon">🔔</div>
      <div class="notif-item-body">
        <div class="notif-item-title">${escHtml(n.title)}</div>
        <div class="notif-item-text">${escHtml(n.body)}</div>
        <div class="notif-item-footer">
          <span class="notif-item-date">${dt}</span>
          <a href="${WA_LINK}" target="_blank" class="notif-wa-btn">
            <svg viewBox="0 0 24 24" fill="currentColor" width="13" height="13"><path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51-.173-.008-.371-.01-.57-.01-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347m-5.421 7.403h-.004a9.87 9.87 0 01-5.031-1.378l-.361-.214-3.741.982.998-3.648-.235-.374a9.86 9.86 0 01-1.51-5.26c.001-5.45 4.436-9.884 9.888-9.884 2.64 0 5.122 1.03 6.988 2.898a9.825 9.825 0 012.893 6.994c-.003 5.45-4.437 9.884-9.885 9.884m8.413-18.297A11.815 11.815 0 0012.05 0C5.495 0 .16 5.335.157 11.892c0 2.096.547 4.142 1.588 5.945L.057 24l6.305-1.654a11.882 11.882 0 005.683 1.448h.005c6.554 0 11.89-5.335 11.893-11.893a11.821 11.821 0 00-3.48-8.413z"/></svg>
            Voir le coupon
          </a>
        </div>
      </div>
    </div>`;
  }).join('');
}

function escHtml(s) {
  return String(s || '').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;');
}

// ── PUSH NOTIFICATIONS (FCM) ──────────────────────────────────
async function initNotifications() {
  try {
    // Créer le canal Android avec haute priorité + visibilité écran verrouillé
    try {
      await FirebaseMessaging.createChannel({
        id:        'mola_prono_channel',
        name:      'Mola Prono',
        importance: 4,  // IMPORTANCE_HIGH
        visibility: 1,  // VISIBILITY_PUBLIC (affiché sur écran verrouillé)
        vibration:  true,
      });
    } catch (_) {}

    await FirebaseMessaging.requestPermissions();

    // Topic principal : tous les utilisateurs
    await FirebaseMessaging.subscribeToTopic({ topic: 'tous' });

    // Géolocalisation + topic pays
    let geo = {};
    try {
      geo = await fetch('https://ip-api.com/json?fields=country,countryCode,city').then(r => r.json());
      if (geo.countryCode) {
        await FirebaseMessaging.subscribeToTopic({ topic: 'pays_' + geo.countryCode.toLowerCase() });
      }
    } catch (_) {}

    // Enregistrer le token FCM + tracer activité du jour
    const { token } = await FirebaseMessaging.getToken();
    if (token) {
      saveDevice(token, geo);
      logActivity(token, geo);
    }

    // Notification reçue en foreground (app ouverte) → petite bannière
    await FirebaseMessaging.addListener('notificationReceived', ({ notification }) => {
      const title = notification.title || 'Mola Prono';
      const body  = notification.body  || '';
      saveNotifToHistory(title, body);
      showBanner(title, body);
    });

    // Tap sur une notification depuis la barre → popup modal + onglet notifs
    await FirebaseMessaging.addListener('notificationActionPerformed', ({ notification }) => {
      const data  = notification.data  || {};
      const title = notification.title || data.title || 'Mola Prono';
      const body  = notification.body  || data.body  || '';
      if (data.notif_id) trackOpen(data.notif_id);
      saveNotifToHistory(title, body);
      showNotifPopup(title, body);
      renderNotifPage();
      updateNotifBadge();
    });

    // Démarrage froid (app tuée, tap sur notification)
    try {
      const initial = await FirebaseMessaging.getInitialNotification();
      if (initial && initial.notification) {
        const data  = initial.notification.data  || {};
        const title = initial.notification.title || data.title || 'Mola Prono';
        const body  = initial.notification.body  || data.body  || '';
        if (data.notif_id) trackOpen(data.notif_id);
        saveNotifToHistory(title, body);
        setTimeout(() => showNotifPopup(title, body), 600);
        renderNotifPage();
      }
    } catch (_) {}

    updateNotifBadge();
  } catch (_) {}
}


function saveDevice(token, geo) {
  fetch(API_SERVER + '/server/save_token.php', {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify({
      token,
      country:      geo.country     || '',
      country_code: geo.countryCode || '',
      city:         geo.city        || '',
    }),
  }).catch(() => {});
}

function getDeviceId() {
  let id = localStorage.getItem('mola_device_id');
  if (!id) {
    id = 'dev_' + Math.random().toString(36).substr(2, 12) + Date.now().toString(36);
    localStorage.setItem('mola_device_id', id);
  }
  return id;
}

function logActivityOnStart() {
  const today = new Date().toISOString().slice(0, 10);
  const key   = 'mola_activity_' + today;
  if (localStorage.getItem(key)) return; // déjà loggé aujourd'hui
  const deviceId = getDeviceId();
  fetch(API_SERVER + '/server/log_activity.php', {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify({ token: deviceId, country: '', country_code: '' }),
  }).then(() => {
    localStorage.setItem(key, '1');
  }).catch(() => {});
}

function logActivity(token, geo) {
  fetch(API_SERVER + '/server/log_activity.php', {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify({
      token,
      country:      geo.country     || '',
      country_code: geo.countryCode || '',
    }),
  }).catch(() => {});
}

function trackOpen(notifId) {
  fetch(API_SERVER + '/server/track_open.php', {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify({ notif_id: notifId }),
  }).catch(() => {});
}

// ── BANNIÈRE IN-APP ───────────────────────────────────────────
function showBanner(title, body) {
  const banner = document.getElementById('notif-banner');
  if (!banner) return;
  document.getElementById('notif-banner-title').textContent = title;
  document.getElementById('notif-banner-body').textContent  = body;
  if (bannerTimer) clearTimeout(bannerTimer);
  banner.classList.add('show');
  bannerTimer = setTimeout(() => banner.classList.remove('show'), 5000);
}

window.dismissBanner = function () {
  const banner = document.getElementById('notif-banner');
  if (banner) banner.classList.remove('show');
  if (bannerTimer) clearTimeout(bannerTimer);
};

// ── POPUP MODAL NOTIFICATION ──────────────────────────────────
function showNotifPopup(title, body) {
  const popup = document.getElementById('notif-popup');
  if (!popup) return;
  document.getElementById('notif-popup-title').textContent = title;
  document.getElementById('notif-popup-body').textContent  = body;
  popup.style.display = 'flex';
}

window.closeNotifPopup = function () {
  const popup = document.getElementById('notif-popup');
  if (popup) popup.style.display = 'none';
  const notifBtn = document.querySelectorAll('.nav-btn')[1];
  if (notifBtn) notifBtn.click();
};

window.openWaCoupon = function () {
  const popup = document.getElementById('notif-popup');
  if (popup) popup.style.display = 'none';
  window.open(WA_LINK, '_blank');
};

function scheduleAutoClear() {
  const now   = new Date();
  const next8 = new Date(now);
  next8.setHours(8, 0, 0, 0);
  if (next8 <= now) next8.setDate(next8.getDate() + 1);
  setTimeout(() => {
    localStorage.removeItem(NOTIF_KEY);
    renderNotifPage();
    updateNotifBadge();
    scheduleAutoClear();
  }, next8 - now);
}

// ── RÉCEPTION NATIVE (notification système → popup in-app) ────
window.onNativeNotifTap = function (title, body) {
  saveNotifToHistory(title, body);
  showNotifPopup(title, body);
  renderNotifPage();
};

// ── PARTAGER L'APPLICATION ────────────────────────────────────
window.shareApp = async function () {
  const url  = 'https://sms.mola-prono.online';
  const text = 'Pronostics football gratuits + notifications push quotidiennes';
  try {
    if (navigator.share) {
      await navigator.share({ title: 'Mola Prono', text, url });
    } else {
      await navigator.clipboard.writeText(url);
      alert('Lien copié dans le presse-papiers !');
    }
  } catch (_) {}
};
