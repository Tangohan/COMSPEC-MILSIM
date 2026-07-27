<?php
declare(strict_types=1);

/** @var array $tenant */
/** @var array<string, mixed> $community */
/** @var array<string, mixed> $branding */
/** @var string $logoUrl */
/** @var array<string, mixed> $setupAnalysis */
/** @var list<array{slug: string, name: string}> $roleOptions */
/** @var string $defaultGuestRoleSlug */

$c = $community ?? [];
$analysis = is_array($setupAnalysis ?? null) ? $setupAnalysis : [];
$items = is_array($analysis['items'] ?? null) ? $analysis['items'] : [];
$optional = is_array($analysis['optional'] ?? null) ? $analysis['optional'] : [];
$percent = (int) ($analysis['percent'] ?? 0);
$done = (int) ($analysis['done'] ?? 0);
$total = (int) ($analysis['total'] ?? 0);
$completed = !empty($analysis['completed']);
$roles = is_array($roleOptions ?? null) ? $roleOptions : [];
$guestSlug = (string) ($defaultGuestRoleSlug ?? 'invite');
$logo = trim((string) ($logoUrl ?? ''));
$pm = is_array($c['public_modules'] ?? null) ? $c['public_modules'] : [];
$registrationMode = \App\Services\Community\TenantCommunityProfileService::normalizeRegistrationMode($c['registration_mode'] ?? 'milsim');
$registrationLabel = \App\Services\Community\TenantCommunityProfileService::registrationModeLabel($registrationMode);
$slugHint = trim((string) ($tenant['slug'] ?? ''));
$publicPageUrl = $slugHint !== '' ? url('c/' . rawurlencode($slugHint)) : '';
$communityName = trim((string) ($tenant['name'] ?? ''));
$contactEmail = trim((string) ($c['contact_email'] ?? ''));
$discordUrl = trim((string) ($c['contact_discord_url'] ?? ''));
$welcomeText = trim((string) ($c['welcome_text'] ?? ''));
$discordLinkMissing = $registrationMode === 'discord' && $discordUrl === '';
$discordInviteMissing = \App\Services\Community\TenantCommunityProfileService::needsDiscordInviteAlert($c);

$setupTenantType = \App\Services\Community\TenantTypeConfig::normalizeType((string) ($tenant['tenant_type'] ?? 'full'));
$setupTypeMeta = \App\Services\Community\TenantTypeConfig::availableTypes()[$setupTenantType] ?? [];
$setupTypeLabel = (string) ($setupTypeMeta['label'] ?? \App\Services\Community\TenantTypeConfig::label($setupTenantType));
$setupTypeDesc = (string) ($setupTypeMeta['description'] ?? '');

$guestRoleLabel = '—';
foreach ($roles as $role) {
    if ((string) ($role['slug'] ?? '') === $guestSlug) {
        $guestRoleLabel = (string) ($role['name'] ?? $guestSlug);
        break;
    }
}

$clipStatus = static function (string $value, int $max = 36): string {
    $value = trim($value);
    if ($value === '') {
        return '—';
    }
    if (mb_strlen($value) <= $max) {
        return $value;
    }

    return mb_substr($value, 0, $max - 1) . '…';
};

