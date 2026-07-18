<?php
declare(strict_types=1);
$enlistments = $enlistments ?? [];
$statusFilter = $statusFilter ?? null;
$counts = is_array($enlistmentCounts ?? null) ? $enlistmentCounts : [];
$nSubmitted = (int) ($counts['submitted'] ?? 0);
$nReviewed = (int) ($counts['reviewed'] ?? 0);
$nRejected = (int) ($counts['rejected'] ?? 0);
$nBlocked = (int) ($counts['blocked'] ?? 0);
$nTotal = array_sum($counts);
$enlistmentSlaHours = max(1, (int) ($enlistmentSlaHours ?? 72));
$submittedOlderThanSla = max(0, (int) ($submittedOlderThanSla ?? 0));
$staffRetrosDueCount = max(0, (int) ($staffRetrosDueCount ?? 0));
$staffRetrosDueFirstId = max(0, (int) ($staffRetrosDueFirstId ?? 0));

$initials = static function (string $first, string $last): string {
    $a = mb_strtoupper(mb_substr(trim($first), 0, 1));
    $b = mb_strtoupper(mb_substr(trim($last), 0, 1));
    if ($a === '' && $b === '') {
        return '?';
    }

    return $a . $b;
};

$statusMeta = static function (string $st): array {
    return match ($st) {
        'submitted' => [
            'class' => 'bg-amber-50 text-amber-950 ring-amber-200/80 border-amber-200',
            'bar' => 'bg-amber-500',
            'label' => 'À traiter',
        ],
        'rejected' => [
            'class' => 'bg-rose-50 text-rose-950 ring-rose-200/80 border-rose-200',
            'bar' => 'bg-rose-500',
            'label' => 'Refusée',
        ],
        'blocked' => [
            'class' => 'bg-slate-800 text-white ring-slate-600 border-slate-700',
            'bar' => 'bg-slate-600',
            'label' => 'Non admis',
        ],
        'reviewed' => [
            'class' => 'bg-emerald-50 text-emerald-950 ring-emerald-200/80 border-emerald-200',
            'bar' => 'bg-emerald-600',
            'label' => 'Acceptée',
        ],
        default => [
            'class' => 'bg-stone-100 text-stone-800 ring-stone-200 border-stone-200',
            'bar' => 'bg-stone-400',
            'label' => 'Statut à vérifier',
        ],
    };
};

$filterLink = static function (?string $key, ?string $current, string $label, int $count, string $baseUrl): string {
    $active = ($key === null && $current === null) || ($key !== null && $current === $key);
    $href = $key === null ? $baseUrl : $baseUrl . '?status=' . rawurlencode($key);
    $cls = $active
        ? 'border-slate-900 bg-slate-900 text-white shadow-sm focus:outline-none focus-visible:ring-2 focus-visible:ring-sky-500/50 focus-visible:ring-offset-2'
        : 'border-slate-300 bg-slate-100 text-slate-900 hover:border-slate-400 hover:bg-slate-200/90 focus:outline-none focus-visible:ring-2 focus-visible:ring-slate-400 focus-visible:ring-offset-2';

    return sprintf(
        '<a href="%s" class="inline-flex min-h-[2.25rem] items-center gap-2 rounded-lg border px-3 py-1.5 text-[11px] font-bold uppercase tracking-wide transition %s">%s%s</a>',
        htmlspecialchars($href, ENT_QUOTES, 'UTF-8'),
        $cls,
        htmlspecialchars($label, ENT_QUOTES, 'UTF-8'),
        $count > 0
            ? '<span class="' . ($active ? 'bg-white/15 text-white' : 'bg-slate-200 text-slate-900') . ' min-w-[1.35rem] rounded-md px-1.5 py-0.5 text-center text-[10px] font-black tabular-nums">' . $count . '</span>'
            : ''
    );
};

$baseList = url('back-office/recruitments');

$submittedViaLabel = static function (string $raw): string {
    $v = strtolower(trim($raw));

    return match ($v) {
        'guest' => 'Invité',
        'account' => 'Compte connecté',
        'preset' => 'Profil enregistré',
        '' => '—',
        default => 'Autre canal',
    };
};

$formatHoursShort = static function (?int $hours): string {
    if ($hours === null) {
        return '—';
    }
    if ($hours < 24) {
        return $hours . ' h';
    }
    $days = intdiv($hours, 24);
    $rem = $hours % 24;

    return $rem > 0 ? $days . ' j ' . $rem . ' h' : $days . ' j';
};

