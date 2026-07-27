<?php
/** @var list<array<string, mixed>> $events */
/** @var array<string, mixed>|null $eventsQuota */
/** @var bool $canCreateEvent */
/** @var string $eventsVue */
/** @var array<string, int> $eventsAttendanceKpis */
/** @var list<array<string, mixed>> $eventsAbsenceReasons */
/** @var list<array<string, mixed>> $eventsRecommendedSlots */
/** @var list<array<string, mixed>> $eventsRegularityScores */
/** @var float $eventsNewMemberParticipationDelta */
$eventsQuota = $eventsQuota ?? null;
$canCreateEvent = $canCreateEvent ?? true;
$eventsVue = $eventsVue ?? 'a_venir';
$eventsAttendanceKpis = $eventsAttendanceKpis ?? ['confirmed_yes' => 0, 'effective_yes' => 0, 'no_show_yes' => 0];
$eventsAbsenceReasons = $eventsAbsenceReasons ?? [];
$eventsRecommendedSlots = $eventsRecommendedSlots ?? [];
$eventsRegularityScores = $eventsRegularityScores ?? [];
$eventsNewMemberParticipationDelta = isset($eventsNewMemberParticipationDelta) ? (float) $eventsNewMemberParticipationDelta : 0.0;

$vueMeta = match ($eventsVue) {
    'passes' => [
        'titre' => 'Créneaux passés',
        'soustitre' => 'Historique et feuilles de présence pour clôturer ou archiver.',
    ],
    'annules' => [
        'titre' => 'Créneaux annulés',
        'soustitre' => 'Retirés du calendrier actif — les membres ont été informés selon les réglages.',
    ],
    default => [
        'titre' => 'À venir',
        'soustitre' => 'Ce que les membres voient dans le calendrier et le pointage.',
    ],
};

$typeBadge = static function (string $et): array {
    return match ($et) {
        'operation' => ['label' => 'Opération', 'class' => 'bg-rose-50 text-rose-800'],
        'formation' => ['label' => 'Formation', 'class' => 'bg-sky-50 text-sky-800'],
        'autre' => ['label' => 'Autre', 'class' => 'bg-violet-50 text-violet-800'],
        default => ['label' => 'Événement', 'class' => 'bg-emerald-50 text-emerald-800'],
    };
};

$absenceLabel = static function (string $code): string {
    return match ($code) {
        'service' => 'Service',
        'sante' => 'Santé',
        'indisponibilite_planifiee' => 'Indisponibilité planifiée',
        'absence_non_justifiee' => 'Absence non justifiée',
        'autre' => 'Autre',
        'non_renseigne' => 'Non précisé',
        default => $code !== '' ? $code : 'Non précisé',
    };
};

$formatWhen = static function (?string $raw): array {
    if ($raw === null || trim($raw) === '') {
        return ['day' => '—', 'mon' => '', 'time' => '', 'full' => '—'];
    }
    $ts = strtotime($raw);
    if ($ts === false) {
        return ['day' => '—', 'mon' => '', 'time' => '', 'full' => $raw];
    }
    $months = [1 => 'jan', 2 => 'fév', 3 => 'mar', 4 => 'avr', 5 => 'mai', 6 => 'juin', 7 => 'juil', 8 => 'aoû', 9 => 'sep', 10 => 'oct', 11 => 'nov', 12 => 'déc'];
    $m = (int) date('n', $ts);

    return [
        'day' => date('d', $ts),
        'mon' => $months[$m] ?? date('M', $ts),
        'time' => date('H:i', $ts),
        'full' => date('d/m/Y H:i', $ts),
    ];
};

