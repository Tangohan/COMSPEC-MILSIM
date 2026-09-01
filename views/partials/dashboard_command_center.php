<?php
declare(strict_types=1);

/**
 * Athena Command — tableau de bord membre densifié (ops) + aside pliable.
 *
 * @var string|null $dashboard_tenant_label
 * @var array<string,mixed>|null $mission_briefing
 * @var array<string,mixed>|null $modpack
 * @var string|null $atakModDownloadUrl
 * @var bool $can_view_atak_operators
 * @var int|null $atak_operators_linked_count
 * @var list<array<string,mixed>> $my_enlistments_pending
 * @var list<array<string,mixed>> $staff_enlistments_pending
 * @var list<array<string,mixed>> $my_applications_all
 * @var list<array<string,mixed>> $staff_applications_all
 * @var bool $show_staff_enlistments
 * @var list<array<string,mixed>> $dashboard_pins
 * @var list<array<string,mixed>> $dashboard_announce_items
 * @var list<array<string,mixed>> $showcase_items
 * @var bool $showcase_training_feature
 * @var array<string,mixed>|null $dashboard_tester_program
 * @var list<array<string,mixed>> $communityMemberships
 * @var bool $show_founder_trial_banner
 * @var string|null $founder_trial_ends_at
 * @var array<string,mixed>|null $currentUser
 * @var list<array<string,mixed>> $dashboard_effectifs_rows
 * @var bool $can_view_personnel_directory
 * @var bool $can_open_effectifs_workspace
 * @var bool $dashboard_is_default_tenant
 * @var int $currentTid
 * @var string|null $arma_playtime_label
 */

$currentTid = (int) ($currentTid ?? \App\Core\Session::get('tenant_id') ?? 0);

$mb = is_array($mission_briefing ?? null) ? $mission_briefing : null;
$mbOp = $mb['next_op'] ?? null;
$mbOps = is_array($mb['upcoming_ops'] ?? null) ? $mb['upcoming_ops'] : [];
if ($mbOps === [] && is_array($mbOp)) {
    $mbOps = [$mbOp];
}
$mbTrain = is_array($mb['trainings'] ?? null) ? $mb['trainings'] : [];
$mbExcerpt = $mb['consigne_excerpt'] ?? null;
$mbPinsA = $mb['pins_anchor_href'] ?? (url('dashboard') . '#dashboard-community-pins');
$mbModpack = is_array($mb['modpack'] ?? null) ? $mb['modpack'] : null;
$monthsFr = ['JAN', 'FÉV', 'MAR', 'AVR', 'MAI', 'JUN', 'JUL', 'AOÛ', 'SEP', 'OCT', 'NOV', 'DÉC'];

$staffPending = $staff_enlistments_pending ?? [];
$myPending = $my_enlistments_pending ?? [];
$showStaff = !empty($show_staff_enlistments);
$pins = $dashboard_pins ?? [];
$trainCount = count($mbTrain);
$staffCount = $showStaff ? count($staffPending) : 0;
$opsCount = count($mbOps);
$myCount = count($myPending);
$hasEnlistments = $myPending !== [] || ($showStaff && $staffPending !== []);

$unitLabel = ($dashboard_tenant_label !== null && $dashboard_tenant_label !== '')
    ? (string) $dashboard_tenant_label
    : 'Communauté';

$todayLabel = date('d/m/Y');
if (class_exists(\IntlDateFormatter::class)) {
    $fmtFr = new \IntlDateFormatter('fr_FR', \IntlDateFormatter::FULL, \IntlDateFormatter::NONE);
    $fmtFr->setPattern('EEEE d MMMM yyyy');
    $formatted = $fmtFr->format(new \DateTimeImmutable('now'));
    if (is_string($formatted) && $formatted !== '') {
        $todayLabel = mb_convert_case($formatted, MB_CASE_TITLE, 'UTF-8');
    }
}

$nextOpDays = null;
if (is_array($mbOp) && !empty($mbOp['starts_at'])) {
    $ts = strtotime((string) $mbOp['starts_at']);
    if ($ts !== false) {
        $nextOpDays = max(0, (int) floor(($ts - time()) / 86400));
    }
}

$cu = $currentUser ?? null;
$displayName = $cu ? (string) ($cu['display_name'] ?? $cu['email'] ?? 'Opérateur') : 'Opérateur';
$roleHint = 'Opérateur';
$gr = $grade ?? null;
if (is_array($gr)) {
    $roleHint = (string) ($gr['label_short'] ?? $gr['short_name'] ?? $gr['label_long'] ?? $gr['name'] ?? 'Opérateur');
}

$modpack = $modpack ?? null;
$atakModDownloadUrl = $atakModDownloadUrl ?? null;
$hasPack = !empty($mbModpack['has_pack']) || (is_array($modpack) && !empty($modpack['id']));
$canViewAtakOperators = !empty($can_view_atak_operators);
$atakOperatorsLinkedCount = isset($atak_operators_linked_count) && $atak_operators_linked_count !== null
    ? (int) $atak_operators_linked_count
    : null;

$pe = $personnelExtras ?? null;
$matricule = is_array($pe) ? ($pe['service_number'] ?? null) : null;
$matriculeLabel = $matricule ? ('Matricule ' . (string) $matricule) : 'Matricule non attribué';
$statut = $cu ? (string) ($cu['status'] ?? '') : '';
$statutLabel = match ($statut) {
    'active' => 'Opérationnel',
    'pending_verification' => 'Vérification e-mail',
    'inactive' => 'Inactif',
    'suspended' => 'Suspendu',
    '' => '—',
    default => 'Compte',
};
$statutBadgeClass = match ($statut) {
    'active' => 'cc-badge--live-solid',
    'pending_verification' => 'cc-badge--sky-solid',
    default => 'cc-badge--ink',
};

$platformRole = '';
if (is_array($cu) && !empty($cu['role_name'])) {
    $platformRole = trim((string) $cu['role_name']);
}
if ($platformRole === '' && function_exists('portal_header_context')) {
    try {
        $phCtx = portal_header_context();
        $platformRole = trim((string) ($phCtx['role_label'] ?? ''));
    } catch (\Throwable) {
        $platformRole = '';
    }
}

$identityParts = [$roleHint];
if ($platformRole !== '' && strcasecmp($platformRole, $roleHint) !== 0) {
    $identityParts[] = $platformRole;
}
$identityParts[] = $matriculeLabel;
$identityParts[] = $unitLabel;
$identityParts[] = $statutLabel;
$identityParts[] = $todayLabel;
$identityLine = implode(' · ', $identityParts);

$heroCopyBits = [];
if (is_array($mbOp) && !empty($mbOp['title'])) {
    $opTitle = (string) $mbOp['title'];
    if ($nextOpDays !== null) {
        $heroCopyBits[] = $nextOpDays === 0
            ? ('Manœuvre du jour : ' . $opTitle)
            : ('Prochaine manœuvre dans ' . $nextOpDays . ' j — ' . $opTitle);
    } else {
        $heroCopyBits[] = 'Prochaine manœuvre : ' . $opTitle;
    }
} elseif ($opsCount === 0) {
    $heroCopyBits[] = 'Aucune manœuvre planifiée';
}
if ($trainCount > 0) {
    $topTrain = $mbTrain[0] ?? null;
    $trainPct = is_array($topTrain) && isset($topTrain['progress_pct'])
        ? max(0, min(100, (int) $topTrain['progress_pct']))
        : null;
    $trainBit = $trainCount === 1
        ? '1 formation en cours'
        : ($trainCount . ' formations en cours');
    if ($trainPct !== null) {
        $trainBit .= ' (' . $trainPct . ' %)';
    }
    $heroCopyBits[] = $trainBit;
} else {
    $heroCopyBits[] = 'Aucune formation ouverte';
}
if ($showStaff && $staffCount > 0) {
    $heroCopyBits[] = $staffCount === 1
        ? '1 candidature à traiter'
        : ($staffCount . ' candidatures à traiter');
}
if ($myCount > 0) {
    $heroCopyBits[] = $myCount === 1
        ? '1 dossier en attente'
        : ($myCount . ' dossiers en attente');
}
if ($mbExcerpt !== null && $mbExcerpt !== '') {
    $excerptShort = trim((string) $mbExcerpt);
    if (function_exists('mb_strlen') && mb_strlen($excerptShort) > 90) {
        $excerptShort = mb_substr($excerptShort, 0, 87) . '…';
    } elseif (strlen($excerptShort) > 90) {
        $excerptShort = substr($excerptShort, 0, 87) . '…';
    }
    $heroCopyBits[] = 'Consigne : ' . $excerptShort;
}
$heroCopy = $heroCopyBits !== []
    ? implode(' · ', $heroCopyBits)
    : ('Situation du ' . $todayLabel . ' — aucune alerte opérationnelle.');

