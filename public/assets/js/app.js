/* =========================================================
   RemitRova App — connected to the real backend.
   Auth and NGN NUBAN provisioning call the real Laravel API on
   Railway. Sending money, PLN wallets, and transaction history are
   NOT built on the backend yet — those are clearly marked as
   "coming soon" in the UI rather than faked, now that real data
   exists elsewhere on the same screens.
   Depends on assets/js/i18n.js being loaded first (t(), setLang()).
   ========================================================= */

// Served same-origin from the Laravel backend now, so API calls use
// relative paths — no hardcoded domain, no CORS needed.
const API_BASE = '';

let auth = { token: null, customer: null };
let authMode = 'login';
let historyFilter = 'all';

/* ---------------- persistence ---------------- */
function loadAuth() {
  const raw = localStorage.getItem('remitrova_auth');
  return raw ? JSON.parse(raw) : null;
}
function saveAuth() {
  localStorage.setItem('remitrova_auth', JSON.stringify(auth));
}
function clearAuth() {
  localStorage.removeItem('remitrova_auth');
  auth = { token: null, customer: null };
}

/* ---------------- API helper ---------------- */
async function apiFetch(path, options = {}) {
  const headers = Object.assign(
    { 'Content-Type': 'application/json', 'Accept': 'application/json' },
    options.headers || {}
  );
  if (auth.token) headers['Authorization'] = 'Bearer ' + auth.token;

  let res;
  try {
    res = await fetch(API_BASE + path, Object.assign({}, options, { headers }));
  } catch (networkErr) {
    const err = new Error('Could not reach the server. Check your connection and try again.');
    err.status = 0;
    throw err;
  }

  let data = null;
  try { data = await res.json(); } catch (e) { /* empty/non-JSON body is fine for some responses */ }

  if (!res.ok) {
    let message = 'Request failed (' + res.status + ').';
    if (data) {
      if (data.message) message = data.message;
      else if (data.errors) {
        const firstField = Object.values(data.errors)[0];
        if (Array.isArray(firstField) && firstField[0]) message = firstField[0];
      }
    }
    const err = new Error(message);
    err.status = res.status;
    err.data = data;
    throw err;
  }

  return data;
}

/* ---------------- i18n plumbing ---------------- */
function applyStaticTranslations() {
  document.querySelectorAll('[data-i18n]').forEach(el => {
    const key = el.getAttribute('data-i18n');
    const val = t(key);
    if (typeof val === 'string') el.textContent = val;
  });
  document.querySelectorAll('[data-i18n-placeholder]').forEach(el => {
    const key = el.getAttribute('data-i18n-placeholder');
    const val = t(key);
    if (typeof val === 'string') el.placeholder = val;
  });
  refreshAuthModeText();
  document.documentElement.lang = currentLang;
}

/* ---------------- helpers ---------------- */
function fmt(n, decimals = 2) {
  return Number(n).toLocaleString('en-US', { minimumFractionDigits: decimals, maximumFractionDigits: decimals });
}
function initials(name) {
  return (name || '?').trim().split(/\s+/).map(w => w[0]).slice(0, 2).join('').toUpperCase();
}
function showToast(msg) {
  const el = document.getElementById('toast');
  el.textContent = msg;
  el.classList.add('show');
  clearTimeout(showToast._t);
  showToast._t = setTimeout(() => el.classList.remove('show'), 2600);
}
function comingSoon() {
  showToast(t('toast_coming_soon'));
}

/* ---------------- landing <-> auth navigation ---------------- */
function goToAuth(mode) {
  const landing = document.getElementById('screen-landing');
  if (landing) landing.classList.remove('active');
  document.getElementById('screen-auth').classList.add('active');
  setAuthMode(mode);
  window.scrollTo(0, 0);
}
function goToLanding() {
  const landing = document.getElementById('screen-landing');
  if (!landing) return; // this build has no landing page — nothing to go back to
  document.getElementById('screen-auth').classList.remove('active');
  document.getElementById('screen-app').classList.remove('active');
  landing.classList.add('active');
  window.scrollTo(0, 0);
}

/* ---------------- auth ---------------- */
function setAuthMode(mode) {
  authMode = mode;
  document.getElementById('tabLogin').classList.toggle('active', mode === 'login');
  document.getElementById('tabSignup').classList.toggle('active', mode === 'signup');
  document.getElementById('fieldName').style.display = mode === 'signup' ? 'block' : 'none';
  document.getElementById('fieldCountry').style.display = mode === 'signup' ? 'block' : 'none';
  document.getElementById('authError').classList.remove('show');
  refreshAuthModeText();
}

