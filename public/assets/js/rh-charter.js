/**
 * Lecture de la charte : débloque la confirmation une fois le texte parcouru.
 */
(function () {
    var box = document.getElementById('hr-charter-scroll');
    var chk = document.getElementById('hr-charter-confirm');
    var btn = document.getElementById('hr-charter-submit');
    var hint = document.getElementById('hr-charter-hint');
    var fill = document.getElementById('hr-charter-progress-fill');
    var pctEl = document.getElementById('hr-charter-progress-pct');
    var docPanel = box ? box.closest('.hr-charter__doc') : null;
    var confirmPanel = document.querySelector('.hr-charter__confirm');
    if (!box || !chk || !btn) {
        return;
    }

    function innerScrollable(el) {
        return el.scrollHeight - el.clientHeight > 8;
    }

    function nearInnerBottom(el, slack) {
        slack = slack || 16;
        return el.scrollHeight - el.scrollTop - el.clientHeight <= slack;
    }

    function pageRevealedBottom(el, slack) {
        slack = slack || 28;
        return el.getBoundingClientRect().bottom <= window.innerHeight + slack;
    }

    function progress(el) {
        if (innerScrollable(el)) {
            var max = el.scrollHeight - el.clientHeight;
            return Math.max(0, Math.min(100, Math.round((el.scrollTop / max) * 100)));
        }
        var rect = el.getBoundingClientRect();
        if (rect.height <= 8) {
            return 100;
        }
        var seen = window.innerHeight - rect.top;
        return Math.max(0, Math.min(100, Math.round((seen / rect.height) * 100)));
    }

    function refresh() {
        var pct = progress(box);
        var ok = pct >= 98 || nearInnerBottom(box) || (!innerScrollable(box) && pageRevealedBottom(box));
        var needsJump = innerScrollable(box) ? !ok : !ok && !pageRevealedBottom(box, 80);
        chk.disabled = !ok;
        btn.disabled = !ok || !chk.checked;
        if (fill) {
            fill.style.width = pct + '%';
        }
        var bar = document.getElementById('hr-charter-progress');
        if (bar) {
            bar.setAttribute('aria-valuenow', String(pct));
        }
        if (pctEl) {
            pctEl.textContent = pct + ' %';
        }
        if (docPanel) {
            docPanel.classList.toggle('is-read', ok);
        }
        if (confirmPanel) {
            confirmPanel.classList.toggle('is-ready', ok);
        }
        if (hint) {
            hint.textContent = ok
                ? 'Vous pouvez cocher la case et enregistrer votre prise en compte.'
                : 'Faites défiler le texte jusqu’en bas pour activer la case.';
        }
        var jumpBtn = document.getElementById('hr-charter-jump');
        if (jumpBtn) {
            jumpBtn.hidden = !needsJump;
        }
    }

    box.addEventListener('scroll', refresh, { passive: true });
    window.addEventListener('scroll', refresh, { passive: true });
    chk.addEventListener('change', refresh);
    window.addEventListener('resize', refresh);
    var jump = document.getElementById('hr-charter-jump');
    if (jump) {
        jump.addEventListener('click', function () {
            if (innerScrollable(box)) {
                box.scrollTo({ top: box.scrollHeight, behavior: 'smooth' });
            } else {
                box.scrollIntoView({ block: 'end', behavior: 'smooth' });
            }
        });
    }
    refresh();
    window.addEventListener('load', refresh);
})();
