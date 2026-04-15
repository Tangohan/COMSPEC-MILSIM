/**
 * Active la confirmation de charte après lecture (fin du bloc scrollable).
 */
(function () {
    var box = document.getElementById('hr-charter-scroll');
    var chk = document.getElementById('hr-charter-confirm');
    var btn = document.getElementById('hr-charter-submit');
    var hint = document.getElementById('hr-charter-hint');
    if (!box || !chk || !btn) return;

    function nearBottom(el, slack) {
        slack = slack || 8;
        return el.scrollHeight - el.scrollTop - el.clientHeight <= slack;
    }

    function refresh() {
        var ok = nearBottom(box);
        chk.disabled = !ok;
        btn.disabled = !ok || !chk.checked;
        if (hint) {
            hint.textContent = ok
                ? 'Vous pouvez cocher la case et valider.'
                : 'Faites défiler le texte jusqu’en bas pour activer la case.';
        }
    }

    box.addEventListener('scroll', refresh, { passive: true });
    chk.addEventListener('change', refresh);
    window.addEventListener('resize', refresh);
    refresh();
    if (nearBottom(box)) {
        chk.disabled = false;
        refresh();
    }
})();
