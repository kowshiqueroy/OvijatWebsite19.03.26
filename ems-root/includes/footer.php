  </main><!-- /page-content -->
  <footer class="ems-footer">
    <?= e(setting('school_name','EMS')) ?> &mdash; Education Management System &nbsp;|&nbsp;
    Powered by <a href="https://sohojweb.com" target="_blank">SohojWeb</a>
  </footer>
</div><!-- /main-content -->
</div><!-- /wrapper -->

<!-- Bootstrap 5 JS -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<!-- EMS App JS -->
<script src="<?= $ASSET ?>/js/app.js"></script>
<!-- EMS Utilities: error popup, export, duplicate prevention -->
<script src="<?= $ASSET ?? 'assets' ?>/js/ems-utils.js"></script>

<?php if (!empty($extra_js)) foreach ((array)$extra_js as $js): ?>
<script src="<?= e($js) ?>"></script>
<?php endforeach; ?>

<?php if (!empty($inline_js)): ?>
<script><?= $inline_js ?></script>
<?php endif; ?>

</body>
</html>
