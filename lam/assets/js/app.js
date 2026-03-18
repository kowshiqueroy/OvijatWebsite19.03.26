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

// ── Number formatting ─────────────────────────────────────────
function fmtMoney(n) {
  return '$' + parseFloat(n || 0).toFixed(2).replace(/\B(?=(\d{3})+(?!\d))/g, ',');
}

// ── POS Cart ──────────────────────────────────────────────────
const Cart = (() => {
  let items = JSON.parse(sessionStorage.getItem('pos_cart') || '[]');

  function save()   { sessionStorage.setItem('pos_cart', JSON.stringify(items)); }
  function getAll() { return items; }

  function add(variant) {
    const existing = items.find(i => i.variant_id == variant.variant_id);
    if (existing) {
      if (existing.qty < variant.quantity) existing.qty++;
    } else {
      items.push({ ...variant, qty: 1 });
    }
    save();
    render();
  }

  function remove(variantId) {
    items = items.filter(i => i.variant_id != variantId);
    save();
    render();
  }

  function updateQty(variantId, qty) {
    const item = items.find(i => i.variant_id == variantId);
    if (!item) return;
    qty = parseInt(qty);
    if (qty <= 0) { remove(variantId); return; }
    if (qty > item.quantity) qty = item.quantity;
    item.qty = qty;
    save();
    render();
  }

  function clear() { items = []; save(); render(); }

  function subtotal() {
    return items.reduce((s, i) => s + i.price * i.qty, 0);
  }

  function render() {
    const container = document.getElementById('cartItems');
    const countEl   = document.getElementById('cartCount');
    const subEl     = document.getElementById('cartSubtotal');
    if (!container) return;

    if (countEl) countEl.textContent = items.reduce((s, i) => s + i.qty, 0);
    if (subEl)   subEl.textContent   = fmtMoney(subtotal());

    if (items.length === 0) {
      container.innerHTML = '<p class="text-muted text-center" style="padding:24px">Cart is empty</p>';
    } else {
      container.innerHTML = items.map(item => `
        <div class="cart-item">
          <div class="item-name">
            ${escHtml(item.name)}
            <div class="text-muted" style="font-size:.75rem">${escHtml(item.size||'')} ${escHtml(item.color||'')}</div>
          </div>
          <div class="item-qty">
            <button class="qty-btn" onclick="Cart.updateQty(${item.variant_id}, ${item.qty - 1})">−</button>
            <input class="qty-input" type="number" value="${item.qty}" min="1" max="${item.quantity}"
              onchange="Cart.updateQty(${item.variant_id}, this.value)">
            <button class="qty-btn" onclick="Cart.updateQty(${item.variant_id}, ${item.qty + 1})">+</button>
          </div>
          <div class="item-price">${fmtMoney(item.price * item.qty)}</div>
          <button class="item-remove" onclick="Cart.remove(${item.variant_id})" title="Remove">✕</button>
        </div>
      `).join('');
    }
    updateTotals();
  }

  function updateTotals() {
    const sub      = subtotal();
    const discType = (document.getElementById('discountType')?.value || 'percent');
    const discVal  = parseFloat(document.getElementById('discountPct')?.value   || 0);
    const vatType  = (document.getElementById('vatType')?.value || 'percent');
    const vatVal   = parseFloat(document.getElementById('vatRate')?.value        || 0);
    const toggle   = document.getElementById('usePointsToggle');
    const ptUsed   = (toggle && toggle.checked)
                       ? parseInt(document.getElementById('pointsUsed')?.value || 0)
                       : 0;
    const ptRate   = parseFloat(document.querySelector('[data-pts-rate]')?.dataset.ptsRate || 0.01);
    const ptVal    = ptUsed * ptRate;

    // Update points value preview
    const prev = document.getElementById('pointsValuePreview');
    if (prev) prev.textContent = ptUsed > 0 ? `= ${fmtMoney(ptVal)} off` : '';

    const discAmt  = discType === 'percent' ? sub * (discVal / 100) : Math.min(discVal, sub);
    const afterDis = sub - discAmt - ptVal;
    const vatAmt   = vatType === 'percent' ? afterDis * (vatVal / 100) : vatVal;
    const total    = Math.max(0, afterDis + vatAmt);

    setText('summarySubtotal',   fmtMoney(sub));
    setText('summaryDiscount',   fmtMoney(discAmt));
    setText('summaryPoints',     fmtMoney(ptVal));
    setText('summaryVat',        fmtMoney(vatAmt));
    setText('summaryTotal',      fmtMoney(total));
    setValue('hiddenTotal',      total.toFixed(2));
    setValue('hiddenDiscAmt',    discAmt.toFixed(2));
    setValue('hiddenVatAmt',     vatAmt.toFixed(2));
    setValue('hiddenPtVal',      ptVal.toFixed(2));
    setValue('hiddenCartJson',   JSON.stringify(items));
  }

  function setText(id, v) { const el = document.getElementById(id); if (el) el.textContent = v; }
  function setValue(id, v){ const el = document.getElementById(id); if (el) el.value = v; }

  // initial render
  render();

  return { add, remove, updateQty, clear, render, updateTotals, getAll };
})();

