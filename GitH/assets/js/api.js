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

/** Escapes text before it is placed into HTML. */
function esc(v) {
  return String(v ?? '').replace(/[&<>"']/g, c => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));
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

// ---------- Year level / semester tags (materials are tagged, students are matched against them) ----------
const YEAR_TAGS = [[1, '1st Year'], [2, '2nd Year'], [3, '3rd Year'], [4, '4th Year'], [5, '5th Year']];
const SEMESTER_TAGS = [[1, '1st Semester'], [2, '2nd Semester']];

/** A row of checkboxes (multi-select). `checked` is an array of numbers. */
function tagCheckboxesHtml(name, tags, checked) {
  const on = (checked || []).map(Number);
  return `<div class="check-group">` + tags.map(([v, l]) =>
    `<label class="check-pill"><input type="checkbox" name="${name}" value="${v}" ${on.includes(v) ? 'checked' : ''}> ${l}</label>`).join('') + `</div>`;
}
/** Numbers ticked in the checkbox group called `name`. */
function checkedTags(name) {
  return [...document.querySelectorAll(`input[name="${name}"]:checked`)].map(i => Number(i.value));
}
/** 5 -> [1,3] (bit n-1 = tag n). */
function maskToList(mask, max) {
  const out = []; for (let n = 1; n <= max; n++) if (mask & (1 << (n - 1))) out.push(n); return out;
}
function yearMaskLabel(mask) {
  if (mask === 31) return 'All Years';
  const short = { 1: '1st', 2: '2nd', 3: '3rd', 4: '4th', 5: '5th' };
  const l = maskToList(mask, 5).map(n => short[n]);
  return l.length ? l.join(', ') + ' Year' : '—';
}
function semesterMaskLabel(mask) {
  return mask === 3 ? '1st & 2nd Semester' : mask === 1 ? '1st Semester' : mask === 2 ? '2nd Semester' : '—';
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