$confirmed = (int) ($eventsAttendanceKpis['confirmed_yes'] ?? 0);
$effective = (int) ($eventsAttendanceKpis['effective_yes'] ?? 0);
$noShow = (int) ($eventsAttendanceKpis['no_show_yes'] ?? 0);
$effectiveRate = $confirmed > 0 ? ($effective / $confirmed) * 100 : 0.0;
$noShowRate = $confirmed > 0 ? ($noShow / $confirmed) * 100 : 0.0;
$dowLabel = static function (int $day): string {
    return match ($day) {
        1 => 'Dimanche',
        2 => 'Lundi',
        3 => 'Mardi',
        4 => 'Mercredi',
        5 => 'Jeudi',
        6 => 'Vendredi',
        7 => 'Samedi',
        default => 'Jour',
    };
};
$nEv = count($events);
?>

<?php if (!empty($isBackOfficeShell)): ?>
<?php require base_path('views/partials/ath_events_ops.php'); return; ?>
<?php endif; ?>

<div class="bo-events" x-data="{ insightsOpen: false, formOpen: <?= $canCreateEvent ? 'true' : 'false' ?> }">
    <header class="bo-events__hero">
        <div class="bo-events__hero-inner">
            <div>
                <p class="bo-events__eyebrow">État-major · Planning</p>
                <h1 class="bo-events__title">Créneaux &amp; présence</h1>
                <p class="bo-events__lead">
                    Publiez des séances, suivez les réponses et le pointage. Les membres confirment depuis l’espace « Pointage &amp; présence ».
                </p>
            </div>
            <div class="bo-events__hero-actions">
                <a href="<?= url('back-office/events/insights') ?>" class="bo-events__btn bo-events__btn--ghost">Insights présence</a>
                <a href="<?= url('back-office') ?>" class="bo-events__btn bo-events__btn--solid">Centre de pilotage</a>
            </div>
        </div>
    </header>

    <div class="bo-events__deck">
        <?php $s = \App\Core\Session::getFlash('success'); $e = \App\Core\Session::getFlash('error'); ?>
        <?php if ($s): ?>
            <div class="bo-events__flash bo-events__flash--ok" role="status"><?= htmlspecialchars($s) ?></div>
        <?php endif; ?>
        <?php if ($e): ?>
            <div class="bo-events__flash bo-events__flash--err" role="alert"><?= htmlspecialchars($e) ?></div>
        <?php endif; ?>

        <section class="bo-events__insights" :class="insightsOpen && 'is-open'">
            <button type="button" class="bo-events__insights-toggle" @click="insightsOpen = !insightsOpen" :aria-expanded="insightsOpen.toString()">
                <span>
                    <p class="bo-events__insights-kicker">Synthèse 90 jours</p>
                    <p class="bo-events__insights-title">Pilotage des présences</p>
                    <p class="bo-events__insights-hint">
                        <?= number_format($effectiveRate, 0, ',', ' ') ?>% de présence effective ·
                        <?= number_format($noShowRate, 0, ',', ' ') ?>% de no-show
                    </p>
                </span>
                <svg class="bo-events__chevron" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="m19.5 8.25-7.5 7.5-7.5-7.5" /></svg>
            </button>
            <div class="bo-events__insights-body" x-show="insightsOpen" x-cloak>
                <div class="bo-events__kpi-grid">
                    <div class="bo-events__kpi">
                        <p class="bo-events__kpi-label">Présence effective</p>
                        <p class="bo-events__kpi-value"><?= number_format($effectiveRate, 1, ',', ' ') ?>%</p>
                        <p class="bo-events__kpi-meta"><?= $effective ?> / <?= $confirmed ?> confirmés « présents » pointés</p>
                    </div>
                    <div class="bo-events__kpi bo-events__kpi--warn">
                        <p class="bo-events__kpi-label">No-show</p>
                        <p class="bo-events__kpi-value"><?= number_format($noShowRate, 1, ',', ' ') ?>%</p>
                        <p class="bo-events__kpi-meta"><?= $noShow ?> confirmés non pointés</p>
                    </div>
                    <div class="bo-events__kpi bo-events__kpi--ok">
                        <p class="bo-events__kpi-label">Nouveaux membres</p>
                        <p class="bo-events__kpi-value"><?= number_format($eventsNewMemberParticipationDelta * 100, 1, ',', ' ') ?> pts</p>
                        <p class="bo-events__kpi-meta">Écart moyen participation J+30 → J+90</p>
                    </div>
                </div>
                <div class="bo-events__insight-cols">
                    <div class="bo-events__insight-col">
                        <h3>Motifs d’absence</h3>
                        <ul class="bo-events__pill-list">
                            <?php foreach ($eventsAbsenceReasons as $reason): ?>
                                <li>
                                    <span><?= htmlspecialchars($absenceLabel((string) ($reason['absence_reason'] ?? 'non_renseigne'))) ?></span>
                                    <strong><?= (int) ($reason['total'] ?? 0) ?></strong>
                                </li>
                            <?php endforeach; ?>
                            <?php if ($eventsAbsenceReasons === []): ?>
                                <li><span class="text-slate-500">Aucune absence consolidée</span></li>
                            <?php endif; ?>
                        </ul>
                    </div>
                    <div class="bo-events__insight-col">
                        <h3>Créneaux recommandés</h3>
                        <ul class="bo-events__pill-list">
                            <?php foreach ($eventsRecommendedSlots as $slot): ?>
                                <li>
                                    <span><?= htmlspecialchars($dowLabel((int) ($slot['day_of_week'] ?? 0))) ?> · <?= str_pad((string) (int) ($slot['hour_slot'] ?? 0), 2, '0', STR_PAD_LEFT) ?>h</span>
                                    <strong><?= number_format(((float) ($slot['attendance_rate'] ?? 0)) * 100, 0, ',', ' ') ?>%</strong>
                                </li>
                            <?php endforeach; ?>
                            <?php if ($eventsRecommendedSlots === []): ?>
                                <li><span class="text-slate-500">Pas assez de données</span></li>
                            <?php endif; ?>
                        </ul>
                    </div>
                    <div class="bo-events__insight-col">
                        <h3>Régularité à surveiller</h3>
                        <ul class="bo-events__pill-list">
                            <?php foreach ($eventsRegularityScores as $member): ?>
                                <li>
                                    <span><?= htmlspecialchars((string) ($member['display_name'] ?? 'Membre')) ?></span>
                                    <strong><?= number_format(((float) ($member['regularity_score'] ?? 0)) * 100, 0, ',', ' ') ?>%</strong>
                                </li>
                            <?php endforeach; ?>
                            <?php if ($eventsRegularityScores === []): ?>
                                <li><span class="text-slate-500">Moins de 2 engagements</span></li>
                            <?php endif; ?>
                        </ul>
                    </div>
                </div>
            </div>
        </section>

        <div class="bo-events__toolbar">
            <div>
                <h2><?= htmlspecialchars($vueMeta['titre']) ?></h2>
                <p><?= htmlspecialchars($vueMeta['soustitre']) ?></p>
            </div>
            <nav class="bo-events__tabs" aria-label="Filtre des créneaux">
                <a href="<?= url('back-office/events') ?>?vue=a_venir" class="bo-events__tab<?= $eventsVue === 'a_venir' ? ' is-active' : '' ?>">À venir</a>
                <a href="<?= url('back-office/events') ?>?vue=passes" class="bo-events__tab<?= $eventsVue === 'passes' ? ' is-active' : '' ?>">Passés</a>
                <a href="<?= url('back-office/events') ?>?vue=annules" class="bo-events__tab<?= $eventsVue === 'annules' ? ' is-active' : '' ?>">Annulés</a>
            </nav>
        </div>

        <?php
        $quotaBanner = $eventsQuota;
        $quotaCanProceed = $canCreateEvent;
        $variant = 'light';
        $quotaFromKey = 'events';
        require __DIR__ . '/../../partials/quota_limited_banner.php';
        ?>

        <section class="bo-events__panel <?= !$canCreateEvent ? 'opacity-80' : '' ?>">
            <button type="button" class="bo-events__panel-head" @click="formOpen = !formOpen" :aria-expanded="formOpen.toString()">
                <div>
                    <h2>Nouveau créneau</h2>
                    <p>Titre et début suffisent ; le reste aide les membres à s’organiser.</p>
                </div>
                <svg class="bo-events__chevron" :style="formOpen ? 'transform: rotate(180deg)' : ''" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="m19.5 8.25-7.5 7.5-7.5-7.5" /></svg>
            </button>
            <form method="post" action="<?= url('back-office/events') ?>" enctype="multipart/form-data" class="bo-events__form" x-show="formOpen" x-cloak>
                <input type="hidden" name="_csrf_token" value="<?= htmlspecialchars(\App\Core\Csrf::token()) ?>">
                <input type="hidden" name="return_vue" value="<?= htmlspecialchars($eventsVue, ENT_QUOTES, 'UTF-8') ?>">
                <div class="bo-events__form-grid">
                    <div class="bo-events__field--full">
                        <label class="bo-events__label" for="ev-title">Titre</label>
                        <input id="ev-title" type="text" name="title" required placeholder="Ex. Briefing opération Forêt Noire" class="bo-events__input" <?= !$canCreateEvent ? 'disabled' : '' ?>>
                    </div>
                    <div class="bo-events__field--full">
                        <label class="bo-events__label" for="ev-desc">Description <span>(optionnel)</span></label>
                        <textarea id="ev-desc" name="description" rows="3" placeholder="Consignes, tenue, lieu de rendez-vous…" class="bo-events__textarea" <?= !$canCreateEvent ? 'disabled' : '' ?>></textarea>
                    </div>
                    <div>
                        <label class="bo-events__label" for="ev-start">Début</label>
                        <input id="ev-start" type="datetime-local" name="starts_at" required step="60" class="bo-events__input" <?= !$canCreateEvent ? 'disabled' : '' ?>>
                    </div>
                    <div>
                        <label class="bo-events__label" for="ev-end">Fin <span>(optionnel)</span></label>
                        <input id="ev-end" type="datetime-local" name="ends_at" step="60" class="bo-events__input" <?= !$canCreateEvent ? 'disabled' : '' ?>>
                    </div>
                    <div>
                        <label class="bo-events__label" for="ev-loc">Lieu <span>(optionnel)</span></label>
                        <input id="ev-loc" type="text" name="location" placeholder="Serveur, salle, coordonnées…" class="bo-events__input" <?= !$canCreateEvent ? 'disabled' : '' ?>>
                    </div>
                    <div>
                        <label class="bo-events__label" for="ev-tag">Repère interne <span>(optionnel)</span></label>
                        <input id="ev-tag" type="text" name="campaign_tag" placeholder="Ex. Saison 2026 — Alpha" class="bo-events__input" <?= !$canCreateEvent ? 'disabled' : '' ?>>
                    </div>
                    <div class="bo-events__field--full">
                        <label class="bo-events__label" for="ev-type">Type de créneau</label>
                        <select id="ev-type" name="event_type" class="bo-events__select" <?= !$canCreateEvent ? 'disabled' : '' ?>>
                            <option value="operation">Opération</option>
                            <option value="evenement" selected>Événement</option>
                            <option value="formation">Formation (créneau)</option>
                            <option value="autre">Autre</option>
                        </select>
                    </div>
                    <?php
                    $eventDetailsSource = [];
                    require __DIR__ . '/partials/event_details_fields.php';
                    ?>
                </div>
                <div class="bo-events__form-actions">
                    <button type="submit" class="bo-events__submit" <?= !$canCreateEvent ? 'disabled' : '' ?>">Publier le créneau</button>
                    <?php if (!$canCreateEvent): ?>
                        <p class="bo-events__hint" style="color:#92400e;font-weight:600">Création limitée — consultez le bandeau de quota.</p>
                    <?php endif; ?>
                </div>
            </form>
        </section>

        <section aria-labelledby="liste-creneaux">
            <div class="bo-events__list-head">
                <h2 id="liste-creneaux">Liste</h2>
                <?php if ($nEv > 0): ?>
                    <span class="bo-events__count"><?= $nEv ?> créneau<?= $nEv > 1 ? 'x' : '' ?></span>
                <?php endif; ?>
            </div>

            <?php if ($events === []): ?>
                <div class="bo-events__empty">
                    <div class="bo-events__empty-icon" aria-hidden="true">
                        <svg class="h-6 w-6" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M6.75 3v2.25M17.25 3v2.25M3 18.75V7.5a2.25 2.25 0 0 1 2.25-2.25h13.5A2.25 2.25 0 0 1 21 7.5v11.25m-18 0A2.25 2.25 0 0 0 5.25 21h13.5a2.25 2.25 0 0 0 2.25-2.25m-18 0v-7.5A2.25 2.25 0 0 1 5.25 9h13.5a2.25 2.25 0 0 1 2.25 2.25v7.5" /></svg>
                    </div>
                    <p>Aucun créneau dans cette vue</p>
                    <span>Changez d’onglet ou publiez un nouveau créneau ci-dessus.</span>
                </div>
            <?php else: ?>
                <ul class="bo-events__cards">
                    <?php foreach ($events as $ev):
                        $eid = (int) ($ev['id'] ?? 0);
                        $et = (string) ($ev['event_type'] ?? 'evenement');
                        $badge = $typeBadge($et);
                        $when = $formatWhen(isset($ev['starts_at']) ? (string) $ev['starts_at'] : null);
                        $loc = trim((string) ($ev['location'] ?? ''));
                        ?>
                        <li>
                            <article class="bo-events__card">
                                <div class="bo-events__dateblock" title="<?= htmlspecialchars($when['full'], ENT_QUOTES, 'UTF-8') ?>">
                                    <span class="bo-events__dateblock-day"><?= htmlspecialchars($when['day']) ?></span>
                                    <span class="bo-events__dateblock-mon"><?= htmlspecialchars($when['mon']) ?></span>
                                    <?php if ($when['time'] !== ''): ?>
                                        <span class="bo-events__dateblock-time"><?= htmlspecialchars($when['time']) ?></span>
                                    <?php endif; ?>
                                </div>
                                <div class="bo-events__card-body">
                                    <div class="bo-events__badges">
                                        <span class="bo-events__badge <?= htmlspecialchars($badge['class']) ?>"><?= htmlspecialchars($badge['label']) ?></span>
                                        <?php if ($eventsVue === 'annules' && !empty($ev['cancelled_at'])): ?>
                                            <span class="bo-events__badge bg-amber-50 text-amber-900">Annulé</span>
                                        <?php endif; ?>
                                    </div>
                                    <h3 class="bo-events__card-title"><?= htmlspecialchars((string) ($ev['title'] ?? '')) ?></h3>
                                    <div class="bo-events__meta">
                                        <span><?= htmlspecialchars($when['full']) ?></span>
                                        <?php if ($loc !== ''): ?>
                                            <span class="truncate"><?= htmlspecialchars($loc) ?></span>
                                        <?php endif; ?>
                                    </div>
                                    <?php if ($eventsVue === 'annules' && !empty($ev['cancelled_at'])): ?>
                                        <p class="bo-events__hint" style="color:#92400e">Annulation enregistrée le <?= htmlspecialchars((string) $ev['cancelled_at']) ?></p>
                                    <?php endif; ?>
                                </div>
                                <div class="bo-events__card-actions">
                                    <a href="<?= url('back-office/events/' . $eid) ?>" class="bo-events__action bo-events__action--primary">RSVP &amp; pointage</a>
                                    <a href="<?= url('back-office/events/' . $eid . '/export-presences') ?>" class="bo-events__action bo-events__action--ghost">Feuille de présence</a>
                                </div>
                            </article>
                        </li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>
        </section>
    </div>
</div>
