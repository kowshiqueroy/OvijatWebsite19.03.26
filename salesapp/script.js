/* Ovijat EIS v3.0 – Global JS */

(function () {
    'use strict';

    /* ── Sidebar toggle (mobile) ────────────────────────────── */
    function initSidebar() {
        var sidebar = document.getElementById('sidebar');
        var overlay = document.getElementById('sidebarOverlay');
        var toggle  = document.getElementById('sidebarToggle');
        if (!sidebar) return;

        function open() {
            sidebar.classList.add('open');
            if (overlay) overlay.classList.add('visible');
            document.body.style.overflow = 'hidden';
        }
        function close() {
            sidebar.classList.remove('open');
            if (overlay) overlay.classList.remove('visible');
            document.body.style.overflow = '';
        }

        if (toggle)  toggle.addEventListener('click', function () {
            sidebar.classList.contains('open') ? close() : open();
        });
        if (overlay) overlay.addEventListener('click', close);

        /* Auto-close on nav click (mobile) */
        sidebar.querySelectorAll('a.nav-item').forEach(function (el) {
            el.addEventListener('click', function () {
                if (window.innerWidth <= 768) close();
            });
        });
    }

    /* ── Sub-table row toggle ───────────────────────────────── */
    window.toggleRow = function (icon) {
        var row    = icon.closest('tr');
        var subRow = row ? row.nextElementSibling : null;
        if (subRow && subRow.classList.contains('sub-row')) {
            var isOpen = subRow.classList.toggle('open');
            icon.style.transform = isOpen ? 'rotate(90deg)' : 'rotate(0deg)';
        }
    };

    /* ── Date preset buttons ────────────────────────────────── */
    function initDatePresets() {
        document.querySelectorAll('.date-preset-btn').forEach(function (btn) {
            btn.addEventListener('click', function () {
                var preset = this.getAttribute('data-preset');
                var fromEl = document.getElementById('date_from');
                var toEl   = document.getElementById('date_to');
                if (!fromEl || !toEl) return;

                var today = new Date();
                var from, to = formatDate(today);

                switch (preset) {
                    case 'today':
                        from = to;
                        break;
                    case 'week':
                        var d = new Date(today);
                        d.setDate(today.getDate() - ((today.getDay() + 6) % 7));
                        from = formatDate(d);
                        break;
                    case 'month':
                        from = formatDate(new Date(today.getFullYear(), today.getMonth(), 1));
                        break;
                    case 'last_month':
                        var lm  = new Date(today.getFullYear(), today.getMonth() - 1, 1);
                        var lme = new Date(today.getFullYear(), today.getMonth(), 0);
                        from = formatDate(lm);
                        to   = formatDate(lme);
                        break;
                }

                if (from) { fromEl.value = from; toEl.value = to; }

                document.querySelectorAll('.date-preset-btn').forEach(function (b) {
                    b.classList.remove('active');
                });
                this.classList.add('active');

                var form = fromEl.closest('form');
                if (form) form.submit();
            });
        });
    }

    /* ── Toast notification ─────────────────────────────────── */
    window.showToast = function (message, type) {
        type = type || 'success';
        var container = document.querySelector('.toast-container');
        if (!container) {
            container = document.createElement('div');
            container.className = 'toast-container';
            document.body.appendChild(container);
        }
        var icons = { success: 'fa-check-circle', error: 'fa-circle-xmark', warning: 'fa-triangle-exclamation' };
        var toast = document.createElement('div');
        toast.className = 'toast ' + type;
        toast.innerHTML = '<i class="fa-solid ' + (icons[type] || 'fa-check-circle') + '"></i> ' + message;
        container.appendChild(toast);
        setTimeout(function () {
            toast.style.opacity = '0';
            toast.style.transition = 'opacity 0.3s';
            setTimeout(function () { toast.remove(); }, 320);
        }, 3200);
    };

    /* ── Confirm action ─────────────────────────────────────── */
    window.confirmAction = function (message, href) {
        if (window.confirm(message || 'Are you sure?')) {
            window.location.href = href;
        }
        return false;
    };

    /* ── Per-page selector ──────────────────────────────────── */
    function initPerPage() {
        var sel = document.getElementById('perPageSelect');
        if (!sel) return;
        sel.addEventListener('change', function () {
            var url = new URL(window.location.href);
            url.searchParams.set('per_page', this.value);
            url.searchParams.set('page', '1');
            window.location.href = url.toString();
        });
    }

    /* ── Helpers ────────────────────────────────────────────── */
    function formatDate(d) {
        return d.getFullYear() + '-' +
            String(d.getMonth() + 1).padStart(2, '0') + '-' +
            String(d.getDate()).padStart(2, '0');
    }

    /* ── Init ───────────────────────────────────────────────── */
    document.addEventListener('DOMContentLoaded', function () {
        initSidebar();
        initDatePresets();
        initPerPage();
    });

}());
