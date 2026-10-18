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

$settingsHubEmbed = !empty($settingsHubEmbedInscription);

$renderToggle = static function (
    string $label,
    string $help,
    string $name,
    bool $checked,
    string $effectOn = '',
    string $effectOff = '',
) use ($h): void {
    ?>
    <label class="bcs-switch">
        <input type="hidden" name="<?= $h($name) ?>" value="0">
        <input type="checkbox" class="bcs-switch__input" name="<?= $h($name) ?>" value="1" <?= $checked ? 'checked' : '' ?>>
        <span class="bcs-switch__track" aria-hidden="true"></span>
        <span class="bcs-switch__copy">
            <span class="bcs-switch__title"><?= $h($label) ?></span>
            <?php if ($help !== ''): ?><span class="bcs-switch__help"><?= $h($help) ?></span><?php endif; ?>
            <?php if ($effectOn !== ''): ?><span class="bcs-switch__effect bcs-switch__effect--on"><strong>Activé :</strong> <span><?= $h($effectOn) ?></span></span><?php endif; ?>
            <?php if ($effectOff !== ''): ?><span class="bcs-switch__effect bcs-switch__effect--off"><strong>Désactivé :</strong> <span><?= $h($effectOff) ?></span></span><?php endif; ?>
        </span>
    </label>
    <?php
};
?>
<?php if (!$settingsHubEmbed): ?>
<div class="bcs bo-community-settings">

    <?php if ($discordInviteMissing): ?>
        <div class="bcs-notice" role="status">
            <span aria-hidden="true">⚠</span>
            <span><strong>Lien Discord manquant.</strong> Le recrutement se fait via Discord, mais aucun lien d’invitation n’est renseigné : les candidats ne pourront pas rejoindre votre serveur. <a class="bcs-link" href="#coordonnees">Renseigner le lien Discord</a></span>
        </div>
    <?php endif; ?>
