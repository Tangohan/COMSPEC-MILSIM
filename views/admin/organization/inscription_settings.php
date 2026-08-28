<?php
declare(strict_types=1);

/** @var array $tenant */
/** @var array<string, mixed> $community */
/** @var list<array{slug: string, name: string}> $roleOptions */
/** @var string $defaultGuestRoleSlug */
/** @var string $inscriptionFormAction */

$c = $community ?? [];
$formAction = (string) ($inscriptionFormAction ?? url('back-office/community/inscription'));

$registrationMode = \App\Services\Community\TenantCommunityProfileService::normalizeRegistrationMode($c['registration_mode'] ?? 'milsim');
$registrationLabel = \App\Services\Community\TenantCommunityProfileService::registrationModeLabel($registrationMode);

$roles = is_array($roleOptions ?? null) ? $roleOptions : [];
$guestRoleSlug = trim((string) ($defaultGuestRoleSlug ?? ($c['default_guest_role_slug'] ?? 'invite')));
$guestRoleLabel = '—';
foreach ($roles as $role) {
    if (($role['slug'] ?? '') === $guestRoleSlug) {
        $guestRoleLabel = (string) ($role['name'] ?? $guestRoleSlug);
        break;
    }
}

$communityLocked = !empty($c['community_locked']);
$contactFormEnabled = !empty($c['contact_form_enabled']);
$requireAiAck = !array_key_exists('require_ai_ack', $c) || !empty($c['require_ai_ack']);
$refuseOtherCommunityMembers = !empty($c['refuse_other_community_members']);
$recruitmentBadgeOpen = !empty($c['public_recruitment_badge_open']);

$em = \App\Services\Community\EnlistmentMilsimPackService::forCommunity($c);
$availabilitySlotsSelected = is_array($em['availability_slots'] ?? null) ? $em['availability_slots'] : [];
$motivationData = is_array($em['motivation'] ?? null) ? $em['motivation'] : [];
$availabilityQ15 = trim((string) ($em['availability_q15'] ?? ''));

$discordInviteMissing = \App\Services\Community\TenantCommunityProfileService::needsDiscordInviteAlert($c);

$h = static fn (string $v): string => htmlspecialchars($v, ENT_QUOTES, 'UTF-8');

