// =========================================================
// LASSO — shared UI helpers (icons, page shells, modal, file drop zones)
// Loaded after api.js on every page.
// =========================================================
const ICONS = {"store":"<path d=\"M15 21v-5a1 1 0 0 0-1-1h-4a1 1 0 0 0-1 1v5\" /> <path d=\"M17.774 10.31a1.12 1.12 0 0 0-1.549 0 2.5 2.5 0 0 1-3.451 0 1.12 1.12 0 0 0-1.548 0 2.5 2.5 0 0 1-3.452 0 1.12 1.12 0 0 0-1.549 0 2.5 2.5 0 0 1-3.77-3.248l2.889-4.184A2 2 0 0 1 7 2h10a2 2 0 0 1 1.653.873l2.895 4.192a2.5 2.5 0 0 1-3.774 3.244\" /> <path d=\"M4 10.95V19a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2v-8.05\" />","cart":"<path d=\"m2.05 2.05 1.099-.028a1 1 0 0 1 1.008.815l2.69 14.347A1 1 0 0 0 7.83 18H18\" /> <path d=\"M4.563 5h16.435a1 1 0 0 1 .981 1.204l-1.026 6.226A2 2 0 0 1 18.962 14H6.25\" /> <circle cx=\"18\" cy=\"20\" r=\"2\" /> <circle cx=\"8\" cy=\"20\" r=\"2\" />","library":"<path d=\"m16 6 4 14\" /> <path d=\"M12 6v14\" /> <path d=\"M8 8v12\" /> <path d=\"M4 4v16\" />","user":"<path d=\"M19 21v-2a4 4 0 0 0-4-4H9a4 4 0 0 0-4 4v2\" /> <circle cx=\"12\" cy=\"7\" r=\"4\" />","users":"<path d=\"M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2\" /> <path d=\"M16 3.128a4 4 0 0 1 0 7.744\" /> <path d=\"M22 21v-2a4 4 0 0 0-3-3.87\" /> <circle cx=\"9\" cy=\"7\" r=\"4\" />","search":"<path d=\"m21 21-4.34-4.34\" /> <circle cx=\"11\" cy=\"11\" r=\"8\" />","logout":"<path d=\"m16 17 5-5-5-5\" /> <path d=\"M21 12H9\" /> <path d=\"M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4\" />","dashboard":"<rect width=\"7\" height=\"9\" x=\"3\" y=\"3\" rx=\"1\" /> <rect width=\"7\" height=\"5\" x=\"14\" y=\"3\" rx=\"1\" /> <rect width=\"7\" height=\"9\" x=\"14\" y=\"12\" rx=\"1\" /> <rect width=\"7\" height=\"5\" x=\"3\" y=\"16\" rx=\"1\" />","materials":"<path d=\"M12 5v16\" /> <path d=\"M20.001 19A2 2 0 0022 17V5a2 2 0 00-1.999-2L16 3.002A5 5 0 0012 5a5 5 0 00-4-2H4a2 2 0 00-2 2v12a2 2 0 001.999 2H8a5 5 0 014 2 5 5 0 014-2z\" />","sales":"<path d=\"M3 3v16a2 2 0 0 0 2 2h16\" /> <path d=\"M18 17V9\" /> <path d=\"M13 17V5\" /> <path d=\"M8 17v-3\" />","plus":"<path d=\"M5 12h14\" /> <path d=\"M12 5v14\" />","check":"<path d=\"M20 6 9 17l-5-5\" />","x":"<path d=\"M18 6 6 18\" /> <path d=\"m6 6 12 12\" />","trash":"<path d=\"M10 11v6\" /> <path d=\"M14 11v6\" /> <path d=\"M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6\" /> <path d=\"M3 6h18\" /> <path d=\"M8 6V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2\" />","upload":"<path d=\"M12 3v12\" /> <path d=\"m17 8-5-5-5 5\" /> <path d=\"M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4\" />","eye":"<path d=\"M2.062 12.348a1 1 0 0 1 0-.696 10.75 10.75 0 0 1 19.876 0 1 1 0 0 1 0 .696 10.75 10.75 0 0 1-19.876 0\" /> <circle cx=\"12\" cy=\"12\" r=\"3\" />","receipt":"<path d=\"M12 17V7\" /> <path d=\"M16 8h-6a2 2 0 0 0 0 4h4a2 2 0 0 1 0 4H8\" /> <path d=\"M4 3a1 1 0 0 1 1-1 1.3 1.3 0 0 1 .7.2l.933.6a1.3 1.3 0 0 0 1.4 0l.934-.6a1.3 1.3 0 0 1 1.4 0l.933.6a1.3 1.3 0 0 0 1.4 0l.933-.6a1.3 1.3 0 0 1 1.4 0l.934.6a1.3 1.3 0 0 0 1.4 0l.933-.6A1.3 1.3 0 0 1 19 2a1 1 0 0 1 1 1v18a1 1 0 0 1-1 1 1.3 1.3 0 0 1-.7-.2l-.933-.6a1.3 1.3 0 0 0-1.4 0l-.934.6a1.3 1.3 0 0 1-1.4 0l-.933-.6a1.3 1.3 0 0 0-1.4 0l-.933.6a1.3 1.3 0 0 1-1.4 0l-.934-.6a1.3 1.3 0 0 0-1.4 0l-.933.6a1.3 1.3 0 0 1-.7.2 1 1 0 0 1-1-1z\" />","bell":"<path d=\"M10.268 21a2 2 0 0 0 3.464 0\" /> <path d=\"M3.262 15.326A1 1 0 0 0 4 17h16a1 1 0 0 0 .74-1.673C19.41 13.956 18 12.499 18 8A6 6 0 0 0 6 8c0 4.499-1.411 5.956-2.738 7.326\" />","filter":"<path d=\"M10 20a1 1 0 0 0 .553.895l2 1A1 1 0 0 0 14 21v-7a2 2 0 0 1 .517-1.341L21.74 4.67A1 1 0 0 0 21 3H3a1 1 0 0 0-.742 1.67l7.225 7.989A2 2 0 0 1 10 14z\" />","star":"<path d=\"M11.525 2.295a.53.53 0 0 1 .95 0l2.31 4.679a2.123 2.123 0 0 0 1.595 1.16l5.166.756a.53.53 0 0 1 .294.904l-3.736 3.638a2.123 2.123 0 0 0-.611 1.878l.882 5.14a.53.53 0 0 1-.771.56l-4.618-2.428a2.122 2.122 0 0 0-1.973 0L6.396 21.01a.53.53 0 0 1-.77-.56l.881-5.139a2.122 2.122 0 0 0-.611-1.879L2.16 9.795a.53.53 0 0 1 .294-.906l5.165-.755a2.122 2.122 0 0 0 1.597-1.16z\" />","lock":"<rect width=\"18\" height=\"11\" x=\"3\" y=\"11\" rx=\"2\" ry=\"2\" /> <path d=\"M7 11V7a5 5 0 0 1 10 0v4\" />","clock":"<circle cx=\"12\" cy=\"12\" r=\"10\" /> <path d=\"M12 6v6l4 2\" />","edit":"<path d=\"M21.174 6.812a1 1 0 0 0-3.986-3.987L3.842 16.174a2 2 0 0 0-.5.83l-1.321 4.352a.5.5 0 0 0 .623.622l4.353-1.32a2 2 0 0 0 .83-.497z\" /> <path d=\"m15 5 4 4\" />","chevron-right":"<path d=\"m9 18 6-6-6-6\" />","chevron-left":"<path d=\"m15 18-6-6 6-6\" />","chevron-down":"<path d=\"m6 9 6 6 6-6\" />","info":"<circle cx=\"12\" cy=\"12\" r=\"10\" /> <path d=\"M12 16v-4\" /> <path d=\"M12 8h.01\" />","menu":"<path d=\"M4 5h16\" /> <path d=\"M4 12h16\" /> <path d=\"M4 19h16\" />","file":"<path d=\"M6 22a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h8a2.4 2.4 0 0 1 1.704.706l3.588 3.588A2.4 2.4 0 0 1 20 8v12a2 2 0 0 1-2 2z\" /> <path d=\"M14 2v5a1 1 0 0 0 1 1h5\" /> <path d=\"M10 9H8\" /> <path d=\"M16 13H8\" /> <path d=\"M16 17H8\" />","image":"<rect width=\"18\" height=\"18\" x=\"3\" y=\"3\" rx=\"2\" ry=\"2\" /> <circle cx=\"9\" cy=\"9\" r=\"2\" /> <path d=\"m21 15-3.086-3.086a2 2 0 0 0-2.828 0L6 21\" />","check-circle":"<circle cx=\"12\" cy=\"12\" r=\"10\" /> <path d=\"m16 9-5.5 5.5L8 12\" />","alert":"<path d=\"m21.73 18-8-14a2 2 0 0 0-3.48 0l-8 14A2 2 0 0 0 4 21h16a2 2 0 0 0 1.73-3\" /> <path d=\"M12 9v4\" /> <path d=\"M12 17h.01\" />","back":"<path d=\"m12 19-7-7 7-7\" /> <path d=\"M19 12H5\" />","print":"<path d=\"M6 18H4a2 2 0 0 1-2-2v-5a2 2 0 0 1 2-2h16a2 2 0 0 1 2 2v5a2 2 0 0 1-2 2h-2\" /> <path d=\"M6 9V3a1 1 0 0 1 1-1h10a1 1 0 0 1 1 1v6\" /> <rect x=\"6\" y=\"14\" width=\"12\" height=\"8\" rx=\"1\" />","reset":"<path d=\"M3 12a9 9 0 1 0 9-9 9.75 9.75 0 0 0-6.74 2.74L3 8\" /> <path d=\"M3 3v5h5\" />","id-card":"<path d=\"M13 19a4 4 0 00-8 0\" /> <path d=\"M16 10h2\" /> <path d=\"M16 14h2\" /> <circle cx=\"9\" cy=\"12\" r=\"3\" /> <rect x=\"2\" y=\"5\" width=\"20\" height=\"14\" rx=\"2\" />","undo":"<path d=\"M9 14 4 9l5-5\" /> <path d=\"M4 9h10.5a5.5 5.5 0 0 1 5.5 5.5a5.5 5.5 0 0 1-5.5 5.5H11\" />"};