<?php endif; ?>

    <form method="post" action="<?= $h($formAction) ?>" id="bo-inscription-settings-form" class="bcs-panel">
        <?= \App\Core\Csrf::field() ?>
        <input type="hidden" name="em_settings_enlistment_partial" value="1">
        <input type="hidden" name="settings_tab" value="inscription">

            <section class="bcs-card" id="parcours">
                <div class="bcs-card__head">
                    <div class="bcs-card__heading">
                        <h3 class="bcs-card__title">Arrivée des candidats</h3>
                        <p class="bcs-card__desc">Le type de formulaire proposé, le rôle donné aux nouveaux et l’ouverture du recrutement.</p>
                    </div>
                </div>
                <div class="bcs-card__body">
                    <div class="bcs-field">
                        <label class="bcs-label" for="registration_mode">Type de candidature</label>
                        <p class="bcs-help"><em>Dossier MilSim complet</em> : questionnaire détaillé (expérience, disponibilités, motivation). <em>Formulaire court</em> : quelques champs, arrivée rapide. <em>Discord</em> : le candidat donne son pseudo et répond à vos questions, la suite se passe sur votre serveur.</p>
                        <select id="registration_mode" name="registration_mode" class="bcs-input--short">
                            <option value="milsim" <?= $registrationMode === 'milsim' ? 'selected' : '' ?>>Dossier MilSim complet</option>
                            <option value="simple" <?= $registrationMode === 'simple' ? 'selected' : '' ?>>Formulaire court</option>
                            <option value="discord" <?= $registrationMode === 'discord' ? 'selected' : '' ?>>Recrutement via Discord</option>
                        </select>
                        <?php if ($registrationMode === 'discord'): ?>
                            <p class="bcs-help">Les questions posées aux candidats Discord se règlent dans <a class="bcs-link" href="<?= $h(url('back-office/recruitments/discord-questions')) ?>">Questions Discord</a>.</p>
                        <?php endif; ?>
                    </div>
                    <div class="bcs-field">
                        <label class="bcs-label" for="default_guest_role_slug">Rôle donné aux nouveaux arrivants</label>
                        <p class="bcs-help">Attribué automatiquement à l’inscription. Choisissez un rôle aux droits limités (ex. « Invité » ou « Recrue ») : vous le changerez après validation.</p>
                        <select id="default_guest_role_slug" name="default_guest_role_slug" class="bcs-input--short">
                            <?php if ($roles === []): ?>
                                <option value="<?= $h($guestRoleSlug) ?>"><?= $h($guestRoleLabel) ?></option>
                            <?php else: ?>
                                <?php foreach ($roles as $role): ?>
                                    <option value="<?= $h((string) $role['slug']) ?>" <?= ($role['slug'] ?? '') === $guestRoleSlug ? 'selected' : '' ?>><?= $h((string) $role['name']) ?></option>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </select>
                    </div>
                    <?php $renderToggle(
                        'Fermer temporairement le recrutement',
                        'Utile pendant une campagne, une pause estivale ou si vous êtes complets.',
                        'community_locked',
                        $communityLocked,
                        'plus aucune candidature ne peut être déposée. Votre page publique reste visible.',
                        'les visiteurs peuvent candidater depuis votre page publique.'
                    ); ?>
                    <?php $renderToggle(
                        'Badge « Recrutement ouvert »',
                        'Un badge bien visible en haut de votre page publique.',
                        'public_recruitment_badge_open',
                        $recruitmentBadgeOpen,
                        'le badge s’affiche sur la page publique.',
                        'pas de badge (le bouton Candidater reste présent).'
                    ); ?>
                    <?php $renderToggle(
                        'Exiger l’attestation « rédigé sans IA »',
                        'Le candidat coche une case confirmant avoir écrit son dossier lui-même, sans assistance par IA.',
                        'require_ai_ack',
                        $requireAiAck,
                        'la case est obligatoire pour envoyer la candidature.',
                        'le dossier peut être envoyé sans cette attestation.'
                    ); ?>
                    <?php $renderToggle(
                        'Refuser les membres d’une autre communauté',
                        'Concerne uniquement les comptes Athena déjà rattachés à une autre communauté.',
                        'refuse_other_community_members',
                        $refuseOtherCommunityMembers,
                        'ces comptes ne peuvent pas candidater chez vous. Les visiteurs sans compte ou sans communauté restent acceptés.',
                        'tout le monde peut candidater, même un membre d’une autre unité.'
                    ); ?>
                </div>
            </section>

            <section class="bcs-card" id="coordonnees">
                <span id="contact"></span>
                <div class="bcs-card__head">
                    <div class="bcs-card__heading">
                        <h3 class="bcs-card__title">Contact pour les candidats</h3>
                        <p class="bcs-card__desc">Comment un visiteur peut vous poser une question avant de candidater.</p>
                    </div>
                </div>
                <div class="bcs-card__body">
                    <div class="bcs-grid">
                        <div class="bcs-field">
                            <label class="bcs-label" for="contact_email">E-mail de contact</label>
                            <p class="bcs-help">Affiché sur la page publique ; reçoit aussi les messages du formulaire « Nous écrire ».</p>
                            <input type="email" id="contact_email" name="contact_email" maxlength="255" value="<?= $h((string) ($c['contact_email'] ?? '')) ?>" placeholder="recrutement@mon-unite.fr">
                        </div>
                        <div class="bcs-field">
                            <label class="bcs-label" for="contact_discord_url">Lien d’invitation Discord<?= $registrationMode === 'discord' ? ' (obligatoire)' : '' ?></label>
                            <p class="bcs-help">Un lien qui n’expire pas, vers votre salon d’accueil.</p>
                            <input type="url" id="contact_discord_url" name="contact_discord_url" maxlength="500" value="<?= $h((string) ($c['contact_discord_url'] ?? '')) ?>" placeholder="https://discord.gg/…">
                        </div>
                    </div>
                    <div class="bcs-field">
                        <label class="bcs-label" for="contact_intro">Phrase d’introduction <span class="bcs-opt">(facultatif)</span></label>
                        <p class="bcs-help">Affichée au-dessus des moyens de contact.</p>
                        <input type="text" id="contact_intro" name="contact_intro" maxlength="500" value="<?= $h((string) ($c['contact_intro'] ?? '')) ?>" placeholder="Une question avant de candidater ? Écrivez-nous, on répond sous 48 h.">
                    </div>
                    <?php $renderToggle(
                        'Formulaire « Nous écrire »',
                        'Un petit formulaire de message sur votre page publique (nécessite l’e-mail ci-dessus).',
                        'contact_form_enabled',
                        $contactFormEnabled,
                        'les visiteurs peuvent vous envoyer un message, reçu sur l’e-mail de contact.',
                        'seuls l’e-mail et le Discord sont affichés.'
                    ); ?>
                </div>
            </section>

            <section class="bcs-card" id="dossier">
                <div class="bcs-card__head">
                    <div class="bcs-card__heading">
                        <h3 class="bcs-card__title">Questions du dossier</h3>
                        <p class="bcs-card__desc">Les deux réglages les plus utiles du formulaire de candidature. Le reste (champs, règles, textes) se règle dans l’éditeur complet.</p>
                    </div>
                </div>
                <div class="bcs-card__body">
                <div class="bo-setting-subblock bcs-sub" id="creneaux-disponibilite">
                    <p class="bcs-sub__title">Créneaux de disponibilité</p>
                    <p class="bcs-sub__help">Cochez les créneaux où vous jouez : le candidat indiquera lesquels lui conviennent. Si rien n’est coché, il décrira ses disponibilités en texte libre.</p>
                    <div class="bcs-field" style="border-top:0;padding-top:0;">
                        <label class="bcs-label" for="em_availability_q15">Question de confirmation <span class="bcs-opt">(facultatif)</span></label>
                        <p class="bcs-help">Affichée près des créneaux. Laissée vide, une formulation est proposée automatiquement.</p>
                        <input type="text" name="em_availability_q15" id="em_availability_q15" maxlength="400" value="<?= $h($availabilityQ15) ?>" placeholder="Confirmez vos créneaux de disponibilité…">
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

                <div class="bo-setting-subblock bcs-sub" id="section-motivation">
                    <p class="bcs-sub__title">Section Motivation</p>
                    <p class="bcs-sub__help">Le bloc où le candidat explique pourquoi il veut vous rejoindre : titre, introduction, questions et caractère obligatoire.</p>
                    <?php
                    $inputPrefix = 'em_motivation';
                    $formAttr = '';
                    include base_path('views/partials/motivation_section_editor.php');
                    ?>
                </div>
                </div>

                <p class="bcs-card__foot">
                    Textes détaillés, règles et champs avancés :
                    <a class="bcs-link" href="<?= $h(url('back-office/community/presentation') . '#pack-milsim-editor') ?>">éditeur complet du formulaire de candidature</a>
                </p>
            </section>

        <?php if (!$settingsHubEmbed): ?>
        <div class="bcs-savebar">
            <div class="bcs-savebar__copy">
                <p class="bcs-savebar__title">Enregistrer les modifications</p>
                <p class="bcs-savebar__hint">Parcours d’arrivée, contact des candidats et questions du dossier.</p>
            </div>
            <button type="submit" class="bcs-btn bcs-btn--primary">Enregistrer</button>
        </div>
        <?php endif; ?>
    </form>

    <?php if (!$settingsHubEmbed): ?>
    <p class="bcs-footer-links">
        Voir aussi :
        <a class="bcs-link" href="<?= $h(url('back-office/community')) ?>">Paramètres de la communauté</a>
        · <a class="bcs-link" href="<?= $h(url('back-office/community/presentation')) ?>">Page d’accueil publique</a>
        · <a class="bcs-link" href="<?= $h(url('back-office/configuration-initiale')) ?>">Assistant de démarrage</a>
    </p>
</div>
    <?php endif; ?>
