<?php
/**
 * Réduit l’exfiltration facilitée (menu contextuel, glisser-déposer, sélection sur la zone protégée).
 * Détection « minimale » de la touche Impr. écran : si le navigateur envoie l’événement, on floute la zone
 * [data-doc-viewport]. Ce n’est pas fiable sur tous les OS (souvent intercepté avant la page).
 */
?>
<style>
    [data-doc-protect] {
        -webkit-user-select: none;
        user-select: none;
        -webkit-touch-callout: none;
    }
    [data-doc-protect] canvas,
    [data-doc-protect] img.doc-protect-asset {
        -webkit-user-drag: none;
        user-drag: none;
    }
    .doc-viewport-inner.doc-viewport--blurred {
        filter: blur(56px);
        -webkit-filter: blur(56px);
        transition: filter 0.18s ease, -webkit-filter 0.18s ease;
    }
    .doc-screenshot-shield.doc-screenshot-shield--active {
        display: flex;
        flex-direction: column;
        opacity: 1;
        pointer-events: auto;
    }
</style>
<script>
(function () {
    document.addEventListener('contextmenu', function (e) {
        if (e.target.closest('[data-doc-protect]')) {
            e.preventDefault();
        }
    }, true);
    document.addEventListener('dragstart', function (e) {
        if (e.target.closest('[data-doc-protect]')) {
            e.preventDefault();
        }
    }, true);

    function isPrintScreenKey(e) {
        return e.key === 'PrintScreen' || e.code === 'PrintScreen' || e.keyCode === 44;
    }

    function applyDocumentBlur() {
        document.querySelectorAll('[data-doc-viewport]').forEach(function (vp) {
            var inner = vp.querySelector('.doc-viewport-inner');
            var shield = vp.querySelector('.doc-screenshot-shield');
            if (inner) {
                inner.classList.add('doc-viewport--blurred');
            }
            if (shield) {
                shield.classList.remove('hidden', 'opacity-0', 'pointer-events-none');
                shield.classList.add('doc-screenshot-shield--active');
                shield.setAttribute('aria-hidden', 'false');
            }
        });
    }

    function clearDocumentBlur() {
        document.querySelectorAll('[data-doc-viewport]').forEach(function (vp) {
            var inner = vp.querySelector('.doc-viewport-inner');
            var shield = vp.querySelector('.doc-screenshot-shield');
            if (inner) {
                inner.classList.remove('doc-viewport--blurred');
            }
            if (shield) {
                shield.classList.add('hidden', 'opacity-0', 'pointer-events-none');
                shield.classList.remove('doc-screenshot-shield--active');
                shield.setAttribute('aria-hidden', 'true');
            }
        });
    }

    function onPossiblePrintScreen(e) {
        if (!isPrintScreenKey(e)) {
            return;
        }
        if (!document.querySelector('[data-doc-viewport]')) {
            return;
        }
        try {
            e.preventDefault();
        } catch (_x) {}
        applyDocumentBlur();
    }

    function initPrintScreenGuard() {
        if (!document.querySelector('[data-doc-viewport]')) {
            return;
        }
        window.addEventListener('keydown', onPossiblePrintScreen, true);
        window.addEventListener('keyup', onPossiblePrintScreen, true);
        document.addEventListener('click', function (e) {
            if (e.target && e.target.closest && e.target.closest('.doc-screenshot-restore')) {
                e.preventDefault();
                clearDocumentBlur();
            }
        });
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initPrintScreenGuard);
    } else {
        initPrintScreenGuard();
    }
})();
</script>
