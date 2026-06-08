/**
 * CitySlot UI v2 — theme, sidebar, animations
 */
(function () {
    'use strict';

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

    /* ── Alerts fade ── */
    function initAlerts() {
        document.querySelectorAll('.alert').forEach(function (alert) {
            setTimeout(function () {
                alert.style.transition = 'opacity 620ms cubic-bezier(0.33,1,0.68,1)';
                alert.style.opacity = '0.88';
            }, 7000);
        });
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
        initAlerts();
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }
})();