$initials = function_exists('user_display_initials')
    ? user_display_initials($displayName)
    : mb_strtoupper(mb_substr(preg_replace('/\s+/', '', $displayName) ?: 'A', 0, 1));
$dashPp = is_array($personnelProfile ?? null) ? $personnelProfile : null;
$dashDisplaySettings = null;
if (is_array($cu)) {
    try {
        $dashDisplaySettings = \App\Core\Container::get(\App\Repositories\UserProfileDisplaySettingsRepository::class)
            ->getOrDefaults((int) $cu['id']);
    } catch (\Throwable) {
        $dashDisplaySettings = ['site_photo_priority' => 'operator'];
    }
}
$avatarSrc = function_exists('user_site_avatar_url')
    ? user_site_avatar_url(is_array($cu) ? $cu : null, $dashPp, is_array($dashDisplaySettings) ? $dashDisplaySettings : null)
    : (function_exists('user_media_public_url')
        ? user_media_public_url(is_array($cu) ? ($cu['avatar_url'] ?? null) : null)
        : null);
$heroImageRel = 'assets/images/hero-explosion.jpg';
if (!is_file(base_path('public/' . $heroImageRel))) {
    $heroImageRel = 'assets/images/fog-team.jpg';
}
$heroImageUrl = asset_url($heroImageRel);
$heroHasImage = is_file(base_path('public/' . $heroImageRel));

$showFounderTrialBanner = $show_founder_trial_banner ?? false;
$founderTrialEndsAt = $founder_trial_ends_at ?? null;
$dashCtxCommunity = count($communityMemberships ?? []) > 0;
$dashCtxTrial = $showFounderTrialBanner && is_string($founderTrialEndsAt) && $founderTrialEndsAt !== '';
$activeDashNav = 'overview';

// --- Identité enrichie pour la tuile « Mon compte » de l'aside ---
$athenaIdentifier = is_array($cu) ? trim((string) ($cu['athena_identifier'] ?? '')) : '';
$candidateDossierNumber = isset($candidate_dossier_number) && $candidate_dossier_number !== null ? (int) $candidate_dossier_number : null;
$canManageInvitationsAside = !empty($can_manage_invitations);
$pendingInvitationsCountAside = (int) ($pending_invitations_count ?? 0);
$emailAlertsDisabledCountAside = (int) ($email_alerts_disabled_count ?? 0);

$memberSinceRaw = is_array($pe) ? ($pe['date_of_enlistment'] ?? null) : null;
if (!$memberSinceRaw && is_array($cu)) {
    $memberSinceRaw = $cu['created_at'] ?? null;
}
$memberSinceLabel = '—';
$memberSinceDateLabel = null;
if ($memberSinceRaw) {
    $tsSince = strtotime((string) $memberSinceRaw);
    if ($tsSince !== false) {
        $memberSinceDateLabel = date('d/m/Y', $tsSince);
        $daysSince = max(0, (int) floor((time() - $tsSince) / 86400));
        $yearsSince = intdiv($daysSince, 365);
        $monthsSince = intdiv($daysSince % 365, 30);
        if ($yearsSince > 0) {
            $memberSinceLabel = $yearsSince . ' an' . ($yearsSince > 1 ? 's' : '') . ($monthsSince > 0 ? ' et ' . $monthsSince . ' mois' : '');
        } elseif ($monthsSince > 0) {
            $memberSinceLabel = $monthsSince . ' mois';
        } else {
            $memberSinceLabel = $daysSince . ' jour' . ($daysSince > 1 ? 's' : '');
        }
    }
}