/** Inline Lucide icon (stroke follows the text colour). */
function icon(name, size = 20, extra = '') {
  return `<svg class="icon ${extra}" width="${size}" height="${size}" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">${ICONS[name] || ''}</svg>`;
}
/** kind: success | danger | info | warning | neutral | brand */
function badge(kind, text) { return `<span class="badge badge-${kind}">${esc(text)}</span>`; }
function initials(name) {
  const p = String(name || '?').trim().split(/\s+/).filter(Boolean);
  return ((p[0] || '?')[0] + (p.length > 1 ? p[p.length - 1][0] : '')).toUpperCase();
}
function brandHtml(href) { return `<a class="brand" href="${href}"><span class="brand-mark">L</span>LASSO</a>`; }

/* ---------------- student shell: top navigation ---------------- */
function renderStudentShell(active) {
  const el = document.getElementById('shell');
  if (!el) return;
  const link = (key, href, ic, label, extra = '') =>
    `<a class="navlink ${active === key ? 'active' : ''}" href="${href}">${icon(ic, 18)} ${label}${extra}</a>`;
  el.className = 'topnav';
  el.innerHTML = `
    ${brandHtml('/student/store.html')}
    <button class="iconbtn nav-toggle" aria-label="Menu" onclick="document.getElementById('shell').classList.toggle('nav-open')">${icon('menu')}</button>
    <nav class="topnav-links">
      ${link('store', '/student/store.html', 'store', 'Store')}
      ${link('cart', '/student/cart.html', 'cart', 'Cart', ' <span class="nav-badge hidden" id="cartBadge">0</span>')}
      ${link('library', '/student/library.html', 'library', 'Library')}
    </nav>
    <div class="topnav-right">
      <a class="iconbtn ${active === 'profile' ? 'active' : ''}" href="/student/profile.html" title="Profile" aria-label="Profile">${icon('user', 20)}<span class="lbl">Profile</span></a>
      <button class="iconbtn" onclick="confirmLogout()" title="Log out" aria-label="Log out">${icon('logout', 20)}<span class="lbl">Log out</span></button>
    </div>`;
  refreshCartBadge();
}
async function refreshCartBadge() {
  const b = document.getElementById('cartBadge');
  if (!b) return;
  const r = await apiGet('/cart/list.php');
  const n = r.success ? (r.items || []).length : 0;
  b.textContent = n; b.classList.toggle('hidden', n === 0);
}

