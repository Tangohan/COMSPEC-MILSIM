<?php
declare(strict_types=1);

$ssEndpoint = url('api/community/report');
$ssCsrf = \App\Core\Csrf::token();
?>
<form
    class="dash-rail__anomaly"
    data-site-support-form
    data-site-support-endpoint="<?= htmlspecialchars($ssEndpoint, ENT_QUOTES, 'UTF-8') ?>"
    data-site-support-csrf="<?= htmlspecialchars($ssCsrf, ENT_QUOTES, 'UTF-8') ?>"
>
    <p class="dash-rail__anomaly-lead">
        Problème technique, compte fantôme, dossier RH ou autre demande transversale : le message part vers
        l’administration du site, pas vers la gestion interne de votre organisation.
    </p>
    <label class="dash-rail__anomaly-label">
        Type de demande
        <select name="help_subject" required>
            <option value="">Choisir…</option>
            <option value="compte_fantome">Compte supprimé encore visible</option>
            <option value="dysfonctionnement">Dysfonctionnement technique</option>
            <option value="rh">Problème RH ou dossier personnel</option>
            <option value="droits">Droits, rôles ou accès plateforme</option>
            <option value="abonnement">Formule ou facturation</option>
            <option value="donnees">Données, synchronisation ou export</option>
            <option value="autre">Autre demande à l’administration</option>
        </select>
    </label>
    <label class="dash-rail__anomaly-label">
        Repère utile <span>(optionnel)</span>
        <input type="text" name="reference_note" maxlength="500" autocomplete="off" placeholder="Compte, page, manœuvre, référence dossier…">
    </label>
    <label class="dash-rail__anomaly-label">
        Description
        <textarea name="details" rows="5" maxlength="2000" required placeholder="Expliquez le problème ou la demande, et ce que l’administration du site devrait vérifier."></textarea>
    </label>
    <p class="dash-rail__anomaly-status" data-site-support-status hidden></p>
    <button type="submit" class="dash-rail__anomaly-submit">Transmettre à l’administration du site</button>
</form>
