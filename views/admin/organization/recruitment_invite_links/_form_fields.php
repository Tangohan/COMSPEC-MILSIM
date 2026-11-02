<?php
declare(strict_types=1);

/**
 * Champs communs à la création et à la modification d'un lien d'invitation.
 *
 * @var array<string, string> $linkForm valeurs actuelles (label, custom_slug, link_action, registration_mode, opening_id,
 *   unit_id, priority_code_id, grade_id, requires_approval, expiry, expires_at, max_uses)
 * @var bool $linkFormIsEdit
 * @var string $linkFormCurrentExpiry échéance actuelle lisible (modification)
 */

use App\Services\Recruitment\InviteLinkService;

$h = static fn (string $v): string => htmlspecialchars($v, ENT_QUOTES, 'UTF-8');
$f = is_array($linkForm ?? null) ? $linkForm : [];
$v = static fn (string $k, string $d = ''): string => (string) ($f[$k] ?? $d);
$isEdit = (bool) ($linkFormIsEdit ?? false);
$openings = is_array($recruitmentOpenings ?? null) ? $recruitmentOpenings : [];
$units = is_array($inviteLinkUnits ?? null) ? $inviteLinkUnits : [];
$grades = is_array($inviteLinkGrades ?? null) ? $inviteLinkGrades : [];
$priorityCodes = is_array($inviteLinkPriorityCodes ?? null) ? $inviteLinkPriorityCodes : [];
$canDirectJoin = (bool) ($inviteLinkCanDirectJoin ?? false);
$customSlugAllowed = (bool) ($inviteLinkCustomSlug ?? false);
$action = $v('link_action', 'apply') === 'join' ? 'join' : 'apply';
$expiry = $v('expiry', $isEdit ? 'keep' : '7d');
$sel = static fn (bool $on): string => $on ? ' selected' : '';
?>
<div class="ath-form__grid">
    <label class="ath-field">
        <span class="ath-field__label">Nom interne *</span>
        <input type="text" name="label" value="<?= $h($v('label')) ?>" maxlength="180" required class="ath-field__input" placeholder="Recrutement Discord · novembre">
        <span class="ath-field__help">Pour retrouver le lien dans la liste et ses statistiques ; jamais montré aux invités.</span>
    </label>
    <?php if (!$isEdit): ?>
    <label class="ath-field">
        <span class="ath-field__label">Adresse personnalisée</span>
        <input type="text" name="custom_slug" value="<?= $h($v('custom_slug')) ?>" maxlength="40" class="ath-field__input" placeholder="<?= $customSlugAllowed ? 'tfj' : 'Formules payantes' ?>"
               pattern="[a-z0-9][a-z0-9\-]{1,38}[a-z0-9]" title="3 à 40 caractères : lettres minuscules, chiffres et tirets"<?= $customSlugAllowed ? '' : ' disabled' ?>>
        <span class="ath-field__help"><?= $customSlugAllowed
            ? 'Par exemple « tfj » donne ' . $h(url('i/tfj')) . '. Laissez vide pour une adresse générée, impossible à deviner. Non modifiable ensuite.'
            : 'Réservé aux formules payantes : une adresse impossible à deviner est générée.' ?></span>
    </label>
    <?php endif; ?>
</div>

<fieldset class="ath-form__grid" style="margin-top:14px;border:0;padding:0;">
    <legend class="ath-field__label" style="margin-bottom:6px;">Ce que fait le lien *</legend>
    <label class="ath-check">
        <input type="radio" name="link_action" value="apply"<?= $action === 'apply' ? ' checked' : '' ?> data-link-action>
        <span><strong>Candidater</strong> : ouvre le formulaire de candidature de la communauté, déjà rempli pour cette invitation</span>
    </label>
    <label class="ath-check">
        <input type="radio" name="link_action" value="join"<?= $action === 'join' ? ' checked' : '' ?> data-link-action>
        <span><strong>Rejoindre</strong> : la personne devient membre<?= $canDirectJoin ? ', tout de suite ou après validation' : ' après validation de l’équipe' ?></span>
    </label>
</fieldset>

<div class="ath-form__grid" style="margin-top:14px;" data-link-section="apply"<?= $action === 'apply' ? '' : ' hidden' ?>>
    <label class="ath-field">
        <span class="ath-field__label">Type de candidature</span>
        <select name="registration_mode" class="ath-field__select">
            <option value="">Celui de la communauté</option>
            <option value="milsim"<?= $sel($v('registration_mode') === 'milsim') ?>>Dossier MilSim complet</option>
            <option value="simple"<?= $sel($v('registration_mode') === 'simple') ?>>Formulaire court</option>
            <option value="discord"<?= $sel($v('registration_mode') === 'discord') ?>>Recrutement via Discord</option>
        </select>
        <span class="ath-field__help">Formulaire présenté aux personnes qui arrivent par ce lien.</span>
    </label>
    <label class="ath-field">
        <span class="ath-field__label">Code prioritaire appliqué</span>
        <select name="priority_code_id" class="ath-field__select">
            <option value="">Aucun</option>
            <?php foreach ($priorityCodes as $pc): ?>
            <option value="<?= (int) $pc['id'] ?>"<?= $sel((int) $v('priority_code_id', '0') === (int) $pc['id']) ?>><?= $h((string) $pc['label']) ?></option>
            <?php endforeach; ?>
        </select>
        <span class="ath-field__help">Accélère les candidatures comme un code prioritaire saisi à la main.</span>
    </label>