?>
<style>
    .recruitment-bureau .recruitment-sla-save {
        background-color: #0f172a !important;
        border-color: #0f172a !important;
        color: #ffffff !important;
    }
    .recruitment-bureau .recruitment-sla-save:hover {
        background-color: #1e293b !important;
        border-color: #1e293b !important;
    }
    .recruitment-bureau .recruitment-bureau__view-table {
        display: none;
    }
    .recruitment-bureau .recruitment-bureau__view-cards {
        display: block;
        list-style: none;
        margin: 0;
        padding: 0;
    }
    @media (min-width: 768px) {
        .recruitment-bureau .recruitment-bureau__view-table {
            display: block;
        }
        .recruitment-bureau .recruitment-bureau__view-cards {
            display: none;
        }
    }

    /* Grille dense façon tableur */
    .recruitment-sheets {
        border: 1px solid #cbd5e1;
        background: #ffffff;
        overflow: auto;
        max-height: min(70vh, 52rem);
    }
    .recruitment-sheets__table {
        width: 100%;
        min-width: 88rem;
        border-collapse: separate;
        border-spacing: 0;
        font-size: 0.8125rem;
        line-height: 1.35;
    }
    .recruitment-sheets__table th,
    .recruitment-sheets__table td {
        border-right: 1px solid #e2e8f0;
        border-bottom: 1px solid #e2e8f0;
        padding: 0.4rem 0.55rem;
        vertical-align: middle;
    }
    .recruitment-sheets__table th:last-child,
    .recruitment-sheets__table td:last-child {
        border-right: 0;
    }
    .recruitment-sheets__table thead th {
        position: sticky;
        top: 0;
        z-index: 2;
        background: #f1f5f9;
        color: #475569;
        font-size: 0.625rem;
        font-weight: 800;
        letter-spacing: 0.12em;
        text-transform: uppercase;
        white-space: nowrap;
        border-bottom: 1px solid #94a3b8;
        box-shadow: 0 1px 0 #94a3b8;
    }
    .recruitment-sheets__table tbody tr:nth-child(even) td {
        background: #f8fafc;
    }
    .recruitment-sheets__table tbody tr:hover td {
        background: #eff6ff;
    }
    .recruitment-sheets__badge {
        display: inline-flex;
        align-items: center;
        gap: 0.25rem;
        border-radius: 0.25rem;
        border: 1px solid transparent;
        padding: 0.1rem 0.4rem;
        font-size: 0.6875rem;
        font-weight: 800;
        letter-spacing: 0.02em;
        white-space: nowrap;
    }
    .recruitment-sheets__badge--ok {
        background: #ecfdf5;
        border-color: #a7f3d0;
        color: #065f46;
    }
    .recruitment-sheets__badge--watch {
        background: #fffbeb;
        border-color: #fde68a;
        color: #92400e;
    }
    .recruitment-sheets__badge--late {
        background: #fff1f2;
        border-color: #fecdd3;
        color: #9f1239;
    }
    .recruitment-sheets__badge--muted {
        background: #f1f5f9;
        border-color: #e2e8f0;
        color: #64748b;
    }
    .recruitment-sheets__badge--done {
        background: #ecfdf5;
        border-color: #a7f3d0;
        color: #047857;
    }
    .recruitment-sheets__meta {
        display: block;
        margin-top: 0.1rem;
        font-size: 0.6875rem;
        color: #64748b;
        font-variant-numeric: tabular-nums;
    }
    /* KPI : classes Tailwind grid-cols-3 / sm:grid-cols-6 absentes du build → grille CSS autonome */
    .recruitment-queue-kpi {
        display: grid;
        grid-template-columns: repeat(2, minmax(0, 1fr));
        gap: 0.5rem;
    }
    @media (min-width: 640px) {
        .recruitment-queue-kpi {
            grid-template-columns: repeat(3, minmax(0, 1fr));
        }
    }
    @media (min-width: 1024px) {
        .recruitment-queue-kpi {
            grid-template-columns: repeat(6, minmax(0, 1fr));
        }
    }
    .recruitment-queue-kpi__card {
        min-width: 0;
        border-radius: 0.5rem;
        border: 1px solid #e7e5e4;
        background: #fff;
        padding: 0.5rem 0.65rem;
    }
    .recruitment-queue-kpi__label {
        margin: 0;
        font-size: 0.5625rem;
        font-weight: 800;
        letter-spacing: 0.16em;
        text-transform: uppercase;
        color: #78716c;
    }
    .recruitment-queue-kpi__value {
        margin: 0.15rem 0 0;
        font-size: 1.125rem;
        font-weight: 900;
        font-variant-numeric: tabular-nums;
        letter-spacing: -0.02em;
        color: #1c1917;
        line-height: 1.15;
    }