/* ---------------- admin shell: sidebar + top bar ---------------- */
function renderAdminShell(active, user) {
  const side = document.getElementById('sidebar'), top = document.getElementById('adminTop');
  if (!side || !top) return;
  const link = (key, href, ic, label) => `<a class="navlink ${active === key ? 'active' : ''}" href="${href}">${icon(ic, 20)} ${label}</a>`;
  side.innerHTML = `
    ${brandHtml('/admin/dashboard.html')}
    ${link('dashboard', '/admin/dashboard.html', 'dashboard', 'Dashboard')}
    ${link('materials', '/admin/materials.html', 'materials', 'Materials')}
    ${link('users', '/admin/users.html', 'users', 'Users')}
    ${link('sales', '/admin/sales.html', 'sales', 'Sales')}
    <div class="sidebar-foot">Learning Asset Subscription and Sales Operation</div>`;
  const who = (user && (user.admin_id || user.full_name)) || 'admin';
  top.innerHTML = `
    <button class="iconbtn nav-toggle" aria-label="Menu" onclick="toggleAdminNav()">${icon('menu')}</button>
    <div class="row-gap">
      <span class="account"><span class="avatar">${esc(initials(user && user.full_name || who))}</span>${esc(who)}</span>
      <button class="iconbtn" onclick="confirmLogout()" title="Log out" aria-label="Log out">${icon('logout', 20)}</button>
    </div>`;
}
function toggleAdminNav() { document.getElementById('adminRoot').classList.toggle('nav-open'); }