function refreshAuthModeText() {
  const mode = authMode;
  document.getElementById('authTitle').textContent = mode === 'signup' ? t('signup_title') : t('login_title');
  document.getElementById('authSub').textContent = mode === 'signup' ? t('signup_sub') : t('login_sub');
  document.getElementById('authSubmitBtn').textContent = mode === 'signup' ? t('btn_signup') : t('btn_login');
  const footKey = mode === 'signup' ? 'foot_have_account' : 'foot_new_here';
  const linkKey = mode === 'signup' ? 'link_login' : 'link_create_account';
  const nextMode = mode === 'signup' ? 'login' : 'signup';
  document.getElementById('authFoot').innerHTML =
    `<span>${t(footKey)}</span> <a href="#" onclick="setAuthMode('${nextMode}'); return false;" style="font-weight:700;text-decoration:underline;">${t(linkKey)}</a>`;
}

async function handleAuthSubmit(e) {
  e.preventDefault();
  const email = document.getElementById('authEmail').value.trim();
  const password = document.getElementById('authPassword').value;
  const errEl = document.getElementById('authError');
  const submitBtn = document.getElementById('authSubmitBtn');

  errEl.classList.remove('show');

  if (!email || password.length < 6) {
    errEl.textContent = t('auth_error_generic');
    errEl.classList.add('show');
    return false;
  }

  submitBtn.disabled = true;
  const originalLabel = submitBtn.textContent;
  submitBtn.textContent = t('please_wait');

  try {
    let data;
    if (authMode === 'signup') {
      const name = document.getElementById('authName').value.trim();
      const country = document.getElementById('authCountry').value;
      if (!name) {
        throw new Error(t('auth_error_generic'));
      }
      data = await apiFetch('/api/auth/register', {
        method: 'POST',
        body: JSON.stringify({ name, email, password, country }),
      });
      showToast(t('toast_account_created'));
    } else {
      data = await apiFetch('/api/auth/login', {
        method: 'POST',
        body: JSON.stringify({ email, password }),
      });
      showToast(t('toast_welcome_back') + ', ' + data.customer.name.split(' ')[0] + '!');
    }

    auth = { token: data.token, customer: data.customer };
    saveAuth();
    enterApp();
  } catch (err) {
    errEl.textContent = err.message || t('auth_error_generic');
    errEl.classList.add('show');
  } finally {
    submitBtn.disabled = false;
    submitBtn.textContent = originalLabel;
  }

  return false;
}

function enterApp() {
  const landing = document.getElementById('screen-landing');
  if (landing) landing.classList.remove('active');
  document.getElementById('screen-auth').classList.remove('active');
  document.getElementById('screen-app').classList.add('active');
  renderAll();
}

async function logout() {
  try {
    await apiFetch('/api/auth/logout', { method: 'POST' });
  } catch (e) {
    // Even if the network call fails, still clear local state below —
    // there's no world where staying "logged in" locally is the right
    // fallback for a failed logout request.
  }
  clearAuth();
  document.getElementById('screen-app').classList.remove('active');
  const landing = document.getElementById('screen-landing');
  if (landing) landing.classList.remove('active');
  document.getElementById('screen-auth').classList.add('active');
  setAuthMode('login');
  document.getElementById('authForm').reset();
}

/* ---------------- navigation ---------------- */
function goto(pageId) {
  document.querySelectorAll('.app-page').forEach(p => p.classList.remove('active'));
  document.getElementById(pageId).classList.add('active');
  document.querySelectorAll('.nav-item[data-page]').forEach(b => {
    b.classList.toggle('active', b.dataset.page === pageId);
  });
  if (pageId === 'page-history') renderHistory();
  if (pageId === 'page-profile') renderProfile();
  window.scrollTo(0, 0);
}

/* ---------------- render: dashboard ---------------- */
function renderAll() {
  const c = auth.customer;
  document.getElementById('headerAvatar').textContent = initials(c.name);
  document.getElementById('headerName').textContent = c.name.split(' ')[0];
  renderWallets();
  renderHomeTx();
}

function renderWallets() {
  const c = auth.customer;
  const row = document.getElementById('walletsRow');
  row.innerHTML = `
    <div class="wallet-card ngn">
      <div class="wallet-top"><span>🇳🇬 ${t('nigeria_wallet')}</span><span class="wallet-flag">NGN</span></div>
      <div class="wallet-balance"><small>₦</small>${fmt(c.ngn_balance || 0)}</div>
      <div class="wallet-account">${c.nuban ? c.nuban : t('nuban_not_provisioned')}</div>
    </div>
    <div class="wallet-card pln" style="opacity:0.55;">
      <div class="wallet-top"><span>🇵🇱 ${t('poland_wallet')}</span><span class="wallet-flag">PLN</span></div>
      <div class="wallet-balance" style="font-size:20px;">${t('coming_soon_label')}</div>
      <div class="wallet-account">&nbsp;</div>
    </div>
  `;
}

