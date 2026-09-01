<?php
declare(strict_types=1);

$oaEndpoint = url('api/community/report');
$oaCsrf = \App\Core\Csrf::token();
?>
<form
    class="dash-rail__anomaly"
    data-org-anomaly-form
    data-org-anomaly-endpoint="<?= htmlspecialchars($oaEndpoint, ENT_QUOTES, 'UTF-8') ?>"
    data-org-anomaly-csrf="<?= htmlspecialchars($oaCsrf, ENT_QUOTES, 'UTF-8') ?>"
>
    <p class="dash-rail__anomaly-lead">
        Décrivez tout dysfonctionnement, erreur ou irrégularité. Le message part vers la gestion de l’organisation, pas vers le forum.
    </p>
    <label class="dash-rail__anomaly-label">
        Nature de l’anomalie
        <select name="help_subject" required>
            <option value="">Choisir…</option>
            <option value="fiche">Fiche, grade ou unité</option>
            <option value="compte">Compte, connexion ou droits</option>
            <option value="planning">Planning, manœuvres ou événements</option>
            <option value="formation">Formations</option>
            <option value="documents">Documents</option>
            <option value="atak">Carte et liaisons</option>
            <option value="acces">Accès à un espace</option>
            <option value="autre">Autre</option>
        </select>
    </label>
    <label class="dash-rail__anomaly-label">
        Repère utile <span>(optionnel)</span>
        <input type="text" name="reference_note" maxlength="500" autocomplete="off" placeholder="Page, nom, horaire, manœuvre…">
    </label>
    <label class="dash-rail__anomaly-label">
        Description
        <textarea name="details" rows="5" maxlength="2000" required placeholder="Expliquez ce qui ne va pas, et ce que la gestion devrait constater."></textarea>
    </label>
    <p class="dash-rail__anomaly-status" data-org-anomaly-status hidden></p>
    <button type="submit" class="dash-rail__anomaly-submit">Transmettre à la gestion</button>
</form>