/* ---------------- modal ---------------- */
function ensureModal() {
  let bd = document.getElementById('modalBackdrop');
  if (!bd) {
    bd = document.createElement('div');
    bd.id = 'modalBackdrop'; bd.className = 'modal-backdrop';
    bd.innerHTML = '<div class="modal" id="modalContent"></div>';
    bd.addEventListener('click', (e) => { if (e.target === bd) closeModal(); });
    document.body.appendChild(bd);
  }
  return bd;
}
/** Opens the shared modal with the given HTML. opts.wide makes it wider (true or a pixel width). */
function openModal(html, opts = {}) {
  const bd = ensureModal(); const m = document.getElementById('modalContent');
  m.style.maxWidth = opts.wide ? (typeof opts.wide === 'number' ? opts.wide + 'px' : '760px') : '600px';
  m.innerHTML = html; bd.style.display = 'flex';
}
function closeModal() { const bd = document.getElementById('modalBackdrop'); if (bd) bd.style.display = 'none'; }
/** Standard modal header with a close button. */
function modalHead(title, sub) {
  return `<div class="modal-head"><div><h2>${title}</h2>${sub ? `<div class="page-sub" style="margin-top:4px;">${sub}</div>` : ''}</div>
    <button class="btn-close" onclick="closeModal()" aria-label="Close">${icon('x', 18)}</button></div>`;
}

/** Log out only after the person confirms. */
function confirmLogout() {
  openModal(`${modalHead('Log out of LASSO?', 'You will need to log in again to continue.')}
    <div class="modal-actions"><button class="btn btn-outline" onclick="closeModal()">Cancel</button>
    <button class="btn btn-primary" id="confirmLogoutBtn" onclick="this.disabled = true; this.textContent = 'Logging out…'; logout('/index.html')">Log out</button></div>`);
  setTimeout(() => { const b = document.getElementById('confirmLogoutBtn'); if (b) b.focus(); }, 0);
}