function txRowHtml() {
  return ''; // no transaction-history endpoint exists on the backend yet
}

function renderHomeTx() {
  const list = document.getElementById('txListHome');
  list.innerHTML = `<div class="empty-tx">${t('history_coming_soon')}</div>`;
}

function renderHistory() {
  const list = document.getElementById('txListFull');
  list.innerHTML = `<div class="empty-tx">${t('history_coming_soon')}</div>`;
}

function setHistoryFilter(f) {
  historyFilter = f;
  document.querySelectorAll('#historyFilters .filter-tab').forEach(b => b.classList.toggle('active', b.dataset.filter === f));
  renderHistory();
}

function renderProfile() {
  const c = auth.customer;
  document.getElementById('profileAvatar').textContent = initials(c.name);
  document.getElementById('profileName').textContent = c.name;
  document.getElementById('profileEmail').textContent = c.email;
  document.getElementById('profileNuban').textContent = c.nuban || t('nuban_not_provisioned');
  document.getElementById('profileCountry').textContent = c.country === 'NG' ? t('country_home_ng') : t('country_home_pl');
}

/* =========================================================
   RECEIVE — real NUBAN provisioning against the live backend.
   ========================================================= */
function openReceiveSheet() {
  const c = auth.customer;
  document.getElementById('receiveSheetTitle').textContent = t('receive_title_ngn');
  document.getElementById('receiveSheetHint').textContent = t('receive_hint_ngn');
  document.getElementById('receiveAccountLabel').textContent = t('nuban_label');
  document.getElementById('nubanError').classList.remove('show');

  if (c.nuban) {
    document.getElementById('receiveAccountNumber').textContent = c.nuban;
    document.getElementById('receiveBankName').textContent = t('bank_powered_by');
    document.getElementById('copyAccountBtn').style.display = '';
    document.getElementById('provisionNubanBtn').style.display = 'none';
  } else {
    document.getElementById('receiveAccountNumber').textContent = '—';
    document.getElementById('receiveBankName').textContent = '';
    document.getElementById('copyAccountBtn').style.display = 'none';
    document.getElementById('provisionNubanBtn').style.display = '';
    document.getElementById('provisionNubanBtn').disabled = false;
    document.getElementById('provisionNubanBtn').textContent = t('get_my_nuban_btn');
  }

  document.getElementById('receiveBackdrop').classList.add('open');
  document.getElementById('receiveSheet').classList.add('open');
  document.body.style.overflow = 'hidden';
}
function closeReceiveSheet() {
  document.getElementById('receiveBackdrop').classList.remove('open');
  document.getElementById('receiveSheet').classList.remove('open');
  document.body.style.overflow = '';
}
function copyAccountNumber() {
  const val = document.getElementById('receiveAccountNumber').textContent;
  navigator.clipboard?.writeText(val).catch(() => {});
  showToast(t('toast_copied'));
}

async function provisionNuban() {
  const btn = document.getElementById('provisionNubanBtn');
  const errEl = document.getElementById('nubanError');
  errEl.classList.remove('show');
  btn.disabled = true;
  btn.textContent = t('please_wait');

  try {
    const data = await apiFetch('/api/accounts/nuban', { method: 'POST' });
    auth.customer.nuban = data.account_number;
    saveAuth();
    showToast(t('toast_nuban_ready'));
    openReceiveSheet(); // re-render the sheet with the real number now shown
  } catch (err) {
    // Shown honestly, including the real backend/Paga error if that's
    // what comes back — no fallback to fake success.
    errEl.textContent = err.message || t('auth_error_generic');
    errEl.classList.add('show');
    btn.disabled = false;
    btn.textContent = t('get_my_nuban_btn');
  }
}

/* ---------------- boot ---------------- */
(function init() {
  document.querySelectorAll('.lang-btn').forEach(b => b.classList.toggle('active', b.dataset.lang === currentLang));
  applyStaticTranslations();

  auth = loadAuth() || { token: null, customer: null };

  if (auth.token && auth.customer) {
    // Trust the cached customer for instant paint, then quietly refresh
    // from the real /me endpoint in case the balance/NUBAN changed
    // since last visit (e.g. Paga's webhook credited a deposit).
    enterApp();
    apiFetch('/api/auth/me').then(customer => {
      auth.customer = customer;
      saveAuth();
      renderAll();
    }).catch(() => {
      // Token likely expired/revoked — send back to a clean login.
      clearAuth();
      document.getElementById('screen-app').classList.remove('active');
      document.getElementById('screen-auth').classList.add('active');
      setAuthMode('login');
    });
  } else {
    setAuthMode('login');
  }
})();
