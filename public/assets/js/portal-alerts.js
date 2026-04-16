/**
 * Cloche « annonces » : fonctionne sur toute page avec [data-portal-alerts-wrap],
 * indépendamment du shell portail ([data-portal-nav]).
 */
(function () {
    function placePanel(wrap) {
        if (!wrap) return;
        var trig = wrap.querySelector('[data-portal-alerts-trigger]');
        var panel = wrap.querySelector('[data-portal-alerts-panel]');
        if (!trig || !panel || panel.hidden) return;

        var gap = 10;
        var viewportPadding = 10;
        var trigRect = trig.getBoundingClientRect();
        panel.style.position = 'fixed';
        panel.style.top = Math.round(trigRect.bottom + gap) + 'px';
        panel.style.right = '';
        panel.style.left = '0px';
        panel.style.maxHeight = 'min(70vh, 420px)';

        var panelRect = panel.getBoundingClientRect();
        var left = trigRect.right - panelRect.width;
        var maxLeft = window.innerWidth - panelRect.width - viewportPadding;
        left = Math.max(viewportPadding, Math.min(left, maxLeft));

        var maxTop = window.innerHeight - panelRect.height - viewportPadding;
        var top = trigRect.bottom + gap;
        if (top > maxTop) {
            top = Math.max(viewportPadding, trigRect.top - panelRect.height - gap);
        }

        panel.style.left = Math.round(left) + 'px';
        panel.style.top = Math.round(top) + 'px';
    }

    function closePanel(wrap) {
        if (!wrap) return;
        var trig = wrap.querySelector('[data-portal-alerts-trigger]');
        var panel = wrap.querySelector('[data-portal-alerts-panel]');
        if (panel) {
            panel.hidden = true;
            panel.style.position = '';
            panel.style.top = '';
            panel.style.left = '';
            panel.style.right = '';
            panel.style.maxHeight = '';
        }
        if (trig) trig.setAttribute('aria-expanded', 'false');
    }

    function closeAllPanels() {
        document.querySelectorAll('[data-portal-alerts-wrap]').forEach(closePanel);
    }

    function togglePanel(wrap) {
        var trig = wrap.querySelector('[data-portal-alerts-trigger]');
        var panel = wrap.querySelector('[data-portal-alerts-panel]');
        if (!trig || !panel) return;
        var open = trig.getAttribute('aria-expanded') === 'true';
        if (open) {
            closePanel(wrap);
            return;
        }
        document.querySelectorAll('[data-portal-alerts-wrap]').forEach(function (other) {
            if (other !== wrap) closePanel(other);
        });
        panel.hidden = false;
        trig.setAttribute('aria-expanded', 'true');
        placePanel(wrap);
        window.setTimeout(function () {
            var first = panel.querySelector('a, button');
            if (first && first.focus) first.focus();
        }, 0);
    }

    function initWrap(wrap) {
        if (wrap.getAttribute('data-portal-alerts-init') === '1') return;
        wrap.setAttribute('data-portal-alerts-init', '1');
        var trig = wrap.querySelector('[data-portal-alerts-trigger]');
        var panel = wrap.querySelector('[data-portal-alerts-panel]');
        if (!trig || !panel) return;
        trig.addEventListener('click', function (e) {
            e.stopPropagation();
            togglePanel(wrap);
        });
    }

    document.querySelectorAll('[data-portal-alerts-wrap]').forEach(initWrap);

    document.addEventListener('click', function (e) {
        if (!e.target) return;
        var inside = e.target.closest('[data-portal-alerts-wrap]');
        if (inside) return;
        closeAllPanels();
    });

    document.addEventListener('keydown', function (e) {
        if (e.key !== 'Escape') return;
        closeAllPanels();
    });

    window.addEventListener('resize', function () {
        document.querySelectorAll('[data-portal-alerts-wrap]').forEach(placePanel);
    }, { passive: true });

    window.addEventListener('scroll', function () {
        document.querySelectorAll('[data-portal-alerts-wrap]').forEach(placePanel);
    }, { passive: true });
})();