/* ---------------- file drop zones ---------------- */
function dropzoneHtml(name, title, hint, accept = 'image/*', required = true) {
  return `<label class="dropzone">${icon('upload', 22)}<span class="dz-title">${title}</span><span class="small">${hint || ''}</span>
    <input type="file" name="${name}" accept="${accept}" ${required ? 'required' : ''}></label>`;
}
/** Shows the chosen file name(s) and supports drag & drop. Call after inserting drop zones. */
/** Clears every drop zone inside root (after a form reset). */
function resetDropzones(root = document) { root.querySelectorAll('.dropzone').forEach(dz => dz.resetPreview && dz.resetPreview()); }
function bindDropzones(root = document) {
  root.querySelectorAll('.dropzone').forEach((dz) => {
    if (dz.dataset.bound) return; dz.dataset.bound = '1';
    const input = dz.querySelector('input[type=file]'), title = dz.querySelector('.dz-title'), original = title.textContent;
    const show = () => {
      const files = [...input.files], n = files.length;
      dz.classList.toggle('has-file', n > 0);
      title.textContent = n > 1 ? `${n} files selected` : n === 1 ? files[0].name : original;
      // Thumbnail of the chosen image (the first one when several are picked) so the right photo is easy to confirm
      const old = dz.querySelector('.dz-preview'); if (old) { URL.revokeObjectURL(old.dataset.url || ''); old.remove(); }
      const img = files.find(f => f.type.startsWith('image/'));
      if (img) {
        const url = URL.createObjectURL(img);
        dz.insertAdjacentHTML('afterbegin', `<span class="dz-preview" data-url="${url}"><img src="${url}" alt="Preview of ${esc(img.name)}">${n > 1 ? `<span class="dz-more">+${n - 1} more</span>` : ''}</span>`);
      }
      dz.classList.toggle('has-preview', !!img);
    };
    dz.resetPreview = () => { input.value = ''; show(); };
    input.addEventListener('change', show);
    ['dragenter', 'dragover'].forEach(ev => dz.addEventListener(ev, (e) => { e.preventDefault(); dz.classList.add('drag'); }));
    ['dragleave', 'drop'].forEach(ev => dz.addEventListener(ev, (e) => { e.preventDefault(); dz.classList.remove('drag'); }));
    dz.addEventListener('drop', (e) => { if (e.dataTransfer && e.dataTransfer.files.length) { input.files = e.dataTransfer.files; show(); } });
  });
}

/* ---------------- scrolling page reader ----------------
   Full-screen reader: every page in one scrolling column, with a numbered
   thumbnail sidebar (like a PDF viewer). Pages load as they come near the
   screen. With track = true, a page counts as read after ~2 seconds on screen.
   openPageReader({ material, pages: [page numbers], startPage, track, percent, onClose }) */
