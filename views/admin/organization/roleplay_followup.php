<?php
$rpFeatureEnabled = !empty($rpFeatureEnabled);
$rpRows = is_array($rpRows ?? null) ? $rpRows : [];
$rpConfig = is_array($rpConfig ?? null) ? $rpConfig : [];
$rpTrackedCount = (int) ($rpTrackedCount ?? 0);
$rpEligibleCount = (int) ($rpEligibleCount ?? 0);
$rpTotalActiveMembers = (int) ($rpTotalActiveMembers ?? count($rpRows));
$rpTimelineTableReady = !empty($rpTimelineTableReady);

$timelineStatusFr = static function (?string $raw): string {
    return match (trim((string) $raw)) {
        'planned' => 'Prévu',
        'completed' => 'Terminé',
        'blocked' => 'Bloqué',
        'cancelled' => 'Annulé',
        '' => '—',
        default => trim((string) $raw),
    };
};
?>
<style>
    .rp-followup-sheets {
        border: 1px solid #cbd5e1;
        background: #ffffff;
        overflow: auto;
        max-height: min(72vh, 56rem);
    }
    .rp-followup-sheets__table {
        width: 100%;
        min-width: 72rem;
        border-collapse: separate;
        border-spacing: 0;
        font-size: 0.8125rem;
        line-height: 1.35;
    }
    .rp-followup-sheets__table th,
    .rp-followup-sheets__table td {
        border-right: 1px solid #e2e8f0;
        border-bottom: 1px solid #e2e8f0;
        padding: 0.35rem 0.5rem;
        vertical-align: middle;
    }
    .rp-followup-sheets__table th:last-child,
    .rp-followup-sheets__table td:last-child {
        border-right: 0;
    }
    .rp-followup-sheets__table thead th {
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
    .rp-followup-sheets__table tbody tr:nth-child(even) td {
        background: #f8fafc;
    }
    .rp-followup-sheets__table tbody tr:hover td {
        background: #eff6ff;
    }
    .rp-followup-sheets__meta {
        display: block;
        margin-top: 0.05rem;
        font-size: 0.6875rem;
        color: #64748b;
        font-variant-numeric: tabular-nums;
    }
    .rp-followup-sheets__badge {
        display: inline-flex;
        align-items: center;
        border-radius: 0.25rem;
        border: 1px solid transparent;
        padding: 0.1rem 0.4rem;
        font-size: 0.6875rem;
        font-weight: 800;
        letter-spacing: 0.02em;
        white-space: nowrap;
    }
    .rp-followup-sheets__badge--ok {
        background: #ecfdf5;
        border-color: #a7f3d0;
        color: #065f46;
    }
    .rp-followup-sheets__badge--watch {
        background: #fffbeb;
        border-color: #fde68a;
        color: #92400e;
    }
    .rp-followup-sheets__badge--late {
        background: #fff1f2;
        border-color: #fecdd3;
        color: #9f1239;
    }
    .rp-followup-sheets__badge--muted {
        background: #f1f5f9;
        border-color: #e2e8f0;
        color: #64748b;
    }
    .rp-followup-kpi {
        display: grid;
        grid-template-columns: repeat(2, minmax(0, 1fr));
        gap: 0.5rem;
    }
    @media (min-width: 640px) {
        .rp-followup-kpi {
            grid-template-columns: repeat(4, minmax(0, 1fr));
        }
    }
    .rp-followup-kpi__card {
        min-width: 0;
        border-radius: 0.5rem;
        border: 1px solid #e2e8f0;
        background: #fff;
        padding: 0.45rem 0.65rem;
    }
    .rp-followup-kpi__label {
        margin: 0;
        font-size: 0.5625rem;
        font-weight: 800;
        letter-spacing: 0.14em;
        text-transform: uppercase;
        color: #64748b;
    }
    .rp-followup-kpi__value {
        margin: 0.15rem 0 0;
        font-size: 1.125rem;
        font-weight: 900;
        font-variant-numeric: tabular-nums;
        letter-spacing: -0.02em;
        color: #0f172a;
        line-height: 1.15;
    }
</style>

<div class="rp-followup-bureau flex min-h-0 w-full max-w-none flex-1 flex-col bg-slate-50">
    <div class="shrink-0 border-b border-slate-200 bg-white px-3 py-2.5 sm:px-4">
        <div class="flex flex-wrap items-center justify-between gap-2">
            <div class="min-w-0">
                <h1 class="truncate text-base font-black tracking-tight text-slate-900 sm:text-lg">Suivi roleplay</h1>
                <p class="text-xs text-slate-500">Tutorat, avancement et échéances — trié par échéance la plus proche.</p>
            </div>
            <div class="flex flex-wrap items-center gap-1.5">
                <a href="<?= htmlspecialchars(url('back-office/configuration'), ENT_QUOTES, 'UTF-8') ?>" class="inline-flex h-8 items-center rounded border border-slate-300 bg-white px-2.5 text-xs font-semibold text-slate-700 hover:bg-slate-50">Configurer le module</a>
                <a href="<?= htmlspecialchars(url('back-office/users'), ENT_QUOTES, 'UTF-8') ?>" class="inline-flex h-8 items-center rounded border border-slate-300 bg-white px-2.5 text-xs font-semibold text-slate-700 hover:bg-slate-50">Gérer les membres</a>
            </div>
        </div>
    </div>

    <div class="shrink-0 border-b border-slate-200 bg-slate-50/90 px-3 py-2.5 sm:px-4">
        <div class="rp-followup-kpi" role="group" aria-label="Indicateurs de suivi">
            <div class="rp-followup-kpi__card" style="<?= $rpFeatureEnabled ? 'border-color:#a7f3d0;background:#ecfdf5;' : 'border-color:#fecdd3;background:#fff1f2;' ?>">
                <p class="rp-followup-kpi__label" style="<?= $rpFeatureEnabled ? 'color:#065f46;' : 'color:#9f1239;' ?>">Statut module</p>
                <p class="rp-followup-kpi__value" style="<?= $rpFeatureEnabled ? 'color:#064e3b;' : 'color:#881337;' ?>"><?= $rpFeatureEnabled ? 'Actif' : 'Désactivé' ?></p>
            </div>
            <div class="rp-followup-kpi__card">
                <p class="rp-followup-kpi__label">Membres actifs</p>
                <p class="rp-followup-kpi__value"><?= $rpTotalActiveMembers ?></p>
            </div>
            <div class="rp-followup-kpi__card">
                <p class="rp-followup-kpi__label">Dossiers suivis</p>
                <p class="rp-followup-kpi__value"><?= $rpTrackedCount ?></p>
            </div>
            <div class="rp-followup-kpi__card">
                <p class="rp-followup-kpi__label">Éligibles</p>
                <p class="rp-followup-kpi__value"><?= $rpEligibleCount ?></p>
            </div>
        </div>
    </div>

    <?php if (!$rpTimelineTableReady): ?>
    <div class="shrink-0 border-b border-amber-200 bg-amber-50 px-3 py-2 text-xs text-amber-950 sm:px-4" role="status">
        L’historique des événements roleplay n’est pas encore disponible. Demandez à l’équipe technique d’appliquer les mises à jour de la base pour activer cette fonctionnalité.
    </div>
    <?php endif; ?>

    <div class="min-h-0 flex-1 px-0">
        <?php if ($rpRows === []): ?>
        <div class="m-4 rounded-lg border border-dashed border-slate-300 bg-white px-6 py-12 text-center sm:m-6">
            <p class="text-sm font-semibold text-slate-800">Aucun membre actif pour cette communauté.</p>
        </div>
        <?php else: ?>
        <div class="rp-followup-sheets" role="region" aria-label="Tableau de suivi roleplay">
            <table class="rp-followup-sheets__table text-left">
                <thead>
                    <tr>
                        <th>Membre</th>
                        <th>Tutorat</th>
                        <th>Avancement</th>
                        <th>Fonction</th>
                        <th>Filière</th>
                        <th>Échéance</th>
                        <th>Éligibilité</th>
                        <th>Dernier événement</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($rpRows as $row):
                        $name = trim((string) ($row['display_name'] ?? ''));
                        if ($name === '') {
                            $name = trim((string) ($row['callsign'] ?? ''));
                        }
                        $nextDue = $row['next_due'] ? date('d/m/Y', strtotime((string) $row['next_due'])) : '—';
                        $latest = is_array($row['latest_timeline'] ?? null) ? $row['latest_timeline'] : null;
                        $fnCell = trim((string) ($row['function'] ?? ''));
                        $originRaw = trim((string) ($row['origin'] ?? ''));
                        $originFr = match ($originRaw) {
                            'internal' => 'Interne',
                            'external' => 'Externe',
                            default => '',
                        };
                        $stageLabel = trim((string) ($row['stage'] ?? ''));
                        $statusLabel = trim((string) ($row['status'] ?? ''));
                        $dueBadge = !empty($row['next_due_is_overdue'])
                            ? 'rp-followup-sheets__badge--late'
                            : 'rp-followup-sheets__badge--muted';
                        $eligBadge = !empty($row['eligible'])
                            ? 'rp-followup-sheets__badge--ok'
                            : 'rp-followup-sheets__badge--watch';
                    ?>
                    <tr>
                        <td>
                            <span class="font-semibold text-slate-900"><?= htmlspecialchars($name !== '' ? $name : ('Compte n°' . (int) $row['user_id']), ENT_QUOTES, 'UTF-8') ?></span>
                            <span class="rp-followup-sheets__meta">
                                Étape : <?= htmlspecialchars($stageLabel !== '' ? $stageLabel : '—', ENT_QUOTES, 'UTF-8') ?>
                                <?php if ($statusLabel !== ''): ?> · <?= htmlspecialchars($statusLabel, ENT_QUOTES, 'UTF-8') ?><?php endif; ?>
                            </span>
                        </td>
                        <td class="text-slate-700"><?= htmlspecialchars((string) ($row['tutor_label'] ?: '—'), ENT_QUOTES, 'UTF-8') ?></td>
                        <td>
                            <?php if ($row['progress'] !== null): ?>
                            <div class="min-w-[5.5rem]">
                                <div class="h-1.5 overflow-hidden rounded-sm bg-slate-100"><div class="h-full bg-emerald-500" style="width: <?= max(0, min(100, (int) $row['progress'])) ?>%"></div></div>
                                <span class="rp-followup-sheets__meta"><?= (int) $row['progress'] ?> %</span>
                            </div>
                            <?php else: ?><span class="text-slate-500">—</span><?php endif; ?>
                        </td>
                        <td class="text-slate-700"><?= htmlspecialchars($fnCell !== '' ? $fnCell : '—', ENT_QUOTES, 'UTF-8') ?></td>
                        <td class="text-slate-700">
                            <span class="font-medium"><?= htmlspecialchars((string) ($row['track'] !== '' ? $row['track'] : '—'), ENT_QUOTES, 'UTF-8') ?></span>
                            <?php if ($originFr !== ''): ?><span class="rp-followup-sheets__meta">Profil : <?= htmlspecialchars($originFr, ENT_QUOTES, 'UTF-8') ?></span><?php endif; ?>
                        </td>
                        <td>
                            <span class="rp-followup-sheets__badge <?= $dueBadge ?>"><?= htmlspecialchars($nextDue, ENT_QUOTES, 'UTF-8') ?></span>
                        </td>
                        <td>
                            <span class="rp-followup-sheets__badge <?= $eligBadge ?>"><?= !empty($row['eligible']) ? 'Éligible' : 'À compléter' ?></span>
                        </td>
                        <td class="text-slate-700">
                            <?php if ($latest): ?>
                            <span class="font-medium text-slate-800"><?= htmlspecialchars((string) ($latest['title'] ?? '—'), ENT_QUOTES, 'UTF-8') ?></span>
                            <span class="rp-followup-sheets__meta">
                                <?= htmlspecialchars($timelineStatusFr((string) ($latest['status'] ?? 'planned')), ENT_QUOTES, 'UTF-8') ?>
                                <?php if (!empty($latest['event_date'])): ?> · <?= htmlspecialchars(date('d/m/Y', strtotime((string) $latest['event_date'])), ENT_QUOTES, 'UTF-8') ?><?php endif; ?>
                            </span>
                            <?php else: ?><span class="text-slate-500">—</span><?php endif; ?>
                        </td>
                        <td>
                            <a href="<?= htmlspecialchars(url('personnel/' . (int) $row['user_id'] . '/edit'), ENT_QUOTES, 'UTF-8') ?>" class="text-xs font-bold text-emerald-800 underline decoration-emerald-200 underline-offset-2 hover:text-emerald-950">Ouvrir dossier</a>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php endif; ?>
    </div>
</div>
