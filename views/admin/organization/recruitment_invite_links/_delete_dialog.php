<?php
declare(strict_types=1);

/**
 * Confirmation de suppression d'un lien d'invitation (fenêtre du kit back-office).
 * Ouverte par tout bouton [data-invite-link-delete="<adresse POST>"] (data-invite-link-label = nom du lien).
 */
?>
<dialog id="invite-link-delete-dialog" class="ath-dialog" aria-labelledby="invite-link-delete-title">
    <form method="post" action="#" class="ath-dialog__form" data-invite-link-delete-form>
        <?= \App\Core\Csrf::field() ?>
        <div class="ath-dialog__head ath-dialog__head--split">
            <div>
                <h2 class="ath-dialog__title" id="invite-link-delete-title">Supprimer le lien d’invitation</h2>
                <p class="ath-dialog__sub"><span translate="no" data-invite-link-delete-label></span></p>
            </div>
            <button type="button" class="ath-dialog__close" data-invite-link-delete-close aria-label="Fermer">✕</button>
        </div>
        <div class="ath-dialog__body">
            <ul>
                <li>Le lien cesse aussitôt de fonctionner : les personnes qui l’ouvrent voient qu’il a été supprimé.</li>
                <li>Les candidatures et adhésions déjà faites avec ce lien sont conservées.</li>
                <li>Les statistiques du lien (visites, inscriptions, usages) sont effacées.</li>
                <li>Le lien ne compte plus dans la limite de liens actifs de votre formule.</li>
                <li>Cette action est définitive. Pour suspendre le lien sans le perdre, révoquez-le plutôt.</li>
            </ul>
        </div>
        <div class="ath-dialog__foot">
            <button type="button" class="ath-btn" data-invite-link-delete-close>Annuler</button>
            <button type="submit" class="ath-btn ilx-btn-danger">Supprimer définitivement</button>
        </div>
    </form>
</dialog>
<script>
(function () {
    var dialog = document.getElementById('invite-link-delete-dialog');
    if (!dialog) { return; }
    var form = dialog.querySelector('[data-invite-link-delete-form]');
    var label = dialog.querySelector('[data-invite-link-delete-label]');
    document.addEventListener('click', function (event) {
        var btn = event.target.closest('[data-invite-link-delete]');
        if (btn) {
            event.preventDefault();
            form.setAttribute('action', btn.getAttribute('data-invite-link-delete'));
            label.textContent = btn.getAttribute('data-invite-link-label') || '';
            if (typeof dialog.showModal === 'function') { dialog.showModal(); } else if (window.confirm(dialog.querySelector('.ath-dialog__title').textContent)) { form.submit(); }
            return;
        }
        if (event.target.closest('[data-invite-link-delete-close]')) { dialog.close(); }
    });
})();
</script>