let _reader = null;
const scrollBox = (el, top, smooth) => { if (el.scrollTo) el.scrollTo({ top, behavior: smooth ? 'smooth' : 'auto' }); else el.scrollTop = top; };
function openPageReader(opts) {
  closePageReader(true);
  const m = opts.material, pages = opts.pages, src = p => `/api/materials/view_page.php?material_id=${m.id}&page=${p}`;
  const el = document.createElement('div');
  el.className = 'reader open'; el.id = 'pageReader';
  el.setAttribute('role', 'dialog'); el.setAttribute('aria-label', `Reading ${m.title}`);
  el.innerHTML = `
    <div class="reader-bar">
      <button class="btn btn-outline btn-sm" onclick="closePageReader()">${icon('back', 16)} ${esc(opts.backLabel || 'Back')}</button>
      <button class="btn btn-ghost btn-sm reader-pages-btn" onclick="document.getElementById('pageReader').classList.toggle('thumbs-open')" aria-label="Show page list">${icon('menu', 16)} Pages</button>
      <div class="title">${esc(m.title)}${opts.preview ? ' <span class="badge badge-warning">Preview</span>' : ''}</div>
      <div class="muted small" id="rdPos"></div>
      ${opts.track ? '<div class="muted small" id="rdPct"></div>' : ''}
      <span class="protected-note small">${icon('lock', 14)} Protected · download and print disabled</span>
    </div>
    <div class="reader-body">
      <nav class="reader-thumbs" aria-label="Pages">
        ${pages.map(p => `<button type="button" class="rthumb" data-page="${p}" aria-label="Go to page ${p}">
          <span class="rthumb-img"><img loading="lazy" alt="" data-src="${src(p)}"></span><span class="rthumb-no">${p}</span></button>`).join('')}
      </nav>
      <div class="reader-stage protected" id="rdStage">
        ${pages.map(p => `<figure class="rpage" id="rp-${p}" data-page="${p}"><img class="viewer-page-img protected" alt="Page ${p}" data-src="${src(p)}"><figcaption>Page ${p}</figcaption></figure>`).join('')}
        ${opts.footer || ''}
      </div>
    </div>`;
  document.body.appendChild(el);
  document.body.style.overflow = 'hidden';
  const stage = el.querySelector('#rdStage'), thumbsNav = el.querySelector('.reader-thumbs');
  const state = _reader = { el, timers: {}, done: new Set(), visible: new Map(), current: null, observers: [], onClose: opts.onClose };

  const setPct = pct => { const x = el.querySelector('#rdPct'); if (x) x.textContent = `${Math.round(pct)}% complete`; };
  if (opts.track) setPct(opts.percent || 0);

  const markCurrent = (p) => {
    if (state.current === p) return; state.current = p;
    el.querySelector('#rdPos').textContent = `Page ${p} of ${m.total_pages}`;
    thumbsNav.querySelectorAll('.rthumb').forEach(b => { const on = +b.dataset.page === p; b.classList.toggle('active', on); if (on) b.setAttribute('aria-current', 'page'); else b.removeAttribute('aria-current'); });
    const t = thumbsNav.querySelector(`.rthumb[data-page="${p}"]`);
    if (t) { const top = t.offsetTop - thumbsNav.clientHeight / 2 + t.clientHeight / 2; scrollBox(thumbsNav, top, true); }
  };

  // 1) Load page images shortly before they scroll into view
  const loader = new IntersectionObserver(entries => entries.forEach(e => {
    if (!e.isIntersecting) return;
    const img = e.target.querySelector('img'); if (img && img.dataset.src) { img.src = img.dataset.src; delete img.dataset.src; }
    loader.unobserve(e.target);
  }), { root: stage, rootMargin: '1200px 0px' });
  const thumbLoader = new IntersectionObserver(entries => entries.forEach(e => {
    if (!e.isIntersecting) return;
    const img = e.target.querySelector('img'); if (img && img.dataset.src) { img.src = img.dataset.src; delete img.dataset.src; }
    thumbLoader.unobserve(e.target);
  }), { root: thumbsNav, rootMargin: '400px 0px' });

  // 2) Which page is on screen now, and which pages stayed ~2s (read)
  const watcher = new IntersectionObserver(entries => {
    entries.forEach(e => {
      const p = +e.target.dataset.page;
      state.visible.set(p, e.intersectionRatio);
      if (!opts.track || state.done.has(p)) return;
      if (e.intersectionRatio >= 0.5 && !state.timers[p]) {
        state.timers[p] = setTimeout(async () => {
          state.done.add(p);
          const r = await apiPost('/materials/mark_read.php', { material_id: m.id, page: p });
          if (r.success) setPct(r.percent_complete);
        }, 2000);
      } else if (e.intersectionRatio < 0.5 && state.timers[p]) { clearTimeout(state.timers[p]); delete state.timers[p]; }
    });
    let best = null, bestR = 0;
    state.visible.forEach((r, p) => { if (r > bestR) { bestR = r; best = p; } });
    if (best) markCurrent(best);
  }, { root: stage, threshold: [0, 0.25, 0.5, 0.75, 1] });

  stage.querySelectorAll('.rpage').forEach(f => { loader.observe(f); watcher.observe(f); });
  thumbsNav.querySelectorAll('.rthumb').forEach(b => {
    thumbLoader.observe(b);
    b.addEventListener('click', () => { goToPage(+b.dataset.page); el.classList.remove('thumbs-open'); });
  });
  state.observers = [loader, thumbLoader, watcher];

  function goToPage(p, instant) {
    const f = stage.querySelector(`#rp-${p}`); if (!f) return;
    const img = f.querySelector('img'); if (img.dataset.src) { img.src = img.dataset.src; delete img.dataset.src; }
    scrollBox(stage, f.offsetTop - 16, !instant);
  }
  const start = pages.includes(opts.startPage) ? opts.startPage : pages[0];
  if (start) { markCurrent(start); requestAnimationFrame(() => goToPage(start, true)); }
  el._keys = e => { if (e.key === 'Escape') closePageReader(); };
  document.addEventListener('keydown', el._keys);
}
function closePageReader(silent) {
  const st = _reader; if (!st) return;
  Object.values(st.timers).forEach(clearTimeout);
  st.observers.forEach(o => o.disconnect());
  document.removeEventListener('keydown', st.el._keys);
  st.el.remove(); document.body.style.overflow = '';
  _reader = null;
  if (!silent && st.onClose) st.onClose();
}