</style>
<div class="recruitment-bureau recruitment-bureau--queue space-y-5 w-full max-w-none">
        <?php if ($staffRetrosDueCount > 0): ?>
        <div class="flex flex-wrap items-center justify-between gap-3 rounded-lg border border-amber-200 bg-amber-50 px-3 py-2.5" role="status">
            <p class="text-sm font-semibold text-amber-950">
                <?= $staffRetrosDueCount === 1
                    ? '1 dossier a plus de 30 jours sans bilan d’équipe.'
                    : $staffRetrosDueCount . ' dossiers ont plus de 30 jours sans bilan d’équipe.' ?>
                <span class="font-normal text-amber-900/80">La colonne Bilan les signale aussi ligne par ligne.</span>
            </p>
            <a href="<?= htmlspecialchars($staffRetrosDueFirstId > 0 ? url('back-office/recruitments/' . $staffRetrosDueFirstId . '?dossier=1#bilan-recrutement') : url('back-office/recruitments'), ENT_QUOTES, 'UTF-8') ?>" class="inline-flex min-h-[2.25rem] items-center rounded-lg bg-emerald-600 px-3 text-[11px] font-bold uppercase tracking-wide text-white transition hover:bg-emerald-500">Renseigner un bilan</a>
        </div>
        <?php endif; ?>

        <div class="overflow-hidden rounded-xl border border-stone-300/80 bg-white shadow-sm">
            <div class="border-b border-stone-200 bg-stone-50 px-4 py-4 sm:px-5 sm:py-5">
                <div class="flex flex-col gap-3 lg:flex-row lg:items-end lg:justify-between">
                    <div>
                        <p class="text-[10px] font-bold uppercase tracking-[0.28em] text-stone-500">Bureau recrutement</p>
                        <h1 class="mt-0.5 text-xl font-black tracking-tight text-stone-900 sm:text-2xl">Dossiers de candidature</h1>
                        <p class="mt-1.5 max-w-3xl text-sm leading-relaxed text-stone-600">
                            Tableau de suivi : délais d’action et bilans d’équipe visibles sur chaque ligne.
                        </p>
                    </div>
                    <div class="flex flex-wrap gap-1.5">
                        <a href="<?= htmlspecialchars(url('enlistment')) ?>" class="inline-flex min-h-[2.25rem] items-center rounded-lg border border-stone-300 bg-white px-2.5 py-1.5 text-[10px] font-bold uppercase tracking-[0.14em] text-stone-800 shadow-sm transition hover:border-emerald-400/60 hover:bg-emerald-50/50">
                            Formulaire public
                        </a>
                        <?php if (can('invitations.send') || can('admin.organization') || can('admin.access')): ?>
                        <a href="<?= htmlspecialchars(url('back-office/invitations')) ?>" class="inline-flex min-h-[2.25rem] items-center rounded-lg border border-amber-300 bg-amber-50 px-2.5 py-1.5 text-[10px] font-bold uppercase tracking-[0.14em] text-amber-950 shadow-sm transition hover:bg-amber-100">
                            Invitations
                        </a>
                        <?php endif; ?>
                        <a href="<?= htmlspecialchars(url('back-office/recruitments/messages-prefaits')) ?>" class="inline-flex min-h-[2.25rem] items-center rounded-lg border border-slate-300 bg-slate-100 px-2.5 py-1.5 text-[10px] font-bold uppercase tracking-[0.14em] text-slate-900 shadow-sm transition hover:bg-slate-200">
                            Modèles de texte
                        </a>
                        <a href="<?= htmlspecialchars(url('back-office/recruitments/equipe')) ?>" class="inline-flex min-h-[2.25rem] items-center rounded-lg border border-sky-300 bg-sky-50 px-2.5 py-1.5 text-[10px] font-bold uppercase tracking-[0.14em] text-sky-950 shadow-sm transition hover:bg-sky-100">
                            Fil recruteurs
                        </a>
                        <a href="<?= htmlspecialchars(url('back-office/recruitments/settings')) ?>" class="inline-flex min-h-[2.25rem] items-center rounded-lg border border-stone-900 bg-stone-900 px-2.5 py-1.5 text-[10px] font-bold uppercase tracking-[0.14em] text-white shadow-sm transition hover:bg-stone-800">
                            Délais d’alerte
                        </a>
                    </div>
                </div>
            </div>

            <div class="border-b border-stone-200 bg-white px-3 py-4 sm:px-5">
                <div class="recruitment-queue-kpi" role="group" aria-label="Indicateurs de la file">
                    <div class="recruitment-queue-kpi__card">
                        <p class="recruitment-queue-kpi__label">Total</p>
                        <p class="recruitment-queue-kpi__value"><?= $nTotal ?></p>
                    </div>
                    <div class="recruitment-queue-kpi__card" style="border-color:#fde68a;background:#fffbeb;">
                        <p class="recruitment-queue-kpi__label" style="color:#92400e;">À traiter</p>
                        <p class="recruitment-queue-kpi__value" style="color:#78350f;"><?= $nSubmitted ?></p>
                    </div>
                    <div class="recruitment-queue-kpi__card" style="<?= $submittedOlderThanSla > 0 ? 'border-color:#fecaca;background:#fff1f2;' : 'border-color:#bae6fd;background:#f0f9ff;' ?>">
                        <p class="recruitment-queue-kpi__label" style="<?= $submittedOlderThanSla > 0 ? 'color:#9f1239;' : 'color:#075985;' ?>">Hors délai</p>
                        <p class="recruitment-queue-kpi__value" style="<?= $submittedOlderThanSla > 0 ? 'color:#881337;' : 'color:#0c4a6e;' ?>"><?= $submittedOlderThanSla ?></p>
                    </div>
                    <div class="recruitment-queue-kpi__card" style="border-color:#a7f3d0;background:#ecfdf5;">
                        <p class="recruitment-queue-kpi__label" style="color:#065f46;">Acceptées</p>
                        <p class="recruitment-queue-kpi__value" style="color:#064e3b;"><?= $nReviewed ?></p>
                    </div>
                    <div class="recruitment-queue-kpi__card" style="border-color:#fecdd3;background:#fff1f2;">
                        <p class="recruitment-queue-kpi__label" style="color:#9f1239;">Refusées</p>
                        <p class="recruitment-queue-kpi__value" style="color:#881337;"><?= $nRejected ?></p>
                    </div>
                    <div class="recruitment-queue-kpi__card" style="border-color:#d6d3d1;background:#fafaf9;">
                        <p class="recruitment-queue-kpi__label">Non admis</p>
                        <p class="recruitment-queue-kpi__value"><?= $nBlocked ?></p>
                    </div>
                </div>

                <div class="mt-4 flex flex-wrap items-end justify-between gap-3 border-t border-stone-200/80 pt-4">
                    <div class="flex flex-wrap gap-2">
                        <?= $filterLink(null, $statusFilter, 'Tous', $nTotal, $baseList) ?>
                        <?= $filterLink('submitted', $statusFilter, 'À traiter', $nSubmitted, $baseList) ?>
                        <?= $filterLink('reviewed', $statusFilter, 'Acceptées', $nReviewed, $baseList) ?>
                        <?= $filterLink('rejected', $statusFilter, 'Refusées', $nRejected, $baseList) ?>
                        <?= $filterLink('blocked', $statusFilter, 'Non admis', $nBlocked, $baseList) ?>
                    </div>

                    <form method="post" action="<?= htmlspecialchars(url('back-office/recruitments/settings')) ?>" class="flex flex-wrap items-end gap-2 rounded-lg border border-stone-200 bg-stone-50/90 px-3 py-2">
                        <?= \App\Core\Csrf::field() ?>
                        <div>
                            <label for="enlistment-sla-hours" class="block text-[10px] font-bold uppercase tracking-wide text-stone-600">Délai d’alerte (heures)</label>
                            <input
                                type="number"
                                id="enlistment-sla-hours"
                                name="enlistment_sla_hours"
                                min="1"
                                max="720"
                                value="<?= $enlistmentSlaHours ?>"
                                class="mt-1 w-[5.5rem] rounded-md border border-stone-300 bg-white px-2 py-1.5 text-sm font-semibold text-stone-900 shadow-inner focus:border-[#1c4d6e] focus:outline-none focus:ring-2 focus:ring-[#1c4d6e]/20"
                            >
                        </div>
                        <button type="submit" class="recruitment-sla-save inline-flex min-h-[2.25rem] items-center justify-center rounded-lg border border-slate-900 bg-slate-900 px-3 py-1.5 text-[10px] font-bold uppercase tracking-wide text-white shadow-sm transition hover:bg-slate-800">Enregistrer</button>
                        <a href="<?= htmlspecialchars(url('back-office/recruitments/settings')) ?>" class="inline-flex min-h-[2.25rem] items-center justify-center rounded-lg border border-slate-300 bg-white px-3 py-1.5 text-[10px] font-bold uppercase tracking-wide text-slate-900 transition hover:bg-slate-100">Détail</a>
                    </form>
                </div>
            </div>

            <div class="px-0 py-0 sm:px-0">
                <?php if (empty($enlistments)): ?>
                    <div class="m-4 rounded-xl border-2 border-dashed border-stone-300 bg-[#faf8f3] px-6 py-12 text-center sm:m-6">
                        <p class="text-lg font-extrabold text-stone-900">Aucun dossier<?= $statusFilter ? ' pour ce filtre' : '' ?></p>
                        <p class="mt-2 mx-auto max-w-md text-sm text-stone-600">
                            Les candidatures reçues depuis la page d’enrôlement apparaîtront ici.
                        </p>
                        <a href="<?= htmlspecialchars(url('enlistment')) ?>" class="mt-6 inline-flex items-center rounded-lg border border-stone-900 bg-stone-900 px-5 py-2.5 text-xs font-bold uppercase tracking-wider text-white shadow-sm transition hover:bg-stone-800">
                            Voir le formulaire public
                        </a>
                    </div>
                <?php else: ?>
                    <div class="recruitment-bureau__view-table recruitment-sheets">
                        <table class="recruitment-sheets__table text-left">
                            <thead>
                                <tr>
                                    <th>Réception</th>
                                    <th>Candidat</th>
                                    <th>Contact</th>
                                    <th>État</th>
                                    <th>Instruit par</th>
                                    <th>Affectation</th>
                                    <th>Rôle</th>
                                    <th>Dernière action</th>
                                    <th>Délai / alerte</th>
                                    <th>Bilan</th>
                                    <th>Lien compte</th>
                                    <th class="text-right">Ouvrir</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($enlistments as $e): ?>
                                    <?php
                                    $st = (string) ($e['status'] ?? '');
                                    $meta = $statusMeta($st);
                                    $fid = (int) ($e['id'] ?? 0);
                                    $fn = (string) ($e['first_name'] ?? '');
                                    $ln = (string) ($e['last_name'] ?? '');
                                    $full = trim($fn . ' ' . $ln) ?: '—';
                                    $isSubmitted = $st === 'submitted';
                                    $slaBreached = !empty($e['submitted_sla_breached']);
                                    $ageHours = isset($e['submitted_age_hours']) ? (int) $e['submitted_age_hours'] : null;
                                    $ageDays = isset($e['enlistment_age_days']) ? (int) $e['enlistment_age_days'] : null;
                                    $lastActionAt = trim((string) ($e['last_action_at'] ?? ''));
                                    $retroStatus = (string) ($e['staff_retro_status'] ?? 'waiting');
                                    $retroDoneAt = trim((string) ($e['staff_retro_done_at'] ?? ''));
                                    $instructorLabel = trim((string) ($e['instructor_label'] ?? ''));
                                    $assignmentUnit = trim((string) ($e['assignment_unit_label'] ?? ''));
                                    $assignmentRole = trim((string) ($e['assignment_role_label'] ?? ''));
                                    $assignmentOpening = trim((string) ($e['assignment_opening_title'] ?? ''));
                                    $defineUrl = trim((string) ($e['assignment_define_url'] ?? ''));
                                    if ($defineUrl === '') {
                                        $defineUrl = url('back-office/recruitments/' . $fid . '?dossier=1#coordination-dossier');
                                    }

                                    $slaLabel = 'Sans objet';
                                    $slaBadge = 'recruitment-sheets__badge--muted';
                                    $slaMeta = '';
                                    if ($isSubmitted && $ageHours !== null) {
                                        if ($slaBreached) {
                                            $over = max(0, $ageHours - $enlistmentSlaHours);
                                            $slaLabel = 'En retard';
                                            $slaBadge = 'recruitment-sheets__badge--late';
                                            $slaMeta = $formatHoursShort($ageHours) . ' · seuil ' . $enlistmentSlaHours . ' h (+' . $formatHoursShort($over) . ')';
                                        } else {
                                            $remaining = max(0, $enlistmentSlaHours - $ageHours);
                                            $watchFrom = (int) max(0, $enlistmentSlaHours - max(1, (int) ceil($enlistmentSlaHours / 4)));
                                            if ($ageHours >= $watchFrom) {
                                                $slaLabel = 'À surveiller';
                                                $slaBadge = 'recruitment-sheets__badge--watch';
                                            } else {
                                                $slaLabel = 'OK';
                                                $slaBadge = 'recruitment-sheets__badge--ok';
                                            }
                                            $slaMeta = $formatHoursShort($ageHours) . ' / ' . $enlistmentSlaHours . ' h · reste ' . $formatHoursShort($remaining);
                                        }
                                    } elseif (!$isSubmitted) {
                                        $slaMeta = 'Dossier déjà instruit';
                                    }

                                    $bilanLabel = '—';
                                    $bilanBadge = 'recruitment-sheets__badge--muted';
                                    $bilanMeta = '';
                                    $bilanHref = null;
                                    if ($retroStatus === 'done') {
                                        $bilanLabel = 'Fait';
                                        $bilanBadge = 'recruitment-sheets__badge--done';
                                        if ($retroDoneAt !== '') {
                                            $bilanMeta = 'Le ' . date('d/m/Y', strtotime($retroDoneAt) ?: time());
                                        } elseif ($ageDays !== null) {
                                            $bilanMeta = $ageDays . ' j depuis réception';
                                        }
                                    } elseif ($retroStatus === 'due') {
                                        $bilanLabel = 'À faire';
                                        $bilanBadge = 'recruitment-sheets__badge--late';
                                        $bilanMeta = ($ageDays !== null ? $ageDays . ' j' : '30 j+') . ' sans bilan';
                                        $bilanHref = url('back-office/recruitments/' . $fid . '?dossier=1#bilan-recrutement');
                                    } elseif ($retroStatus === 'not_applicable') {
                                        $bilanLabel = 'Non concerné';
                                        $bilanBadge = 'recruitment-sheets__badge--muted';
                                        $bilanMeta = $st === 'blocked' ? 'Candidature non admise' : 'Candidature refusée';
                                    } elseif ($retroStatus === 'waiting') {
                                        $bilanLabel = 'Pas encore';
                                        $bilanBadge = 'recruitment-sheets__badge--muted';
                                        $daysLeft = $ageDays !== null ? max(0, 30 - $ageDays) : 30;
                                        $bilanMeta = 'Dans ' . $daysLeft . ' j';
                                    } elseif ($retroStatus === 'unavailable') {
                                        $bilanLabel = 'Indisponible';
                                        $bilanBadge = 'recruitment-sheets__badge--muted';
                                    }
                                    ?>
                                    <tr>
                                        <td class="whitespace-nowrap text-stone-700 tabular-nums">
                                            <?= !empty($e['created_at']) ? htmlspecialchars(date('d/m/Y', strtotime((string) $e['created_at']))) : '—' ?>
                                            <span class="recruitment-sheets__meta"><?= !empty($e['created_at']) ? htmlspecialchars(date('H:i', strtotime((string) $e['created_at']))) : '' ?></span>
                                        </td>
                                        <td>
                                            <div class="flex items-center gap-2 min-w-0">
                                                <span class="flex h-7 w-7 shrink-0 items-center justify-center rounded border border-stone-200 bg-stone-100 text-[10px] font-black text-stone-800"><?= htmlspecialchars($initials($fn, $ln)) ?></span>
                                                <span class="font-semibold text-stone-900 truncate" title="<?= htmlspecialchars($full) ?>"><?= htmlspecialchars($full) ?></span>
                                            </div>
                                        </td>
                                        <td class="max-w-[12rem] truncate text-stone-700" title="<?= htmlspecialchars((string) ($e['email'] ?? '')) ?>"><?= htmlspecialchars((string) ($e['email'] ?? '—')) ?></td>
                                        <td>
                                            <span class="inline-flex items-center rounded border px-1.5 py-0.5 text-[11px] font-bold ring-1 <?= htmlspecialchars($meta['class']) ?>"><?= htmlspecialchars($meta['label']) ?></span>
                                        </td>
                                        <td>
                                            <?php if ($instructorLabel !== ''): ?>
                                                <span class="font-semibold text-stone-900"><?= htmlspecialchars($instructorLabel) ?></span>
                                            <?php else: ?>
                                                <span class="text-stone-400">—</span>
                                                <form method="post" action="<?= htmlspecialchars(url('back-office/recruitments/' . $fid . '/referent'), ENT_QUOTES, 'UTF-8') ?>" class="mt-1">
                                                    <?= \App\Core\Csrf::field() ?>
                                                    <button type="submit" class="text-[10px] font-bold uppercase tracking-wide text-[#059669] underline decoration-[#059669]/35 underline-offset-2 hover:decoration-[#059669]">Me désigner</button>
                                                </form>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <?php if ($assignmentUnit !== ''): ?>
                                                <span class="font-semibold text-stone-900"><?= htmlspecialchars($assignmentUnit) ?></span>
                                                <?php if ($assignmentOpening !== ''): ?>
                                                    <span class="recruitment-sheets__meta truncate max-w-[10rem]" title="<?= htmlspecialchars($assignmentOpening) ?>"><?= htmlspecialchars($assignmentOpening) ?></span>
                                                <?php endif; ?>
                                            <?php else: ?>
                                                <span class="text-stone-400">—</span>
                                                <a href="<?= htmlspecialchars($defineUrl, ENT_QUOTES, 'UTF-8') ?>" class="recruitment-sheets__meta font-bold text-[#059669] underline decoration-[#059669]/30 underline-offset-2 hover:decoration-[#059669]">Définir</a>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <?php if ($assignmentRole !== ''): ?>
                                                <span class="font-semibold text-stone-900"><?= htmlspecialchars($assignmentRole) ?></span>
                                            <?php else: ?>
                                                <span class="text-stone-400">—</span>
                                                <a href="<?= htmlspecialchars($defineUrl, ENT_QUOTES, 'UTF-8') ?>" class="recruitment-sheets__meta font-bold text-[#059669] underline decoration-[#059669]/30 underline-offset-2 hover:decoration-[#059669]">Définir</a>
                                            <?php endif; ?>
                                        </td>
                                        <td class="whitespace-nowrap text-stone-700 tabular-nums">
                                            <?php if ($lastActionAt !== ''): ?>
                                                <?= htmlspecialchars(date('d/m/Y H:i', strtotime($lastActionAt))) ?>
                                            <?php else: ?>
                                                —
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <span class="recruitment-sheets__badge <?= $slaBadge ?>"><?= htmlspecialchars($slaLabel) ?></span>
                                            <?php if ($slaMeta !== ''): ?>
                                                <span class="recruitment-sheets__meta"><?= htmlspecialchars($slaMeta) ?></span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <?php if ($bilanHref !== null): ?>
                                                <a href="<?= htmlspecialchars($bilanHref, ENT_QUOTES, 'UTF-8') ?>" class="recruitment-sheets__badge <?= $bilanBadge ?> hover:opacity-90"><?= htmlspecialchars($bilanLabel) ?></a>
                                            <?php else: ?>
                                                <span class="recruitment-sheets__badge <?= $bilanBadge ?>"><?= htmlspecialchars($bilanLabel) ?></span>
                                            <?php endif; ?>
                                            <?php if ($bilanMeta !== ''): ?>
                                                <span class="recruitment-sheets__meta"><?= htmlspecialchars($bilanMeta) ?></span>
                                            <?php endif; ?>
                                        </td>
                                        <td class="text-sm">
                                            <?php if (!empty($e['submitter_user_id'])): ?>
                                                <a href="<?= htmlspecialchars(url('personnel/' . (int) $e['submitter_user_id'])) ?>" class="font-semibold text-[#1c4d6e] underline decoration-[#1c4d6e]/30 underline-offset-2 hover:decoration-[#1c4d6e]">Fiche membre</a>
                                                <span class="recruitment-sheets__meta"><?= htmlspecialchars($submittedViaLabel((string) ($e['submitted_via'] ?? ''))) ?></span>
                                            <?php else: ?>
                                                <span class="text-stone-500">Invité</span>
                                            <?php endif; ?>
                                        </td>
                                        <td class="text-right whitespace-nowrap">
                                            <a href="<?= htmlspecialchars(url('back-office/recruitments/' . $fid . '?dossier=1'), ENT_QUOTES, 'UTF-8') ?>" class="inline-flex items-center gap-1 rounded border border-stone-300 bg-white px-2 py-1 text-[11px] font-bold uppercase tracking-wide text-stone-900 shadow-sm transition hover:border-emerald-400/50 hover:bg-emerald-50/40">
                                                Ouvrir
                                                <span aria-hidden="true">→</span>
                                            </a>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>

                    <ul class="recruitment-bureau__view-cards space-y-2 p-3">
                        <?php foreach ($enlistments as $e): ?>
                            <?php
                            $st = (string) ($e['status'] ?? '');
                            $meta = $statusMeta($st);
                            $fid = (int) ($e['id'] ?? 0);
                            $fn = (string) ($e['first_name'] ?? '');
                            $ln = (string) ($e['last_name'] ?? '');
                            $full = trim($fn . ' ' . $ln) ?: '—';
                            $isSubmitted = $st === 'submitted';
                            $slaBreached = !empty($e['submitted_sla_breached']);
                            $ageHours = isset($e['submitted_age_hours']) ? (int) $e['submitted_age_hours'] : null;
                            $ageDays = isset($e['enlistment_age_days']) ? (int) $e['enlistment_age_days'] : null;
                            $retroStatus = (string) ($e['staff_retro_status'] ?? 'waiting');
                            ?>
                            <li class="overflow-hidden rounded-xl border border-stone-200 bg-white shadow-sm">
                                <div class="h-1 <?= htmlspecialchars($meta['bar']) ?>" aria-hidden="true"></div>
                                <div class="flex gap-3 p-3">
                                    <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-lg border border-stone-200 bg-stone-100 text-xs font-black text-stone-800"><?= htmlspecialchars($initials($fn, $ln)) ?></span>
                                    <div class="min-w-0 flex-1">
                                        <div class="flex flex-wrap items-start justify-between gap-2">
                                            <div>
                                                <p class="font-bold text-stone-900"><?= htmlspecialchars($full) ?></p>
                                                <p class="text-xs text-stone-500 tabular-nums"><?= !empty($e['created_at']) ? htmlspecialchars(date('d/m/Y H:i', strtotime((string) $e['created_at']))) : '—' ?></p>
                                            </div>
                                            <span class="shrink-0 rounded border px-2 py-0.5 text-[10px] font-bold uppercase tracking-wide ring-1 <?= htmlspecialchars($meta['class']) ?>"><?= htmlspecialchars($meta['label']) ?></span>
                                        </div>
                                        <div class="mt-2 flex flex-wrap gap-1.5">
                                            <?php if ($isSubmitted && $ageHours !== null): ?>
                                                <span class="recruitment-sheets__badge <?= $slaBreached ? 'recruitment-sheets__badge--late' : 'recruitment-sheets__badge--ok' ?>">
                                                    <?= $slaBreached ? 'Délai dépassé' : 'Délai OK' ?> · <?= htmlspecialchars($formatHoursShort($ageHours)) ?>
                                                </span>
                                            <?php endif; ?>
                                            <?php if ($retroStatus === 'due'): ?>
                                                <span class="recruitment-sheets__badge recruitment-sheets__badge--late">Bilan à faire<?= $ageDays !== null ? ' · ' . $ageDays . ' j' : '' ?></span>
                                            <?php elseif ($retroStatus === 'done'): ?>
                                                <span class="recruitment-sheets__badge recruitment-sheets__badge--done">Bilan fait<?= !empty($e['staff_retro_done_at']) ? ' · ' . date('d/m', strtotime((string) $e['staff_retro_done_at']) ?: time()) : '' ?></span>
                                            <?php endif; ?>
                                            <?php if (trim((string) ($e['instructor_label'] ?? '')) !== ''): ?>
                                                <span class="recruitment-sheets__badge recruitment-sheets__badge--muted">Instruit par <?= htmlspecialchars(trim((string) $e['instructor_label'])) ?></span>
                                            <?php endif; ?>
                                            <?php if (trim((string) ($e['assignment_unit_label'] ?? '')) !== '' || trim((string) ($e['assignment_role_label'] ?? '')) !== ''): ?>
                                                <span class="recruitment-sheets__badge recruitment-sheets__badge--muted">
                                                    <?= htmlspecialchars(trim(implode(' · ', array_filter([
                                                        trim((string) ($e['assignment_unit_label'] ?? '')),
                                                        trim((string) ($e['assignment_role_label'] ?? '')),
                                                    ])))) ?>
                                                </span>
                                            <?php endif; ?>
                                        </div>
                                        <p class="mt-2 truncate text-sm text-stone-600"><?= htmlspecialchars((string) ($e['email'] ?? '—')) ?></p>
                                        <a href="<?= htmlspecialchars(url('back-office/recruitments/' . $fid . '?dossier=1'), ENT_QUOTES, 'UTF-8') ?>" class="mt-3 inline-flex w-full items-center justify-center rounded-lg border border-stone-900 bg-stone-900 py-2 text-xs font-bold uppercase tracking-wide text-white shadow-sm transition hover:bg-stone-800">Consulter le dossier</a>
                                    </div>
                                </div>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                <?php endif; ?>
            </div>
        </div>

</div>
