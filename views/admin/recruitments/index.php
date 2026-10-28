<?php
declare(strict_types=1);
$enlistments = $enlistments ?? [];
$statusFilter = $statusFilter ?? null;
$stageFilter = $stageFilter ?? null;
$stageCounts = is_array($stageCounts ?? null) ? $stageCounts : [];
$nInterview = (int) ($stageCounts['interview_scheduled'] ?? 0);
$nOnHold = (int) ($stageCounts['on_hold'] ?? 0);
$counts = is_array($enlistmentCounts ?? null) ? $enlistmentCounts : [];
$nSubmitted = (int) ($counts['submitted'] ?? 0);
$nReviewed = (int) ($counts['reviewed'] ?? 0);
$nRejected = (int) ($counts['rejected'] ?? 0);
$nBlocked = (int) ($counts['blocked'] ?? 0);
$nCancelled = (int) ($counts['cancelled'] ?? 0);
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
        'cancelled' => [
            'class' => 'bg-stone-100 text-stone-700 ring-stone-200 border-stone-200',
            'bar' => 'bg-stone-400',
            'label' => 'Annulée',
        ],
        default => [
            'class' => 'bg-stone-100 text-stone-800 ring-stone-200 border-stone-200',
            'bar' => 'bg-stone-400',
            'label' => 'Statut à vérifier',
        ],
    };
};

// Étape de pipeline (affinage visuel de l'État) : distingue « Entretien proposé » / « Mise en attente »
// d'un simple « À traiter », sans changer le statut réel du dossier (toujours `submitted` en base).
$stageMeta = static function (string $stage) use ($statusMeta): array {
    return match ($stage) {
        'interview_scheduled' => [
            'class' => 'bg-sky-50 text-sky-950 ring-sky-200/80 border-sky-200',
            'bar' => 'bg-sky-500',
            'label' => 'Entretien proposé',
        ],
        'on_hold' => [
            'class' => 'bg-orange-50 text-orange-950 ring-orange-200/80 border-orange-200',
            'bar' => 'bg-orange-500',
            'label' => 'Mise en attente',
        ],
        // Accepté avec intégration à finaliser : c’est une candidature acceptée, pas un statut inconnu.
        'accepted', 'acceptance_onboarding' => $statusMeta('reviewed'),
        default => $statusMeta($stage),
    };
};

