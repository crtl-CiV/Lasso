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
    el.style.cssText = 'position:fixed;bottom:20px;left:50%;transform:translateX(-50%);z-index:999;padding:12px 20px;border-radius:8px;font-size:14px;font-weight:600;box-shadow:0 8px 24px rgba(15,23,42,.25);transition:opacity .3s;';
    document.body.appendChild(el);
  }
  const colors = { info: '#4f46e5', success: '#16a34a', error: '#dc2626' };
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
  if (d >= 45) return `${Math.round(d / 30.4)} months left`;
  if (d >= 30) return '1 month left';
  if (d > 1) return `${d} days left`;
  if (d === 1) return '1 day left';
  if (d === 0) return 'Expires today';
  return `Expired ${Math.abs(d)} day${Math.abs(d) === 1 ? '' : 's'} ago`;
}
/** "3 months" for a number of 3-month semesters. */
function termsToMonths(n) { return `${(n || 1) * 3} months`; }
/** Short date: "Oct 24, 2026" from a YYYY-MM-DD string or a timestamp. */
function fmtDate(v) {
  if (!v) return '—';
  const d = new Date(/^\d{4}-\d{2}-\d{2}$/.test(v) ? v + 'T00:00:00' : v);
  return isNaN(d) ? '—' : d.toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric' });
}
function subIsActive(s) { return s.status === 'active' && daysLeft(s.expiry_date) >= 0; }

async function logout(redirectTo) {
  await apiPost('/auth/logout.php', {});
  window.location.href = redirectTo || '/index.html';
}

/* ---------------- passwords ---------------- */
// Same rules the server enforces (bootstrap.php passwordProblem)
const PW_RULES = [
  ['len', 'At least 8 characters', pw => pw.length >= 8],
  ['num', 'At least 1 number', pw => /[0-9]/.test(pw)],
  ['cap', 'At least 1 capital letter', pw => /[A-Z]/.test(pw)],
  ['sym', 'At least 1 special character (e.g. ! @ # $)', pw => /[^A-Za-z0-9]/.test(pw)],
];
function passwordOk(pw) { return PW_RULES.every(([, , test]) => test(pw)); }
/** Live checklist under a new-password box. Put <ul data-pw-rules-for="inputId"></ul> in the page. */
function bindPasswordRules(root = document) {
  root.querySelectorAll('[data-pw-rules-for]').forEach(ul => {
    if (ul.dataset.bound) return; ul.dataset.bound = '1';
    const input = document.getElementById(ul.dataset.pwRulesFor); if (!input) return;
    ul.classList.add('pw-rules');
    const draw = () => { ul.innerHTML = PW_RULES.map(([k, label, test]) => `<li class="${test(input.value) ? 'ok' : ''}">${label}</li>`).join(''); };
    input.addEventListener('input', draw); draw();
  });
}
/** Adds a show/hide (eye) button to every password box, including ones added later (modals). */
const EYE = '<path d="M2.06 12.35a1 1 0 0 1 0-.7 10.75 10.75 0 0 1 19.88 0 1 1 0 0 1 0 .7 10.75 10.75 0 0 1-19.88 0"/><circle cx="12" cy="12" r="3"/>';
const EYE_OFF = '<path d="M10.73 5.08A10.43 10.43 0 0 1 12 5c4.97 0 8.4 3.6 9.94 6.65a1 1 0 0 1 0 .7 13 13 0 0 1-1.67 2.68"/><path d="M14.08 14.16a3 3 0 0 1-4.24-4.24"/><path d="M17.48 17.5A10.74 10.74 0 0 1 2.06 12.35a1 1 0 0 1 0-.7 13.16 13.16 0 0 1 4.46-5.15"/><path d="m2 2 20 20"/>';
function enhancePasswords(root = document) {
  root.querySelectorAll('input[type=password]:not([data-eye])').forEach(input => {
    input.dataset.eye = '1';
    const wrap = document.createElement('span'); wrap.className = 'pw-wrap';
    input.parentNode.insertBefore(wrap, input); wrap.appendChild(input);
    const btn = document.createElement('button');
    btn.type = 'button'; btn.className = 'pw-eye';
    const paint = () => {
      const shown = input.type === 'text';
      btn.innerHTML = `<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">${shown ? EYE_OFF : EYE}</svg>`;
      btn.setAttribute('aria-label', shown ? 'Hide password' : 'Show password'); btn.title = shown ? 'Hide password' : 'Show password';
      btn.setAttribute('aria-pressed', shown);
    };
    btn.addEventListener('click', () => { input.type = input.type === 'password' ? 'text' : 'password'; paint(); input.focus(); });
    paint(); wrap.appendChild(btn);
  });
}
document.addEventListener('DOMContentLoaded', () => {
  enhancePasswords(); bindPasswordRules();
  new MutationObserver(() => { enhancePasswords(); bindPasswordRules(); }).observe(document.body, { childList: true, subtree: true });
});

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
