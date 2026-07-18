<?php
declare(strict_types=1);

/**
 * Athena Command — tableau de bord membre densifié (ops) + aside pliable.
 *
 * @var string|null $dashboard_tenant_label
 * @var array<string,mixed>|null $mission_briefing
 * @var array<string,mixed>|null $modpack
 * @var string|null $atakModDownloadUrl
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
 * @var array<string,mixed>|null $personnelExtras
 * @var array<string,mixed>|null $grade
 * @var int $currentTid
 */

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
$avatarSrc = function_exists('user_media_public_url')
    ? user_media_public_url(is_array($cu) ? ($cu['avatar_url'] ?? null) : null)
    : null;
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
?>
<div class="dash-cc dash-cc--rail">
    <?php require base_path('views/partials/dashboard_aside.php'); ?>

    <div class="dash-cc__main">
        <?php require base_path('views/partials/header_dashboard.php'); ?>

        <!-- Hero sombre (réf. Caverne) — catalogue immédiatement après -->
        <section class="dash-hero" aria-labelledby="dash-hero-title">
            <div class="dash-hero__shell">
                <h1 id="dash-hero-title" class="dash-hero__title">Dashboard</h1>

                <div class="dash-hero__media<?= $heroHasImage ? '' : ' dash-hero__media--fallback' ?>">
                    <?php if ($heroHasImage): ?>
                        <img
                            src="<?= htmlspecialchars($heroImageUrl, ENT_QUOTES, 'UTF-8') ?>"
                            alt=""
                            class="dash-hero__img"
                            width="1600"
                            height="720"
                            decoding="async"
                            fetchpriority="high"
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
        $announce_items = is_array($dashboard_announce_items ?? null) ? $dashboard_announce_items : [];
        $announce_heading = 'Alertes & annonces';
        $announce_kicker = 'Transmission';
        $announce_empty = 'Aucune alerte ni annonce pour le moment.';
        $announce_id = 'dashboard-announce';
        $announce_manage_url = \App\Core\Gate::getInstance()->allows('dashboard.pins.manage')
            || \App\Core\Gate::getInstance()->allows('admin.organization')
            || \App\Core\Gate::getInstance()->allows('admin.access')
            ? url('back-office/alerts')
            : null;
        require base_path('views/partials/announce_tiles.php');
        ?>

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
                    <a href="<?= url('formations') ?>" class="cc-btn cc-btn-primary">Ouvrir le catalogue</a>
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

        <section class="dash-idstrip" aria-label="Identité opérationnelle">
            <div class="dash-idstrip__shell">
                <div class="dash-idstrip__facts">
                    <div class="dash-idstrip__fact">
                        <span class="dash-idstrip__label">Communauté</span>
                        <span class="dash-idstrip__value"><?= htmlspecialchars($unitLabel, ENT_QUOTES, 'UTF-8') ?></span>
                    </div>
                    <div class="dash-idstrip__fact">
                        <span class="dash-idstrip__label">Grade</span>
                        <span class="dash-idstrip__value"><?= htmlspecialchars($roleHint, ENT_QUOTES, 'UTF-8') ?></span>
                    </div>
                    <div class="dash-idstrip__fact">
                        <span class="dash-idstrip__label">Matricule</span>
                        <span class="dash-idstrip__value"><?= htmlspecialchars($matricule ? (string) $matricule : 'Non attribué', ENT_QUOTES, 'UTF-8') ?></span>
                    </div>
                    <?php if ($platformRole !== ''): ?>
                    <div class="dash-idstrip__fact">
                        <span class="dash-idstrip__label">Rôle</span>
                        <span class="dash-idstrip__value"><?= htmlspecialchars($platformRole, ENT_QUOTES, 'UTF-8') ?></span>
                    </div>
                    <?php endif; ?>
                    <div class="dash-idstrip__fact">
                        <span class="dash-idstrip__label">Statut</span>
                        <span class="dash-idstrip__value dash-idstrip__value--status"><?= htmlspecialchars($statutLabel, ENT_QUOTES, 'UTF-8') ?></span>
                    </div>
                    <div class="dash-idstrip__fact">
                        <span class="dash-idstrip__label">Date</span>
                        <span class="dash-idstrip__value"><?= htmlspecialchars($todayLabel, ENT_QUOTES, 'UTF-8') ?></span>
                    </div>
                </div>
                <?php if ($dashCtxCommunity && count($communityMemberships ?? []) > 1): ?>
                <div class="dash-idstrip__switch">
                    <span class="dash-idstrip__label">Autres communautés</span>
                    <div class="dash-idstrip__chips">
                        <?php foreach ($communityMemberships as $m): ?>
                            <?php if ((int) ($m['tenant_id'] ?? 0) === $currentTid) {
                                continue;
                            } ?>
                            <form method="post" action="<?= url('community/switch') ?>" class="inline" onsubmit="var b=this.querySelector('button[type=submit]');if(b){b.disabled=true;b.setAttribute('aria-busy','true');b.textContent='…';}">
                                <?= \App\Core\Csrf::field() ?>
                                <input type="hidden" name="tenant_id" value="<?= (int) $m['tenant_id'] ?>">
                                <button type="submit" class="dash-idstrip__chip"><?= htmlspecialchars(community_display_name($m), ENT_QUOTES, 'UTF-8') ?></button>
                            </form>
                        <?php endforeach; ?>
                    </div>
                </div>
                <?php endif; ?>
                <?php if ($dashCtxTrial): ?>
                    <a href="<?= url('platform/upgrade') ?>" class="dash-idstrip__trial">
                        Essai fondateur jusqu’au <?= htmlspecialchars(date('d/m/Y', strtotime($founderTrialEndsAt)), ENT_QUOTES, 'UTF-8') ?> →
                    </a>
                <?php endif; ?>
            </div>
        </section>

        <!-- KPIs allégés -->
        <section class="border-b border-slate-200 bg-[#f8fafc]">
            <div class="cc-shell space-y-4 py-5 md:py-6">
                <div class="flex flex-wrap items-center justify-between gap-3">
                    <div class="min-w-0">
                        <p class="cc-kicker cc-kicker--primary">Situation</p>
                        <p class="text-sm font-semibold text-slate-700"><?= htmlspecialchars($identityLine, ENT_QUOTES, 'UTF-8') ?></p>
                    </div>
                    <div class="cc-toolbar__actions">
                        <a href="<?= url('personnel/me') ?>" class="cc-btn cc-btn-ghost">Ma fiche</a>
                        <a href="<?= url('documents') ?>" class="cc-btn cc-btn-ghost">Publier un ordre</a>
                        <a href="<?= url('evenements') ?>" class="cc-btn cc-btn-primary">Nouvelle manœuvre</a>
                    </div>
                </div>
                <div class="grid grid-cols-2 gap-2.5 lg:grid-cols-4">
                    <div class="cc-card cc-kpi">
                        <p class="cc-kpi__label">Manœuvres</p>
                        <p class="cc-kpi__value"><?= $opsCount > 0 ? (int) $opsCount : '—' ?></p>
                        <p class="cc-kpi__meta <?= $opsCount === 0 ? 'cc-kpi__meta--muted' : '' ?>">
                            <?php if ($nextOpDays !== null): ?>J−<?= (int) $nextOpDays ?><?php elseif ($opsCount > 0): ?>À venir<?php else: ?>Aucune planifiée<?php endif; ?>
                        </p>
                    </div>
                    <div class="cc-card cc-kpi">
                        <p class="cc-kpi__label">Formations</p>
                        <p class="cc-kpi__value"><?= $trainCount > 0 ? (int) $trainCount : '—' ?></p>
                        <p class="cc-kpi__meta <?= $trainCount === 0 ? 'cc-kpi__meta--muted' : '' ?>"><?= $trainCount > 0 ? 'En cours' : 'Aucune ouverte' ?></p>
                    </div>
                    <div class="cc-card cc-kpi">
                        <p class="cc-kpi__label">Candidatures</p>
                        <p class="cc-kpi__value"><?= $showStaff ? ($staffCount > 0 ? (string) (int) $staffCount : '—') : '—' ?></p>
                        <p class="cc-kpi__meta <?= !$showStaff || $staffCount === 0 ? 'cc-kpi__meta--muted' : 'cc-kpi__meta--warn' ?>">
                            <?= !$showStaff ? 'Non concerné' : ($staffCount > 0 ? 'À traiter' : 'À jour') ?>
                        </p>
                    </div>
                    <div class="cc-card cc-kpi">
                        <p class="cc-kpi__label">Mes dossiers</p>
                        <p class="cc-kpi__value"><?= $myCount > 0 ? (int) $myCount : '—' ?></p>
                        <p class="cc-kpi__meta <?= $myCount > 0 ? 'cc-kpi__meta--sky' : 'cc-kpi__meta--muted' ?>"><?= $myCount > 0 ? 'En attente' : 'Aucun' ?></p>
                    </div>
                </div>
            </div>
        </section>

        <?php if ($mbExcerpt !== null && $mbExcerpt !== ''): ?>
        <div class="border-b border-amber-200/80 bg-amber-50">
            <div class="cc-shell flex flex-wrap items-center justify-between gap-2 py-2.5">
                <p class="text-sm text-amber-950"><span class="font-bold">Consigne ·</span> <?= htmlspecialchars((string) $mbExcerpt, ENT_QUOTES, 'UTF-8') ?></p>
                <a href="<?= htmlspecialchars((string) $mbPinsA, ENT_QUOTES, 'UTF-8') ?>" class="text-[11px] font-bold uppercase tracking-wider text-amber-900 hover:underline">Voir →</a>
            </div>
        </div>
        <?php endif; ?>

        <div class="cc-shell space-y-10 py-8 md:py-10">

            <!-- 1. VOTRE ACTIVITÉ -->
            <section aria-labelledby="dash-activity-heading">
                <p id="dash-activity-heading" class="cc-section-label">Votre activité</p>
                <div class="cc-card overflow-hidden">
                    <div class="cc-card__head">
                        <div>
                            <p class="cc-kicker cc-kicker--primary">Instruction</p>
                            <h2 class="cc-card__title">Formations prioritaires</h2>
                        </div>
                        <a href="<?= url('formations/mes-formations') ?>" class="cc-card__link">Mes parcours</a>
                    </div>
                    <?php if ($mbTrain === []): ?>
                        <div class="cc-empty m-3">
                            <p>Aucune formation en cours. Parcourez le catalogue pour démarrer un parcours.</p>
                            <a href="<?= url('formations') ?>" class="cc-btn cc-btn-primary">Ouvrir le catalogue</a>
                        </div>
                    <?php else: ?>
                        <ul class="cc-rows">
                            <?php foreach ($mbTrain as $t): ?>
                                <?php
                                $pct = isset($t['progress_pct']) ? max(0, min(100, (int) $t['progress_pct'])) : 0;
                                $subtitle = trim((string) ($t['subtitle'] ?? ''));
                                $urgent = !empty($t['urgent']);
                                ?>
                                <li>
                                    <a href="<?= htmlspecialchars((string) ($t['href'] ?? '#'), ENT_QUOTES, 'UTF-8') ?>" class="cc-row">
                                        <div class="cc-row__body">
                                            <div class="flex flex-wrap items-center justify-between gap-2">
                                                <p class="cc-row__title"><?= htmlspecialchars((string) ($t['title'] ?? ''), ENT_QUOTES, 'UTF-8') ?></p>
                                                <div class="flex items-center gap-2">
                                                    <?php if ($urgent): ?>
                                                        <span class="cc-badge cc-badge--urgent">Prioritaire</span>
                                                    <?php endif; ?>
                                                    <span class="text-xs font-bold tabular-nums text-emerald-700"><?= $pct ?> %</span>
                                                </div>
                                            </div>
                                            <?php if ($subtitle !== ''): ?>
                                                <p class="cc-row__meta"><?= htmlspecialchars($subtitle, ENT_QUOTES, 'UTF-8') ?></p>
                                            <?php else: ?>
                                                <p class="cc-row__meta">Avancement : <?= $pct ?> %</p>
                                            <?php endif; ?>
                                            <div class="cc-progress mt-2.5" role="progressbar" aria-valuenow="<?= $pct ?>" aria-valuemin="0" aria-valuemax="100" aria-label="Avancement <?= $pct ?> pour cent">
                                                <span style="width:<?= $pct ?>%"></span>
                                            </div>
                                        </div>
                                    </a>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    <?php endif; ?>
                </div>
            </section>

            <!-- 2. Situation tactique + 3. Calendrier -->
            <div class="cc-grid cc-grid--main">
                <section class="cc-c2 p-5" aria-labelledby="dash-tactical-heading">
                    <div class="flex items-start justify-between gap-3">
                        <div>
                            <p class="text-[10px] font-bold uppercase tracking-[0.22em] text-emerald-300">Situation tactique</p>
                            <h2 id="dash-tactical-heading" class="mt-1.5 text-lg font-black tracking-tight text-white">ATAK &amp; Modpack</h2>
                        </div>
                        <?php if ($hasPack): ?>
                        <span class="cc-badge cc-badge--on-dark">
                            <span class="cc-badge__dot" aria-hidden="true"></span>
                            Pack dispo
                        </span>
                        <?php endif; ?>
                    </div>

                    <?php if (is_array($modpack) && !empty($modpack['id'])): ?>
                        <?php
                        $sizeFormatted = '—';
                        if (!empty($modpack['size'])) {
                            $b = (int) $modpack['size'];
                            $sizeFormatted = $b >= 1073741824
                                ? number_format($b / 1073741824, 1, ',', ' ') . ' Go'
                                : ($b >= 1048576 ? number_format($b / 1048576, 1, ',', ' ') . ' Mo' : number_format($b / 1024, 1, ',', ' ') . ' Ko');
                        }
                        $updatedAt = !empty($modpack['updated_at']) ? date('d.m.y', strtotime((string) $modpack['updated_at'])) : '—';
                        $detailUrl = !empty($mbModpack['detail_href'])
                            ? (string) $mbModpack['detail_href']
                            : (!empty($modpack['slug']) ? url('modpacks/' . rawurlencode((string) $modpack['slug'])) : url('modpacks'));
                        $downloadUrl = url('modpacks/' . (int) $modpack['id'] . '/download');
                        $packTitle = !empty($mbModpack['title']) ? (string) $mbModpack['title'] : (string) ($modpack['name'] ?? $modpack['title'] ?? 'Modpack');
                        ?>
                        <p class="mt-3 text-sm font-semibold text-white/90"><?= htmlspecialchars($packTitle, ENT_QUOTES, 'UTF-8') ?></p>
                        <div class="mt-3 grid grid-cols-2 gap-2">
                            <div class="rounded-lg border border-white/10 bg-white/5 px-3 py-2.5">
                                <p class="text-[9px] font-bold uppercase tracking-wider text-white/50">Version</p>
                                <p class="mt-0.5 text-sm font-bold text-white"><?= htmlspecialchars((string) ($modpack['version'] ?? '—'), ENT_QUOTES, 'UTF-8') ?></p>
                            </div>
                            <div class="rounded-lg border border-white/10 bg-white/5 px-3 py-2.5">
                                <p class="text-[9px] font-bold uppercase tracking-wider text-white/50">Taille</p>
                                <p class="mt-0.5 text-sm font-bold text-white"><?= htmlspecialchars($sizeFormatted, ENT_QUOTES, 'UTF-8') ?></p>
                            </div>
                        </div>
                        <p class="mt-2.5 text-xs text-white/55">Mise à jour <?= htmlspecialchars($updatedAt, ENT_QUOTES, 'UTF-8') ?></p>
                        <div class="mt-4 flex flex-col gap-2">
                            <a href="<?= htmlspecialchars($downloadUrl, ENT_QUOTES, 'UTF-8') ?>" class="cc-btn cc-btn-primary w-full">Télécharger le modpack</a>
                            <a href="<?= htmlspecialchars($detailUrl, ENT_QUOTES, 'UTF-8') ?>" class="text-center text-[10px] font-bold uppercase tracking-wider text-white/50 hover:text-emerald-300">Voir la fiche</a>
                        </div>
                    <?php else: ?>
                        <p class="mt-4 text-sm leading-relaxed text-white/60">Aucun pack publié pour cette communauté. Ouvrez la carte tactique ou parcourez les packs disponibles.</p>
                        <a href="<?= url('modpacks') ?>" class="cc-btn cc-btn-ghost--on-dark mt-3">Parcourir les packs</a>
                    <?php endif; ?>

                    <div class="mt-5 grid grid-cols-2 gap-2 border-t border-white/10 pt-4">
                        <a href="<?= url('atak') ?>" class="rounded-lg border border-white/10 bg-white/5 px-3 py-2.5 text-center transition hover:bg-white/10">
                            <p class="text-xs font-black uppercase text-white">ATAK</p>
                            <p class="mt-0.5 text-[10px] text-white/50">Carte tactique</p>
                        </a>
                        <?php if ($atakModDownloadUrl): ?>
                        <a href="<?= htmlspecialchars((string) $atakModDownloadUrl, ENT_QUOTES, 'UTF-8') ?>" class="rounded-lg border border-white/10 bg-white/5 px-3 py-2.5 text-center transition hover:bg-white/10">
                            <p class="text-xs font-black uppercase text-white">Mod ATAK</p>
                            <p class="mt-0.5 text-[10px] text-white/50">Télécharger</p>
                        </a>
                        <?php else: ?>
                        <a href="<?= url('orbat') ?>" class="rounded-lg border border-white/10 bg-white/5 px-3 py-2.5 text-center transition hover:bg-white/10">
                            <p class="text-xs font-black uppercase text-white">ORBAT</p>
                            <p class="mt-0.5 text-[10px] text-white/50">Effectifs</p>
                        </a>
                        <?php endif; ?>
                    </div>
                </section>

                <section class="cc-card overflow-hidden" aria-labelledby="dash-calendar-heading">
                    <div class="cc-card__head">
                        <div>
                            <p class="cc-kicker cc-kicker--primary">Calendrier</p>
                            <h2 id="dash-calendar-heading" class="cc-card__title">Prochaines manœuvres</h2>
                        </div>
                        <a href="<?= url('evenements') ?>" class="cc-card__link">Tout voir</a>
                    </div>
                    <?php if ($mbOps === []): ?>
                        <div class="cc-empty m-3">
                            <p>Aucune manœuvre planifiée pour le moment.</p>
                            <a href="<?= url('evenements') ?>" class="cc-btn cc-btn-primary">Ouvrir le calendrier</a>
                        </div>
                    <?php else: ?>
                        <ul class="cc-rows">
                            <?php foreach ($mbOps as $op): ?>
                                <?php
                                $starts = (string) ($op['starts_at'] ?? '');
                                $day = '—';
                                $mon = '';
                                $time = '';
                                if ($starts !== '') {
                                    $ts = strtotime($starts);
                                    if ($ts !== false) {
                                        $day = date('d', $ts);
                                        $mon = $monthsFr[(int) date('n', $ts) - 1] ?? date('M', $ts);
                                        $time = date('H\hi', $ts);
                                    }
                                }
                                $rsvp = (string) ($op['rsvp_label'] ?? '');
                                $badgeClass = 'cc-badge--ink';
                                if ($rsvp === 'Vous participez') {
                                    $badgeClass = 'cc-badge--live-solid';
                                } elseif ($rsvp === 'Peut-être') {
                                    $badgeClass = 'cc-badge--sky-solid';
                                } elseif ($rsvp === 'Vous ne participez pas') {
                                    $badgeClass = 'cc-badge--rose';
                                }
                                $summary = trim((string) ($op['summary'] ?? ''));
                                ?>
                                <li>
                                    <div class="cc-row">
                                        <div class="cc-cal">
                                            <span class="cc-cal__d"><?= htmlspecialchars($day, ENT_QUOTES, 'UTF-8') ?></span>
                                            <span class="cc-cal__m"><?= htmlspecialchars($mon !== '' ? $mon : '—', ENT_QUOTES, 'UTF-8') ?></span>
                                        </div>
                                        <div class="cc-row__body">
                                            <div class="flex flex-wrap items-start justify-between gap-2">
                                                <p class="cc-row__title"><?= htmlspecialchars((string) ($op['title'] ?? 'Opération'), ENT_QUOTES, 'UTF-8') ?></p>
                                                <?php if ($rsvp !== ''): ?>
                                                    <span class="cc-badge <?= $badgeClass ?>"><?= htmlspecialchars($rsvp, ENT_QUOTES, 'UTF-8') ?></span>
                                                <?php endif; ?>
                                            </div>
                                            <?php if ($summary !== ''): ?>
                                                <p class="cc-row__meta line-clamp-2"><?= htmlspecialchars($summary, ENT_QUOTES, 'UTF-8') ?></p>
                                            <?php endif; ?>
                                            <?php if ($time !== ''): ?>
                                                <p class="cc-row__meta mt-1 font-bold"><?= htmlspecialchars($time, ENT_QUOTES, 'UTF-8') ?></p>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    <?php endif; ?>
                </section>
            </div>

            <?php if ($hasEnlistments): ?>
                <?php require base_path('views/partials/dashboard_enlistments.php'); ?>
            <?php endif; ?>
        </div><!-- /cc-shell (bloc principal, largeur 72rem) -->

        <?php
        // 4. Toutes les demandes de candidature — tableau plein page, hors du cc-shell 72rem,
        // pour utiliser toute la largeur disponible du dashboard (comme les tableaux personnel/évènements).
        $myApplicationsAll = $my_applications_all ?? [];
        $staffApplicationsAll = $staff_applications_all ?? [];
        $hasApplicationsTable = $myApplicationsAll !== [] || ($showStaff && $staffApplicationsAll !== []);
        ?>
        <?php if ($hasApplicationsTable): ?>
        <section class="dash-apps-full" aria-labelledby="dash-applications-heading">
            <p id="dash-applications-heading" class="cc-section-label dash-apps-full__label">Candidatures</p>
            <?php require base_path('views/partials/dashboard_applications_table.php'); ?>
        </section>
        <?php endif; ?>

        <div class="cc-shell space-y-10 py-8 md:py-10">
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
        </div>
    </div>
</div>
<style>
.no-scrollbar::-webkit-scrollbar { display: none; }
.no-scrollbar { -ms-overflow-style: none; scrollbar-width: none; }

/* Tableau candidatures : plein page dans le flux dashboard (hors cc-shell 72rem), comme les
   tableaux personnel/évènements — largeur pleine du panneau principal, pas de carte étroite. */
.dash-apps-full {
    width: 100%;
    padding: 2rem 1.25rem 2.5rem;
}
.dash-apps-full__label { padding: 0; }
@media (min-width: 768px) {
    .dash-apps-full { padding: 2.25rem 2rem 2.75rem; }
}
</style>
