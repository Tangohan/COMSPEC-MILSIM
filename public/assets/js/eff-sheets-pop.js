/**
 * Popovers tableur effectifs (.eff-sheets__pop) — ancrage fixed hors overflow:auto.
 */
(function () {
    function clearAnchor(details) {
        details.classList.remove('is-anchored');
        var panel = details.querySelector('.eff-sheets__pop-panel');
        if (!panel) return;
        panel.style.top = '';
        panel.style.left = '';
        panel.style.right = '';
        panel.style.maxHeight = '';
        panel.style.width = '';
    }

    function placePanel(details) {
        var summary = details.querySelector('summary');
        var panel = details.querySelector('.eff-sheets__pop-panel');
        if (!summary || !panel || !details.open) return;

        details.classList.add('is-anchored');
        // Mesure après passage en fixed (sinon largeur/hauteur faussées dans la cellule)
        panel.style.top = '0px';
        panel.style.left = '0px';
        panel.style.visibility = 'hidden';

        var gap = 6;
        var margin = 10;
        var anchor = summary.getBoundingClientRect();
        var panelRect = panel.getBoundingClientRect();
        var vw = window.innerWidth;
        var vh = window.innerHeight;
        var preferEnd = details.classList.contains('eff-sheets__pop--end');

        var width = Math.min(panelRect.width || 320, vw - margin * 2);
        var maxH = Math.min(vh - margin * 2, 28 * 16);
        panel.style.width = width + 'px';
        panel.style.maxHeight = maxH + 'px';

        panelRect = panel.getBoundingClientRect();
        var height = panelRect.height || Math.min(240, maxH);

        var top = anchor.bottom + gap;
        if (top + height > vh - margin) {
            var up = anchor.top - gap - height;
            if (up >= margin) {
                top = up;
            } else {
                top = Math.max(margin, Math.min(top, vh - margin - Math.min(height, maxH)));
                panel.style.maxHeight = Math.max(120, vh - top - margin) + 'px';
            }
        }

        var left;
        if (preferEnd) {
            left = anchor.right - width;
        } else {
            left = anchor.left;
        }
        left = Math.max(margin, Math.min(left, vw - margin - width));

        panel.style.top = Math.round(top) + 'px';
        panel.style.left = Math.round(left) + 'px';
        panel.style.visibility = '';
    }

    function closeOthers(except) {
        document.querySelectorAll('details.eff-sheets__pop[open]').forEach(function (el) {
            if (el !== except) {
                el.removeAttribute('open');
                clearAnchor(el);
            }
        });
    }

    function onToggle(ev) {
        var details = ev.target;
        if (!(details instanceof HTMLDetailsElement) || !details.classList.contains('eff-sheets__pop')) {
            return;
        }
        if (details.open) {
            closeOthers(details);
            placePanel(details);
        } else {
            clearAnchor(details);
        }
    }

    function repositionOpen() {
        document.querySelectorAll('details.eff-sheets__pop[open]').forEach(placePanel);
    }

    function onDocPointer(ev) {
        var open = document.querySelector('details.eff-sheets__pop[open]');
        if (!open) return;
        if (open.contains(ev.target)) return;
        open.removeAttribute('open');
        clearAnchor(open);
    }

    function onKey(ev) {
        if (ev.key !== 'Escape') return;
        document.querySelectorAll('details.eff-sheets__pop[open]').forEach(function (el) {
            el.removeAttribute('open');
            clearAnchor(el);
        });
    }

    document.addEventListener('toggle', onToggle, true);
    document.addEventListener('pointerdown', onDocPointer, true);
    document.addEventListener('keydown', onKey);
    window.addEventListener('resize', repositionOpen);
    document.querySelectorAll('.eff-sheets').forEach(function (scroller) {
        scroller.addEventListener('scroll', repositionOpen, { passive: true });
    });
})();