$renderToggle = static function (
    string $label,
    string $help,
    string $name,
    bool $checked,
    string $valueOn = 'Actif',
    string $valueOff = 'Inactif',
) use ($h): void {
    ?>
    <div class="bo-setting-row">
        <div class="bo-setting-row__copy">
            <div class="bo-setting-row__label"><?= $h($label) ?></div>
            <div class="bo-setting-row__help"><?= $h($help) ?></div>
        </div>
        <div class="bo-setting-row__control">
            <span class="bo-setting-row__value" data-bo-toggle-value="<?= $h($name) ?>"><?= $h($checked ? $valueOn : $valueOff) ?></span>
            <label class="ath-toggle <?= $checked ? 'is-on' : 'is-off' ?>">
                <input type="hidden" name="<?= $h($name) ?>" value="0">
                <input type="checkbox" class="ath-toggle__input" name="<?= $h($name) ?>" value="1" <?= $checked ? 'checked' : '' ?> data-bo-toggle="<?= $h($name) ?>" data-on="<?= $h($valueOn) ?>" data-off="<?= $h($valueOff) ?>">
                <span class="ath-toggle__knob" aria-hidden="true"></span>
            </label>
        </div>
    </div>
    <?php
};
?>
<div class="bo-community-settings">

    <?php if ($discordInviteMissing): ?>
        <?php
        $notice_tone = 'warning';
        $notice_title = 'Lien Discord manquant';
        $notice_body = 'Le recrutement via Discord est actif, mais aucun lien d\'invitation n\'est renseigné. '
            . 'Les candidats ne pourront pas ouvrir votre serveur depuis le formulaire public. '
            . '<a href="#coordonnees">Renseigner le lien Discord</a>';
        include base_path('views/partials/bo_dsfr_notice.php');
        ?>
    <?php endif; ?>

    <form method="post" action="<?= $h($formAction) ?>" id="bo-inscription-settings-form">
        <?= \App\Core\Csrf::field() ?>
        <input type="hidden" name="em_settings_enlistment_partial" value="1">

        <div class="bo-settings-grid">

            <section class="ath-card ath-rise bo-setting-group" id="parcours">
                <p class="bo-setting-group__kicker">Parcours</p>
                <h2 class="bo-setting-group__title">Arrivée des membres</h2>
                <div class="bo-setting-group__rows">
                    <div class="bo-setting-row bo-setting-row--stack">
                        <div class="bo-setting-row__copy">
                            <div class="bo-setting-row__label">Mode d’inscription</div>
                            <div class="bo-setting-row__help">Détermine le parcours de candidature proposé aux visiteurs.</div>
                        </div>
                        <div class="bo-setting-row__control">
                            <select id="registration_mode" name="registration_mode" class="bo-setting-row__field--wide">
                                <option value="milsim" <?= $registrationMode === 'milsim' ? 'selected' : '' ?>>Dossier MilSim complet</option>
                                <option value="simple" <?= $registrationMode === 'simple' ? 'selected' : '' ?>>Formulaire court</option>
                                <option value="discord" <?= $registrationMode === 'discord' ? 'selected' : '' ?>>Recrutement via Discord</option>
                            </select>
                        </div>
                    </div>
                    <div class="bo-setting-row bo-setting-row--stack">
                        <div class="bo-setting-row__copy">
                            <div class="bo-setting-row__label">Rôle d’accueil</div>
                            <div class="bo-setting-row__help">Attribué automatiquement au nouvel arrivant.</div>
                        </div>
                        <div class="bo-setting-row__control">
                            <select id="default_guest_role_slug" name="default_guest_role_slug" class="bo-setting-row__field--wide">
                                <?php if ($roles === []): ?>
                                    <option value="<?= $h($guestRoleSlug) ?>"><?= $h($guestRoleLabel) ?></option>
                                <?php else: ?>
                                    <?php foreach ($roles as $role): ?>
                                        <option value="<?= $h((string) $role['slug']) ?>" <?= ($role['slug'] ?? '') === $guestRoleSlug ? 'selected' : '' ?>><?= $h((string) $role['name']) ?></option>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </select>
                        </div>
                    </div>
                    <?php $renderToggle(
                        'Fermeture temporaire du recrutement',
                        'Suspend le dépôt de nouvelles candidatures.',
                        'community_locked',
                        $communityLocked,
                        'Fermée',
                        'Ouverte'
                    ); ?>
                    <?php $renderToggle(
                        'Accusé de réception des règles',
                        'Exige l’acceptation des règles avant dépôt d’une candidature.',
                        'require_ai_ack',
                        $requireAiAck,
                        'Exigé',
                        'Facultatif'
                    ); ?>
                    <?php $renderToggle(
                        'Refuser les comptes déjà rattachés à une autre communauté',
                        'Bloque les candidatures des comptes Athena déjà membres d’une autre communauté. Les visiteurs sans compte et les comptes sans communauté (uniquement l’espace « Pas d’organisation ») restent acceptés.',
                        'refuse_other_community_members',
                        $refuseOtherCommunityMembers,
                        'Refus activé',
                        'Autorisé'
                    ); ?>
                    <?php $renderToggle(
                        'Badge « recrutement ouvert »',
                        'Affiche un badge sur la fiche publique lorsque le recrutement est ouvert.',
                        'public_recruitment_badge_open',
                        $recruitmentBadgeOpen,
                        'Affiché',
                        'Masqué'
                    ); ?>
                </div>
                <p class="bo-settings-note">
                    Mode actuel : <strong><?= $h($registrationLabel) ?></strong>
                    <?php if ($registrationMode === 'discord'): ?>
                        · <a href="<?= $h(url('back-office/recruitments/discord-questions')) ?>">Configurer les questions Discord</a>
                    <?php endif; ?>
                </p>
            </section>

            <section class="ath-card ath-rise bo-setting-group" id="coordonnees">
                <p class="bo-setting-group__kicker">Contact</p>
                <h2 class="bo-setting-group__title">Coordonnées pour les candidats</h2>
                <div class="bo-setting-group__rows">
                    <div class="bo-setting-row bo-setting-row--stack">
                        <div class="bo-setting-row__copy">
                            <div class="bo-setting-row__label">E-mail de contact</div>
                            <div class="bo-setting-row__help">Affiché aux candidats et visiteurs.</div>
                        </div>
                        <div class="bo-setting-row__control">
                            <input type="email" id="contact_email" name="contact_email" class="bo-setting-row__field--wide" maxlength="255" value="<?= $h((string) ($c['contact_email'] ?? '')) ?>">
                        </div>
                    </div>
                    <div class="bo-setting-row bo-setting-row--stack">
                        <div class="bo-setting-row__copy">
                            <div class="bo-setting-row__label">Lien Discord<?= $registrationMode === 'discord' ? ' *' : '' ?></div>
                            <div class="bo-setting-row__help">Obligatoire pour le recrutement via Discord.</div>
                        </div>
                        <div class="bo-setting-row__control">
                            <input type="url" id="contact_discord_url" name="contact_discord_url" class="bo-setting-row__field--wide" maxlength="500" value="<?= $h((string) ($c['contact_discord_url'] ?? '')) ?>" placeholder="https://discord.gg/…">
                        </div>
                    </div>
                    <div class="bo-setting-row bo-setting-row--stack">
                        <div class="bo-setting-row__copy">
                            <div class="bo-setting-row__label">Message d’introduction contact</div>
                            <div class="bo-setting-row__help">Texte affiché au-dessus du formulaire de contact.</div>
                        </div>
                        <div class="bo-setting-row__control">
                            <input type="text" id="contact_intro" name="contact_intro" class="bo-setting-row__field--wide" maxlength="500" value="<?= $h((string) ($c['contact_intro'] ?? '')) ?>">
                        </div>
                    </div>
                    <?php $renderToggle(
                        'Formulaire « nous écrire »',
                        'Permet aux visiteurs d’envoyer un message depuis la fiche publique.',
                        'contact_form_enabled',
                        $contactFormEnabled,
                        'Actif',
                        'Inactif'
                    ); ?>
                </div>
            </section>

            <section class="ath-card ath-rise bo-setting-group" id="dossier">
                <p class="bo-setting-group__kicker">Dossier</p>
                <h2 class="bo-setting-group__title">Questions du formulaire public</h2>

                <div class="bo-setting-subblock" id="creneaux-disponibilite">
                    <p class="bo-setting-subblock__title">Créneaux de disponibilité</p>
                    <p class="bo-setting-subblock__help">Choisissez les créneaux proposés aux candidats sur le dossier de candidature. Si aucun n’est coché, le candidat pourra décrire sa disponibilité en texte libre.</p>
                    <div class="bo-setting-row bo-setting-row--stack" style="border-top:none;padding-top:0;">
                        <div class="bo-setting-row__copy">
                            <div class="bo-setting-row__label">Question de confirmation</div>
                            <div class="bo-setting-row__help">Texte affiché près des créneaux. Laissé vide, un libellé est proposé automatiquement si des créneaux sont cochés.</div>
                        </div>
                        <div class="bo-setting-row__control">
                            <input type="text" name="em_availability_q15" id="em_availability_q15" class="bo-setting-row__field--wide" maxlength="400" value="<?= $h($availabilityQ15) ?>" placeholder="Confirmez vos créneaux de disponibilité…">
                        </div>
                    </div>
                    <?php
                    $selectedSlots = $availabilitySlotsSelected;
                    $idsInputName = 'em_availability_slot_ids[]';
                    $customInputName = 'em_availability_slot_custom[]';
                    $configuredFlagName = '';
                    $formId = '';
                    include base_path('views/partials/availability_slots_editor.php');
                    ?>
                </div>

                <div class="bo-setting-subblock" id="section-motivation">
                    <p class="bo-setting-subblock__title">Section Motivation</p>
                    <p class="bo-setting-subblock__help">Personnalisez le bloc Motivation du formulaire public : titre, introduction, questions et caractère obligatoire.</p>
                    <?php
                    $inputPrefix = 'em_motivation';
                    $formAttr = '';
                    include base_path('views/partials/motivation_section_editor.php');
                    ?>
                </div>

                <p class="bo-settings-note">
                    Pour les textes détaillés, règles et champs avancés du dossier :
                    <a href="<?= $h(url('back-office/community/presentation') . '#pack-milsim-editor') ?>">Éditeur complet du dossier candidature</a>
                </p>
            </section>

        </div>

        <div class="bo-settings-save">
            <button type="submit" class="ath-btn ath-btn--solid">Enregistrer</button>
        </div>
    </form>

    <p class="bo-settings-note">
        Identité et vitrine :
        <a href="<?= $h(url('back-office/community')) ?>">Identité &amp; options</a>
        · <a href="<?= $h(url('back-office/community/presentation')) ?>">Page d’accueil publique</a>
        · <a href="<?= $h(url('back-office/configuration-initiale')) ?>">Assistant de démarrage</a>
    </p>
</div>
<script>
(function () {
    document.querySelectorAll('.ath-toggle__input[data-bo-toggle]').forEach(function (input) {
        var sync = function () {
            var label = document.querySelector('[data-bo-toggle-value="' + input.getAttribute('data-bo-toggle') + '"]');
            var wrap = input.closest('.ath-toggle');
            if (label) {
                label.textContent = input.checked ? (input.getAttribute('data-on') || 'Actif') : (input.getAttribute('data-off') || 'Inactif');
            }
            if (wrap) {
                wrap.classList.toggle('is-on', input.checked);
                wrap.classList.toggle('is-off', !input.checked);
            }
        };
        input.addEventListener('change', sync);
        sync();
    });
})();
</script>
