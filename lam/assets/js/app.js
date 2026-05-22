// ============================================================
// assets/js/app.js — Core UI interactions
// ============================================================
const navToggle  = document.getElementById('navToggle');
const sideNav    = document.getElementById('sideNav');
const navOverlay = document.getElementById('navOverlay');

// Function to open/close
function toggleMenu() {
  sideNav.classList.toggle('open');
  navOverlay.classList.toggle('open');
}

// Function to force close
function closeMenu() {
  sideNav.classList.remove('open');
  navOverlay.classList.remove('open');
}

// Event Listeners
navToggle?.addEventListener('click', (e) => {
  e.preventDefault();
  toggleMenu();
});

navOverlay?.addEventListener('click', closeMenu);

// Close if a link is clicked
document.querySelectorAll('.nav-link').forEach(link => {
  link.addEventListener('click', closeMenu);
});

// ── Modal helpers ─────────────────────────────────────────────
function openModal(id)  { document.getElementById(id)?.classList.add('open'); }
function closeModal(id) { document.getElementById(id)?.classList.remove('open'); }

document.addEventListener('click', e => {
  if (e.target.classList.contains('modal-backdrop')) {
    e.target.classList.remove('open');
  }
  if (e.target.classList.contains('modal-close')) {
    e.target.closest('.modal-backdrop')?.classList.remove('open');
  }
});

// ── Confirm delete ────────────────────────────────────────────
document.querySelectorAll('[data-confirm]').forEach(el => {
  el.addEventListener('click', e => {
    if (!confirm(el.dataset.confirm || 'Are you sure?')) e.preventDefault();
  });
});

// ── Flash auto-dismiss ─────────────────────────────────────── 
setTimeout(() => {
  document.querySelectorAll('.flash').forEach(el => {
    el.style.transition = 'opacity .4s';
    el.style.opacity = '0';
    setTimeout(() => el.remove(), 400);
  });
}, 4000);

// ── XSS escape ───────────────────────────────────────────────
function escHtml(s) {
  return String(s).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
}

// ── Print invoice ─────────────────────────────────────────────
function printInvoice() {
  window.print();
}
