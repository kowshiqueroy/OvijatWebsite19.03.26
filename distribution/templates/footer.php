        </div><!-- .main-content -->
    </div><!-- #page-content-wrapper -->
</div><!-- #wrapper -->

<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
<script src="<?php echo BASE_URL; ?>assets/js/export.js?v=<?php echo filemtime(__DIR__ . '/../assets/js/export.js'); ?>"></script>
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

    /* ── Collapsible sections (accordion) ── */
    function initSections() {
        const sections = [];

        document.querySelectorAll('.sb-section-toggle').forEach(function (btn) {
            const targetId = btn.getAttribute('data-target');
            const body     = document.getElementById(targetId);
            if (!body) return;

            // Start everything collapsed
            body.classList.add('collapsed');
            btn.classList.add('collapsed');

            sections.push({ btn: btn, body: body });
        });

        // Open only the section that contains the active page link
        const activeLink = sidebar && sidebar.querySelector('.sidebar-link.active');
        if (activeLink) {
            const activeBody = activeLink.closest('.sb-section-body');
            if (activeBody) {
                activeBody.classList.remove('collapsed');
                const activeBtn = activeBody.closest('.sb-section').querySelector('.sb-section-toggle');
                if (activeBtn) activeBtn.classList.remove('collapsed');
            }
        }

        // Accordion click: open clicked section, close all others
        sections.forEach(function (sec) {
            sec.btn.addEventListener('click', function () {
                const willOpen = sec.body.classList.contains('collapsed');
                sections.forEach(function (s) {
                    s.body.classList.add('collapsed');
                    s.btn.classList.add('collapsed');
                });
                if (willOpen) {
                    sec.body.classList.remove('collapsed');
                    sec.btn.classList.remove('collapsed');
                }
            });
        });
    }
    initSections();

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