$statusRows = [
    [
        'label' => 'Nom affiché',
        'help' => 'Visible sur la page publique et le portail.',
        'value' => $communityName !== '' ? $clipStatus($communityName) : '—',
        'ok' => $communityName !== '',
    ],
    [
        'label' => 'Adresse publique',
        'help' => 'Adresse courte de la vitrine consultable sans connexion.',
        'value' => $slugHint !== '' ? $clipStatus($slugHint, 28) : '—',
        'ok' => $slugHint !== '',
    ],
    [
        'label' => 'E-mail de contact',
        'help' => 'Affiché aux candidats et visiteurs.',
        'value' => $contactEmail !== '' ? $clipStatus($contactEmail, 28) : '—',
        'ok' => $contactEmail !== '',
    ],
    [
        'label' => 'Lien Discord',
        'help' => $registrationMode === 'discord'
            ? 'Obligatoire pour le recrutement via Discord.'
            : 'Facultatif — utile pour orienter les candidats.',
        'value' => $discordUrl !== '' ? 'Renseigné' : '—',
        'ok' => $discordUrl !== '',
        'warn' => $discordLinkMissing,
    ],
    [
        'label' => 'Mode d’inscription',
        'help' => 'Comment les candidats rejoignent votre communauté.',
        'value' => $clipStatus($registrationLabel, 28),
        'ok' => true,
    ],
    [
        'label' => 'Rôle d’accueil',
        'help' => 'Attribué automatiquement à l’arrivée.',
        'value' => $clipStatus($guestRoleLabel, 24),
        'ok' => $roles !== [] && $guestRoleLabel !== '—',
    ],
    [
        'label' => 'Profil de communauté',
        'help' => 'Détermine les modules et menus activés.',
        'value' => $setupTypeLabel,
        'ok' => true,
    ],
    [
        'label' => 'Logo',
        'help' => 'Apparait sur la page publique et dans le portail.',
        'value' => $logo !== '' ? 'Défini' : '—',
        'ok' => $logo !== '',
    ],
];

$missingPriority = [];
if ($contactEmail === '') {
    $missingPriority[] = 'e-mail de contact';
}
if ($discordLinkMissing) {
    $missingPriority[] = 'lien Discord';
}
$showPriority = $missingPriority !== [];

