/* =====================================================================
   CHMS — front-end behaviour layer
   Progressive enhancement only: the app works without it.
   ===================================================================== */
(function () {
    'use strict';

    const reduceMotion = window.matchMedia('(prefers-reduced-motion: reduce)').matches;

    /* ---- Brand palette exposed to JS (kept in sync with theme.css) ---- */
    const BRAND = {
        primary: '#2b5b97',
        primary500: '#3a72b3',
        primary300: '#90b6dd',
        success: '#16a34a',
        warning: '#d97706',
        danger:  '#dc2626',
        info:    '#0284c7',
        violet:  '#7c3aed',
        ink:     '#1e293b',
        muted:   '#94a3b8',
        grid:    'rgba(148,163,184,.18)',
    };
    // Ordered palette for categorical charts (doughnut/bar series)
    const PALETTE = [
        BRAND.primary, BRAND.success, BRAND.warning, BRAND.danger,
        BRAND.info, BRAND.violet, BRAND.primary300, '#0d9488',
    ];
    window.CHMS = { BRAND, PALETTE };

    /* ---- Global Chart.js defaults (if Chart is present) -------------- */
    function themeCharts() {
        if (!window.Chart) return;
        const C = window.Chart;
        C.defaults.font.family = "'Inter', ui-sans-serif, system-ui, sans-serif";
        C.defaults.font.size = 12;
        C.defaults.color = BRAND.muted;
        C.defaults.borderColor = BRAND.grid;
        C.defaults.animation = reduceMotion ? false : { duration: 900, easing: 'easeOutQuart' };
        C.defaults.plugins.legend.labels.usePointStyle = true;
        C.defaults.plugins.legend.labels.boxWidth = 8;
        C.defaults.plugins.legend.labels.padding = 16;
        C.defaults.plugins.tooltip.backgroundColor = 'rgba(20,35,57,.95)';
        C.defaults.plugins.tooltip.padding = 10;
        C.defaults.plugins.tooltip.cornerRadius = 8;
        C.defaults.plugins.tooltip.titleFont = { weight: '600' };
        C.defaults.maintainAspectRatio = false;
        if (C.defaults.scale && C.defaults.scale.grid) C.defaults.scale.grid.drawTicks = false;
    }

    /* Build a vertical gradient fill for line/area charts */
    window.chmsAreaGradient = function (ctx, hex, h) {
        const g = ctx.createLinearGradient(0, 0, 0, h || 240);
        g.addColorStop(0, hexA(hex, .28));
        g.addColorStop(1, hexA(hex, 0));
        return g;
    };
    function hexA(hex, a) {
        const n = parseInt(hex.slice(1), 16);
        return `rgba(${(n >> 16) & 255},${(n >> 8) & 255},${n & 255},${a})`;
    }

    /* ---- Animated count-up for [data-count] elements ----------------- */
    function countUp(el) {
        const target = parseFloat(el.getAttribute('data-count')) || 0;
        const decimals = parseInt(el.getAttribute('data-decimals') || '0', 10);
        const prefix = el.getAttribute('data-prefix') || '';
        const suffix = el.getAttribute('data-suffix') || '';
        if (reduceMotion) { el.textContent = prefix + fmt(target, decimals) + suffix; return; }
        const dur = 1100, t0 = performance.now();
        function tick(now) {
            const p = Math.min((now - t0) / dur, 1);
            const eased = 1 - Math.pow(1 - p, 3);
            el.textContent = prefix + fmt(target * eased, decimals) + suffix;
            if (p < 1) requestAnimationFrame(tick);
        }
        requestAnimationFrame(tick);
    }
    function fmt(n, d) {
        return n.toLocaleString(undefined, { minimumFractionDigits: d, maximumFractionDigits: d });
    }

    /* ---- Reveal-on-scroll for [data-reveal] -------------------------- */
    function initReveal() {
        const items = document.querySelectorAll('[data-reveal]');
        if (!items.length) return;
        if (reduceMotion || !('IntersectionObserver' in window)) {
            items.forEach(el => el.classList.add('reveal'));
            return;
        }
        const io = new IntersectionObserver((entries, obs) => {
            entries.forEach(entry => {
                if (!entry.isIntersecting) return;
                const el = entry.target;
                const i = +el.getAttribute('data-reveal') || 0;
                el.style.setProperty('--d', (i * 70) + 'ms');
                el.classList.add('reveal');
                obs.unobserve(el);
            });
        }, { threshold: 0.08, rootMargin: '0px 0px -40px 0px' });
        items.forEach(el => io.observe(el));
    }

    /* ---- Animate progress bars to their target width ----------------- */
    function initBars() {
        document.querySelectorAll('.bar-fill[data-width]').forEach(bar => {
            const w = bar.getAttribute('data-width');
            requestAnimationFrame(() => requestAnimationFrame(() => { bar.style.width = w + '%'; }));
        });
    }

    /* ---- Counters fire when scrolled into view ----------------------- */
    function initCounters() {
        const els = document.querySelectorAll('[data-count]');
        if (!els.length) return;
        if (!('IntersectionObserver' in window)) { els.forEach(countUp); return; }
        const io = new IntersectionObserver((entries, obs) => {
            entries.forEach(e => { if (e.isIntersecting) { countUp(e.target); obs.unobserve(e.target); } });
        }, { threshold: 0.4 });
        els.forEach(el => io.observe(el));
    }

    // Apply Chart.js defaults as soon as this script runs — it is loaded
    // right after chart.umd.js, so views' DOMContentLoaded chart code sees them.
    themeCharts();

    function boot() {
        themeCharts();
        initReveal();
        initCounters();
        initBars();
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', boot);
    } else { boot(); }
})();