// Données situation tactique (carte + modal)
$tactSizeFormatted = '—';
$tactUpdatedAt = '—';
$tactDetailUrl = url('modpacks');
$tactDownloadUrl = null;
$tactPackTitle = 'Modpack communautaire';
$tactPackVersion = '—';
if (is_array($modpack) && !empty($modpack['id'])) {
    if (!empty($modpack['size'])) {
        $b = (int) $modpack['size'];
        $tactSizeFormatted = $b >= 1073741824
            ? number_format($b / 1073741824, 1, ',', ' ') . ' Go'
            : ($b >= 1048576 ? number_format($b / 1048576, 1, ',', ' ') . ' Mo' : number_format($b / 1024, 1, ',', ' ') . ' Ko');
    }
    $tactUpdatedAt = !empty($modpack['updated_at']) ? date('d.m.y', strtotime((string) $modpack['updated_at'])) : '—';
    $tactDetailUrl = !empty($mbModpack['detail_href'])
        ? (string) $mbModpack['detail_href']
        : (!empty($modpack['slug']) ? url('modpacks/' . rawurlencode((string) $modpack['slug'])) : url('modpacks'));
    $tactDownloadUrl = url('modpacks/' . (int) $modpack['id'] . '/download');
    $tactPackTitle = !empty($mbModpack['title']) ? (string) $mbModpack['title'] : (string) ($modpack['name'] ?? $modpack['title'] ?? 'Modpack');
    $tactPackVersion = (string) ($modpack['version'] ?? '—');
}
?>
<div class="dash-cc dash-cc--rail">
    <?php require base_path('views/partials/dashboard_aside.php'); ?>

    <div class="dash-cc__main" x-data="{
        tacticalOpen: false,
        calendarOpen: false
    }">
        <?php
        $athena_header_skip_banners = true;
        require base_path('views/partials/header_dashboard.php');
        require base_path('views/partials/dashboard_idstrip.php');
        require base_path('views/partials/navbar_info_banners.php');
        ?>

        <!-- Hero sombre (réf. Caverne) — catalogue immédiatement après -->
        <section class="dash-hero" aria-labelledby="dash-hero-title">
            <div class="dash-hero__shell">
                <h1 id="dash-hero-title" class="dash-hero__title">Dashboard</h1>

                <div class="dash-hero__media<?= $heroHasImage ? '' : ' dash-hero__media--fallback' ?>">
                    <?php if ($heroHasImage): ?>
                        <img
                            src="<?= htmlspecialchars($heroImageUrl, ENT_QUOTES, 'UTF-8') ?>"
                            alt="Visuel du tableau de bord"
                            class="dash-hero__img"
                            width="1600"
                            height="720"
                            decoding="async"
                            fetchpriority="high"
                            data-img-fallback="hero"
                            data-img-label="Visuel de présentation indisponible"
                        >
                    <?php endif; ?>
                    <div class="dash-hero__veil" aria-hidden="true"></div>
                </div>

                <div class="dash-hero__grid">
                    <div class="dash-hero__col dash-hero__col--label">
                        <p class="dash-hero__label">Votre briefing</p>
                        <p class="dash-hero__unit"><?= htmlspecialchars($unitLabel, ENT_QUOTES, 'UTF-8') ?></p>
                    </div>
                    <div class="dash-hero__col dash-hero__col--identity">
                        <div class="dash-hero__person">
                            <?php
                            $class = 'dash-hero__avatar' . ($avatarSrc ? ' dash-hero__avatar--photo' : '');
                            $imgClass = 'dash-hero__avatar-img';
                            $alt = '';
                            require base_path('views/partials/ui/user_avatar.php');
                            ?>
                            <span class="dash-hero__person-meta">
                                <strong><?= htmlspecialchars($displayName, ENT_QUOTES, 'UTF-8') ?></strong>
                                <em><?= htmlspecialchars($roleHint, ENT_QUOTES, 'UTF-8') ?></em>
                                <span class="dash-hero__person-facts">
                                    <?= htmlspecialchars($matriculeLabel, ENT_QUOTES, 'UTF-8') ?>
                                    <?php if ($platformRole !== ''): ?>
                                        · <?= htmlspecialchars($platformRole, ENT_QUOTES, 'UTF-8') ?>
                                    <?php endif; ?>
                                    · <?= htmlspecialchars($statutLabel, ENT_QUOTES, 'UTF-8') ?>
                                    <?php
                                    $selfPlaytime = trim((string) ($arma_playtime_label ?? ''));
                                    if ($selfPlaytime !== ''):
                                    ?>
                                        · Temps en mission <?= htmlspecialchars($selfPlaytime, ENT_QUOTES, 'UTF-8') ?>
                                    <?php endif; ?>
                                </span>
                            </span>
                        </div>
                    </div>
                    <div class="dash-hero__col dash-hero__col--copy">
                        <p class="dash-hero__copy"><?= htmlspecialchars($heroCopy, ENT_QUOTES, 'UTF-8') ?></p>
                    </div>
                </div>
            </div>
        </section>

        <?php
        $dashSteamId = \App\Support\SteamId::normalize(is_array($cu) ? (string) ($cu['steam_id'] ?? '') : '');
        $dashSteamLinked = $dashSteamId !== null;
        ?>
        <section class="dash-steam-tile" id="connexion-steam" aria-labelledby="dash-steam-title">
            <div class="dash-steam-tile__shell">
                <a class="dash-steam-tile__open" href="<?= htmlspecialchars(url('account/steam/connect'), ENT_QUOTES, 'UTF-8') ?>">
                    <span class="dash-steam-tile__kicker">Liaison</span>
                    <strong id="dash-steam-title" class="dash-steam-tile__title"><?= $dashSteamLinked ? 'Steam associé' : 'Connexion Steam' ?></strong>
                    <em class="dash-steam-tile__hint">
                        <?php if ($dashSteamLinked): ?>
                            Votre compte Steam est lié. En jeu, Overwatch peut vous reconnaître. Cliquez pour changer de compte Steam.
                        <?php else: ?>
                            Associez votre compte Steam pour être reconnu en jeu. Steam s’ouvre, vous vous connectez, puis vous revenez ici.
                        <?php endif; ?>
                    </em>
                    <span class="dash-steam-tile__cta"><?= $dashSteamLinked ? 'Changer le compte Steam' : 'Se connecter avec Steam' ?></span>
                </a>
            </div>
        </section>

        <?php
        $announce_items = is_array($dashboard_announce_items ?? null) ? $dashboard_announce_items : [];
        $announce_heading = 'Alertes & annonces';
        $announce_kicker = 'Transmission';
        $announce_empty = 'Aucune alerte ni annonce pour le moment.';
        $announce_id = 'dashboard-announce';
        $announce_list_url = url('alertes');
        $announce_manage_url = \App\Core\Gate::getInstance()->allows('dashboard.pins.manage')
            || \App\Core\Gate::getInstance()->allows('admin.organization')
            || \App\Core\Gate::getInstance()->allows('admin.access')
            ? url('back-office/alerts')
            : null;
        require base_path('views/partials/announce_tiles.php');
        require base_path('views/partials/dashboard_popup_modal.php');
        ?>

        <?php if (!empty($can_publish_dashboard_articles)): ?>
        <div class="dash-hub-stack" aria-label="Publications">
            <?php require base_path('views/partials/dashboard_quick_articles.php'); ?>
        </div>
        <?php endif; ?>

        <section class="dash-org-anomaly-tile" id="signaler-anomalie" aria-labelledby="dash-org-anomaly-title">
            <div class="dash-org-anomaly-tile__shell">
                <button
                    type="button"
                    class="dash-org-anomaly-tile__open"
                    data-dash-rail-open-external="org-anomaly"
                    aria-controls="dash-rail-nested-org-anomaly"
                >
                    <span class="dash-org-anomaly-tile__kicker">Gestion</span>
                    <strong id="dash-org-anomaly-title" class="dash-org-anomaly-tile__title">Signaler une anomalie</strong>
                    <em class="dash-org-anomaly-tile__hint">Tout dysfonctionnement, erreur ou irrégularité à transmettre à la gestion de l’organisation.</em>
                    <span class="dash-org-anomaly-tile__cta">Ouvrir le formulaire</span>
                </button>
                <div class="dash-org-anomaly-tile__form">
                    <p class="dash-org-anomaly-tile__kicker">Gestion</p>
                    <h2 class="dash-org-anomaly-tile__title">Signaler une anomalie</h2>
                    <?php require base_path('views/partials/dashboard_org_anomaly_form.php'); ?>
                </div>
            </div>
        </section>

        <?php
        $showLiaisonStrip = $canViewAtakOperators;
        $showRsvpQuick = is_array($mbOp) && (int) ($mbOp['id'] ?? 0) > 0;
        ?>
        <?php if ($showLiaisonStrip || $showRsvpQuick): ?>
        <div class="dash-ops-stack">
            <div class="dash-ops-stack__inner">
                <?php if ($showLiaisonStrip): ?>
                <section class="dash-liaison" aria-labelledby="dash-atak-operators-heading">
                    <p id="dash-atak-operators-heading" class="cc-section-label dash-ops-stack__label">Liaison tactique</p>
                    <a href="<?= url('back-office/atak/operateurs') ?>" class="dash-liaison__card">
                        <div class="dash-liaison__copy">
                            <p class="cc-kicker cc-kicker--primary">État-major</p>
                            <h2 class="dash-liaison__title">Effectifs en liaison</h2>
                            <p class="dash-liaison__hint">
                                Consultez le tableur des opérateurs actuellement connectés à la carte tactique.
                            </p>
                        </div>
                        <div class="dash-liaison__aside">
                            <?php if ($atakOperatorsLinkedCount !== null): ?>
                            <div class="dash-liaison__stat" aria-label="<?= (int) $atakOperatorsLinkedCount ?> opérateurs en liaison">
                                <span class="dash-liaison__stat-label">En liaison</span>
                                <span class="dash-liaison__stat-value"><?= (int) $atakOperatorsLinkedCount ?></span>
                            </div>
                            <?php endif; ?>
                            <span class="dash-liaison__cta">
                                Ouvrir le tableur
                                <span aria-hidden="true">→</span>
                            </span>
                        </div>
                    </a>
                </section>
                <?php endif; ?>

                <?php if ($showRsvpQuick): ?>
                <section class="dash-rsvp-quick" aria-labelledby="dash-rsvp-quick-heading">
                    <div class="dash-rsvp-quick__shell">
                        <div class="dash-rsvp-quick__info">
                            <p class="cc-kicker cc-kicker--primary">Réponse rapide</p>
                            <h2 id="dash-rsvp-quick-heading" class="dash-rsvp-quick__title">
                                <?= htmlspecialchars((string) ($mbOp['title'] ?? 'Prochaine manœuvre'), ENT_QUOTES, 'UTF-8') ?>
                            </h2>
                            <?php $rsvpQuickTs = !empty($mbOp['starts_at']) ? strtotime((string) $mbOp['starts_at']) : false; ?>
                            <p class="dash-rsvp-quick__meta">
                                <?= $rsvpQuickTs !== false ? htmlspecialchars(date('d/m/Y H\hi', $rsvpQuickTs), ENT_QUOTES, 'UTF-8') : 'Date à confirmer' ?>
                                <?php
                                $rsvpQuickLabel = (string) ($mbOp['rsvp_label'] ?? '');
                                if ($rsvpQuickLabel === '') {
                                    $rsvpQuickLabel = 'Réponse non renseignée';
                                }
                                ?>
                                · <span data-rsvp-meta-label data-event-id="<?= (int) $mbOp['id'] ?>"><?= htmlspecialchars($rsvpQuickLabel, ENT_QUOTES, 'UTF-8') ?></span>
                            </p>
                        </div>
                        <div class="dash-rsvp-quick__actions">
                            <?php
                            $rsvpEventId = (int) $mbOp['id'];
                            $rsvpCurrentStatus = (string) ($mbOp['rsvp_status'] ?? '');
                            $rsvpCompact = false;
                            require base_path('views/partials/dashboard_rsvp_buttons.php');
                            ?>
                        </div>
                    </div>
                </section>
                <?php endif; ?>
            </div>
        </div>
        <?php endif; ?>

        <?php if (!empty($showcase_training_feature)): ?>
        <section class="dash-showcase" aria-labelledby="dash-showcase-heading" <?php if (!empty($showcase_items)): ?>x-data="trainingShowcase"<?php endif; ?>>
            <div class="dash-showcase__head">
                <div>
                    <p class="dash-showcase__kicker">Catalogue</p>
                    <h2 id="dash-showcase-heading" class="dash-showcase__title">Nos formations<span class="dash-showcase__dot">.</span></h2>
                </div>
                <?php if (!empty($showcase_items)): ?>
                <div class="dash-showcase__nav">
                    <button type="button" class="dash-showcase__nav-btn" @click="scrollTrack(-360)" aria-label="Défiler vers la gauche">
                        <svg width="16" height="16" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/></svg>
                    </button>
                    <button type="button" class="dash-showcase__nav-btn" @click="scrollTrack(360)" aria-label="Défiler vers la droite">
                        <svg width="16" height="16" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
                    </button>
                </div>
                <?php endif; ?>
            </div>
            <?php if (empty($showcase_items)): ?>
                <div class="dash-showcase__empty">
                    <p>Aucune formation publiée pour le moment.</p>
      <a href="<?= url('formations') ?>" class="cc-btn cc-btn-primary" data-lms-module-entry="formation">Ouvrir le catalogue</a>
                </div>
            <?php else: ?>
                <div x-ref="track" class="dash-showcase__track no-scrollbar">
                    <?php foreach ($showcase_items as $sc): ?>
                    <button type="button" class="dash-showcase__card" @click="openModal = <?= (int) $sc['id'] ?>">
                        <img
                            src="<?= htmlspecialchars((string) $sc['thumb'], ENT_QUOTES, 'UTF-8') ?>"
                            alt="<?= htmlspecialchars((string) $sc['title'], ENT_QUOTES, 'UTF-8') ?>"
                            class="dash-showcase__card-img"
                            width="224"
                            height="320"
                            loading="lazy"
                            decoding="async"
                        >
                        <span class="dash-showcase__card-veil" aria-hidden="true"></span>
                        <span class="dash-showcase__card-body">
                            <span class="dash-showcase__card-badge"><?= htmlspecialchars((string) $sc['badge_label'], ENT_QUOTES, 'UTF-8') ?></span>
                            <span class="dash-showcase__card-title"><?= htmlspecialchars((string) $sc['title'], ENT_QUOTES, 'UTF-8') ?></span>
                        </span>
                    </button>
                    <?php endforeach; ?>
                </div>
                <template x-if="openModal !== null">
                    <div class="dash-showcase__modal fixed inset-0 z-50 flex items-center justify-center p-4">
                        <div class="absolute inset-0 bg-slate-950/80 backdrop-blur-sm" @click="openModal = null"></div>
                        <div class="relative flex max-h-[85vh] w-full max-w-4xl flex-col overflow-hidden rounded-2xl bg-white shadow-2xl md:flex-row">
                            <button type="button" @click="openModal = null" class="absolute right-4 top-4 z-10 rounded-full bg-slate-100 p-2" aria-label="Fermer">
                                <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                            </button>
                            <div class="h-56 w-full shrink-0 bg-slate-900 md:h-auto md:w-1/2" x-show="active()">
                                <img :src="active() ? active().banner : ''" :alt="active() ? active().title : ''" class="h-full w-full object-cover">
                            </div>
                            <div class="flex-1 overflow-y-auto p-8" x-show="active()">
                                <p class="cc-kicker cc-kicker--primary">Détails</p>
                                <h2 class="mt-2 text-2xl font-black tracking-tight text-slate-900" x-text="active().title"></h2>
                                <p class="mt-6 whitespace-pre-wrap text-sm leading-relaxed text-slate-600" x-text="active().description"></p>
                                <a :href="active().course_url" class="cc-btn cc-btn-primary mt-8 w-full">Ouvrir la formation</a>
                            </div>
                        </div>
                    </div>
                </template>
            <?php endif; ?>
        </section>
        <?php endif; ?>

        <?php if ($mbExcerpt !== null && $mbExcerpt !== ''): ?>
        <div class="border-b border-amber-200/80 bg-amber-50">
            <div class="dash-apps-full flex flex-wrap items-center justify-between gap-2 py-2.5 !pb-2.5 !pt-2.5">
                <p class="text-sm text-amber-950"><span class="font-bold">Consigne ·</span> <?= htmlspecialchars((string) $mbExcerpt, ENT_QUOTES, 'UTF-8') ?></p>
                <a href="<?= htmlspecialchars((string) $mbPinsA, ENT_QUOTES, 'UTF-8') ?>" class="text-[11px] font-bold uppercase tracking-wider text-amber-900 hover:underline">Voir →</a>
            </div>
        </div>
        <?php endif; ?>

        <section class="dash-apps-full" aria-labelledby="dash-activity-heading">
            <p id="dash-activity-heading" class="cc-section-label dash-apps-full__label">Votre activité</p>
            <div class="cc-card overflow-hidden">
                <div class="cc-card__head">
                    <div>
                        <p class="cc-kicker cc-kicker--primary">Instruction</p>
                        <h2 class="cc-card__title">Formations prioritaires</h2>
                    </div>
                    <a href="<?= url('formations/mes-formations') ?>" class="cc-card__link" data-lms-module-entry="formation">Mes parcours</a>
                </div>
                <?php if ($mbTrain === []): ?>
                    <div class="cc-empty m-3">
                        <p>Aucune formation en cours. Parcourez le catalogue pour démarrer un parcours.</p>
          <a href="<?= url('formations') ?>" class="cc-btn cc-btn-primary" data-lms-module-entry="formation">Ouvrir le catalogue</a>
                    </div>
                <?php else: ?>
                    <p class="dash-train-hint px-3 pt-2">Faites défiler horizontalement pour voir toutes les colonnes.</p>
                    <div class="dash-train-sheet">
                        <table class="dash-train-sheet__table">
                            <thead>
                                <tr>
                                    <th>#</th>
                                    <th>Formation</th>
                                    <th>Priorité</th>
                                    <th>Avancement</th>
                                    <th>Échéance</th>
                                    <th class="text-right">Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($mbTrain as $ti => $t): ?>
                                    <?php
                                    $pct = isset($t['progress_pct']) ? max(0, min(100, (int) $t['progress_pct'])) : 0;
                                    $urgent = !empty($t['urgent']);
                                    $mandatory = !empty($t['mandatory']);
                                    $subtitle = trim((string) ($t['subtitle'] ?? ''));
                                    $detailLine = trim((string) ($t['detail_line'] ?? ''));
                                    $remainingLabel = trim((string) ($t['remaining_label'] ?? ''));
                                    $deadlineLabel = trim((string) ($t['deadline_label'] ?? ''));
                                    $deadlineKind = (string) ($t['deadline_kind'] ?? '');
                                    $actionLabel = trim((string) ($t['action_label'] ?? ''));
                                    if ($actionLabel === '') {
                                        $actionLabel = 'Ouvrir';
                                    }
                                    if ($deadlineLabel === '') {
                                        $deadlineLabel = 'Sans échéance';
                                        $deadlineKind = 'none';
                                    }
                                    $prioLabel = $mandatory ? 'Obligatoire' : ($urgent ? 'Prioritaire' : 'Optionnelle');
                                    $prioClass = ($mandatory || $urgent) ? 'das-badge--rose' : 'das-badge--muted';
                                    $deadlineHint = match ($deadlineKind) {
                                        'expires' => 'À terminer avant',
                                        'session' => 'Prochaine session',
                                        default => null,
                                    };
                                    ?>
                                    <tr>
                                        <td class="dash-train-sheet__num"><?= (int) ($ti + 1) ?></td>
                                        <td>
                                            <span class="dash-train-sheet__title"><?= htmlspecialchars((string) ($t['title'] ?? ''), ENT_QUOTES, 'UTF-8') ?></span>
                                            <?php if ($subtitle !== ''): ?>
                                                <span class="dash-train-sheet__meta"><?= htmlspecialchars($subtitle, ENT_QUOTES, 'UTF-8') ?></span>
                                            <?php endif; ?>
                                            <?php if ($detailLine !== ''): ?>
                                                <span class="dash-train-sheet__detail"><?= htmlspecialchars($detailLine, ENT_QUOTES, 'UTF-8') ?></span>
                                            <?php endif; ?>
                                        </td>
                                        <td><span class="das-badge <?= $prioClass ?>"><?= htmlspecialchars($prioLabel, ENT_QUOTES, 'UTF-8') ?></span></td>
                                        <td>
                                            <div class="dash-train-sheet__pct">
                                                <span class="dash-train-sheet__pct-val"><?= $pct ?> %</span>
                                                <span class="dash-train-sheet__bar" role="progressbar" aria-valuenow="<?= $pct ?>" aria-valuemin="0" aria-valuemax="100" aria-label="Avancement <?= $pct ?> pour cent">
                                                    <span style="width:<?= $pct ?>%"></span>
                                                </span>
                                                <?php if ($remainingLabel !== ''): ?>
                                                    <span class="dash-train-sheet__remain"><?= htmlspecialchars($remainingLabel, ENT_QUOTES, 'UTF-8') ?></span>
                                                <?php endif; ?>
                                            </div>
                                        </td>
                                        <td class="dash-train-sheet__muted">
                                            <?php if ($deadlineHint !== null): ?>
                                                <span class="dash-train-sheet__deadline-hint"><?= htmlspecialchars($deadlineHint, ENT_QUOTES, 'UTF-8') ?></span>
                                            <?php endif; ?>
                                            <span><?= htmlspecialchars($deadlineLabel, ENT_QUOTES, 'UTF-8') ?></span>
                                        </td>
                                        <td class="text-right">
                                            <a href="<?= htmlspecialchars((string) ($t['href'] ?? '#'), ENT_QUOTES, 'UTF-8') ?>" class="das-btn"><?= htmlspecialchars($actionLabel, ENT_QUOTES, 'UTF-8') ?></a>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
        </section>

        <?php if ($hasEnlistments): ?>
        <section class="dash-apps-full !pt-0">
            <?php require base_path('views/partials/dashboard_enlistments.php'); ?>
        </section>
        <?php endif; ?>

        <?php
        $myApplicationsAll = $my_applications_all ?? [];
        $staffApplicationsAll = $staff_applications_all ?? [];
        $hasApplicationsTable = $myApplicationsAll !== [] || ($showStaff && $staffApplicationsAll !== []);
        ?>
        <?php if ($hasApplicationsTable): ?>
        <section class="dash-apps-full !pt-0" aria-labelledby="dash-applications-heading">
            <p id="dash-applications-heading" class="cc-section-label dash-apps-full__label">Candidatures</p>
            <?php require base_path('views/partials/dashboard_applications_table.php'); ?>
        </section>
        <?php endif; ?>

        <?php
        $canViewPersonnelDirectory = !empty($can_view_personnel_directory);
        $dashboardEffectifsRows = is_array($dashboard_effectifs_rows ?? null) ? $dashboard_effectifs_rows : [];
        $hasEffectifsTable = $canViewPersonnelDirectory && empty($dashboard_is_default_tenant);
        ?>
        <?php if ($hasEffectifsTable): ?>
        <section class="dash-apps-full !pt-0" aria-labelledby="dash-effectifs-heading">
            <p id="dash-effectifs-heading" class="cc-section-label dash-apps-full__label">Effectifs</p>
            <?php require base_path('views/partials/dashboard_effectifs_table.php'); ?>
        </section>
        <?php endif; ?>

        <div class="cc-shell space-y-10 py-8 md:py-10">
            <?php $followedChannels = is_array($followed_channels ?? null) ? $followed_channels : []; ?>
            <?php if ($followedChannels !== []): ?>
            <section class="cc-card overflow-hidden" aria-labelledby="dash-channels-heading">
                <div class="cc-card__head">
                    <div>
                        <p class="cc-kicker cc-kicker--primary">Salons</p>
                        <h2 id="dash-channels-heading" class="cc-card__title">Mes salons suivis</h2>
                    </div>
                    <a href="<?= url('forum') ?>" class="cc-card__link">Ouvrir le forum</a>
                </div>
                <ul class="dash-channels-list">
                    <?php foreach ($followedChannels as $ch): ?>
                        <?php
                        $chUnread = (int) ($ch['unread_count'] ?? 0);
                        $chHref = $chUnread > 0 && !empty($ch['last_topic_href']) ? (string) $ch['last_topic_href'] : (string) ($ch['href'] ?? '#');
                        $chLast = trim((string) ($ch['last_topic_title'] ?? ''));
                        ?>
                        <li>
                            <a href="<?= htmlspecialchars($chHref, ENT_QUOTES, 'UTF-8') ?>" class="dash-channels-item">
                                <span class="dash-channels-item__hash" aria-hidden="true">#</span>
                                <span class="dash-channels-item__body">
                                    <span class="dash-channels-item__name"><?= htmlspecialchars((string) ($ch['name'] ?? ''), ENT_QUOTES, 'UTF-8') ?></span>
                                    <?php if ($chLast !== ''): ?>
                                        <span class="dash-channels-item__last"><?= htmlspecialchars($chLast, ENT_QUOTES, 'UTF-8') ?></span>
                                    <?php endif; ?>
                                </span>
                                <?php if ($chUnread > 0): ?>
                                    <span class="dash-channels-item__badge"><?= $chUnread > 99 ? '99+' : $chUnread ?></span>
                                <?php endif; ?>
                            </a>
                        </li>
                    <?php endforeach; ?>
                </ul>
            </section>
            <?php endif; ?>

            <?php
            $linkPins = [];
            foreach ($pins as $pin) {
                if ((string) ($pin['kind'] ?? '') === 'notice' && !empty($pin['notice_text'])) {
                    continue;
                }
                $linkPins[] = $pin;
            }
            ?>
            <?php if ($linkPins !== []): ?>
            <section id="dashboard-community-pins" class="cc-card scroll-mt-24 overflow-hidden">
                <div class="cc-card__head">
                    <div>
                        <p class="cc-kicker cc-kicker--primary">Communauté</p>
                        <h2 class="cc-card__title">Épingles</h2>
                    </div>
                    <?php if (\App\Core\Gate::getInstance()->allows('dashboard.pins.manage')): ?>
                        <a href="<?= url('back-office/dashboard-pins') ?>" class="cc-card__link">Gérer</a>
                    <?php endif; ?>
                </div>
                <div class="grid gap-2 p-3 sm:grid-cols-2">
                    <?php foreach ($linkPins as $pin): ?>
                            <a href="<?= htmlspecialchars((string) ($pin['href'] ?? '#'), ENT_QUOTES, 'UTF-8') ?>" class="rounded-xl border border-slate-200 bg-slate-50/60 px-3.5 py-2.5 text-sm font-bold text-slate-800 transition hover:border-emerald-300 hover:bg-white hover:text-emerald-900">
                                <?= htmlspecialchars((string) ($pin['label'] ?? ''), ENT_QUOTES, 'UTF-8') ?>
                            </a>
                    <?php endforeach; ?>
                </div>
            </section>
            <?php endif; ?>

            <?php if (is_array($dashboard_tester_program) && !empty($dashboard_tester_program['communities'])): ?>
            <section class="cc-card border-amber-200 bg-amber-50/40 p-5" aria-labelledby="dash-tester-heading">
                <p id="dash-tester-heading" class="cc-kicker text-amber-800">Programme de préqualification</p>
                <h2 class="mt-1.5 text-base font-black tracking-tight text-slate-900">Accès anticipé</h2>
                <p class="mt-1.5 text-sm text-slate-600">Vous participez à la validation de modules pour votre communauté.</p>
                <ul class="mt-3 flex flex-wrap gap-2">
                    <?php foreach ($dashboard_tester_program['communities'] as $tc): ?>
                        <li class="rounded-full border border-amber-200 bg-white px-3 py-1 text-xs font-semibold text-amber-950"><?= htmlspecialchars((string) ($tc['name'] ?? ''), ENT_QUOTES, 'UTF-8') ?></li>
                    <?php endforeach; ?>
                </ul>
            </section>
            <?php endif; ?>

            <?php require base_path('views/partials/dashboard_rh_parcours.php'); ?>
        </div>

        <!-- Modal situation tactique -->
        <div
            x-show="tacticalOpen"
            x-cloak
            class="dash-modal"
            role="dialog"
            aria-modal="true"
            aria-labelledby="dash-tactical-modal-title"
            @keydown.escape.window="tacticalOpen = false"
        >
            <div class="dash-modal__backdrop" @click="tacticalOpen = false"></div>
            <div class="dash-modal__panel dash-modal__panel--dark">
                <div class="dash-modal__head">
                    <div>
                        <p class="text-[10px] font-bold uppercase tracking-[0.22em] text-emerald-300">Situation tactique</p>
                        <h2 id="dash-tactical-modal-title" class="mt-1 text-xl font-black text-white">ATAK &amp; Modpack</h2>
                    </div>
                    <button type="button" class="dash-modal__close" @click="tacticalOpen = false" aria-label="Fermer">
                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path stroke-linecap="round" d="M6 6l12 12M18 6L6 18"/></svg>
                    </button>
                </div>
                <div class="dash-modal__body space-y-5">
                    <?php if (is_array($modpack) && !empty($modpack['id'])): ?>
                        <div>
                            <p class="text-sm font-semibold text-white/90"><?= htmlspecialchars($tactPackTitle, ENT_QUOTES, 'UTF-8') ?></p>
                            <div class="mt-3 grid grid-cols-2 gap-2 sm:grid-cols-3">
                                <div class="rounded-lg border border-white/10 bg-white/5 px-3 py-2.5">
                                    <p class="text-[9px] font-bold uppercase tracking-wider text-white/50">Version</p>
                                    <p class="mt-0.5 text-sm font-bold text-white"><?= htmlspecialchars($tactPackVersion, ENT_QUOTES, 'UTF-8') ?></p>
                                </div>
                                <div class="rounded-lg border border-white/10 bg-white/5 px-3 py-2.5">
                                    <p class="text-[9px] font-bold uppercase tracking-wider text-white/50">Taille</p>
                                    <p class="mt-0.5 text-sm font-bold text-white"><?= htmlspecialchars($tactSizeFormatted, ENT_QUOTES, 'UTF-8') ?></p>
                                </div>
                                <div class="rounded-lg border border-white/10 bg-white/5 px-3 py-2.5">
                                    <p class="text-[9px] font-bold uppercase tracking-wider text-white/50">Mise à jour</p>
                                    <p class="mt-0.5 text-sm font-bold text-white"><?= htmlspecialchars($tactUpdatedAt, ENT_QUOTES, 'UTF-8') ?></p>
                                </div>
                            </div>
                        </div>
                        <div class="flex flex-col gap-2 sm:flex-row">
                            <?php if ($tactDownloadUrl): ?>
                            <a href="<?= htmlspecialchars($tactDownloadUrl, ENT_QUOTES, 'UTF-8') ?>" class="cc-btn cc-btn-primary flex-1 text-center">Télécharger le modpack</a>
                            <?php endif; ?>
                            <a href="<?= htmlspecialchars($tactDetailUrl, ENT_QUOTES, 'UTF-8') ?>" class="cc-btn cc-btn-ghost--on-dark flex-1 text-center">Voir la fiche</a>
                        </div>
                    <?php else: ?>
                        <p class="text-sm leading-relaxed text-white/70">Aucun pack publié pour cette communauté. Parcourez les packs ou ouvrez la carte tactique.</p>
                        <a href="<?= url('modpacks') ?>" class="cc-btn cc-btn-primary">Parcourir les packs</a>
                    <?php endif; ?>
                    <div class="grid grid-cols-2 gap-2 border-t border-white/10 pt-4<?= $canViewAtakOperators ? ' sm:grid-cols-3' : '' ?>">
                        <a href="<?= url('atak') ?>" class="rounded-lg border border-white/10 bg-white/5 px-3 py-3 text-center transition hover:bg-white/10">
                            <p class="text-xs font-black uppercase text-white">ATAK</p>
                            <p class="mt-0.5 text-[10px] text-white/50">Carte tactique</p>
                        </a>
                        <?php if ($canViewAtakOperators): ?>
                        <a href="<?= url('back-office/atak/operateurs') ?>" class="rounded-lg border border-emerald-400/30 bg-emerald-500/10 px-3 py-3 text-center transition hover:bg-emerald-500/20">
                            <p class="text-xs font-black uppercase text-emerald-200">Effectifs</p>
                            <p class="mt-0.5 text-[10px] text-white/50">
                                <?php if ($atakOperatorsLinkedCount !== null): ?>
                                    <?= (int) $atakOperatorsLinkedCount ?> en liaison
                                <?php else: ?>
                                    Tableur
                                <?php endif; ?>
                            </p>
                        </a>
                        <?php endif; ?>
                        <?php if ($atakModDownloadUrl): ?>
                        <a href="<?= htmlspecialchars((string) $atakModDownloadUrl, ENT_QUOTES, 'UTF-8') ?>" class="rounded-lg border border-white/10 bg-white/5 px-3 py-3 text-center transition hover:bg-white/10">
                            <p class="text-xs font-black uppercase text-white">Pack Overwatch</p>
                            <p class="mt-0.5 text-[10px] text-white/50">Télécharger</p>
                        </a>
                        <?php else: ?>
                        <a href="<?= url('orbat') ?>" class="rounded-lg border border-white/10 bg-white/5 px-3 py-3 text-center transition hover:bg-white/10">
                            <p class="text-xs font-black uppercase text-white">ORBAT</p>
                            <p class="mt-0.5 text-[10px] text-white/50">Effectifs</p>
                        </a>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>

        <!-- Modal calendrier manœuvres -->
        <div
            x-show="calendarOpen"
            x-cloak
            class="dash-modal"
            role="dialog"
            aria-modal="true"
            aria-labelledby="dash-calendar-modal-title"
            @keydown.escape.window="calendarOpen = false"
        >
            <div class="dash-modal__backdrop" @click="calendarOpen = false"></div>
            <div class="dash-modal__panel">
                <div class="dash-modal__head dash-modal__head--light">
                    <div>
                        <p class="cc-kicker cc-kicker--primary">Calendrier</p>
                        <h2 id="dash-calendar-modal-title" class="mt-1 text-xl font-black text-slate-900">Prochaines manœuvres</h2>
                    </div>
                    <button type="button" class="dash-modal__close dash-modal__close--light" @click="calendarOpen = false" aria-label="Fermer">
                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path stroke-linecap="round" d="M6 6l12 12M18 6L6 18"/></svg>
                    </button>
                </div>
                <div class="dash-modal__body">
                    <?php if ($mbOps === []): ?>
                        <div class="cc-empty">
                            <p>Aucune manœuvre planifiée pour le moment.</p>
                            <a href="<?= url('evenements') ?>" class="cc-btn cc-btn-primary">Ouvrir le calendrier complet</a>
                        </div>
                    <?php else: ?>
                        <ul class="dash-cal-modal__list">
                            <?php foreach ($mbOps as $op): ?>
                                <?php
                                $starts = (string) ($op['starts_at'] ?? '');
                                $day = '—';
                                $mon = '';
                                $time = '';
                                $dateFull = '';
                                if ($starts !== '') {
                                    $ts = strtotime($starts);
                                    if ($ts !== false) {
                                        $day = date('d', $ts);
                                        $mon = $monthsFr[(int) date('n', $ts) - 1] ?? date('M', $ts);
                                        $time = date('H\hi', $ts);
                                        $dateFull = date('d/m/Y', $ts);
                                    }
                                }
                                $rsvp = (string) ($op['rsvp_label'] ?? '');
                                $summary = trim((string) ($op['summary'] ?? ''));
                                $listHref = trim((string) ($op['list_href'] ?? ''));
                                if ($listHref === '') {
                                    $listHref = url('evenements');
                                }
                                ?>
                                <li class="dash-cal-modal__item">
                                    <div class="cc-cal">
                                        <span class="cc-cal__d"><?= htmlspecialchars($day, ENT_QUOTES, 'UTF-8') ?></span>
                                        <span class="cc-cal__m"><?= htmlspecialchars($mon !== '' ? $mon : '—', ENT_QUOTES, 'UTF-8') ?></span>
                                    </div>
                                    <div class="min-w-0 flex-1">
                                        <p class="font-bold text-slate-900"><?= htmlspecialchars((string) ($op['title'] ?? 'Opération'), ENT_QUOTES, 'UTF-8') ?></p>
                                        <p class="mt-0.5 text-xs text-slate-500">
                                            <?= $dateFull !== '' ? htmlspecialchars($dateFull, ENT_QUOTES, 'UTF-8') : '' ?>
                                            <?= $time !== '' ? ' · ' . htmlspecialchars($time, ENT_QUOTES, 'UTF-8') : '' ?>
                                            · <span data-rsvp-meta-label data-event-id="<?= (int) ($op['id'] ?? 0) ?>"><?= htmlspecialchars($rsvp !== '' ? $rsvp : 'Réponse non renseignée', ENT_QUOTES, 'UTF-8') ?></span>
                                        </p>
                                        <?php if ($summary !== ''): ?>
                                            <p class="mt-1 text-sm text-slate-600 line-clamp-2"><?= htmlspecialchars($summary, ENT_QUOTES, 'UTF-8') ?></p>
                                        <?php endif; ?>
                                        <?php
                                        $rsvpEventId = (int) ($op['id'] ?? 0);
                                        $rsvpCurrentStatus = (string) ($op['rsvp_status'] ?? '');
                                        $rsvpCompact = true;
                                        require base_path('views/partials/dashboard_rsvp_buttons.php');
                                        ?>
                                    </div>
                                    <a href="<?= htmlspecialchars($listHref, ENT_QUOTES, 'UTF-8') ?>" class="das-btn shrink-0">Ouvrir</a>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                        <div class="mt-4 border-t border-slate-100 pt-4">
                            <a href="<?= url('evenements') ?>" class="cc-btn cc-btn-primary w-full text-center">Voir tout le calendrier</a>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>
