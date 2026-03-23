<?php
// ============================================================
// includes/footer.php — Common page footer
// ============================================================
?>
</main>

<!-- ── Variant Selector Modal ──────────────────────────────── -->
<div class="modal-backdrop" id="variantModal">
  <div class="modal">
    <div class="modal-header">
      <span class="modal-title">Select Variant</span>
      <button class="modal-close">✕</button>
    </div>
    <div class="modal-body" id="variantModalBody"></div>
  </div>
</div>

<!-- ── Footer ──────────────────────────────────────────────── -->
<footer class="app-footer no-print">
  <?= APP_NAME ?> v<?= APP_VERSION ?> &nbsp;·&nbsp; <?= date('Y') ?> Developed by kowshiqueroy@gmail.com
</footer>

<script src="<?= BASE_URL ?>/assets/js/app.js"></script>
<script>

// Show offline banner on any page
window.addEventListener('offline', function() {
  const bar = document.createElement('div');
  bar.id = 'offlineBanner';
  bar.style.cssText = 'position:fixed;top:100px;left:0;right:0;z-index:999;background:#ef4444;color:#fff;text-align:center;padding:6px;font-size:5.85rem;font-weight:700';
  bar.innerHTML = '⚠️ You are offline';
  document.body.appendChild(bar);
});
window.addEventListener('online', function() {
  //change text of offline banner to "Back Online" green and then remove after 2 seconds
  const offlineBanner = document.getElementById('offlineBanner');
  if (offlineBanner) {
    offlineBanner.style.background = '#10b981';
    offlineBanner.innerHTML = '✅ Back online';

    setTimeout(() => {
      offlineBanner.remove();
    }, 2000);
  }
});


</script>
<?php if (!empty($extraJs)): ?>
  <script><?= $extraJs ?></script>
<?php endif ?>
</body>
</html>