$err = \App\Core\Session::getFlash('error');
$ok = \App\Core\Session::getFlash('success');
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
<div class="bo-setup">

    <?php if ($err): ?>
        <div class="bo-settings-flash bo-settings-flash--err" role="alert"><?= $h((string) $err) ?></div>
    <?php endif; ?>
    <?php if ($ok): ?>
        <div class="bo-settings-flash bo-settings-flash--ok" role="status"><?= $h((string) $ok) ?></div>
    <?php endif; ?>
    <?php if ($discordInviteMissing): ?>
        <div class="bo-settings-flash bo-settings-flash--warn" role="alert">
            Le recrutement via Discord est actif, mais aucun lien d’invitation n’est renseigné.
            Les candidats ne pourront pas ouvrir votre serveur depuis le formulaire public.
            <a href="#contact">Renseigner le lien Discord</a>
        </div>
    <?php endif; ?>

    <div class="bo-setup__overview">
        <section class="ath-card ath-rise bo-setup__main" aria-labelledby="bo-setup-checklist-title">
            <p class="bo-setup__kicker" id="bo-setup-checklist-title">Checklist des éléments essentiels</p>
            <div class="bo-setup__chips" aria-label="État des éléments essentiels">
                <?php foreach ($items as $label => $isDone): ?>
                    <span class="bo-setup__chip <?= $isDone ? 'bo-setup__chip--ok' : 'bo-setup__chip--warn' ?>">
                        <span aria-hidden="true"><?= $isDone ? '✓' : '!' ?></span><?= $h((string) $label) ?>
                    </span>
                <?php endforeach; ?>
                <?php foreach ($optional as $label => $isDone): ?>
                    <span class="bo-setup__chip bo-setup__chip--opt <?= $isDone ? 'is-done' : '' ?>">
                        <span aria-hidden="true"><?= $isDone ? '✓' : '·' ?></span><?= $h((string) $label) ?>
                        <span class="bo-setup__chip-opt">(optionnel)</span>
                    </span>
                <?php endforeach; ?>
            </div>
            <div class="bo-setup__status-list" aria-label="Synthèse des réglages">
                <?php foreach ($statusRows as $row): ?>
                    <?php
                    $rowOk = !empty($row['ok']);
                    $rowWarn = !empty($row['warn']) || (!$rowOk && !empty($row['value']) && $row['value'] === '—');
                    $valueClass = $rowOk ? 'is-ok' : ($rowWarn ? 'is-warn' : '');
                    ?>
                    <div class="bo-setup__status-row">
                        <div class="bo-setup__status-copy">
                            <div class="bo-setup__status-label"><?= $h((string) $row['label']) ?></div>
                            <div class="bo-setup__status-help"><?= $h((string) $row['help']) ?></div>
                        </div>
                        <div class="bo-setup__status-value <?= $h($valueClass) ?>"><?= $h((string) $row['value']) ?></div>
                    </div>
                <?php endforeach; ?>
            </div>
        </section>

        <aside class="bo-setup__aside">
            <div class="ath-panel-dark ath-rise bo-setup__progress">
                <p class="ath-panel-dark__kicker">Profil renseigné</p>
                <p class="bo-setup__progress-value"><?= $percent ?> %</p>
                <p class="bo-setup__progress-meta"><?= $done ?> / <?= $total ?> élément<?= $total > 1 ? 's' : '' ?> essentiel<?= $total > 1 ? 's' : '' ?></p>
                <div class="bo-setup__progress-bar" role="progressbar" aria-valuemin="0" aria-valuemax="100" aria-valuenow="<?= max(0, min(100, $percent)) ?>">
                    <div class="bo-setup__progress-fill" style="width:<?= max(0, min(100, $percent)) ?>%"></div>
                </div>
            </div>

            <?php if ($showPriority): ?>
                <div class="ath-banner-warn ath-rise" role="status">
                    <p class="ath-banner-warn__kicker">À compléter en priorité</p>
                    <p class="ath-banner-warn__text">
                        <?php if (count($missingPriority) === 2): ?>
                            Sans e-mail de contact ni lien Discord, les candidats ne peuvent ni vous joindre ni rejoindre le serveur : le recrutement reste bloqué.
                        <?php elseif ($missingPriority[0] === 'lien Discord'): ?>
                            Sans lien Discord, les candidats ne pourront pas rejoindre votre serveur depuis le formulaire public.
                        <?php else: ?>
                            Sans e-mail de contact, les candidats et visiteurs ne peuvent pas vous joindre facilement.
                        <?php endif; ?>
                    </p>
                </div>
            <?php elseif ($percent >= 100 && !$completed): ?>
                <div class="ath-banner-warn ath-rise" role="status" style="background:#f2fbf7;border-color:#b8e6d0;">
                    <p class="ath-banner-warn__kicker" style="color:#0b6b47;">Prêt à terminer</p>
                    <p class="ath-banner-warn__text" style="color:#0b6b47;">
                        Les éléments essentiels sont renseignés. Vous pouvez encore ajuster les détails ci-dessous, puis terminer pour accéder pleinement au back-office.
                    </p>
                </div>
            <?php endif; ?>

            <section class="ath-card ath-rise" style="padding:16px 18px;">
                <p class="bo-setup__kicker">Profil de communauté</p>
                <p class="bo-setup__type-title"><?= $h($setupTypeLabel) ?></p>
                <?php if ($setupTypeDesc !== ''): ?>
                    <p class="bo-setup__type-desc"><?= $h($setupTypeDesc) ?></p>
                <?php endif; ?>
                <div class="bo-setup__type-action">
                    <a href="<?= $h(url('back-office/organisation/parametres') . '#profil') ?>" class="ath-btn">Modifier le type de communauté</a>
                </div>
            </section>
        </aside>
    </div>

    <form method="post" action="<?= $h(url('back-office/configuration-initiale')) ?>" enctype="multipart/form-data" class="bo-setup__form" id="initial-setup-form">
        <?= \App\Core\Csrf::field() ?>

        <div class="bo-settings-grid">
            <section class="ath-card ath-rise bo-setting-group" id="identite">
                <p class="bo-setting-group__kicker">Identité</p>
                <h2 class="bo-setting-group__title">Identité visuelle</h2>
                <div class="bo-setting-group__rows">
                    <div class="bo-setting-row">
                        <div class="bo-setting-row__copy">
                            <div class="bo-setting-row__label">Logo</div>
                            <div class="bo-setting-row__help">JPG, PNG ou WebP · 12 Mo maximum. Visible sur la page publique et dans le portail.</div>
                        </div>
                        <div class="bo-setting-row__control">
                            <?php if ($logo !== ''): ?>
                                <img src="<?= $h($logo) ?>" alt="" class="bo-setting-thumb">
                            <?php endif; ?>
                            <span class="bo-setting-row__value"><?= $logo !== '' ? 'Défini' : '—' ?></span>
                            <input id="org_logo" type="file" name="org_logo" class="bo-setting-file" accept="image/jpeg,image/png,image/webp,.jpg,.jpeg,.png,.webp" <?= $slugHint === '' ? 'disabled' : '' ?>>
                        </div>
                    </div>
                    <?php if ($logo !== ''): ?>
                        <label class="bo-setting-remove">
                            <input type="hidden" name="remove_org_logo" value="0">
                            <input type="checkbox" name="remove_org_logo" value="1">
                            Retirer le logo actuel
                        </label>
                    <?php endif; ?>
                    <div class="bo-setting-row bo-setting-row--stack">
                        <div class="bo-setting-row__copy">
                            <div class="bo-setting-row__label">Message d’accueil</div>
                            <div class="bo-setting-row__help">Texte court affiché aux visiteurs sur la page publique.</div>
                        </div>
                        <div class="bo-setting-row__control">
                            <textarea id="welcome_text" name="welcome_text" rows="4" maxlength="500" class="bo-setting-row__field--wide" placeholder="Présentez votre unité en quelques phrases…"><?= $h($welcomeText) ?></textarea>
                        </div>
                    </div>
                </div>
            </section>

            <section class="ath-card ath-rise bo-setting-group" id="contact">
                <p class="bo-setting-group__kicker">Contact</p>
                <h2 class="bo-setting-group__title">Coordonnées</h2>
                <div class="bo-setting-group__rows">
                    <div class="bo-setting-row bo-setting-row--stack">
                        <div class="bo-setting-row__copy">
                            <div class="bo-setting-row__label">E-mail de contact</div>
                            <div class="bo-setting-row__help">Affiché aux candidats et visiteurs.</div>
                        </div>
                        <div class="bo-setting-row__control">
                            <input id="contact_email" type="email" name="contact_email" class="bo-setting-row__field--wide" value="<?= $h($contactEmail) ?>" maxlength="255" placeholder="contact@votre-unite.fr" autocomplete="email">
                        </div>
                    </div>
                    <div class="bo-setting-row bo-setting-row--stack">
                        <div class="bo-setting-row__copy">
                            <div class="bo-setting-row__label">
                                Lien Discord
                                <?php if ($registrationMode === 'discord'): ?>
                                    <span style="color:#a32222;">*</span>
                                <?php else: ?>
                                    <span style="font-weight:600;color:var(--ath-subtle)">(optionnel)</span>
                                <?php endif; ?>
                            </div>
                            <div class="bo-setting-row__help">
                                <?php if ($discordLinkMissing): ?>
                                    Obligatoire pour le recrutement via Discord — sans ce lien, les candidats ne pourront pas ouvrir votre serveur.
                                <?php else: ?>
                                    Invitation vers votre serveur, affichée aux candidats si besoin.
                                <?php endif; ?>
                            </div>
                        </div>
                        <div class="bo-setting-row__control">
                            <input id="contact_discord_url" type="url" name="contact_discord_url" class="bo-setting-row__field--wide" value="<?= $h($discordUrl) ?>" maxlength="500" placeholder="https://discord.gg/…" <?= $discordLinkMissing ? 'aria-invalid="true"' : '' ?>>
                        </div>
                    </div>
                </div>
            </section>

            <section class="ath-card ath-rise bo-setting-group" id="inscription">
                <p class="bo-setting-group__kicker">Inscription</p>
                <h2 class="bo-setting-group__title">Recrutement</h2>
                <div class="bo-setting-group__rows">
                    <div class="bo-setting-row bo-setting-row--stack">
                        <div class="bo-setting-row__copy">
                            <div class="bo-setting-row__label">Mode du formulaire de candidature</div>
                            <div class="bo-setting-row__help">Choisissez le niveau de détail demandé aux candidats.</div>
                        </div>
                        <div class="bo-setting-row__control">
                            <select id="registration_mode" name="registration_mode" class="bo-setting-row__field--wide">
                                <option value="milsim" <?= $registrationMode === 'milsim' ? 'selected' : '' ?>>MilSim complet (dossier détaillé)</option>
                                <option value="simple" <?= $registrationMode === 'simple' ? 'selected' : '' ?>>Mode simple (champs réduits)</option>
                                <option value="discord" <?= $registrationMode === 'discord' ? 'selected' : '' ?>>Recrutement via Discord (pseudo et questions personnalisées)</option>
                            </select>
                        </div>
                    </div>
                    <?php if ($registrationMode === 'discord'): ?>
                        <p class="bo-settings-note" style="margin-top:0;">
                            <?= $discordLinkMissing
                                ? 'Renseignez le lien Discord ci-dessus avant de terminer — il est affiché aux candidats sur le formulaire public.'
                                : 'Pensez à composer les questions dans le bureau recrutement après cette étape.' ?>
                        </p>
                    <?php endif; ?>
                    <?php $renderToggle(
                        'Fermer le recrutement',
                        'Les nouvelles candidatures ne sont plus acceptées.',
                        'community_locked',
                        !empty($c['community_locked']),
                        'Fermé',
                        'Ouvert'
                    ); ?>
                    <?php $renderToggle(
                        'Accusé de réception des règles',
                        'Exiger la confirmation des règles avant le dépôt d’une candidature.',
                        'require_ai_ack',
                        !array_key_exists('require_ai_ack', $c) || !empty($c['require_ai_ack']),
                        'Exigé',
                        'Non exigé'
                    ); ?>
                    <?php $renderToggle(
                        'Badge « recrutement ouvert »',
                        'Affiche un indicateur sur la fiche publique de la communauté.',
                        'public_recruitment_badge_open',
                        !empty($c['public_recruitment_badge_open']),
                        'Affiché',
                        'Masqué'
                    ); ?>
                </div>
            </section>

            <section class="ath-card ath-rise bo-setting-group" id="modules">
                <p class="bo-setting-group__kicker">Vitrine</p>
                <h2 class="bo-setting-group__title">Modules visibles sur la page publique</h2>
                <div class="bo-setting-group__rows">
                    <?php
                    $modLabels = [
                        'forum' => 'Forum',
                        'documents' => 'Documents',
                        'events' => 'Événements',
                        'roster' => 'Effectifs',
                        'training' => 'Formations',
                        'analytics' => 'Indicateurs',
                    ];
                    foreach ($modLabels as $modKey => $modLabel):
                        $renderToggle(
                            'Module « ' . $modLabel . ' »',
                            'Affiche ce module sur la page de présentation publique.',
                            'public_mod_' . $modKey,
                            !empty($pm[$modKey]),
                            'Visible',
                            'Masqué'
                        );
                    endforeach;
                    ?>
                </div>
            </section>
        </div>

        <section class="ath-card ath-rise bo-setting-group" id="roles">
            <div style="display:flex;flex-wrap:wrap;align-items:flex-start;justify-content:space-between;gap:12px;">
                <div>
                    <p class="bo-setting-group__kicker">Rôles</p>
                    <h2 class="bo-setting-group__title">Accueil des nouveaux inscrits</h2>
                    <p class="bo-setting-row__help" style="margin-top:6px;max-width:40rem;">
                        <?= count($roles) ?> rôle<?= count($roles) > 1 ? 's' : '' ?> déjà créé<?= count($roles) > 1 ? 's' : '' ?> pour votre communauté.
                        Choisissez le rôle attribué aux nouveaux inscrits.
                    </p>
                </div>
                <a href="<?= $h(url('back-office/roles')) ?>" class="ath-btn">Gérer les rôles</a>
            </div>
            <?php if ($roles !== []): ?>
                <ul class="bo-setup__role-chips">
                    <?php foreach (array_slice($roles, 0, 12) as $role): ?>
                        <li class="bo-setup__role-chip"><?= $h((string) ($role['name'] ?? '')) ?></li>
                    <?php endforeach; ?>
                    <?php if (count($roles) > 12): ?>
                        <li class="bo-setup__role-chip">+<?= count($roles) - 12 ?></li>
                    <?php endif; ?>
                </ul>
                <div class="bo-setting-group__rows">
                    <div class="bo-setting-row bo-setting-row--stack">
                        <div class="bo-setting-row__copy">
                            <div class="bo-setting-row__label">Rôle attribué aux nouveaux inscrits</div>
                            <div class="bo-setting-row__help">Appliqué automatiquement à l’arrivée d’un invité.</div>
                        </div>
                        <div class="bo-setting-row__control">
                            <select id="default_guest_role_slug" name="default_guest_role_slug" class="bo-setting-row__field--wide">
                                <?php foreach ($roles as $role): ?>
                                    <?php $rs = (string) ($role['slug'] ?? ''); ?>
                                    <option value="<?= $h($rs) ?>" <?= $guestSlug === $rs ? 'selected' : '' ?>><?= $h((string) ($role['name'] ?? $rs)) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                </div>
                <p class="bo-settings-note">
                    Pour appliquer un kit de permissions prêt à l’emploi,
                    <a href="<?= $h(url('back-office/roles/presets')) ?>">ouvrez les modèles de rôles</a>.
                </p>
            <?php else: ?>
                <div class="bo-settings-flash bo-settings-flash--warn bo-setup__empty" role="status">
                    Aucun rôle n’a encore été créé. Passez par la gestion des rôles pour en définir.
                </div>
            <?php endif; ?>
        </section>

        <div class="bo-setup__actions" id="initial-setup-actions">
            <button type="submit" class="ath-btn ath-btn--solid">Enregistrer</button>
            <button type="submit" formaction="<?= $h(url('back-office/configuration-initiale/complete')) ?>" class="ath-btn ath-btn--accent" name="save_before_complete" value="1">Terminer et accéder au back-office</button>
            <?php if (!$completed): ?>
                <button type="submit" formaction="<?= $h(url('back-office/configuration-initiale/dismiss')) ?>" formnovalidate class="ath-btn" name="redirect_to" value="setup">Plus tard</button>
            <?php endif; ?>
            <a href="<?= $h(url('back-office')) ?>" class="ath-btn ath-btn--filter">Retour au back-office</a>
        </div>
    </form>

    <section class="ath-card ath-rise bo-setting-group">
        <p class="bo-setting-group__kicker">Suite</p>
        <h2 class="bo-setting-group__title">Pour aller plus loin</h2>
        <p class="bo-setting-row__help" style="margin-top:6px;">Ces étapes ne sont pas obligatoires ici — ouvrez-les quand vous êtes prêt.</p>
        <div class="bo-setup__more-grid">
            <a href="<?= $h(url('orbat')) ?>" class="bo-setup__more-link">
                <p class="bo-setup__more-title">Organisation (ORBAT)</p>
                <p class="bo-setup__more-desc">Affinez la structure des unités et des postes.</p>
            </a>
            <a href="<?= $h(url('back-office/invitations')) ?>" class="bo-setup__more-link">
                <p class="bo-setup__more-title">Invitations</p>
                <p class="bo-setup__more-desc">Invitez vos premiers membres par e-mail.</p>
            </a>
            <a href="<?= $h(url('back-office/community/presentation')) ?>" class="bo-setup__more-link">
                <p class="bo-setup__more-title">Vitrine &amp; candidature</p>
                <p class="bo-setup__more-desc">Textes détaillés de la page d’accueil publique.</p>
            </a>
        </div>
        <?php if ($publicPageUrl !== ''): ?>
            <p class="bo-settings-note">
                <a href="<?= $h($publicPageUrl) ?>" target="_blank" rel="noopener">Voir la page publique ↗</a>
            </p>
        <?php endif; ?>
    </section>
</div>
<script>
(function () {
    document.querySelectorAll('.ath-toggle__input[data-bo-toggle]').forEach(function (input) {
        var sync = function () {
            var label = document.querySelector('[data-bo-toggle-value="' + input.getAttribute('data-bo-toggle') + '"]');
            var wrap = input.closest('.ath-toggle');
            if (wrap) {
                wrap.classList.toggle('is-on', input.checked);
                wrap.classList.toggle('is-off', !input.checked);
            }
            if (label) {
                label.textContent = input.checked ? (input.getAttribute('data-on') || 'Actif') : (input.getAttribute('data-off') || 'Inactif');
            }
        };
        input.addEventListener('change', sync);
        sync();
    });
})();
</script>