<style>
.no-scrollbar::-webkit-scrollbar { display: none; }
.no-scrollbar { -ms-overflow-style: none; scrollbar-width: none; }

.dash-apps-full {
    width: 100%;
    max-width: 100rem;
    margin-left: auto;
    margin-right: auto;
    box-sizing: border-box;
    padding: 2rem 1.25rem 2.5rem;
}
.dash-apps-full__label {
    padding: 0;
    margin: 0 0 0.85rem;
    font-size: 0.75rem;
    font-weight: 900;
    letter-spacing: 0.2em;
    text-transform: uppercase;
    color: #0f172a;
}
@media (min-width: 768px) {
    .dash-apps-full { padding: 2.25rem 2rem 2.75rem; }
}

/* Bande ops (liaison + RSVP) — largeur alignée sur les tableaux dashboard */
.dash-ops-stack {
    width: 100%;
    background: #fff;
    border-bottom: 1px solid #e2e8f0;
}
.dash-ops-stack__inner {
    width: 100%;
    max-width: 100rem;
    margin: 0 auto;
    box-sizing: border-box;
    padding: 1.35rem 1.25rem 1.5rem;
    display: flex;
    flex-direction: column;
    gap: 0.85rem;
}
@media (min-width: 768px) {
    .dash-ops-stack__inner { padding: 1.5rem 2rem 1.75rem; gap: 1rem; }
}
.dash-ops-stack__label { margin: 0 0 0.65rem; }

