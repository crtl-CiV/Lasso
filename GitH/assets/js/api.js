// =========================================================
// LASSO — shared fetch helper for all pages
// =========================================================
const API_BASE = '/lasso/api';

async function apiGet(path) {
  const res = await fetch(API_BASE + path, { credentials: 'same-origin' });
  return res.json();
}

async function apiPost(path, body) {
  const res = await fetch(API_BASE + path, {
    method: 'POST',
    credentials: 'same-origin',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify(body || {}),
  });
  return res.json();
}

async function apiUpload(path, formData) {
  const res = await fetch(API_BASE + path, {
    method: 'POST',
    credentials: 'same-origin',
    body: formData,
  });
  return res.json();
}

function peso(n) {
  return '\u20B1' + Number(n || 0).toLocaleString('en-PH', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
}

function toast(msg, type = 'info') {
  let el = document.getElementById('__toast');
  if (!el) {
    el = document.createElement('div');
    el.id = '__toast';
    el.style.cssText = 'position:fixed;bottom:20px;left:50%;transform:translateX(-50%);z-index:999;padding:12px 20px;border-radius:8px;font-size:.9rem;font-weight:600;box-shadow:0 4px 14px rgba(0,0,0,.2);transition:opacity .3s;';
    document.body.appendChild(el);
  }
  const colors = { info: '#1e3a8a', success: '#16a34a', error: '#dc2626' };
  el.style.background = colors[type] || colors.info;
  el.style.color = '#fff';
  el.textContent = msg;
  el.style.opacity = '1';
  clearTimeout(el._t);
  el._t = setTimeout(() => { el.style.opacity = '0'; }, 2800);
}

/** Guards a page: redirects to login if not authenticated as the required role. */
async function requireAuth(role) {
  const r = await apiGet('/auth/me.php');
  if (!r.success || (role && r.user.role !== role)) {
    window.location.href = role === 'admin' ? '/lasso/admin-login.html' : '/lasso/index.html';
    return null;
  }
  return r.user;
}

async function logout(redirectTo) {
  await apiPost('/auth/logout.php', {});
  window.location.href = redirectTo || '/lasso/index.html';
}

// Basic piracy-deterrence measures on any page that includes this file
document.addEventListener('contextmenu', (e) => {
  if (e.target.closest('.protected')) e.preventDefault();
});
document.addEventListener('keydown', (e) => {
  // Block common screenshot / dev-tools / save shortcuts while a viewer is open
  if (document.querySelector('.protected') && (
    e.key === 'PrintScreen' ||
    (e.ctrlKey && ['s', 'p', 'u'].includes(e.key.toLowerCase())) ||
    (e.key === 'F12')
  )) {
    e.preventDefault();
  }
});
