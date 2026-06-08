/**
 * CitySlot UI v3 — theme, sidebar, animations, toasts, progress, enhancements
 */
(function () {
    'use strict';

    /* ── Page progress bar ── */
    var progressBar = document.createElement('div');
    progressBar.id = 'page-progress';
    document.body.prepend(progressBar);

    var progressTimer;
    function startProgress() {
        clearTimeout(progressTimer);
        var w = 0;
        progressBar.style.width = '0%';
        progressBar.style.opacity = '1';
        var step = function () {
            w = w < 70 ? w + (Math.random() * 8 + 2) : w + 0.5;
            if (w > 90) w = 90;
            progressBar.style.width = w + '%';
            progressTimer = setTimeout(step, 120);
        };
        step();
    }
    function finishProgress() {
        clearTimeout(progressTimer);
        progressBar.style.width = '100%';
        setTimeout(function () {
            progressBar.style.opacity = '0';
            setTimeout(function () { progressBar.style.width = '0%'; }, 400);
        }, 300);
    }
    document.addEventListener('click', function (e) {
        var a = e.target.closest('a[href]');
        if (a && !a.getAttribute('target') && !a.getAttribute('href').startsWith('#') && !a.getAttribute('href').startsWith('javascript')) {
            startProgress();
        }
    });
    document.querySelectorAll('form').forEach(function (f) {
        f.addEventListener('submit', function () { startProgress(); });
    });
    window.addEventListener('pageshow', finishProgress);
    finishProgress();

    /* ── Toast system ── */
    var toastStack = document.createElement('div');
    toastStack.className = 'toast-stack';
    document.body.appendChild(toastStack);

    var TOAST_ICONS = {
        success: '<svg viewBox="0 0 20 20" fill="currentColor" style="color:var(--success)"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/></svg>',
        error:   '<svg viewBox="0 0 20 20" fill="currentColor" style="color:var(--danger)"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z" clip-rule="evenodd"/></svg>',
        info:    '<svg viewBox="0 0 20 20" fill="currentColor" style="color:var(--slate)"><path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7-4a1 1 0 11-2 0 1 1 0 012 0zM9 9a1 1 0 000 2v3a1 1 0 001 1h1a1 1 0 100-2v-3a1 1 0 00-1-1H9z" clip-rule="evenodd"/></svg>',
        warning: '<svg viewBox="0 0 20 20" fill="currentColor" style="color:var(--copper)"><path fill-rule="evenodd" d="M8.257 3.099c.765-1.36 2.722-1.36 3.486 0l5.58 9.92c.75 1.334-.213 2.98-1.742 2.98H4.42c-1.53 0-2.493-1.646-1.743-2.98l5.58-9.92zM11 13a1 1 0 11-2 0 1 1 0 012 0zm-1-8a1 1 0 00-1 1v3a1 1 0 002 0V6a1 1 0 00-1-1z" clip-rule="evenodd"/></svg>'
    };

    function showToast(message, type) {
        type = type || 'info';
        var toast = document.createElement('div');
        toast.className = 'toast toast-' + type;
        toast.innerHTML =
            '<span class="toast-icon">' + (TOAST_ICONS[type] || TOAST_ICONS.info) + '</span>' +
            '<span class="toast-body">' + message + '</span>' +
            '<button class="toast-close" aria-label="Dismiss">&times;</button>';
        toastStack.appendChild(toast);
        var close = function () {
            toast.classList.add('is-leaving');
            setTimeout(function () { toast.remove(); }, 320);
        };
        toast.querySelector('.toast-close').addEventListener('click', close);
        setTimeout(close, 5000);
    }
    window.showToast = showToast;

    /* Convert inline .alert elements to toasts */
    function initToasts() {
        document.querySelectorAll('.alert').forEach(function (alert) {
            var type = 'info';
            if (alert.classList.contains('alert-success')) type = 'success';
            else if (alert.classList.contains('alert-error')) type = 'error';
            else if (alert.classList.contains('alert-warning')) type = 'warning';
            showToast(alert.textContent.trim(), type);
            alert.style.display = 'none';
        });
    }

    /* ── Stat card icons ── */
    var STAT_ICONS = {
        'active bookings':   '<svg viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M6 2a1 1 0 00-1 1v1H4a2 2 0 00-2 2v10a2 2 0 002 2h12a2 2 0 002-2V6a2 2 0 00-2-2h-1V3a1 1 0 10-2 0v1H7V3a1 1 0 00-1-1zm0 5a1 1 0 000 2h8a1 1 0 100-2H6z" clip-rule="evenodd"/></svg>',
        'pending fines':     '<svg viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M8.257 3.099c.765-1.36 2.722-1.36 3.486 0l5.58 9.92c.75 1.334-.213 2.98-1.742 2.98H4.42c-1.53 0-2.493-1.646-1.743-2.98l5.58-9.92zM11 13a1 1 0 11-2 0 1 1 0 012 0zm-1-8a1 1 0 00-1 1v3a1 1 0 002 0V6a1 1 0 00-1-1z" clip-rule="evenodd"/></svg>',
        'wallet balance':    '<svg viewBox="0 0 20 20" fill="currentColor"><path d="M4 4a2 2 0 00-2 2v1h16V6a2 2 0 00-2-2H4z"/><path fill-rule="evenodd" d="M18 9H2v5a2 2 0 002 2h12a2 2 0 002-2V9zM4 13a1 1 0 011-1h1a1 1 0 110 2H5a1 1 0 01-1-1zm5-1a1 1 0 100 2h1a1 1 0 100-2H9z" clip-rule="evenodd"/></svg>',
        'notifications':     '<svg viewBox="0 0 20 20" fill="currentColor"><path d="M10 2a6 6 0 00-6 6v3.586l-.707.707A1 1 0 004 14h12a1 1 0 00.707-1.707L16 11.586V8a6 6 0 00-6-6zM10 18a3 3 0 01-3-3h6a3 3 0 01-3 3z"/></svg>',
        'loyalty tier':      '<svg viewBox="0 0 20 20" fill="currentColor"><path d="M9.049 2.927c.3-.921 1.603-.921 1.902 0l1.07 3.292a1 1 0 00.95.69h3.462c.969 0 1.371 1.24.588 1.81l-2.8 2.034a1 1 0 00-.364 1.118l1.07 3.292c.3.921-.755 1.688-1.54 1.118l-2.8-2.034a1 1 0 00-1.175 0l-2.8 2.034c-.784.57-1.838-.197-1.539-1.118l1.07-3.292a1 1 0 00-.364-1.118L2.98 8.72c-.783-.57-.38-1.81.588-1.81h3.461a1 1 0 00.951-.69l1.07-3.292z"/></svg>',
        'my spots':          '<svg viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M5.05 4.05a7 7 0 119.9 9.9L10 18.9l-4.95-4.95a7 7 0 010-9.9zM10 11a2 2 0 100-4 2 2 0 000 4z" clip-rule="evenodd"/></svg>',
        'total revenue':     '<svg viewBox="0 0 20 20" fill="currentColor"><path d="M8.433 7.418c.155-.103.346-.196.567-.267v1.698a2.305 2.305 0 01-.567-.267C8.07 8.34 8 8.114 8 8c0-.114.07-.34.433-.582zM11 12.849v-1.698c.22.071.412.164.567.267.364.243.433.468.433.582 0 .114-.07.34-.433.582a2.305 2.305 0 01-.567.267z"/><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm1-13a1 1 0 10-2 0v.092a4.535 4.535 0 00-1.676.662C6.602 6.234 6 7.009 6 8c0 .99.602 1.765 1.324 2.246.48.32 1.054.545 1.676.662v1.941c-.391-.127-.68-.317-.843-.504a1 1 0 10-1.51 1.31c.562.649 1.413 1.076 2.353 1.253V15a1 1 0 102 0v-.092a4.535 4.535 0 001.676-.662C13.398 13.766 14 12.991 14 12c0-.99-.602-1.765-1.324-2.246A4.535 4.535 0 0011 9.092V7.151c.391.127.68.317.843.504a1 1 0 101.511-1.31c-.563-.649-1.413-1.076-2.354-1.253V5z" clip-rule="evenodd"/></svg>',
        'balance':           '<svg viewBox="0 0 20 20" fill="currentColor"><path d="M4 4a2 2 0 00-2 2v1h16V6a2 2 0 00-2-2H4z"/><path fill-rule="evenodd" d="M18 9H2v5a2 2 0 002 2h12a2 2 0 002-2V9zM4 13a1 1 0 011-1h1a1 1 0 110 2H5a1 1 0 01-1-1zm5-1a1 1 0 100 2h1a1 1 0 100-2H9z" clip-rule="evenodd"/></svg>',
        'trust score':       '<svg viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M2.166 4.999A11.954 11.954 0 0010 1.944 11.954 11.954 0 0017.834 5c.11.65.166 1.32.166 2.001 0 5.225-3.34 9.67-8 11.317C5.34 16.67 2 12.225 2 7c0-.682.057-1.35.166-2.001zm11.541 3.708a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/></svg>',
        'total spots':       '<svg viewBox="0 0 20 20" fill="currentColor"><path d="M3 4a1 1 0 011-1h12a1 1 0 011 1v2a1 1 0 01-1 1H4a1 1 0 01-1-1V4zM3 10a1 1 0 011-1h6a1 1 0 011 1v6a1 1 0 01-1 1H4a1 1 0 01-1-1v-6zM14 9a1 1 0 00-1 1v6a1 1 0 001 1h2a1 1 0 001-1v-6a1 1 0 00-1-1h-2z"/></svg>',
        'active bookings':   '<svg viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M6 2a1 1 0 00-1 1v1H4a2 2 0 00-2 2v10a2 2 0 002 2h12a2 2 0 002-2V6a2 2 0 00-2-2h-1V3a1 1 0 10-2 0v1H7V3a1 1 0 00-1-1zm0 5a1 1 0 000 2h8a1 1 0 100-2H6z" clip-rule="evenodd"/></svg>',
        'pending appeals':   '<svg viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7-4a1 1 0 11-2 0 1 1 0 012 0zM9 9a1 1 0 000 2v3a1 1 0 001 1h1a1 1 0 100-2v-3a1 1 0 00-1-1H9z" clip-rule="evenodd"/></svg>',
        'owner verifications': '<svg viewBox="0 0 20 20" fill="currentColor"><path d="M9 2a1 1 0 000 2h2a1 1 0 100-2H9z"/><path fill-rule="evenodd" d="M4 5a2 2 0 012-2 3 3 0 003 3h2a3 3 0 003-3 2 2 0 012 2v11a2 2 0 01-2 2H6a2 2 0 01-2-2V5zm9.707 5.707a1 1 0 00-1.414-1.414L9 12.586l-1.293-1.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/></svg>',
        'violations detected': '<svg viewBox="0 0 20 20" fill="currentColor"><path d="M10 12a2 2 0 100-4 2 2 0 000 4z"/><path fill-rule="evenodd" d="M.458 10C1.732 5.943 5.522 3 10 3s8.268 2.943 9.542 7c-1.274 4.057-5.064 7-9.542 7S1.732 14.057.458 10zM14 10a4 4 0 11-8 0 4 4 0 018 0z" clip-rule="evenodd"/></svg>',
        'flagged spots':     '<svg viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M3 6a3 3 0 013-3h10a1 1 0 01.8 1.6L14.25 7l2.55 2.4A1 1 0 0116 11H6a1 1 0 00-1 1v3a1 1 0 11-2 0V6z" clip-rule="evenodd"/></svg>',
        'my share':          '<svg viewBox="0 0 20 20" fill="currentColor"><path d="M8.433 7.418c.155-.103.346-.196.567-.267v1.698a2.305 2.305 0 01-.567-.267C8.07 8.34 8 8.114 8 8c0-.114.07-.34.433-.582zM11 12.849v-1.698c.22.071.412.164.567.267.364.243.433.468.433.582 0 .114-.07.34-.433.582a2.305 2.305 0 01-.567.267z"/><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm1-13a1 1 0 10-2 0v.092a4.535 4.535 0 00-1.676.662C6.602 6.234 6 7.009 6 8c0 .99.602 1.765 1.324 2.246.48.32 1.054.545 1.676.662v1.941c-.391-.127-.68-.317-.843-.504a1 1 0 10-1.51 1.31c.562.649 1.413 1.076 2.353 1.253V15a1 1 0 102 0v-.092a4.535 4.535 0 001.676-.662C13.398 13.766 14 12.991 14 12c0-.99-.602-1.765-1.324-2.246A4.535 4.535 0 0011 9.092V7.151c.391.127.68.317.843.504a1 1 0 101.511-1.31c-.563-.649-1.413-1.076-2.354-1.253V5z" clip-rule="evenodd"/></svg>',
        'spot listings':     '<svg viewBox="0 0 20 20" fill="currentColor"><path d="M9 2a1 1 0 000 2h2a1 1 0 100-2H9z"/><path fill-rule="evenodd" d="M4 5a2 2 0 012-2 3 3 0 003 3h2a3 3 0 003-3 2 2 0 012 2v11a2 2 0 01-2 2H6a2 2 0 01-2-2V5zm3 4a1 1 0 000 2h.01a1 1 0 100-2H7zm3 0a1 1 0 000 2h3a1 1 0 100-2h-3zm-3 4a1 1 0 100 2h.01a1 1 0 100-2H7zm3 0a1 1 0 100 2h3a1 1 0 100-2h-3z" clip-rule="evenodd"/></svg>'
    };

    function initStatIcons() {
        document.querySelectorAll('.stat-card').forEach(function (card) {
            var label = (card.querySelector('.label') || {}).textContent || '';
            var key = label.trim().toLowerCase();
            var svg = STAT_ICONS[key];
            if (svg && !card.querySelector('.stat-card-icon')) {
                var icon = document.createElement('div');
                icon.className = 'stat-card-icon';
                icon.innerHTML = svg;
                card.appendChild(icon);
            }
        });
    }

    /* ── Table row stagger animation ── */
    function initTableRowAnimations() {
        document.querySelectorAll('tbody tr').forEach(function (row, i) {
            row.classList.add('row-in');
            row.style.animationDelay = Math.min(i * 35, 350) + 'ms';
        });
    }

    /* ── Empty states ── */
    var EMPTY_ICONS = {
        bookings:      '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><path d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>',
        parking:       '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><path d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"/><path d="M15 11a3 3 0 11-6 0 3 3 0 016 0z"/></svg>',
        violations:    '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><path d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>',
        notifications: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><path d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9"/></svg>',
        default:       '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><path d="M20 13V6a2 2 0 00-2-2H6a2 2 0 00-2 2v7m16 0v5a2 2 0 01-2 2H6a2 2 0 01-2-2v-5m16 0h-2.586a1 1 0 00-.707.293l-2.414 2.414a1 1 0 01-.707.293h-3.172a1 1 0 01-.707-.293l-2.414-2.414A1 1 0 006.586 13H4"/></svg>'
    };

    function initEmptyStates() {
        document.querySelectorAll('.text-muted').forEach(function (el) {
            /* Only transform top-level text-muted paragraphs directly in a card */
            if (!el.closest('.card') || el.tagName !== 'P') return;
            var txt = el.textContent.trim();
            if (txt.length < 10) return;

            var icon = 'default';
            var action = null;
            var sub = '';
            var link = el.querySelector('a');

            if (/booking/i.test(txt))       { icon = 'bookings'; sub = 'Your bookings will appear here once you make a reservation.'; }
            if (/violation|spot.*found/i.test(txt)) { icon = 'violations'; sub = 'No records found for the current filters.'; }
            if (/notification/i.test(txt))  { icon = 'notifications'; sub = 'You\'re all caught up — no new notifications.'; }
            if (/spot|parking/i.test(txt))  { icon = 'parking'; sub = 'Try adjusting your search filters.'; }

            var btnHtml = '';
            if (link) {
                btnHtml = '<a href="' + link.href + '" class="btn btn-primary btn-sm">' + link.textContent.trim() + '</a>';
            }

            var wrap = document.createElement('div');
            wrap.className = 'empty-state';
            wrap.innerHTML =
                '<div class="empty-state-icon">' + EMPTY_ICONS[icon] + '</div>' +
                '<p class="empty-state-title">' + txt.replace(/<[^>]*>/g, '').split('.')[0] + '.</p>' +
                (sub ? '<p class="empty-state-sub">' + sub + '</p>' : '') +
                btnHtml;
            el.replaceWith(wrap);
        });
    }

    /* ── Spot availability pulse ── */
    function initSpotPulse() {
        document.querySelectorAll('.spot-card').forEach(function (card) {
            if (!card.querySelector('.spot-avail-dot')) {
                var dot = document.createElement('div');
                dot.className = 'spot-avail-dot';
                dot.textContent = 'Available now';
                var addr = card.querySelector('.spot-addr');
                if (addr) addr.before(dot);
            }
        });
    }

    var STORAGE_KEY = 'cityslot-theme';
    var prefersReduced = window.matchMedia('(prefers-reduced-motion: reduce)').matches;

    /* ── Theme ── */
    function getTheme() {
        return document.documentElement.getAttribute('data-theme') === 'dark' ? 'dark' : 'light';
    }

    function setTheme(theme) {
        if (theme === 'dark') {
            document.documentElement.setAttribute('data-theme', 'dark');
        } else {
            document.documentElement.removeAttribute('data-theme');
        }
        try { localStorage.setItem(STORAGE_KEY, theme); } catch (e) {}
        updateThemeMeta(theme);
        document.querySelectorAll('.theme-toggle-label').forEach(function (el) {
            el.textContent = theme === 'dark' ? 'Light mode' : 'Dark mode';
        });
    }

    function toggleTheme() {
        setTheme(getTheme() === 'dark' ? 'light' : 'dark');
    }

    function updateThemeMeta(theme) {
        var meta = document.querySelector('meta[name="theme-color"]');
        if (meta) meta.setAttribute('content', theme === 'dark' ? '#1B1B1B' : '#10232A');
    }

    function initTheme() {
        document.querySelectorAll('#theme-toggle, #topbar-theme-toggle').forEach(function (btn) {
            btn.addEventListener('click', toggleTheme);
        });
        updateThemeMeta(getTheme());
        document.querySelectorAll('.theme-toggle-label').forEach(function (el) {
            el.textContent = getTheme() === 'dark' ? 'Light mode' : 'Dark mode';
        });
    }

    /* ── Sidebar mobile ── */
    function initSidebar() {
        var sidebar = document.getElementById('sidebar');
        var overlay = document.getElementById('sidebar-overlay');
        var toggle = document.getElementById('sidebar-toggle');
        if (!sidebar) return;

        function open() {
            sidebar.classList.add('is-open');
            if (overlay) overlay.classList.add('is-visible');
            document.body.style.overflow = 'hidden';
        }
        function close() {
            sidebar.classList.remove('is-open');
            if (overlay) overlay.classList.remove('is-visible');
            document.body.style.overflow = '';
        }

        if (toggle) toggle.addEventListener('click', function () {
            sidebar.classList.contains('is-open') ? close() : open();
        });
        if (overlay) overlay.addEventListener('click', close);

        sidebar.querySelectorAll('.sidebar-link').forEach(function (link) {
            link.addEventListener('click', function () {
                if (window.innerWidth <= 1024) close();
            });
        });
    }

    /* ── Topbar scroll ── */
    function initTopbarScroll() {
        var topbar = document.querySelector('.topbar');
        if (!topbar) return;
        var onScroll = function () {
            topbar.classList.toggle('scrolled', window.scrollY > 8);
        };
        onScroll();
        window.addEventListener('scroll', onScroll, { passive: true });
    }

    /* ── Page enter ── */
    function initPageEnter() {
        if (prefersReduced) return;
        document.body.classList.add('page-enter');
    }

    /* ── Scroll reveal ── */
    function isInViewport(el) {
        var rect = el.getBoundingClientRect();
        return rect.top < window.innerHeight * 0.92 && rect.bottom > 0;
    }

    function initScrollReveal() {
        var els = document.querySelectorAll('.reveal, .reveal-stagger');
        if (prefersReduced || !('IntersectionObserver' in window)) {
            els.forEach(function (el) { el.classList.add('is-visible'); });
            return;
        }
        var observer = new IntersectionObserver(function (entries) {
            entries.forEach(function (entry) {
                if (entry.isIntersecting) {
                    entry.target.classList.add('is-visible');
                    observer.unobserve(entry.target);
                }
            });
        }, { threshold: 0.02, rootMargin: '0px 0px -20px 0px' });
        els.forEach(function (el) {
            if (isInViewport(el)) {
                el.classList.add('is-visible');
            } else {
                observer.observe(el);
            }
        });
    }

    function autoReveal() {
        var map = {
            '.page-hero': 'reveal',
            '.stats-grid': 'reveal-stagger',
            '.card': 'reveal',
            '.spot-grid': 'reveal-stagger',
            '.spot-card': 'reveal',
            '.auth-form-box': 'reveal',
            '.alert': 'reveal',
            '.grid-2': 'reveal-stagger'
        };
        Object.keys(map).forEach(function (sel) {
            document.querySelectorAll(sel).forEach(function (el, i) {
                if (el.classList.contains('reveal') || el.classList.contains('reveal-stagger')) return;
                if (sel === '.spot-card' && el.closest('.spot-grid')) return;
                if (sel === '.card' && el.closest('.grid-2')) return;
                el.classList.add(map[sel]);
                if (map[sel] === 'reveal') {
                    el.style.transitionDelay = Math.min(i * 60, 360) + 'ms';
                }
            });
        });
    }

    /* ── Animated counters ── */
    function initCounters() {
        if (prefersReduced) return;
        document.querySelectorAll('[data-count]').forEach(function (el) {
            var target = parseFloat(el.getAttribute('data-count'));
            if (isNaN(target)) return;
            var isFloat = el.getAttribute('data-count-decimal') !== null;
            var duration = 900;
            var start = performance.now();

            function tick(now) {
                var p = Math.min((now - start) / duration, 1);
                var eased = 1 - Math.pow(1 - p, 3);
                var val = target * eased;
                el.textContent = isFloat ? val.toFixed(2) : Math.round(val).toString();
                if (p < 1) requestAnimationFrame(tick);
            }
            requestAnimationFrame(tick);
        });
    }

    /* ── Card lift ── */
    function initCardLift() {
        document.querySelectorAll('a.card, .card-link').forEach(function (el) {
            el.classList.add('card-lift');
        });
    }

    /* ── Form loading ── */
    function initFormLoading() {
        document.querySelectorAll('form').forEach(function (form) {
            form.addEventListener('submit', function () {
                var btn = form.querySelector('button[type="submit"]:not([formnovalidate])');
                if (!btn || btn.classList.contains('is-loading')) return;
                btn.classList.add('is-loading');
                btn.setAttribute('aria-busy', 'true');
                btn.innerHTML = '<span class="btn-spinner" aria-hidden="true"></span> Processing…';
            });
        });
    }

    /* ── Button ripple feedback ── */
    function initButtonFeedback() {
        document.querySelectorAll('.btn').forEach(function (btn) {
            btn.addEventListener('click', function (e) {
                if (prefersReduced || btn.classList.contains('is-loading')) return;
                var rect = btn.getBoundingClientRect();
                var ripple = document.createElement('span');
                var size = Math.max(rect.width, rect.height);
                ripple.style.cssText = 'position:absolute;border-radius:50%;background:rgba(255,255,255,0.35);width:' + size + 'px;height:' + size + 'px;left:' + (e.clientX - rect.left - size / 2) + 'px;top:' + (e.clientY - rect.top - size / 2) + 'px;transform:scale(0);animation:ripple 620ms cubic-bezier(0.33,1,0.68,1) forwards;pointer-events:none';
                btn.appendChild(ripple);
                setTimeout(function () { ripple.remove(); }, 520);
            });
        });
    }

    /* ── Alerts fade (legacy fallback) ── */
    function initAlerts() {
        /* Alerts are now converted to toasts — this is a no-op kept for compat */
    }

    function init() {
        document.documentElement.classList.add('js-ready');
        initTheme();
        initSidebar();
        initTopbarScroll();
        initPageEnter();
        autoReveal();
        initScrollReveal();
        initCounters();
        initCardLift();
        initFormLoading();
        initButtonFeedback();
        initToasts();
        initStatIcons();
        initTableRowAnimations();
        initEmptyStates();
        initSpotPulse();
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }
})();