</div>

<div class="ath-form__grid" style="margin-top:14px;" data-link-section="join"<?= $action === 'join' ? '' : ' hidden' ?>>
    <?php if ($canDirectJoin): ?>
    <label class="ath-field">
        <span class="ath-field__label">Grade à l’arrivée</span>
        <select name="grade_id" class="ath-field__select">
            <option value="">Grade d’entrée des recrues</option>
            <?php foreach ($grades as $g): ?>
                <?php $gid = (int) ($g['id'] ?? 0); ?>
            <option value="<?= $gid ?>"<?= $sel((int) $v('grade_id', '0') === $gid) ?>><?= $h((string) ($g['label_long'] ?? $g['label_short'] ?? '')) ?></option>
            <?php endforeach; ?>
        </select>
        <span class="ath-field__help">Par défaut, le grade le plus bas des militaires du rang.</span>
    </label>
    <div class="ath-field">
        <span class="ath-field__label">Validation</span>
        <label class="ath-check">
            <input type="checkbox" name="requires_approval" value="1"<?= $v('requires_approval', '0') === '1' ? ' checked' : '' ?>>
            <span>Approbation requise : la demande arrive dans la file du recrutement</span>
        </label>
        <span class="ath-field__help">Décoché, la personne rejoint la communauté dès qu’elle clique sur « Rejoindre ».</span>
    </div>
    <?php else: ?>
    <p class="ath-field__help">Votre rôle ne permet pas d’ajouter des membres directement : chaque demande d’adhésion sera validée par l’équipe dans la file du recrutement.</p>
    <?php endif; ?>
</div>

<div class="ath-form__grid" style="margin-top:14px;">
    <label class="ath-field">
        <span class="ath-field__label">Offre de recrutement</span>
        <select name="opening_id" class="ath-field__select">
            <option value="">Aucune</option>
            <?php foreach ($openings as $o): ?>
                <?php $oid = (int) ($o['id'] ?? 0); ?>
            <option value="<?= $oid ?>"<?= $sel((int) $v('opening_id', '0') === $oid) ?>><?= $h((string) ($o['title'] ?? 'Sans titre')) ?></option>
            <?php endforeach; ?>
        </select>
        <span class="ath-field__help">Le dossier est rattaché à cette offre publiée.</span>
    </label>
    <label class="ath-field">
        <span class="ath-field__label">Unité</span>
        <select name="unit_id" class="ath-field__select">
            <option value="">Aucune</option>
            <?php foreach ($units as $u): ?>
                <?php $uid = (int) ($u['id'] ?? 0); ?>
            <option value="<?= $uid ?>"<?= $sel((int) $v('unit_id', '0') === $uid) ?>><?= $h((string) ($u['name'] ?? '')) ?></option>
            <?php endforeach; ?>
        </select>
        <span class="ath-field__help">Candidature : unité visée, notée dans le dossier. Adhésion directe : unité d’affectation.</span>
    </label>
</div>

<div class="ath-form__grid" style="margin-top:14px;">
    <label class="ath-field">
        <span class="ath-field__label">Expire après</span>
        <select name="expiry" class="ath-field__select" data-link-expiry>
            <?php if ($isEdit): ?>
            <option value="keep"<?= $sel($expiry === 'keep') ?>>Garder l’échéance actuelle (<?= $h((string) ($linkFormCurrentExpiry ?? '')) ?>)</option>
            <?php endif; ?>
            <?php foreach (array_keys(InviteLinkService::EXPIRY_PRESETS) as $preset): ?>
            <option value="<?= $h($preset) ?>"<?= $sel($expiry === $preset) ?>><?= $h(InviteLinkService::presetLabel($preset)) ?></option>
            <?php endforeach; ?>
        </select>
        <span class="ath-field__help">Compté à partir de l’enregistrement.</span>
    </label>
    <label class="ath-field" data-link-expiry-date<?= $expiry === 'date' ? '' : ' hidden' ?>>
        <span class="ath-field__label">Date d’expiration</span>
        <input type="datetime-local" name="expires_at" value="<?= $h($v('expires_at')) ?>" class="ath-field__input">
    </label>
    <label class="ath-field">
        <span class="ath-field__label">Nombre maximum d’utilisations</span>
        <input type="number" name="max_uses" min="1" max="100000" value="<?= $h($v('max_uses')) ?>" class="ath-field__input" placeholder="Illimité">
        <span class="ath-field__help">Une utilisation = une candidature déposée ou une adhésion. Vide = illimité.</span>
    </label>
</div>

<script>
(function () {
    var form = document.currentScript.closest('form');
    if (!form) { return; }
    function sync() {
        var checked = form.querySelector('[data-link-action]:checked');
        var action = checked ? checked.value : 'apply';
        form.querySelectorAll('[data-link-section]').forEach(function (el) { el.hidden = el.getAttribute('data-link-section') !== action; });
        var exp = form.querySelector('[data-link-expiry]');
        var date = form.querySelector('[data-link-expiry-date]');
        if (exp && date) { date.hidden = exp.value !== 'date'; }
    }
    form.addEventListener('change', sync);
    sync();
})();
</script>
