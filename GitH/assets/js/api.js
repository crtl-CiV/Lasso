// =========================================================
// LASSO — shared fetch helper for all pages
// =========================================================
const API_BASE = '/api';

// Base URL for files stored in Supabase's public bucket (covers, ID photos,
// profile photos). Fill in your project's URL from Supabase -> Settings -> API.
// Cover/photo paths returned by the API (e.g. 'covers/cover_1_123.jpg') get
// appended to this to form the full image URL.
const SUPABASE_PUBLIC_URL = 'https://rghdjktjaxatbxybtwsu.supabase.co/storage/v1/object/public/lasso-public/';

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

/** Guards a page: redirects to login if not authenticated as an allowed role.
 *  `role` can be a single role string ('admin') or an array (['admin','student']). */
async function requireAuth(role) {
  const r = await apiGet('/auth/me.php');
  const allowed = Array.isArray(role) ? role : (role ? [role] : null);
  if (!r.success || (allowed && !allowed.includes(r.user.role))) {
    window.location.href = '/index.html';
    return null;
  }
  return r.user;
}

// ---------- Year levels (single years first, then the allowed ranges) ----------
const YEAR_LEVEL_OPTIONS = [
  ['1', '1st Year'], ['2', '2nd Year'], ['3', '3rd Year'], ['4', '4th Year'], ['5', '5th Year'],
  ['1-2', '1st - 2nd Year'], ['1-3', '1st - 3rd Year'], ['1-4', '1st - 4th Year'], ['1-5', '1st - 5th Year'],
  ['2-3', '2nd - 3rd Year'], ['2-4', '2nd - 4th Year'], ['2-5', '2nd - 5th Year'],
  ['3-4', '3rd - 4th Year'], ['3-5', '3rd - 5th Year'], ['4-5', '4th - 5th Year'],
];
/** <option> list for a year-level <select>. `selected` is a code like '2' or '1-3'. */
function yearLevelOptionsHtml(selected, blankLabel) {
  const blank = blankLabel !== undefined ? `<option value="">${blankLabel}</option>` : '';
  return blank + YEAR_LEVEL_OPTIONS.map(([v, l]) => `<option value="${v}" ${v === String(selected) ? 'selected' : ''}>${l}</option>`).join('');
}
/** Code ('2' or '1-3') for a material row that carries year_min / year_max. */
function materialYearCode(m) {
  return m.year_min === m.year_max ? String(m.year_min) : `${m.year_min}-${m.year_max}`;
}

// ---------- Subscription duration helpers (expiry is a YYYY-MM-DD date) ----------
function daysLeft(expiryDate) {
  const today = new Date(); today.setHours(0, 0, 0, 0);
  return Math.round((new Date(expiryDate + 'T00:00:00') - today) / 86400000);
}
function durationText(expiryDate) {
  const d = daysLeft(expiryDate);
  if (d > 1) return `${d} days left`;
  if (d === 1) return '1 day left';
  if (d === 0) return 'Expires today';
  return `Expired ${Math.abs(d)} day${Math.abs(d) === 1 ? '' : 's'} ago`;
}
function subIsActive(s) { return s.status === 'active' && daysLeft(s.expiry_date) >= 0; }

async function logout(redirectTo) {
  await apiPost('/auth/logout.php', {});
  window.location.href = redirectTo || '/index.html';
}

/** Auto-injects a hamburger button next to the logo on every page that has
 *  a .topbar, so individual pages don't need markup changes. On mobile the
 *  button toggles the existing nav open/closed as a dropdown (see CSS). */
function initMobileNav() {
  const brand = document.querySelector('.topbar .brand');
  const nav = document.querySelector('.topbar nav');
  if (!brand || !nav) return;

  const btn = document.createElement('button');
  btn.className = 'hamburger';
  btn.setAttribute('aria-label', 'Menu');
  btn.textContent = '☰';
  btn.onclick = () => nav.classList.toggle('nav-open');
  brand.insertAdjacentElement('afterend', btn);

  // Close the menu after tapping a link, and on outside taps
  nav.addEventListener('click', (e) => { if (e.target.tagName === 'A') nav.classList.remove('nav-open'); });
  document.addEventListener('click', (e) => {
    if (!nav.contains(e.target) && !btn.contains(e.target)) nav.classList.remove('nav-open');
  });
}
document.addEventListener('DOMContentLoaded', initMobileNav);

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
