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
        'soustitre' => 'Consultez l’historique et les feuilles de présence pour clôturer ou archiver.',
    ],
    'annules' => [
        'titre' => 'Créneaux annulés',
        'soustitre' => 'Créneaux retirés du calendrier actif. Les membres ont été informés selon les réglages du portail.',
    ],
    default => [
        'titre' => 'À venir',
        'soustitre' => 'Ce que les membres voient dans le calendrier et le pointage.',
    ],
};

$typeBadge = static function (string $et): array {
    return match ($et) {
        'operation' => ['label' => 'Opération', 'class' => 'bg-rose-50 text-rose-800 ring-rose-100'],
        'formation' => ['label' => 'Formation', 'class' => 'bg-sky-50 text-sky-800 ring-sky-100'],
        'autre' => ['label' => 'Autre', 'class' => 'bg-violet-50 text-violet-800 ring-violet-100'],
        default => ['label' => 'Événement', 'class' => 'bg-emerald-50 text-emerald-800 ring-emerald-100'],
    };
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
?>
<div class="bg-slate-50 min-h-[calc(100vh-3.5rem)]">
    <div class="max-w-5xl mx-auto px-4 sm:px-6 lg:px-8 py-8 lg:py-10 space-y-8">
        <header class="rounded-2xl border border-slate-200/80 bg-gradient-to-br from-white via-white to-slate-50 p-6 sm:p-8 shadow-sm">
            <p class="text-[11px] font-semibold uppercase tracking-[0.2em] text-slate-500 mb-2">Pilotage</p>
            <div class="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
                <div>
                    <h1 class="text-2xl sm:text-3xl font-black text-slate-900 tracking-tight">Créneaux, RSVP et pointage</h1>
                    <p class="mt-3 text-sm sm:text-base text-slate-600 max-w-2xl leading-relaxed">
                        Publiez des séances, suivez les réponses et les présences. Les membres utilisent l’espace « Pointage & présence » pour confirmer ou se signaler sur place.
                    </p>
                </div>
                <a href="<?= url('back-office') ?>" class="inline-flex shrink-0 items-center justify-center gap-2 rounded-xl border border-slate-200 bg-white px-4 py-2.5 text-sm font-semibold text-slate-700 shadow-sm transition hover:border-slate-300 hover:bg-slate-50 focus:outline-none focus-visible:ring-2 focus-visible:ring-emerald-600 focus-visible:ring-offset-2">
                    <svg class="h-4 w-4 text-slate-500" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M10.5 19.5 3 12m0 0 7.5-7.5M3 12h18" /></svg>
                    Centre de pilotage
                </a>
            </div>
        </header>

        <?php $s = \App\Core\Session::getFlash('success'); $e = \App\Core\Session::getFlash('error'); ?>
        <?php if ($s): ?>
            <div class="rounded-xl border border-emerald-200 bg-emerald-50/90 px-4 py-3 text-sm text-emerald-900 shadow-sm" role="status">
                <?= htmlspecialchars($s) ?>
            </div>
        <?php endif; ?>
        <?php if ($e): ?>
            <div class="rounded-xl border border-red-200 bg-red-50/90 px-4 py-3 text-sm text-red-900 shadow-sm" role="alert">
                <?= htmlspecialchars($e) ?>
            </div>
        <?php endif; ?>

        <section class="rounded-2xl border border-slate-200/80 bg-white shadow-sm p-5 sm:p-6 space-y-4">
            <div class="flex items-center justify-between gap-3 flex-wrap">
                <div>
                    <h2 class="text-base font-bold text-slate-900">Pilotage présence (90 jours)</h2>
                    <p class="text-sm text-slate-600">KPI, motifs d’absence, créneaux recommandés et score de régularité.</p>
                </div>
                <span class="text-xs font-semibold text-slate-500">Fenêtre glissante</span>
            </div>
            <div class="grid gap-3 sm:grid-cols-3">
                <div class="rounded-xl border border-slate-200 bg-slate-50 px-4 py-3">
                    <p class="text-xs font-semibold text-slate-500 uppercase tracking-wide">Présence confirmée vs effective</p>
                    <p class="mt-1 text-2xl font-black text-slate-900"><?= number_format($effectiveRate, 1, ',', ' ') ?>%</p>
                    <p class="text-xs text-slate-500"><?= $effective ?> / <?= $confirmed ?> RSVP « présent » pointés</p>
                </div>
                <div class="rounded-xl border border-amber-200 bg-amber-50 px-4 py-3">
                    <p class="text-xs font-semibold text-amber-700 uppercase tracking-wide">Taux de no-show</p>
                    <p class="mt-1 text-2xl font-black text-amber-900"><?= number_format($noShowRate, 1, ',', ' ') ?>%</p>
                    <p class="text-xs text-amber-800"><?= $noShow ?> RSVP « présent » non pointés</p>
                </div>
                <div class="rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3">
                    <p class="text-xs font-semibold text-emerald-700 uppercase tracking-wide">Progression nouveaux membres</p>
                    <p class="mt-1 text-2xl font-black text-emerald-900"><?= number_format($eventsNewMemberParticipationDelta * 100, 1, ',', ' ') ?> pts</p>
                    <p class="text-xs text-emerald-800">Différence moyenne participation J+30 → J+90</p>
                </div>
            </div>
            <div class="grid gap-4 lg:grid-cols-3">
                <div>
                    <h3 class="text-sm font-semibold text-slate-800 mb-2">Motifs d’absence</h3>
                    <ul class="space-y-1.5 text-sm">
                        <?php foreach ($eventsAbsenceReasons as $reason): ?>
                            <li class="flex items-center justify-between rounded-lg border border-slate-200 bg-slate-50 px-3 py-2">
                                <span class="text-slate-700"><?= htmlspecialchars((string) ($reason['absence_reason'] ?? 'non_renseigne')) ?></span>
                                <strong class="text-slate-900"><?= (int) ($reason['total'] ?? 0) ?></strong>
                            </li>
                        <?php endforeach; ?>
                        <?php if ($eventsAbsenceReasons === []): ?><li class="text-slate-500 text-xs">Aucune absence consolidée.</li><?php endif; ?>
                    </ul>
                </div>
                <div>
                    <h3 class="text-sm font-semibold text-slate-800 mb-2">Créneaux recommandés</h3>
                    <ul class="space-y-1.5 text-sm">
                        <?php foreach ($eventsRecommendedSlots as $slot): ?>
                            <li class="rounded-lg border border-slate-200 bg-slate-50 px-3 py-2">
                                <div class="font-semibold text-slate-800"><?= htmlspecialchars($dowLabel((int) ($slot['day_of_week'] ?? 0))) ?> · <?= str_pad((string) (int) ($slot['hour_slot'] ?? 0), 2, '0', STR_PAD_LEFT) ?>h</div>
                                <div class="text-xs text-slate-500"><?= number_format(((float) ($slot['attendance_rate'] ?? 0)) * 100, 1, ',', ' ') ?>% de présence effective (échantillon <?= (int) ($slot['sample_size'] ?? 0) ?>)</div>
                            </li>
                        <?php endforeach; ?>
                        <?php if ($eventsRecommendedSlots === []): ?><li class="text-slate-500 text-xs">Pas assez de données pour proposer des créneaux.</li><?php endif; ?>
                    </ul>
                </div>
                <div>
                    <h3 class="text-sm font-semibold text-slate-800 mb-2">Régularité à surveiller</h3>
                    <ul class="space-y-1.5 text-sm">
                        <?php foreach ($eventsRegularityScores as $member): ?>
                            <li class="rounded-lg border border-slate-200 bg-slate-50 px-3 py-2">
                                <div class="font-semibold text-slate-800"><?= htmlspecialchars((string) ($member['display_name'] ?? 'Membre')) ?></div>
                                <div class="text-xs text-slate-500"><?= number_format(((float) ($member['regularity_score'] ?? 0)) * 100, 1, ',', ' ') ?>% sur <?= (int) ($member['commitments'] ?? 0) ?> engagements</div>
                            </li>
                        <?php endforeach; ?>
                        <?php if ($eventsRegularityScores === []): ?><li class="text-slate-500 text-xs">Scores insuffisants (moins de 2 engagements).</li><?php endif; ?>
                    </ul>
                </div>
            </div>
        </section>

        <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
            <div>
                <h2 class="text-lg font-bold text-slate-900"><?= htmlspecialchars($vueMeta['titre']) ?></h2>
                <p class="mt-1 text-sm text-slate-600 max-w-xl"><?= htmlspecialchars($vueMeta['soustitre']) ?></p>
            </div>
            <div class="flex flex-wrap items-center gap-2">
                <nav class="inline-flex flex-wrap rounded-xl bg-slate-200/70 p-1 gap-1 shadow-inner" aria-label="Filtre des créneaux">
                    <a href="<?= url('back-office/events') ?>?vue=a_venir" class="rounded-lg px-3.5 py-2 text-sm font-semibold transition focus:outline-none focus-visible:ring-2 focus-visible:ring-emerald-600 focus-visible:ring-offset-2 <?= $eventsVue === 'a_venir' ? 'bg-white text-slate-900 shadow-sm' : 'text-slate-600 hover:text-slate-900' ?>">À venir</a>
                    <a href="<?= url('back-office/events') ?>?vue=passes" class="rounded-lg px-3.5 py-2 text-sm font-semibold transition focus:outline-none focus-visible:ring-2 focus-visible:ring-emerald-600 focus-visible:ring-offset-2 <?= $eventsVue === 'passes' ? 'bg-white text-slate-900 shadow-sm' : 'text-slate-600 hover:text-slate-900' ?>">Passés</a>
                    <a href="<?= url('back-office/events') ?>?vue=annules" class="rounded-lg px-3.5 py-2 text-sm font-semibold transition focus:outline-none focus-visible:ring-2 focus-visible:ring-emerald-600 focus-visible:ring-offset-2 <?= $eventsVue === 'annules' ? 'bg-white text-slate-900 shadow-sm' : 'text-slate-600 hover:text-slate-900' ?>">Annulés</a>
                </nav>
                <a href="<?= url('back-office/events/insights') ?>" class="inline-flex items-center rounded-xl border border-violet-200 bg-violet-50 px-3.5 py-2 text-sm font-semibold text-violet-900 transition hover:border-violet-300 hover:bg-violet-100 focus:outline-none focus-visible:ring-2 focus-visible:ring-violet-500 focus-visible:ring-offset-2">
                    Insights présence
                </a>
            </div>
        </div>

        <?php
        $quotaBanner = $eventsQuota;
        $quotaCanProceed = $canCreateEvent;
        $variant = 'light';
        $quotaFromKey = 'events';
        require __DIR__ . '/../../partials/quota_limited_banner.php';
        ?>

        <section class="rounded-2xl border border-slate-200/80 bg-white shadow-sm overflow-hidden <?= !$canCreateEvent ? 'opacity-80' : '' ?>">
            <div class="border-b border-slate-100 bg-gradient-to-r from-slate-50 to-white px-5 py-4 sm:px-6">
                <h2 class="text-base font-bold text-slate-900">Nouveau créneau</h2>
                <p class="mt-1 text-sm text-slate-600">Renseignez au minimum le titre et le début ; le reste aide les membres à s’organiser.</p>
            </div>
            <form method="post" action="<?= url('back-office/events') ?>" class="p-5 sm:p-6 space-y-5">
                <input type="hidden" name="_csrf_token" value="<?= htmlspecialchars(\App\Core\Csrf::token()) ?>">
                <input type="hidden" name="return_vue" value="<?= htmlspecialchars($eventsVue, ENT_QUOTES, 'UTF-8') ?>">
                <div class="grid gap-5 sm:grid-cols-2">
                    <div class="sm:col-span-2">
                        <label class="block text-sm font-medium text-slate-700 mb-1.5">Titre</label>
                        <input type="text" name="title" required placeholder="Ex. Briefing opération Forêt Noire" class="w-full rounded-xl border border-slate-200 bg-white px-3.5 py-2.5 text-sm text-slate-900 shadow-sm placeholder:text-slate-400 focus:border-emerald-500 focus:outline-none focus:ring-2 focus:ring-emerald-500/20" <?= !$canCreateEvent ? 'disabled' : '' ?>>
                    </div>
                    <div class="sm:col-span-2">
                        <label class="block text-sm font-medium text-slate-700 mb-1.5">Description <span class="font-normal text-slate-500">(optionnel)</span></label>
                        <textarea name="description" rows="3" placeholder="Consignes, tenue, lieu de rendez-vous détaillé…" class="w-full rounded-xl border border-slate-200 bg-white px-3.5 py-2.5 text-sm text-slate-900 shadow-sm placeholder:text-slate-400 focus:border-emerald-500 focus:outline-none focus:ring-2 focus:ring-emerald-500/20 resize-y min-h-[5rem]" <?= !$canCreateEvent ? 'disabled' : '' ?>></textarea>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-slate-700 mb-1.5">Date et heure de début</label>
                        <input type="datetime-local" name="starts_at" required step="60" class="w-full rounded-xl border border-slate-200 bg-white px-3.5 py-2.5 text-sm text-slate-900 shadow-sm focus:border-emerald-500 focus:outline-none focus:ring-2 focus:ring-emerald-500/20" <?= !$canCreateEvent ? 'disabled' : '' ?>>
                        <p class="mt-1.5 text-xs text-slate-500">Utilisez le sélecteur du navigateur (date puis heure).</p>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-slate-700 mb-1.5">Fin <span class="font-normal text-slate-500">(optionnel)</span></label>
                        <input type="datetime-local" name="ends_at" step="60" class="w-full rounded-xl border border-slate-200 bg-white px-3.5 py-2.5 text-sm text-slate-900 shadow-sm focus:border-emerald-500 focus:outline-none focus:ring-2 focus:ring-emerald-500/20" <?= !$canCreateEvent ? 'disabled' : '' ?>>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-slate-700 mb-1.5">Lieu <span class="font-normal text-slate-500">(optionnel)</span></label>
                        <input type="text" name="location" placeholder="Serveur, salle, coordonnées…" class="w-full rounded-xl border border-slate-200 bg-white px-3.5 py-2.5 text-sm text-slate-900 shadow-sm placeholder:text-slate-400 focus:border-emerald-500 focus:outline-none focus:ring-2 focus:ring-emerald-500/20" <?= !$canCreateEvent ? 'disabled' : '' ?>>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-slate-700 mb-1.5">Campagne ou repère interne <span class="font-normal text-slate-500">(optionnel)</span></label>
                        <input type="text" name="campaign_tag" placeholder="Ex. Saison 2026 — Alpha" class="w-full rounded-xl border border-slate-200 bg-white px-3.5 py-2.5 text-sm text-slate-900 shadow-sm placeholder:text-slate-400 focus:border-emerald-500 focus:outline-none focus:ring-2 focus:ring-emerald-500/20" <?= !$canCreateEvent ? 'disabled' : '' ?>>
                    </div>
                    <div class="sm:col-span-2">
                        <label class="block text-sm font-medium text-slate-700 mb-1.5">Type de créneau</label>
                        <select name="event_type" class="w-full rounded-xl border border-slate-200 bg-white px-3.5 py-2.5 text-sm text-slate-900 shadow-sm focus:border-emerald-500 focus:outline-none focus:ring-2 focus:ring-emerald-500/20" <?= !$canCreateEvent ? 'disabled' : '' ?>>
                            <option value="operation">Opération</option>
                            <option value="evenement" selected>Événement</option>
                            <option value="formation">Formation (créneau)</option>
                            <option value="autre">Autre</option>
                        </select>
                    </div>
                </div>
                <div class="flex flex-wrap items-center gap-3 pt-1">
                    <button type="submit" class="inline-flex items-center justify-center rounded-xl bg-slate-900 px-5 py-2.5 text-sm font-bold text-white shadow-sm transition hover:bg-slate-800 focus:outline-none focus-visible:ring-2 focus-visible:ring-emerald-500 focus-visible:ring-offset-2 disabled:cursor-not-allowed disabled:opacity-50" <?= !$canCreateEvent ? 'disabled' : '' ?>>
                        Publier le créneau
                    </button>
                    <?php if (!$canCreateEvent): ?>
                        <p class="text-xs text-amber-800 font-medium">Création limitée par le quota ou l’offre — consultez le bandeau ci-dessus.</p>
                    <?php endif; ?>
                </div>
            </form>
        </section>

        <section aria-labelledby="liste-creneaux">
            <div class="flex items-end justify-between gap-4 mb-4">
                <h2 id="liste-creneaux" class="text-base font-bold text-slate-900">Liste</h2>
                <?php $nEv = count($events); ?>
                <?php if ($nEv > 0): ?>
                    <span class="text-xs font-medium text-slate-500"><?= $nEv ?> créneau<?= $nEv > 1 ? 'x' : '' ?></span>
                <?php endif; ?>
            </div>

            <?php if ($events === []): ?>
                <div class="rounded-2xl border border-dashed border-slate-200 bg-white/80 px-6 py-14 text-center">
                    <div class="mx-auto flex h-12 w-12 items-center justify-center rounded-full bg-slate-100 text-slate-500 mb-4" aria-hidden="true">
                        <svg class="h-6 w-6" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M6.75 3v2.25M17.25 3v2.25M3 18.75V7.5a2.25 2.25 0 0 1 2.25-2.25h13.5A2.25 2.25 0 0 1 21 7.5v11.25m-18 0A2.25 2.25 0 0 0 5.25 21h13.5a2.25 2.25 0 0 0 2.25-2.25m-18 0v-7.5A2.25 2.25 0 0 1 5.25 9h13.5a2.25 2.25 0 0 1 2.25 2.25v7.5" /></svg>
                    </div>
                    <p class="text-sm font-semibold text-slate-800">Aucun créneau dans cette vue</p>
                    <p class="mt-2 text-sm text-slate-600 max-w-md mx-auto">Changez d’onglet ou créez un nouveau créneau ci-dessus pour qu’il apparaisse ici.</p>
                </div>
            <?php else: ?>
                <ul class="space-y-3">
                    <?php foreach ($events as $ev):
                        $eid = (int) ($ev['id'] ?? 0);
                        $et = (string) ($ev['event_type'] ?? 'evenement');
                        $badge = $typeBadge($et);
                        ?>
                        <li>
                            <article class="group rounded-2xl border border-slate-200/80 bg-white p-5 shadow-sm transition hover:border-slate-300 hover:shadow-md">
                                <div class="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
                                    <div class="min-w-0 flex-1">
                                        <div class="flex flex-wrap items-center gap-2">
                                            <span class="inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-bold ring-1 ring-inset <?= htmlspecialchars($badge['class']) ?>">
                                                <?= htmlspecialchars($badge['label']) ?>
                                            </span>
                                            <?php if ($eventsVue === 'annules' && !empty($ev['cancelled_at'])): ?>
                                                <span class="inline-flex items-center rounded-full bg-amber-50 px-2.5 py-0.5 text-xs font-bold text-amber-900 ring-1 ring-inset ring-amber-100">Annulé</span>
                                            <?php endif; ?>
                                        </div>
                                        <h3 class="mt-2 text-base font-bold text-slate-900 leading-snug"><?= htmlspecialchars((string) ($ev['title'] ?? '')) ?></h3>
                                        <div class="mt-3 flex flex-wrap gap-x-4 gap-y-2 text-sm text-slate-600">
                                            <span class="inline-flex items-center gap-1.5">
                                                <svg class="h-4 w-4 shrink-0 text-slate-400" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M6.75 3v2.25M17.25 3v2.25M3 18.75V7.5a2.25 2.25 0 0 1 2.25-2.25h13.5A2.25 2.25 0 0 1 21 7.5v11.25m-18 0A2.25 2.25 0 0 0 5.25 21h13.5a2.25 2.25 0 0 0 2.25-2.25m-18 0v-7.5A2.25 2.25 0 0 1 5.25 9h13.5a2.25 2.25 0 0 1 2.25 2.25v7.5" /></svg>
                                                <?= htmlspecialchars((string) ($ev['starts_at'] ?? '')) ?>
                                            </span>
                                            <?php if (!empty($ev['location'])): ?>
                                                <span class="inline-flex items-center gap-1.5 min-w-0">
                                                    <svg class="h-4 w-4 shrink-0 text-slate-400" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M15 10.5a3 3 0 1 1-6 0 3 3 0 0 1 6 0Z" /><path stroke-linecap="round" stroke-linejoin="round" d="M19.5 10.5c0 7.125-7.5 11.25-7.5 11.25S4.5 17.625 4.5 10.5a7.5 7.5 0 1 1 15 0Z" /></svg>
                                                    <span class="truncate"><?= htmlspecialchars((string) $ev['location']) ?></span>
                                                </span>
                                            <?php endif; ?>
                                        </div>
                                        <?php if ($eventsVue === 'annules' && !empty($ev['cancelled_at'])): ?>
                                            <p class="mt-2 text-xs text-amber-800">Annulation enregistrée le <?= htmlspecialchars((string) $ev['cancelled_at']) ?></p>
                                        <?php endif; ?>
                                    </div>
                                    <div class="flex flex-wrap items-center gap-2 sm:flex-col sm:items-stretch sm:shrink-0 sm:min-w-[11rem]">
                                        <a href="<?= url('back-office/events/' . $eid) ?>" class="inline-flex items-center justify-center gap-1.5 rounded-xl bg-emerald-600 px-4 py-2.5 text-sm font-bold text-white shadow-sm transition hover:bg-emerald-700 focus:outline-none focus-visible:ring-2 focus-visible:ring-emerald-500 focus-visible:ring-offset-2">
                                            RSVP &amp; pointage
                                            <svg class="h-4 w-4 opacity-90" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="m8.25 4.5 7.5 7.5-7.5 7.5" /></svg>
                                        </a>
                                        <a href="<?= url('back-office/events/' . $eid . '/export-presences') ?>" class="inline-flex items-center justify-center rounded-xl border border-slate-200 bg-white px-4 py-2.5 text-sm font-semibold text-slate-700 shadow-sm transition hover:border-slate-300 hover:bg-slate-50 focus:outline-none focus-visible:ring-2 focus-visible:ring-slate-400 focus-visible:ring-offset-2">
                                            Télécharger la feuille
                                        </a>
                                    </div>
                                </div>
                            </article>
                        </li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>
        </section>
    </div>
</div>