.dash-liaison__card {
    display: flex;
    flex-wrap: wrap;
    align-items: center;
    justify-content: space-between;
    gap: 1rem 1.25rem;
    padding: 1rem 1.15rem;
    border: 1px solid #e2e8f0;
    border-radius: 0.9rem;
    background: #f8fafc;
    text-decoration: none;
    color: inherit;
    transition: border-color 0.15s ease, background 0.15s ease, box-shadow 0.15s ease;
}
.dash-liaison__card:hover {
    border-color: #a7f3d0;
    background: #ecfdf5;
    box-shadow: 0 8px 22px -16px rgba(5, 150, 105, 0.45);
}
.dash-liaison__copy { min-width: 0; flex: 1 1 14rem; max-width: 36rem; }
.dash-liaison__title {
    margin: 0.2rem 0 0;
    font-size: 1.05rem;
    font-weight: 900;
    letter-spacing: -0.02em;
    color: #0f172a;
    line-height: 1.2;
}
.dash-liaison__hint {
    margin: 0.4rem 0 0;
    font-size: 0.8125rem;
    line-height: 1.45;
    color: #64748b;
}
.dash-liaison__aside {
    display: flex;
    flex-wrap: wrap;
    align-items: center;
    gap: 0.75rem 1rem;
    flex: 0 0 auto;
}
.dash-liaison__stat {
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    min-width: 4.5rem;
    padding: 0.55rem 0.85rem;
    border-radius: 0.7rem;
    border: 1px solid #a7f3d0;
    background: #ecfdf5;
}
.dash-liaison__stat-label {
    font-size: 0.5625rem;
    font-weight: 800;
    letter-spacing: 0.12em;
    text-transform: uppercase;
    color: #047857;
}
.dash-liaison__stat-value {
    margin-top: 0.1rem;
    font-size: 1.35rem;
    font-weight: 900;
    font-variant-numeric: tabular-nums;
    line-height: 1;
    color: #065f46;
}
.dash-liaison__cta {
    display: inline-flex;
    align-items: center;
    gap: 0.35rem;
    font-size: 0.8125rem;
    font-weight: 800;
    color: #047857;
}
.dash-liaison__card:hover .dash-liaison__cta { text-decoration: underline; }

