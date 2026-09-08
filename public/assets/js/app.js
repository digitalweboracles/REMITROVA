const API_BASE = '';

let auth = { token: null, customer: null };
let authMode = 'login';
let historyFilter = 'all';

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

async function apiFetch(path, options = {}) {
  const headers = Object.assign({ 'Content-Type': 'application/json', 'Accept': 'application/json' }, options.headers || {});
  if (auth.token) headers['Authorization'] = 'Bearer ' + auth.token;

  let res;
  try {
    res = await fetch(API_BASE + path, Object.assign({}, options, { headers }));
  } catch (e) {
    const err = new Error('Could not reach the server. Check your connection and try again.');
    err.status = 0;
    throw err;
  }

  let data = null;
  try { data = await res.json(); } catch (e) { }

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

function applyStaticTranslations() {
  document.querySelectorAll('[data-i18n]').forEach(el => {
    const val = t(el.getAttribute('data-i18n'));
    if (typeof val === 'string') el.textContent = val;
  });
  document.querySelectorAll('[data-i18n-placeholder]').forEach(el => {
    const val = t(el.getAttribute('data-i18n-placeholder'));
    if (typeof val === 'string') el.placeholder = val;
  });
  refreshAuthModeText();
  document.documentElement.lang = currentLang;
}

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

/* ---------------- ROUTING ---------------- */
const DASHBOARD_PAGE_FOR_PATH = { '/dashboard': 'page-home', '/dashboard/history': 'page-history', '/dashboard/profile': 'page-profile' };
const PATH_FOR_DASHBOARD_PAGE = { 'page-home': '/dashboard', 'page-history': '/dashboard/history', 'page-profile': '/dashboard/profile' };

function navigateTo(path, { replace = false } = {}) {
  if (replace) history.replaceState({ path }, '', path);
  else history.pushState({ path }, '', path);
  renderForPath(path);
}

function renderForPath(path, fromPopstate = false) {
  const isLoggedIn = !!(auth.token && auth.customer);
  const landing = document.getElementById('screen-landing');

  if (path.startsWith('/dashboard')) {
    if (!isLoggedIn) { navigateTo('/login', { replace: true }); return; }
    showScreen('screen-app');
    showDashboardPage(DASHBOARD_PAGE_FOR_PATH[path] || 'page-home');
    return;
  }

  if (path === '/login' || path === '/signup') {
    if (isLoggedIn) {
      if (fromPopstate) { showScreen('screen-app'); showDashboardPage('page-home'); return; }
      navigateTo('/dashboard', { replace: true });
      return;
    }
    showScreen('screen-auth');
    setAuthMode(path === '/signup' ? 'signup' : 'login');
    return;
  }

  if (isLoggedIn) {
    if (fromPopstate) { showScreen('screen-app'); showDashboardPage('page-home'); return; }
    navigateTo('/dashboard', { replace: true });
    return;
  }
  showScreen(landing ? 'screen-landing' : 'screen-auth');
  if (!landing) setAuthMode('login');
}

function showScreen(screenId) {
  ['screen-landing', 'screen-auth', 'screen-app'].forEach(id => {
    const el = document.getElementById(id);
    if (el) el.classList.toggle('active', id === screenId);
  });
  window.scrollTo(0, 0);
}

function showDashboardPage(pageId) {
  document.querySelectorAll('.app-page').forEach(p => p.classList.remove('active'));
  document.getElementById(pageId).classList.add('active');
  document.querySelectorAll('.nav-item[data-page]').forEach(b => b.classList.toggle('active', b.dataset.page === pageId));
  if (pageId === 'page-history') renderHistory();
  if (pageId === 'page-profile') renderProfile();
  window.scrollTo(0, 0);
}

window.addEventListener('popstate', () => renderForPath(window.location.pathname, true));

function goToAuth(mode) { navigateTo(mode === 'signup' ? '/signup' : '/login'); }
function goToLanding() { navigateTo('/'); }

/* ---------------- AUTH ---------------- */
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
  document.getElementById('authFoot').innerHTML = `<span>${t(footKey)}</span> <a href="#" onclick="goToAuth('${nextMode}'); return false;" style="font-weight:700;text-decoration:underline;">${t(linkKey)}</a>`;
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
      if (!name) throw new Error(t('auth_error_generic'));
      data = await apiFetch('/api/auth/register', { method: 'POST', body: JSON.stringify({ name, email, password, country }) });
      showToast(t('toast_account_created'));
    } else {
      data = await apiFetch('/api/auth/login', { method: 'POST', body: JSON.stringify({ email, password }) });
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
  renderAll();
  navigateTo('/dashboard');
}

async function logout() {
  try { await apiFetch('/api/auth/logout', { method: 'POST' }); } catch (e) { }
  clearAuth();
  document.getElementById('authForm').reset();
  navigateTo('/login');
}

function goto(pageId) { navigateTo(PATH_FOR_DASHBOARD_PAGE[pageId] || '/dashboard'); }

/* ---------------- DASHBOARD RENDER ---------------- */
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
    <div class="wallet-spacer" aria-hidden="true"></div>
    <div class="wallet-card ngn">
      <div class="wallet-top"><span>\ud83c\uddf3\ud83c\uddec ${t('nigeria_wallet')}</span><span class="wallet-flag">NGN</span></div>
      <div class="wallet-balance"><small>\u20a6</small>${fmt(c.ngn_balance || 0)}</div>
      <div class="wallet-account">${c.nuban ? c.nuban : t('nuban_not_provisioned')}</div>
    </div>
    <div class="wallet-card pln" style="opacity:0.55;">
      <div class="wallet-top"><span>\ud83c\uddf5\ud83c\uddf1 ${t('poland_wallet')}</span><span class="wallet-flag">PLN</span></div>
      <div class="wallet-balance" style="font-size:20px;">${t('coming_soon_label')}</div>
      <div class="wallet-account">&nbsp;</div>
    </div>
  `;
}

function renderHomeTx() {
  document.getElementById('txListHome').innerHTML = `<div class="empty-tx">${t('history_coming_soon')}</div>`;
}
function renderHistory() {
  document.getElementById('txListFull').innerHTML = `<div class="empty-tx">${t('history_coming_soon')}</div>`;
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

/* ---------------- RECEIVE / NUBAN ---------------- */
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
    document.getElementById('receiveAccountNumber').textContent = '\u2014';
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
  navigator.clipboard?.writeText(val).catch(() => { });
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
    openReceiveSheet();
  } catch (err) {
    errEl.textContent = err.message || t('auth_error_generic');
    errEl.classList.add('show');
    btn.disabled = false;
    btn.textContent = t('get_my_nuban_btn');
  }
}

/* ---------------- BOOT ---------------- */
(function init() {
  document.querySelectorAll('.lang-btn').forEach(b => b.classList.toggle('active', b.dataset.lang === currentLang));
  applyStaticTranslations();

  auth = loadAuth() || { token: null, customer: null };
  renderForPath(window.location.pathname);

  if (auth.token && auth.customer) {
    apiFetch('/api/auth/me').then(customer => {
      auth.customer = customer;
      saveAuth();
      renderAll();
    }).catch(() => {
      clearAuth();
      navigateTo('/login', { replace: true });
    });
  }
})();
