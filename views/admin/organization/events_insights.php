<?php
/** @var array<string, int> $eventsAttendanceKpis */
/** @var list<array<string, mixed>> $eventsAbsenceReasons */
/** @var list<array<string, mixed>> $eventsRecommendedSlots */
/** @var list<array<string, mixed>> $eventsRegularityScores */
/** @var float $eventsNewMemberParticipationDelta */

$eventsAttendanceKpis = $eventsAttendanceKpis ?? ['confirmed_yes' => 0, 'effective_yes' => 0, 'no_show_yes' => 0];
$eventsAbsenceReasons = $eventsAbsenceReasons ?? [];
$eventsRecommendedSlots = $eventsRecommendedSlots ?? [];
$eventsRegularityScores = $eventsRegularityScores ?? [];
$eventsNewMemberParticipationDelta = isset($eventsNewMemberParticipationDelta) ? (float) $eventsNewMemberParticipationDelta : 0.0;

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
                    <h1 class="text-2xl sm:text-3xl font-black text-slate-900 tracking-tight">Insights présence événements</h1>
                    <p class="mt-3 text-sm sm:text-base text-slate-600 max-w-2xl leading-relaxed">
                        Vue consolidée des indicateurs RSVP / présence pour améliorer la planification et le suivi des nouveaux membres.
                    </p>
                </div>
                <a href="<?= url('back-office/events') ?>" class="inline-flex shrink-0 items-center justify-center gap-2 rounded-xl border border-slate-200 bg-white px-4 py-2.5 text-sm font-semibold text-slate-700 shadow-sm transition hover:border-slate-300 hover:bg-slate-50">
                    Retour aux créneaux
                </a>
            </div>
        </header>

        <section class="rounded-2xl border border-slate-200/80 bg-white shadow-sm p-5 sm:p-6 space-y-4">
            <div class="flex items-center justify-between gap-3 flex-wrap">
                <div>
                    <h2 class="text-base font-bold text-slate-900">KPI glissants (90 jours)</h2>
                    <p class="text-sm text-slate-600">Présence effective, no-show, progression onboarding.</p>
                </div>
                <span class="text-xs font-semibold text-slate-500">Mise à jour temps réel</span>
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
    </div>
</div>