.dash-train-hint {
    margin: 0;
    font-size: 0.6875rem;
    color: #64748b;
}
.dash-train-sheet {
    width: 100%;
    overflow: auto;
    border-top: 1px solid #f1f5f9;
}
.dash-train-sheet__table {
    width: 100%;
    min-width: 40rem;
    border-collapse: separate;
    border-spacing: 0;
    font-size: 0.8125rem;
}
.dash-train-sheet__table th,
.dash-train-sheet__table td {
    padding: 0.7rem 0.85rem;
    border-bottom: 1px solid #e2e8f0;
    vertical-align: top;
    text-align: left;
}
.dash-train-sheet__table thead th {
    position: sticky;
    top: 0;
    z-index: 1;
    background: #0f172a;
    color: #e2e8f0;
    font-size: 0.625rem;
    font-weight: 900;
    letter-spacing: 0.12em;
    text-transform: uppercase;
    white-space: nowrap;
}
.dash-train-sheet__table tbody tr:nth-child(even) td { background: #fbfdfc; }
.dash-train-sheet__table tbody tr:hover td { background: #f0fdf4; }
.dash-train-sheet__table td.text-right,
.dash-train-sheet__table th.text-right { text-align: right; }
.dash-train-sheet__num { color: #94a3b8; font-variant-numeric: tabular-nums; width: 2.5rem; }
.dash-train-sheet__title { display: block; font-weight: 700; color: #0f172a; }
.dash-train-sheet__meta { display: block; margin-top: 0.15rem; font-size: 0.7rem; color: #64748b; }
.dash-train-sheet__detail { display: block; margin-top: 0.35rem; font-size: 0.72rem; line-height: 1.35; color: #334155; max-width: 22rem; }
.dash-train-sheet__muted { color: #475569; white-space: nowrap; }
.dash-train-sheet__deadline-hint { display: block; font-size: 0.65rem; font-weight: 700; text-transform: uppercase; letter-spacing: 0.04em; color: #94a3b8; margin-bottom: 0.1rem; }
.dash-train-sheet__pct { display: flex; flex-direction: column; gap: 0.35rem; min-width: 7rem; }
.dash-train-sheet__pct-val { font-size: 0.75rem; font-weight: 800; color: #047857; font-variant-numeric: tabular-nums; }
.dash-train-sheet__remain { font-size: 0.65rem; color: #64748b; line-height: 1.3; }
.dash-train-sheet__bar {
    display: block;
    height: 0.4rem;
    border-radius: 999px;
    background: #e2e8f0;
    overflow: hidden;
}
.dash-train-sheet__bar > span {
    display: block;
    height: 100%;
    border-radius: inherit;
    background: #059669;
}

.das-badge {
    display: inline-flex;
    align-items: center;
    padding: 0.2rem 0.5rem;
    border-radius: 999px;
    border: 1px solid #e2e8f0;
    font-size: 0.625rem;
    font-weight: 800;
    letter-spacing: 0.04em;
    text-transform: uppercase;
    white-space: nowrap;
}
.das-badge--rose { background: #fff1f2; border-color: #fecdd3; color: #9f1239; }
.das-badge--muted { background: #f8fafc; border-color: #e2e8f0; color: #64748b; }
.das-btn {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    padding: 0.4rem 0.75rem;
    border-radius: 0.5rem;
    border: 1px solid #0f172a;
    background: #0f172a;
    color: #ffffff;
    font-size: 0.6875rem;
    font-weight: 800;
    letter-spacing: 0.03em;
    text-decoration: none;
    white-space: nowrap;
    transition: background 0.15s ease, border-color 0.15s ease;
}
.das-btn:hover { background: #059669; border-color: #059669; }

.dash-modal {
    position: fixed;
    inset: 0;
    z-index: 80;
    display: flex;
    align-items: center;
    justify-content: center;
    padding: 1rem;
}
.dash-modal__backdrop {
    position: absolute;
    inset: 0;
    background: rgba(2, 6, 23, 0.72);
    backdrop-filter: blur(4px);
}
.dash-modal__panel {
    position: relative;
    z-index: 1;
    width: min(100%, 36rem);
    max-height: min(88vh, 40rem);
    overflow: auto;
    border-radius: 1rem;
    background: #fff;
    box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.45);
}
.dash-modal__panel--dark {
    background: linear-gradient(160deg, #052e1f 0%, #0a0f0d 55%, #050505 100%);
    color: #fff;
    border: 1px solid rgba(255, 255, 255, 0.08);
}
.dash-modal__head {
    display: flex;
    align-items: flex-start;
    justify-content: space-between;
    gap: 1rem;
    padding: 1.15rem 1.25rem 0.85rem;
}
.dash-modal__head--light { border-bottom: 1px solid #f1f5f9; }
.dash-modal__body { padding: 0.5rem 1.25rem 1.35rem; }
.dash-modal__close {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    width: 2.25rem;
    height: 2.25rem;
    border-radius: 0.65rem;
    border: 1px solid rgba(255, 255, 255, 0.14);
    background: rgba(255, 255, 255, 0.06);
    color: #e2e8f0;
    cursor: pointer;
}
.dash-modal__close--light {
    border-color: #e2e8f0;
    background: #f8fafc;
    color: #334155;
}
.dash-modal__close:hover { background: rgba(255, 255, 255, 0.12); }
.dash-modal__close--light:hover { background: #f1f5f9; }

.dash-cal-modal__list { list-style: none; margin: 0; padding: 0; display: flex; flex-direction: column; gap: 0.65rem; }
.dash-cal-modal__item {
    display: flex;
    align-items: flex-start;
    gap: 0.85rem;
    padding: 0.85rem;
    border: 1px solid #e2e8f0;
    border-radius: 0.85rem;
    background: #f8fafc;
}

[x-cloak] { display: none !important; }

.dash-rsvp-quick { width: 100%; margin: 0; padding: 0; }
.dash-rsvp-quick__shell {
    display: flex;
    flex-wrap: wrap;
    align-items: center;
    gap: 0.85rem 1.25rem;
    padding: 0.95rem 1.1rem;
    border-radius: 0.9rem;
    border: 1px solid #a7f3d0;
    background: linear-gradient(135deg, #ecfdf5 0%, #f0fdf4 48%, #ffffff 100%);
}
.dash-rsvp-quick__info { min-width: 0; flex: 1 1 12rem; max-width: 28rem; }
.dash-rsvp-quick__actions { flex: 0 0 auto; margin-left: 0; }
@media (min-width: 640px) {
    .dash-rsvp-quick__actions { margin-left: auto; }
}
.dash-rsvp-quick__title { margin: 0.15rem 0 0; font-size: 1.05rem; font-weight: 900; color: #0f172a; letter-spacing: -0.01em; line-height: 1.25; }
.dash-rsvp-quick__meta { margin: 0.25rem 0 0; font-size: 0.75rem; font-weight: 600; color: #64748b; }

.dash-channels-list { list-style: none; margin: 0; padding: 0.5rem; display: flex; flex-direction: column; gap: 0.15rem; }
.dash-channels-item {
    display: flex;
    align-items: center;
    gap: 0.65rem;
    padding: 0.6rem 0.65rem;
    border-radius: 0.65rem;
    text-decoration: none;
    transition: background 0.15s ease;
}
.dash-channels-item:hover { background: #f0fdf4; }
.dash-channels-item__hash {
    flex-shrink: 0;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    width: 1.75rem;
    height: 1.75rem;
    border-radius: 0.5rem;
    background: #f1f5f9;
    color: #64748b;
    font-weight: 900;
    font-size: 0.9375rem;
}
.dash-channels-item__body { min-width: 0; flex: 1; display: flex; flex-direction: column; }
.dash-channels-item__name { font-size: 0.8125rem; font-weight: 800; color: #0f172a; }
.dash-channels-item__last { font-size: 0.6875rem; color: #64748b; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
.dash-channels-item__badge {
    flex-shrink: 0;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    min-width: 1.35rem;
    height: 1.35rem;
    padding: 0 0.35rem;
    border-radius: 999px;
    background: #059669;
    color: #fff;
    font-size: 0.6875rem;
    font-weight: 900;
}
</style>
