/**
 * Liste documents : retour visuel pendant l’application des filtres (navigation pleine page).
 */
(function () {
    var form = document.querySelector('[data-doc-catalog-form]');
    var root = document.getElementById('doc-catalog-root');
    var skel = document.getElementById('doc-catalog-skeleton');
    if (!form || !root || !skel) {
        return;
    }
    form.addEventListener('submit', function () {
        root.classList.add('hidden');
        skel.classList.remove('hidden');
    });
})();
