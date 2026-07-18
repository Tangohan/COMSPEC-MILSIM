<?php
declare(strict_types=1);

use App\Core\Csrf;
use App\Core\Session;

/** @var array<string, string> $gdprRequestKinds */
$gdprRequestKinds = $gdprRequestKinds ?? [];
$privacyInboxConfigured = !empty($privacyInboxConfigured);

$error = Session::getFlash('error');
$success = Session::getFlash('success');
$mailto = legal_public_contact_email();
$legalSite = url('legal/site');
?>
<h1>Exercer vos droits sur vos données</h1>
<p class="legal-updated">Demande relative à vos données personnelles</p>

<p>
    Envoyez une demande relative à vos données personnelles (accès, rectification, effacement, opposition, limitation, portabilité, retrait du consentement).
    Nous vous répondrons sur l’adresse e-mail que vous indiquez ci-dessous.
</p>
<p>
    Pour faciliter le traitement, décrivez votre demande de manière précise.
    Une vérification d’identité peut être demandée lorsque nécessaire.
</p>

<?php if ($error): ?>
    <div class="legal-callout legal-callout-warn" role="alert"><?= htmlspecialchars((string) $error, ENT_QUOTES, 'UTF-8') ?></div>
<?php endif; ?>
<?php if ($success): ?>
    <div class="legal-callout legal-callout-tip" role="status"><?= htmlspecialchars((string) $success, ENT_QUOTES, 'UTF-8') ?></div>
<?php endif; ?>

<?php if (!$privacyInboxConfigured): ?>
    <div class="legal-callout legal-callout-warn">
        <strong>Envoi en ligne non configuré</strong> —
        L’administrateur du site n’a pas encore renseigné la boîte de réception des demandes. Vous pouvez écrire directement
        <?php if ($mailto !== null): ?>
            à <a href="mailto:<?= htmlspecialchars($mailto, ENT_QUOTES, 'UTF-8') ?>?subject=<?= rawurlencode('Demande relative à mes données personnelles') ?>"><?= htmlspecialchars($mailto, ENT_QUOTES, 'UTF-8') ?></a>
        <?php else: ?>
            aux coordonnées publiées dans les <a href="<?= htmlspecialchars($legalSite, ENT_QUOTES, 'UTF-8') ?>#mentions">mentions légales</a>
        <?php endif; ?>
        ou contacter les administrateurs de votre communauté.
    </div>
<?php endif; ?>

<form method="post" action="<?= htmlspecialchars(url('demande-donnees'), ENT_QUOTES, 'UTF-8') ?>" class="legal-form-shell">
    <?= Csrf::field() ?>

    <div style="position:absolute;left:-9999px;width:1px;height:1px;overflow:hidden" aria-hidden="true">
        <label for="company_website">Laissez ce champ vide</label>
        <input type="text" name="company_website" id="company_website" value="" tabindex="-1" autocomplete="off">
    </div>

    <div style="margin-bottom:1.25rem">
        <label for="request_kind">Type de demande</label>
        <select name="request_kind" id="request_kind" required>
            <option value="" disabled selected>Choisissez…</option>
            <?php foreach ($gdprRequestKinds as $key => $label): ?>
                <option value="<?= htmlspecialchars($key, ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars($label, ENT_QUOTES, 'UTF-8') ?></option>
            <?php endforeach; ?>
        </select>
    </div>

    <div style="margin-bottom:1.25rem">
        <label for="from_email">Adresse e-mail de réponse</label>
        <input type="email" name="from_email" id="from_email" required maxlength="254" autocomplete="email" placeholder="vous@exemple.com">
    </div>

    <div style="margin-bottom:1.25rem">
        <label for="full_name">Nom ou pseudonyme utilisé sur le portail <span style="font-weight:400;color:var(--legal-muted)">(facultatif)</span></label>
        <input type="text" name="full_name" id="full_name" maxlength="160" autocomplete="name">
    </div>

    <div style="margin-bottom:1.25rem">
        <label for="community_hint">Communauté concernée <span style="font-weight:400;color:var(--legal-muted)">(facultatif)</span></label>
        <input type="text" name="community_hint" id="community_hint" maxlength="200" placeholder="Nom de l’unité ou indice pour vous identifier">
    </div>

    <div style="margin-bottom:1.5rem">
        <label for="message">Votre demande</label>
        <textarea name="message" id="message" required rows="6" maxlength="4000" minlength="10" placeholder="Décrivez précisément ce que vous souhaitez (par exemple : obtenir une copie de vos données, corriger une information, supprimer votre compte…)."></textarea>
        <p style="margin:.5rem 0 0;font-size:.75rem;color:var(--legal-muted)">Minimum 10 caractères, maximum 4&nbsp;000.</p>
    </div>

    <button type="submit" class="legal-btn" <?= $privacyInboxConfigured ? '' : 'disabled style="opacity:.4;cursor:not-allowed"' ?>>
        Envoyer la demande
    </button>
</form>

<p style="margin-top:2rem;font-size:.8125rem;color:var(--legal-muted);line-height:1.65">
    Pour rappel : les administrateurs de votre communauté peuvent aussi traiter certaines demandes qui ne concernent que l’activité interne de cette communauté.
    En cas d’urgence liée à la sécurité de votre compte, changez votre mot de passe et contactez le support selon les
    <a href="<?= htmlspecialchars($legalSite, ENT_QUOTES, 'UTF-8') ?>#mentions">mentions légales</a>.
    Les demandes sont traitées dans les délais prévus par la réglementation, sous réserve de la complexité et du volume des informations demandées.
</p>