// ── Variant selector ──────────────────────────────────────────
function selectVariant(productId) {
  fetch(`index.php?page=pos&action=get_variants&product_id=${productId}`)
    .then(r => r.json())
    .then(variants => {
      if (variants.length === 1) { Cart.add(variants[0]); return; }
      showVariantModal(variants);
    });
}

function showVariantModal(variants) {
  const body = document.getElementById('variantModalBody');
  if (!body) return;
  body.innerHTML = variants.map(v => `
    <button class="btn btn-ghost w-100 mb-1" style="justify-content:space-between" onclick="Cart.add(${JSON.stringify(v).replace(/"/g,'&quot;')}); closeModal('variantModal')">
      <span>${escHtml(v.size||'—')} / ${escHtml(v.color||'—')}</span>
      <strong>${fmtMoney(v.price)} &nbsp; Qty: ${v.quantity}</strong>
    </button>
  `).join('');
  openModal('variantModal');
}

// ── Barcode search (camera stub) ──────────────────────────────
function searchByBarcode(barcode) {
  fetch(`index.php?page=pos&action=barcode_lookup&barcode=${encodeURIComponent(barcode)}`)
    .then(r => r.json())
    .then(data => { if (data.variant_id) Cart.add(data); });
}

// ── Customer lookup ───────────────────────────────────────────
function lookupCustomer(phone) {
  if (!phone) return;
  fetch(`index.php?page=pos&action=lookup_customer&phone=${encodeURIComponent(phone)}`)
    .then(r => r.json())
    .then(c => {
      if (c.id) {
        setValue('customerId',    c.id);
        setValue('customerName',  c.name);
        const ptBadge = document.getElementById('pointsBadge');
        const ptSec   = document.getElementById('pointsSection');
        const ptInput = document.getElementById('pointsUsed');
        if (ptBadge) ptBadge.textContent = c.points + ' pts';
        if (ptInput) { ptInput.max = c.points; ptInput.dataset.ptsRate = '0.01'; }
        // Set data attribute on a container for rate lookup
        if (ptSec) { ptSec.dataset.ptsRate = '0.01'; ptSec.classList.remove('hidden'); }
      }
    });
  function setValue(id, v){ const el = document.getElementById(id); if (el) el.value = v; }
}

// ── XSS escape ───────────────────────────────────────────────
function escHtml(s) {
  return String(s).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
}

// ── Print invoice ─────────────────────────────────────────────
function printInvoice() {
  window.print();
}

// ── Category filter ───────────────────────────────────────────
document.getElementById('categoryFilter')?.addEventListener('change', function() {
  const val = this.value;
  document.querySelectorAll('.product-tile').forEach(tile => {
    if (!val || tile.dataset.category == val) {
      tile.style.display = '';
    } else {
      tile.style.display = 'none';
    }
  });
});

// ── Product search filter ─────────────────────────────────────
document.getElementById('productSearch')?.addEventListener('input', function() {
  const q = this.value.toLowerCase();
  document.querySelectorAll('.product-tile').forEach(tile => {
    tile.style.display = tile.dataset.name?.toLowerCase().includes(q) ? '' : 'none';
  });
});
