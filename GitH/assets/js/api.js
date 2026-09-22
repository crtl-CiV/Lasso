// =========================================================
// LASSO — shared fetch helper for all pages
// =========================================================
const API_BASE = '/api';

// Base URL for files stored in Supabase's public bucket (covers, ID photos,
// profile photos). Fill in your project's URL from Supabase -> Settings -> API.
// Cover/photo paths returned by the API (e.g. 'covers/cover_1_123.jpg') get
// appended to this to form the full image URL.
const SUPABASE_PUBLIC_URL = 'https://YOUR-PROJECT-REF.supabase.co/storage/v1/object/public/lasso-public/';

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
 *  `role` can be a single role string ('admin') or an array (['admin','cashier']). */
async function requireAuth(role) {
  const r = await apiGet('/auth/me.php');
  const allowed = Array.isArray(role) ? role : (role ? [role] : null);
  if (!r.success || (allowed && !allowed.includes(r.user.role))) {
    window.location.href = '/index.html';
    return null;
  }
  return r.user;
}

/** Renders a bell icon with unread count into the given container element ID,
 *  and polls for new notifications every 30 seconds. Admin pages only. */
function initNotificationBell(containerId) {
  const el = document.getElementById(containerId);
  if (!el) return;
  el.innerHTML = `<span id="__bellIcon" style="position:relative; cursor:pointer; font-size:1.3rem;" onclick="toggleNotifPanel()">
    🔔<span id="__bellCount" style="display:none; position:absolute; top:-6px; right:-8px; background:#dc2626; color:#fff; font-size:.65rem; font-weight:800; border-radius:10px; padding:1px 5px;"></span>
  </span>
  <div id="__notifPanel" style="display:none; position:absolute; right:20px; top:56px; width:320px; max-height:400px; overflow-y:auto; background:#fff; border:1px solid #ddd; border-radius:10px; box-shadow:0 8px 24px rgba(0,0,0,.15); z-index:50;">
    <div style="display:flex; justify-content:space-between; align-items:center; padding:10px 14px; border-bottom:1px solid #eee;">
      <b style="font-size:.9rem;">Notifications</b>
      <a href="#" style="font-size:.78rem;" onclick="event.preventDefault(); markAllNotifsRead();">Mark all read</a>
    </div>
    <div id="__notifList"></div>
  </div>`;
  pollNotifications();
  setInterval(pollNotifications, 30000);
}

async function pollNotifications() {
  const r = await apiGet('/admin/notifications_list.php');
  if (!r.success) return;
  const countEl = document.getElementById('__bellCount');
  const listEl = document.getElementById('__notifList');
  if (!countEl || !listEl) return;

  if (r.unread_count > 0) { countEl.textContent = r.unread_count; countEl.style.display = 'inline-block'; }
  else { countEl.style.display = 'none'; }

  listEl.innerHTML = r.notifications.length
    ? r.notifications.map(n => `
        <div style="padding:10px 14px; border-bottom:1px solid #f2f2f2; ${n.is_read ? 'opacity:.55;' : 'background:#fffbea;'}">
          <div style="font-size:.82rem; line-height:1.4;">${n.message}</div>
          <div style="display:flex; justify-content:space-between; align-items:center; margin-top:4px;">
            <span class="muted" style="font-size:.7rem;">${new Date(n.created_at).toLocaleString()}</span>
            ${!n.is_read ? `<a href="#" style="font-size:.72rem;" onclick="event.preventDefault(); markNotifRead(${n.id});">Mark read</a>` : ''}
          </div>
        </div>`).join('')
    : '<div class="muted" style="padding:14px; font-size:.85rem;">No notifications yet.</div>';
}

function toggleNotifPanel() {
  const p = document.getElementById('__notifPanel');
  if (p) p.style.display = p.style.display === 'none' ? 'block' : 'none';
}

async function markNotifRead(id) {
  await apiPost('/admin/notifications_mark_read.php', { id });
  pollNotifications();
}

async function markAllNotifsRead() {
  await apiPost('/admin/notifications_mark_read.php', { all: true });
  pollNotifications();
}

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