$filterLink = static function (?string $key, ?string $current, string $label, int $count, string $baseUrl): string {
    $active = ($key === null && $current === null) || ($key !== null && $current === $key);
    $href = $key === null ? $baseUrl . '?vue=liste' : $baseUrl . '?status=' . rawurlencode($key);
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

$stageFilterLink = static function (string $key, ?string $current, string $label, int $count, string $baseUrl): string {
    $active = $current === $key;
    $href = $baseUrl . '?stage=' . rawurlencode($key);
    $cls = $active
        ? 'border-sky-900 bg-sky-900 text-white shadow-sm focus:outline-none focus-visible:ring-2 focus-visible:ring-sky-500/50 focus-visible:ring-offset-2'
        : 'border-sky-300 bg-sky-50 text-sky-900 hover:border-sky-400 hover:bg-sky-100 focus:outline-none focus-visible:ring-2 focus-visible:ring-sky-400 focus-visible:ring-offset-2';

    return sprintf(
        '<a href="%s" class="inline-flex min-h-[2.25rem] items-center gap-2 rounded-lg border px-3 py-1.5 text-[11px] font-bold uppercase tracking-wide transition %s">%s%s</a>',
        htmlspecialchars($href, ENT_QUOTES, 'UTF-8'),
        $cls,
        htmlspecialchars($label, ENT_QUOTES, 'UTF-8'),
        $count > 0
            ? '<span class="' . ($active ? 'bg-white/15 text-white' : 'bg-sky-200 text-sky-900') . ' min-w-[1.35rem] rounded-md px-1.5 py-0.5 text-center text-[10px] font-black tabular-nums">' . $count . '</span>'
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

$pipelineView = (string) ($pipelineView ?? 'liste');
$pipelineFilter = (string) ($pipelineFilter ?? '');
$pipelineSearch = (string) ($pipelineSearch ?? '');
$pipelineCounts = is_array($pipelineCounts ?? null) ? $pipelineCounts : ['mine' => 0, 'libre' => 0, 'retard' => 0, 'active' => 0, 'interviews' => 0, 'retro' => 0];
$pipelineColumns = is_array($pipelineColumns ?? null) ? $pipelineColumns : [];
$pipelineOffBoard = (int) ($pipelineOffBoard ?? 0);
$pipelineState = array_filter([
    'vue' => $pipelineView,
    'filtre' => $pipelineFilter,
    'q' => $pipelineSearch,
    'status' => $pipelineView === 'liste' ? (string) ($statusFilter ?? '') : '',
    'stage' => $pipelineView === 'liste' ? (string) ($stageFilter ?? '') : '',
], static fn (string $v): bool => $v !== '');
$pipelineReturnQs = http_build_query($pipelineState);
$qLink = static fn (array $override): string => \App\Support\EnlistmentPipelineBoard::link($baseList, $pipelineState, $override);
$h = static fn (mixed $v): string => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
$rpInitials = $initials;
$rpNow = (int) ($nowTs ?? time());
$rpAgo = static function (string $sql) use ($rpNow): string {
    $ts = $sql !== '' ? strtotime($sql) : false;
    if ($ts === false) {
        return '—';
    }
    $m = max(0, intdiv($rpNow - $ts, 60));
    if ($m < 60) {
        return $m < 2 ? 'à l’instant' : 'il y a ' . $m . ' min';
    }
    $hh = intdiv($m, 60);
    if ($hh < 24) {
        return 'il y a ' . $hh . ' h';
    }
    $d = intdiv($hh, 24);

    return $d < 45 ? 'il y a ' . $d . ' j' : 'le ' . date('d/m/Y', $ts);
};
$listBase = static fn (array $o): string => $qLink(['vue' => 'liste'] + $o);
$baseListForPills = $baseList;
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
<div class="recruitment-bureau recruitment-bureau--queue rp w-full max-w-none">
    <header class="rp-head">
        <div>
            <p class="rp-head__eyebrow">Bureau recrutement</p>
            <h1 class="rp-head__title">File des candidatures</h1>
            <p class="rp-head__lead">Chaque dossier est rangé selon la prochaine action à mener. Prenez ce qui n’a pas de recruteur, traitez d’abord ce qui est en retard.</p>
        </div>
        <nav class="rp-head__tools" aria-label="Outils du bureau">
            <a class="rp-tool" href="<?= $h(url('enlistment')) ?>"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" aria-hidden="true"><path d="M14 4h6v6M20 4l-9 9M18 14v5a1 1 0 0 1-1 1H5a1 1 0 0 1-1-1V7a1 1 0 0 1 1-1h5"/></svg>Formulaire public</a>
            <?php if (can('invitations.send') || can('admin.organization') || can('admin.access')): ?>
                <a class="rp-tool" href="<?= $h(url('back-office/invitations')) ?>"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" aria-hidden="true"><rect x="3" y="5" width="18" height="14" rx="2"/><path d="m3 7 9 6 9-6"/></svg>Invitations</a>
            <?php endif; ?>
            <a class="rp-tool" href="<?= $h(url('back-office/recruitments/messages-prefaits')) ?>"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" aria-hidden="true"><path d="M4 5h16v11H8l-4 4z"/></svg>Modèles de texte</a>
            <a class="rp-tool" href="<?= $h(url('back-office/recruitments/equipe')) ?>"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" aria-hidden="true"><circle cx="9" cy="8" r="3"/><path d="M3 19c0-3 3-5 6-5s6 2 6 5M16 5a3 3 0 0 1 0 6M21 19c0-2-1.5-3.6-3.5-4.4"/></svg>Fil recruteurs</a>
            <details class="rp-sla">
                <summary class="rp-tool"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" aria-hidden="true"><circle cx="12" cy="13" r="8"/><path d="M12 9v4l2.5 2.5M9 2h6"/></svg>Alerte à <?= $enlistmentSlaHours ?> h</summary>
                <form class="rp-sla__panel" method="post" action="<?= $h(url('back-office/recruitments/settings')) ?>">
                    <?= \App\Core\Csrf::field() ?>
                    <label for="enlistment-sla-hours">Délai d’alerte (heures)</label>
                    <div class="rp-sla__row">
                        <input type="number" id="enlistment-sla-hours" name="enlistment_sla_hours" min="1" max="720" value="<?= $enlistmentSlaHours ?>">
                        <button type="submit" class="rp-btn recruitment-sla-save">Enregistrer</button>
                    </div>
                    <p class="rp-sla__hint">Un dossier sans action depuis plus longtemps passe « En retard ». <a href="<?= $h(url('back-office/recruitments/settings')) ?>">Tous les réglages →</a></p>
                </form>
            </details>
        </nav>
    </header>

    <?php
    $kpiFilter = static function (string $key) use ($pipelineFilter, $qLink): string {
        return $qLink(['filtre' => $pipelineFilter === $key ? null : $key]);
    };
    ?>
    <div class="rp-focus" role="group" aria-label="Ce qui demande votre attention">
        <a class="rp-kpi rp-kpi--you<?= $pipelineFilter === 'mine' ? ' is-active' : '' ?>" href="<?= $h($kpiFilter('mine')) ?>">
            <span class="rp-kpi__label">Mes dossiers</span>
            <span class="rp-kpi__value"><?= (int) $pipelineCounts['mine'] ?></span>
            <span class="rp-kpi__hint"><?= $pipelineFilter === 'mine' ? 'Filtre actif · retirer' : 'Dont je suis le référent' ?></span>
        </a>
        <a class="rp-kpi rp-kpi--take<?= (int) $pipelineCounts['libre'] > 0 ? ' is-hot' : '' ?><?= $pipelineFilter === 'libre' ? ' is-active' : '' ?>" href="<?= $h($kpiFilter('libre')) ?>">
            <span class="rp-kpi__label">Sans recruteur</span>
            <span class="rp-kpi__value"><?= (int) $pipelineCounts['libre'] ?></span>
            <span class="rp-kpi__hint"><?= $pipelineFilter === 'libre' ? 'Filtre actif · retirer' : 'À prendre en charge' ?></span>
        </a>
        <a class="rp-kpi rp-kpi--late<?= (int) $pipelineCounts['retard'] > 0 ? ' is-hot' : '' ?><?= $pipelineFilter === 'retard' ? ' is-active' : '' ?>" href="<?= $h($kpiFilter('retard')) ?>">
            <span class="rp-kpi__label">En retard</span>
            <span class="rp-kpi__value"><?= (int) $pipelineCounts['retard'] ?></span>
            <span class="rp-kpi__hint"><?= $pipelineFilter === 'retard' ? 'Filtre actif · retirer' : 'Seuil de ' . $enlistmentSlaHours . ' h dépassé' ?></span>
        </a>
        <a class="rp-kpi rp-kpi--soon" href="<?= $h($qLink(['vue' => 'tableau', 'status' => null, 'stage' => null]) . '#col-interview') ?>">
            <span class="rp-kpi__label">Entretiens</span>
            <span class="rp-kpi__value"><?= (int) $pipelineCounts['interviews'] ?></span>
            <span class="rp-kpi__hint">À tenir ou à conclure</span>
        </a>
        <a class="rp-kpi rp-kpi--ok" href="<?= $h($staffRetrosDueFirstId > 0 ? url('back-office/recruitments/' . $staffRetrosDueFirstId . '?dossier=1#bilan-recrutement') : $qLink(['vue' => 'tableau', 'status' => null, 'stage' => null]) . '#col-retro') ?>">
            <span class="rp-kpi__label">Bilans 30 j dus</span>
            <span class="rp-kpi__value"><?= max((int) $pipelineCounts['retro'], $staffRetrosDueCount) ?></span>
            <span class="rp-kpi__hint"><?= $staffRetrosDueFirstId > 0 ? 'Ouvrir le premier' : 'Aucun en attente' ?></span>
        </a>
        <div class="rp-kpi">
            <span class="rp-kpi__label">En cours</span>
            <span class="rp-kpi__value"><?= (int) $pipelineCounts['active'] ?></span>
            <span class="rp-kpi__hint">Sur <?= $nTotal ?> dossiers reçus</span>
        </div>
    </div>

    <div class="rp-bar">
        <div class="rp-seg" role="tablist" aria-label="Affichage">
            <a role="tab" aria-selected="<?= $pipelineView === 'tableau' ? 'true' : 'false' ?>" class="<?= $pipelineView === 'tableau' ? 'is-active' : '' ?>" href="<?= $h($qLink(['vue' => 'tableau', 'status' => null, 'stage' => null])) ?>"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><rect x="3" y="4" width="5" height="16" rx="1"/><rect x="10" y="4" width="5" height="11" rx="1"/><rect x="17" y="4" width="4" height="7" rx="1"/></svg>Tableau</a>
            <a role="tab" aria-selected="<?= $pipelineView === 'liste' ? 'true' : 'false' ?>" class="<?= $pipelineView === 'liste' ? 'is-active' : '' ?>" href="<?= $h($qLink(['vue' => 'liste'])) ?>"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" aria-hidden="true"><path d="M8 6h13M8 12h13M8 18h13M3 6h.01M3 12h.01M3 18h.01"/></svg>Liste complète</a>
        </div>
        <form class="rp-search" method="get" action="<?= $h($baseList) ?>" role="search">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" aria-hidden="true"><circle cx="11" cy="11" r="7"/><path d="m20 20-3.5-3.5"/></svg>
            <?php foreach (['vue', 'filtre', 'status', 'stage'] as $keep): ?>
                <?php if (isset($pipelineState[$keep])): ?><input type="hidden" name="<?= $keep ?>" value="<?= $h($pipelineState[$keep]) ?>"><?php endif; ?>
            <?php endforeach; ?>
            <input type="search" name="q" value="<?= $h($pipelineSearch) ?>" placeholder="Rechercher un nom, un e-mail, un n° de dossier…" aria-label="Rechercher un dossier">
        </form>
        <?php if ($pipelineFilter !== '' || $pipelineSearch !== ''): ?>
            <div class="rp-chips">
                <?php if ($pipelineFilter !== ''): ?>
                    <a class="rp-chip is-active" href="<?= $h($qLink(['filtre' => null])) ?>"><?= $h(\App\Support\EnlistmentPipelineBoard::FILTERS[$pipelineFilter]) ?> <span aria-hidden="true">×</span></a>
                <?php endif; ?>
                <?php if ($pipelineSearch !== ''): ?>
                    <a class="rp-chip is-active" href="<?= $h($qLink(['q' => null])) ?>">« <?= $h($pipelineSearch) ?> » <span aria-hidden="true">×</span></a>
                <?php endif; ?>
                <a class="rp-chip rp-chip--clear" href="<?= $h($qLink(['filtre' => null, 'q' => null])) ?>">Tout effacer</a>
            </div>
        <?php endif; ?>
    </div>

    <?php if ($pipelineView === 'tableau'): ?>
        <?php $boardTotal = array_sum(array_map(static fn (array $c): int => count($c['cards']), $pipelineColumns)); ?>
        <?php if ($boardTotal === 0 && ($pipelineFilter !== '' || $pipelineSearch !== '')): ?>
            <div class="rp-empty">
                <strong>Aucun dossier ne correspond</strong>
                <p>Le filtre ou la recherche ne laisse aucun dossier en cours. Les dossiers clos restent consultables dans la liste complète.</p>
                <a class="rp-btn rp-btn--ghost" href="<?= $h($qLink(['filtre' => null, 'q' => null])) ?>">Effacer les filtres</a>
            </div>
        <?php elseif ($boardTotal === 0): ?>
            <div class="rp-empty">
                <strong>Rien à traiter</strong>
                <p>Aucun dossier n’attend d’action. Les candidatures reçues depuis la page d’enrôlement apparaîtront ici, dans la colonne « À prendre ».</p>
                <a class="rp-btn" href="<?= $h(url('enlistment')) ?>">Voir le formulaire public</a>
            </div>
        <?php else: ?>
            <div class="rp-board" aria-label="Dossiers par prochaine action">
                <?php foreach ($pipelineColumns as $col): ?>
                    <section class="rp-col rp-col--<?= $h($col['key']) ?>" id="col-<?= $h($col['key']) ?>" aria-labelledby="col-<?= $h($col['key']) ?>-t">
                        <header class="rp-col__head">
                            <div>
                                <h2 class="rp-col__title" id="col-<?= $h($col['key']) ?>-t"><?= $h($col['label']) ?></h2>
                                <p class="rp-col__hint"><?= $h($col['hint']) ?></p>
                                <?php if ((int) $col['late'] > 0): ?><span class="rp-col__late"><?= (int) $col['late'] ?> en retard</span><?php endif; ?>
                            </div>
                            <span class="rp-col__count"><?= count($col['cards']) ?></span>
                        </header>
                        <?php if ($col['cards'] === []): ?>
                            <p class="rp-col__empty">Rien ici.</p>
                        <?php endif; ?>
                        <?php foreach ($col['cards'] as $card): ?>
                            <?php require base_path('views/admin/recruitments/partials/pipeline_card.php'); ?>
                        <?php endforeach; ?>
                    </section>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
        <?php if ($pipelineOffBoard > 0): ?>
            <p class="rp-offboard">
                <span><?= $pipelineOffBoard ?> dossier<?= $pipelineOffBoard > 1 ? 's' : '' ?> sans action en attente (refusés, annulés, intégrés) ne figure<?= $pipelineOffBoard > 1 ? 'nt' : '' ?> pas sur le tableau.</span>
                <a href="<?= $h($qLink(['vue' => 'liste'])) ?>">Liste complète →</a>
            </p>
        <?php endif; ?>
    <?php else: ?>
        <div class="overflow-hidden rounded-xl border border-stone-300/80 bg-white shadow-sm">
            <div class="border-b border-stone-200 bg-white px-3 py-3 sm:px-5">
                <?php $statusCurrentForPills = $stageFilter === null ? $statusFilter : '__stage_active__'; ?>
                <div class="flex flex-wrap gap-2">
                    <?= $filterLink(null, $statusCurrentForPills, 'Tous', $nTotal, $baseListForPills) ?>
                    <?= $filterLink('submitted', $statusCurrentForPills, 'À traiter', $nSubmitted, $baseListForPills) ?>
                    <?= $stageFilterLink('interview_scheduled', $stageFilter, 'Entretien', $nInterview, $baseListForPills) ?>
                    <?= $stageFilterLink('on_hold', $stageFilter, 'En attente', $nOnHold, $baseListForPills) ?>
                    <?= $filterLink('reviewed', $statusCurrentForPills, 'Acceptées', $nReviewed, $baseListForPills) ?>
                    <?= $filterLink('rejected', $statusCurrentForPills, 'Refusées', $nRejected, $baseListForPills) ?>
                    <?= $filterLink('blocked', $statusCurrentForPills, 'Non admis', $nBlocked, $baseListForPills) ?>
                    <?php if ($nCancelled > 0): ?>
                        <?= $filterLink('cancelled', $statusCurrentForPills, 'Annulées', $nCancelled, $baseListForPills) ?>
                    <?php endif; ?>
                </div>
            </div>
            <div class="px-0 py-0 sm:px-0">
                <?php if (empty($enlistments)): ?>
                    <div class="m-4 rounded-xl border-2 border-dashed border-stone-300 bg-[#faf8f3] px-6 py-12 text-center sm:m-6">
                        <p class="text-lg font-extrabold text-stone-900">Aucun dossier<?= ($statusFilter || $pipelineFilter !== '' || $pipelineSearch !== '') ? ' pour ce filtre' : '' ?></p>
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
                                    <th>État</th>
                                    <th>Prochaine action</th>
                                    <th>Instruit par</th>
                                    <th>Affectation</th>
                                    <th>Rôle</th>
                                    <th>Dernière action</th>
                                    <th>Délai</th>
                                    <th>Bilan 30 j</th>
                                    <th>Lien compte</th>
                                    <th class="text-right">Ouvrir</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($enlistments as $e): ?>
                                    <?php
                                    $st = (string) ($e['status'] ?? '');
                                    $stage = \App\Repositories\EnlistmentRepository::effectivePipelineStage($e);
                                    $meta = $stageMeta($stage);
                                    $fid = (int) ($e['id'] ?? 0);
                                    $fn = (string) ($e['first_name'] ?? '');
                                    $ln = (string) ($e['last_name'] ?? '');
                                    $full = trim($fn . ' ' . $ln) ?: '—';
                                    $lastActionAt = trim((string) ($e['last_action_at'] ?? ''));
                                    $retroStatus = (string) ($e['staff_retro_status'] ?? 'waiting');
                                    $instructorLabel = trim((string) ($e['instructor_label'] ?? ''));
                                    $assignmentUnit = trim((string) ($e['assignment_unit_label'] ?? ''));
                                    $assignmentRole = trim((string) ($e['assignment_role_label'] ?? ''));
                                    $assignmentOpening = trim((string) ($e['assignment_opening_title'] ?? ''));
                                    $defineUrl = trim((string) ($e['assignment_define_url'] ?? ''));
                                    if ($defineUrl === '') {
                                        $defineUrl = url('back-office/recruitments/' . $fid . '?dossier=1#coordination-dossier');
                                    }

                                    // Délai et bilan : calculés une fois par EnlistmentStaffRowEnricher (mêmes règles que le tableau de bord).
                                    $sheetBadge = static fn (string $tone): string => match ($tone) {
                                        'emerald' => 'recruitment-sheets__badge--ok',
                                        'amber', 'orange' => 'recruitment-sheets__badge--watch',
                                        'rose' => 'recruitment-sheets__badge--late',
                                        default => 'recruitment-sheets__badge--muted',
                                    };
                                    $delay = is_array($e['delay'] ?? null) ? $e['delay'] : ['label' => '—', 'tone' => 'muted', 'meta' => ''];
                                    $slaLabel = (string) $delay['label'];
                                    $slaBadge = $sheetBadge((string) $delay['tone']);
                                    $slaMeta = (string) $delay['meta'];

                                    $retro = is_array($e['retro'] ?? null) ? $e['retro'] : ['label' => '—', 'tone' => 'muted', 'meta' => '', 'actionable' => false];
                                    $bilanLabel = (string) $retro['label'];
                                    $bilanBadge = $retroStatus === 'done' ? 'recruitment-sheets__badge--done' : $sheetBadge((string) $retro['tone']);
                                    $bilanMeta = (string) $retro['meta'];
                                    $bilanHref = !empty($retro['actionable']) ? url('back-office/recruitments/' . $fid . '?dossier=1#bilan-recrutement') : null;
                                    $stageInfo = is_array($e['stage'] ?? null) ? $e['stage'] : null;
                                    ?>
                                    <tr>
                                        <td class="whitespace-nowrap text-stone-700 tabular-nums">
                                            <?= !empty($e['created_at']) ? htmlspecialchars(date('d/m/Y', strtotime((string) $e['created_at']))) : '—' ?>
                                            <span class="recruitment-sheets__meta"><?= !empty($e['created_at']) ? htmlspecialchars(date('H:i', strtotime((string) $e['created_at']))) : '' ?></span>
                                        </td>
                                        <td>
                                            <div class="flex items-center gap-2 min-w-0">
                                                <span class="flex h-7 w-7 shrink-0 items-center justify-center rounded border border-stone-200 bg-stone-100 text-[10px] font-black text-stone-800"><?= htmlspecialchars($initials($fn, $ln)) ?></span>
                                                <span class="min-w-0">
                                                    <a href="<?= htmlspecialchars(url('back-office/recruitments/' . $fid . '?dossier=1'), ENT_QUOTES, 'UTF-8') ?>" class="block font-semibold text-stone-900 truncate hover:underline" title="<?= htmlspecialchars($full) ?>"><?= htmlspecialchars($full) ?></a>
                                                    <span class="recruitment-sheets__meta truncate max-w-[14rem]" title="<?= htmlspecialchars((string) ($e['email'] ?? '')) ?>">#<?= $fid ?> · <?= htmlspecialchars((string) ($e['email'] ?? '—')) ?></span>
                                                </span>
                                            </div>
                                        </td>
                                        <td>
                                            <span class="inline-flex items-center rounded border px-1.5 py-0.5 text-[11px] font-bold ring-1 <?= htmlspecialchars($meta['class']) ?>"><?= htmlspecialchars($meta['label']) ?></span>
                                            <?php if ($stageInfo !== null && (int) $stageInfo['index'] > 0): ?>
                                                <span class="recruitment-sheets__meta">Étape <?= (int) $stageInfo['index'] ?>/<?= (int) $stageInfo['total'] ?> · <?= htmlspecialchars((string) $stageInfo['label']) ?></span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <?php $na = is_array($e['next_action'] ?? null) ? $e['next_action'] : null; ?>
                                            <?php if ($na !== null && $na['column'] !== null): ?>
                                                <span class="rp-next">
                                                    <b><?= htmlspecialchars((string) $na['label']) ?></b>
                                                    <?php if ((string) $na['urgencyLabel'] !== ''): ?><span class="rp-urg rp-urg--<?= htmlspecialchars((string) $na['urgency']) ?>"><?= htmlspecialchars((string) $na['urgencyLabel']) ?></span><?php endif; ?>
                                                    <a href="<?= htmlspecialchars(url('back-office/recruitments/' . $fid . '?dossier=1') . '#' . (string) $na['anchor'], ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars((string) $na['cta']) ?> →</a>
                                                </span>
                                            <?php else: ?>
                                                <span class="text-stone-400"><?= $na !== null ? htmlspecialchars((string) $na['label']) : 'Dossier clos' ?></span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <?php if ($instructorLabel !== ''): ?>
                                                <span class="font-semibold text-stone-900"><?= htmlspecialchars($instructorLabel) ?></span>
                                            <?php else: ?>
                                                <span class="text-stone-400">—</span>
                                                <form method="post" action="<?= htmlspecialchars(url('back-office/recruitments/' . $fid . '/referent'), ENT_QUOTES, 'UTF-8') ?>" class="mt-1">
                                                    <?= \App\Core\Csrf::field() ?>
                                                    <input type="hidden" name="return_qs" value="<?= htmlspecialchars($pipelineReturnQs, ENT_QUOTES, 'UTF-8') ?>">
                                                    <button type="submit" class="text-[10px] font-bold uppercase tracking-wide text-[#059669] underline decoration-[#059669]/35 underline-offset-2 hover:decoration-[#059669]">Me désigner</button>
                                                </form>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <?php if ($assignmentUnit !== ''): ?>
                                                <span class="font-semibold text-stone-900"><?= htmlspecialchars($assignmentUnit) ?></span>
                                                <?php if ((string) ($e['assignment_source'] ?? '') === 'member'): ?>
                                                    <span class="recruitment-sheets__meta">Unité actuelle du membre</span>
                                                <?php endif; ?>
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
                            $stage = \App\Repositories\EnlistmentRepository::effectivePipelineStage($e);
                            $meta = $stageMeta($stage);
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
                                                <a href="<?= htmlspecialchars(url('back-office/recruitments/' . $fid . '?dossier=1#bilan-recrutement'), ENT_QUOTES, 'UTF-8') ?>" class="recruitment-sheets__badge recruitment-sheets__badge--late">Bilan à faire · <?= htmlspecialchars((string) (($e['retro'] ?? [])['meta'] ?? '')) ?></a>
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
                                        <?php if (is_array($e['next_action'] ?? null) && $e['next_action']['column'] !== null): ?>
                                            <p class="mt-2 rp-next"><b><?= htmlspecialchars((string) $e['next_action']['label']) ?></b><?php if ((string) $e['next_action']['urgencyLabel'] !== ''): ?> <span class="rp-urg rp-urg--<?= htmlspecialchars((string) $e['next_action']['urgency']) ?>"><?= htmlspecialchars((string) $e['next_action']['urgencyLabel']) ?></span><?php endif; ?></p>
                                        <?php endif; ?>
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
    <?php endif; ?>
</div>
