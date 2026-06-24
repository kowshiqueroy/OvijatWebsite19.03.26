        </div><!-- .main-content -->
    </div><!-- #page-content-wrapper -->
</div><!-- #wrapper -->

<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
<script src="<?php echo BASE_URL; ?>assets/js/export.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
<script>
(function () {
    const sidebar  = document.getElementById('sidebar-wrapper');
    const backdrop = document.getElementById('sidebar-backdrop');
    const toggle   = document.getElementById('menu-toggle');
    const wrapper  = document.getElementById('wrapper');
    const isMobile = () => window.innerWidth < 768;

    /* ── Mobile: slide-in overlay ── */
    function openMobile() {
        sidebar && sidebar.classList.add('open');
        backdrop && backdrop.classList.add('open');
        document.body.style.overflow = 'hidden';
    }
    function closeMobile() {
        sidebar && sidebar.classList.remove('open');
        backdrop && backdrop.classList.remove('open');
        document.body.style.overflow = '';
    }

    /* ── Desktop: mini (icon-only) ↔ full toggle ── */
    function toggleDesktop() {
        const isMini = wrapper && wrapper.classList.toggle('mini');
        localStorage.setItem('sbMini', isMini ? '1' : '0');
    }

    /* Restore desktop state */
    if (!isMobile() && localStorage.getItem('sbMini') === '1') {
        wrapper && wrapper.classList.add('mini');
    }

    if (toggle) {
        toggle.addEventListener('click', function () {
            if (isMobile()) openMobile();
            else            toggleDesktop();
        });
    }
    backdrop && backdrop.addEventListener('click', closeMobile);

    /* Close on nav link click (mobile) */
    sidebar && sidebar.querySelectorAll('.sidebar-link').forEach(function (a) {
        a.addEventListener('click', function () { if (isMobile()) closeMobile(); });
    });

    /* ── Collapsible sections ── */
    function initSections() {
        document.querySelectorAll('.sb-section-toggle').forEach(function (btn) {
            const targetId = btn.getAttribute('data-target');
            const body     = document.getElementById(targetId);
            if (!body) return;

            // Restore collapse state (default = open)
            const stored = localStorage.getItem('sb-' + targetId);
            if (stored === '0') {
                body.classList.add('collapsed');
                btn.classList.add('collapsed');
            }

            btn.addEventListener('click', function () {
                const isCollapsed = body.classList.toggle('collapsed');
                btn.classList.toggle('collapsed', isCollapsed);
                localStorage.setItem('sb-' + targetId, isCollapsed ? '0' : '1');
            });
        });
    }
    initSections();

    /* Auto-expand section containing the active link */
    const activeLink = sidebar && sidebar.querySelector('.sidebar-link.active');
    if (activeLink) {
        const sectionBody = activeLink.closest('.sb-section-body');
        if (sectionBody && sectionBody.classList.contains('collapsed')) {
            sectionBody.classList.remove('collapsed');
            const btn = sectionBody.closest('.sb-section')?.querySelector('.sb-section-toggle');
            if (btn) btn.classList.remove('collapsed');
        }
    }

    // ── Select2 init ────────────────────────────────────────────
    if (typeof $ !== 'undefined' && $.fn.select2) {
        $('.select2').select2({ width: '100%', theme: 'default' });
    }

    // ── Enter-key form navigation ────────────────────────────────
    $(document).on('keydown', 'input:not([type=submit]):not([type=button]), select', function (e) {
        if (e.key === 'Enter') {
            e.preventDefault();
            const inputs = $(this).closest('form').find(':input:visible:enabled');
            const idx    = inputs.index(this);
            const next   = inputs.eq(idx + 1);
            if (next.length) next.focus();
            else $(this).closest('form').submit();
        }
    });

    // ── Auto-dismiss flash alerts ────────────────────────────────
    setTimeout(function () {
        document.querySelectorAll('.flash-wrapper .alert').forEach(function (el) {
            el.style.transition = 'opacity .5s';
            el.style.opacity    = '0';
            setTimeout(function () { el.remove(); }, 500);
        });
    }, 4000);
})();
</script>
</body>
</html>
