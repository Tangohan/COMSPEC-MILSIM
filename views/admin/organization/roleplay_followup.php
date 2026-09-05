<?php
$rpFeatureEnabled = !empty($rpFeatureEnabled);
$rpRows = is_array($rpRows ?? null) ? $rpRows : [];
$rpConfig = is_array($rpConfig ?? null) ? $rpConfig : [];
$rpTrackedCount = (int) ($rpTrackedCount ?? 0);
$rpEligibleCount = (int) ($rpEligibleCount ?? 0);
$rpTotalActiveMembers = (int) ($rpTotalActiveMembers ?? count($rpRows));
$rpTimelineTableReady = !empty($rpTimelineTableReady);
$rpStagesOptions = is_array($rpStagesOptions ?? null) ? $rpStagesOptions : [];
$rpTracksOptions = is_array($rpTracksOptions ?? null) ? $rpTracksOptions : [];
$rpTutorChoices = is_array($rpTutorChoices ?? null) ? $rpTutorChoices : [];
$rpCsrfToken = (string) ($rpCsrfToken ?? '');
$err = \App\Core\Session::getFlash('error');
$ok = \App\Core\Session::getFlash('success');

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
        border: 1px solid #e2e8f0;
        border-radius: 1rem;
        background: #ffffff;
        overflow: auto;
        max-height: min(72vh, 56rem);
        box-shadow: 0 1px 2px rgba(15, 23, 42, 0.05);
    }
    .rp-followup-sheets__table {
        width: 100%;
        min-width: 86rem;
        border-collapse: separate;
        border-spacing: 0;
        font-size: 0.8125rem;
        line-height: 1.35;
    }
    .rp-followup-sheets__table th,
    .rp-followup-sheets__table td {
        border-right: 1px solid #e2e8f0;
        border-bottom: 1px solid #e2e8f0;
        padding: 0.8rem 0.75rem;
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
        background: #f8fafc;
        color: #64748b;
        font-size: 0.6875rem;
        font-weight: 800;
        letter-spacing: 0.12em;
        text-transform: uppercase;
        white-space: nowrap;
        border-bottom: 1px solid #e2e8f0;
    }
    .rp-followup-sheets__table tbody tr:nth-child(even) td {
        background: #fbfdff;
    }
    .rp-followup-sheets__table tbody tr:hover td {
        background: #f8fafc;
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
        gap: 1rem;
    }
    @media (min-width: 640px) {
        .rp-followup-kpi {
            grid-template-columns: repeat(4, minmax(0, 1fr));
        }
    }
    .rp-followup-kpi__card {
        min-width: 0;
        border-radius: 0.75rem;
        border: 1px solid #e2e8f0;
        background: #fff;
        padding: 1rem 1.1rem;
        box-shadow: 0 1px 2px rgba(15, 23, 42, 0.04);
    }
    .rp-followup-kpi__label {
        margin: 0;
        font-size: 0.625rem;
        font-weight: 800;
        letter-spacing: 0.14em;
        text-transform: uppercase;
        color: #64748b;
    }
    .rp-followup-kpi__value {
        margin: 0.4rem 0 0;
        font-size: 1.5rem;
        font-weight: 900;
        font-variant-numeric: tabular-nums;
        letter-spacing: -0.02em;
        color: #0f172a;
        line-height: 1.15;
    }
    .rp-followup-sheets__actions {
        display: flex;
        flex-wrap: wrap;
        align-items: center;
        gap: 0.3rem;
    }
    .rp-followup-sheets__actions a,
    .rp-followup-sheets__actions button,
    .rp-followup-sheets__actions summary.rp-followup-sheets__chip {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        gap: 0.25rem;
        min-height: 1.8rem;
        padding: 0.3rem 0.65rem;
        border-radius: 0.375rem;
        border: 1px solid #cbd5e1;
        background: #fff;
        color: #0f172a;
        font-size: 0.6875rem;
        font-weight: 700;
        text-decoration: none;
        white-space: nowrap;
        cursor: pointer;
        line-height: 1;
        font-family: inherit;
        list-style: none;
    }
    .rp-followup-sheets__actions summary.rp-followup-sheets__chip::-webkit-details-marker {
        display: none;
    }
    .rp-followup-sheets__actions a:hover,
    .rp-followup-sheets__actions button:hover,
    .rp-followup-sheets__actions summary.rp-followup-sheets__chip:hover {
        background: #f8fafc;
        border-color: #94a3b8;
    }
    .rp-followup-sheets__actions button.is-primary {
        background: #0f172a;
        border-color: #0f172a;
        color: #fff;
    }
    .rp-followup-sheets__actions button.is-primary:hover {
        background: #1e293b;
    }
    .rp-followup-sheets__pop {
        position: relative;
    }
    .rp-followup-sheets__table tbody tr:has(.rp-followup-sheets__pop[open]) td {
        position: relative;
        z-index: 6;
    }
    .rp-followup-sheets__pop-panel {
        position: absolute;
        z-index: 40;
        top: calc(100% + 0.35rem);
        right: 0;
        min-width: min(18rem, 90vw);
        max-width: min(20rem, 90vw);
        padding: 0.65rem;
        border: 1px solid #cbd5e1;
        border-radius: 0.5rem;
        background: #fff;
        box-shadow: 0 12px 32px rgba(15, 23, 42, 0.14);
        white-space: normal;
        overflow: visible;
    }
    .rp-followup-sheets__table tbody tr:last-child .rp-followup-sheets__pop-panel,
    .rp-followup-sheets__table tbody tr:nth-last-child(2) .rp-followup-sheets__pop-panel {
        top: auto;
        bottom: calc(100% + 0.35rem);
    }
    .rp-followup-sheets__pop-panel.is-ported {
        position: fixed;
        z-index: 80;
        right: auto;
        bottom: auto;
    }
    .rp-followup-sheets__pop-form {
        display: grid;
        gap: 0.4rem;
    }
    .rp-followup-sheets__pop-form label {
        margin: 0.1rem 0 0;
        font-size: 0.625rem;
        font-weight: 800;
        letter-spacing: 0.08em;
        text-transform: uppercase;
        color: #64748b;
    }
    .rp-followup-sheets__pop-form select {
        width: 100%;
        box-sizing: border-box;
        border: 1px solid #cbd5e1;
        border-radius: 0.35rem;
        background: #fff;
        color: #0f172a;
        padding: 0.4rem 0.5rem;
        font-size: 0.75rem;
        font-family: inherit;
    }
    .rp-followup-sheets__due-list {
        display: grid;
        gap: 0.1rem;
        font-size: 0.6875rem;
        color: #475569;
    }
    .rp-followup-sheets__due-list span.is-overdue {
        color: #9f1239;
        font-weight: 700;
    }
    .rp-followup-sheets__notes {
        display: block;
        max-width: 14rem;
        overflow: hidden;
        text-overflow: ellipsis;
        white-space: nowrap;
    }
</style>

<div class="rp-followup-bureau mx-auto w-full max-w-6xl space-y-6 px-4 py-8 sm:px-6">
    <header class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
        <p class="text-xs font-bold uppercase tracking-widest text-slate-500">Personnel · Immersion</p>
        <div class="mt-2 flex flex-col gap-5 lg:flex-row lg:items-end lg:justify-between">
            <div class="min-w-0">
                <h1 class="text-2xl font-black tracking-tight text-slate-900">Suivi roleplay</h1>
                <p class="mt-2 max-w-2xl text-sm leading-relaxed text-slate-600">Pilotez le tutorat, l’avancement et les échéances de chaque membre depuis un espace unique.</p>
            </div>
            <nav class="flex flex-wrap gap-2" aria-label="Actions du suivi roleplay">
                <a href="<?= htmlspecialchars(url('back-office/roleplay/immersion'), ENT_QUOTES, 'UTF-8') ?>" class="inline-flex items-center rounded-lg border border-slate-300 bg-white px-4 py-2 text-sm font-semibold text-slate-800 hover:bg-slate-50">Configurer le module</a>
                <a href="<?= htmlspecialchars(url('back-office/roleplay-followup/echeances'), ENT_QUOTES, 'UTF-8') ?>" class="inline-flex items-center rounded-lg border border-slate-300 bg-white px-4 py-2 text-sm font-semibold text-slate-800 hover:bg-slate-50">Gérer les échéances</a>
                <a href="<?= htmlspecialchars(url('back-office/users'), ENT_QUOTES, 'UTF-8') ?>" class="inline-flex items-center rounded-lg bg-slate-900 px-4 py-2 text-sm font-bold text-white hover:bg-slate-800">Gérer les membres</a>
            </nav>
        </div>
    </header>

    <?php if ($err): ?>
    <div class="rounded-xl border border-rose-200 bg-rose-50 px-4 py-3 text-sm font-medium text-rose-900"><?= htmlspecialchars($err, ENT_QUOTES, 'UTF-8') ?></div>
    <?php endif; ?>
    <?php if ($ok): ?>
    <div class="rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm font-medium text-emerald-900"><?= htmlspecialchars($ok, ENT_QUOTES, 'UTF-8') ?></div>
    <?php endif; ?>

    <section aria-labelledby="rp-overview-title">
        <h2 id="rp-overview-title" class="sr-only">Vue d’ensemble</h2>
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
    </section>

    <?php if (!$rpTimelineTableReady): ?>
    <div class="rounded-xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-950" role="status">
        L’historique des événements roleplay n’est pas encore disponible. Demandez à l’équipe technique d’appliquer les mises à jour de la base pour activer cette fonctionnalité.
    </div>
    <?php endif; ?>

    <section aria-labelledby="rp-members-title">
        <div class="mb-3 flex flex-wrap items-end justify-between gap-2 px-1">
            <div>
                <p class="text-xs font-bold uppercase tracking-widest text-slate-500">Dossiers membres</p>
                <h2 id="rp-members-title" class="mt-1 text-lg font-black text-slate-900">Suivi individuel</h2>
            </div>
            <p class="text-xs text-slate-500">Classés par échéance la plus proche</p>
        </div>
        <?php if ($rpRows === []): ?>
        <div class="rounded-2xl border border-dashed border-slate-300 bg-white px-6 py-12 text-center shadow-sm">
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
                        <th>Notes</th>
                        <th>Échéances</th>
                        <th>Bilan RP</th>
                        <th>Éligibilité</th>
                        <th>Dernier événement</th>
                        <th>Actions</th>
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
                        $notesCell = trim((string) ($row['notes'] ?? ''));
                        $dueBadge = !empty($row['next_due_is_overdue'])
                            ? 'rp-followup-sheets__badge--late'
                            : 'rp-followup-sheets__badge--muted';
                        $eligBadge = !empty($row['eligible'])
                            ? 'rp-followup-sheets__badge--ok'
                            : 'rp-followup-sheets__badge--watch';
                        $uid = (int) $row['user_id'];
                        $todayStr = date('Y-m-d');
                        $dueEntries = [
                            'Entretien' => $row['next_interview_date'] ?? null,
                            'Médical' => $row['medical_due_date'] ?? null,
                            'Rotation' => $row['service_rotation_date'] ?? null,
                        ];
                        $bilanNextDueRaw = $row['rp_bilan_next_due_at'] ?? null;
                        $bilanNextDue = $bilanNextDueRaw ? date('d/m/Y', strtotime((string) $bilanNextDueRaw)) : '—';
                        $bilanLastRaw = $row['rp_last_review_at'] ?? null;
                        $bilanLast = $bilanLastRaw ? date('d/m/Y', strtotime((string) $bilanLastRaw)) : null;
                        $bilanBadge = !empty($row['rp_bilan_overdue'])
                            ? 'rp-followup-sheets__badge--late'
                            : 'rp-followup-sheets__badge--muted';
                    ?>
                    <tr>
                        <td>
                            <span class="font-semibold text-slate-900"><?= htmlspecialchars($name !== '' ? $name : ('Compte n°' . $uid), ENT_QUOTES, 'UTF-8') ?></span>
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
                        <td class="text-slate-700">
                            <?php if ($notesCell !== ''): ?>
                            <span class="rp-followup-sheets__notes" title="<?= htmlspecialchars($notesCell, ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars($notesCell, ENT_QUOTES, 'UTF-8') ?></span>
                            <?php else: ?><span class="text-slate-500">—</span><?php endif; ?>
                        </td>
                        <td>
                            <span class="rp-followup-sheets__badge <?= $dueBadge ?>"><?= htmlspecialchars($nextDue, ENT_QUOTES, 'UTF-8') ?></span>
                            <div class="rp-followup-sheets__due-list">
                                <?php foreach ($dueEntries as $dueLabel => $dueRaw): if (!$dueRaw) { continue; } ?>
                                <span class="<?= $dueRaw < $todayStr ? 'is-overdue' : '' ?>"><?= htmlspecialchars($dueLabel, ENT_QUOTES, 'UTF-8') ?> : <?= htmlspecialchars(date('d/m/Y', strtotime((string) $dueRaw)), ENT_QUOTES, 'UTF-8') ?></span>
                                <?php endforeach; ?>
                            </div>
                        </td>
                        <td>
                            <span class="rp-followup-sheets__badge <?= $bilanBadge ?>"><?= htmlspecialchars($bilanNextDue, ENT_QUOTES, 'UTF-8') ?></span>
                            <div class="rp-followup-sheets__meta"><?= $bilanLast !== null ? 'Dernier bilan : ' . htmlspecialchars($bilanLast, ENT_QUOTES, 'UTF-8') : 'Aucun bilan enregistré' ?></div>
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
                            <div class="rp-followup-sheets__actions">
                                <details class="rp-followup-sheets__pop">
                                    <summary class="rp-followup-sheets__chip">Étape ▾</summary>
                                    <div class="rp-followup-sheets__pop-panel">
                                        <form method="post" action="<?= htmlspecialchars(url('back-office/roleplay-followup/' . $uid . '/stage'), ENT_QUOTES, 'UTF-8') ?>" class="rp-followup-sheets__pop-form">
                                            <input type="hidden" name="_csrf_token" value="<?= htmlspecialchars($rpCsrfToken, ENT_QUOTES, 'UTF-8') ?>">
                                            <label for="rp-stage-<?= $uid ?>">Nouvelle étape</label>
                                            <select name="rp_followup_stage" id="rp-stage-<?= $uid ?>">
                                                <option value="">— Non définie —</option>
                                                <?php foreach ($rpStagesOptions as $stOpt): $stOpt = trim((string) $stOpt); if ($stOpt === '') { continue; } ?>
                                                <option value="<?= htmlspecialchars($stOpt, ENT_QUOTES, 'UTF-8') ?>" <?= $stageLabel === $stOpt ? 'selected' : '' ?>><?= htmlspecialchars($stOpt, ENT_QUOTES, 'UTF-8') ?></option>
                                                <?php endforeach; ?>
                                            </select>
                                            <button type="submit" class="is-primary">Enregistrer</button>
                                        </form>
                                    </div>
                                </details>
                                <details class="rp-followup-sheets__pop">
                                    <summary class="rp-followup-sheets__chip">Tuteur ▾</summary>
                                    <div class="rp-followup-sheets__pop-panel">
                                        <form method="post" action="<?= htmlspecialchars(url('back-office/roleplay-followup/' . $uid . '/tutor'), ENT_QUOTES, 'UTF-8') ?>" class="rp-followup-sheets__pop-form">
                                            <input type="hidden" name="_csrf_token" value="<?= htmlspecialchars($rpCsrfToken, ENT_QUOTES, 'UTF-8') ?>">
                                            <label for="rp-tutor-<?= $uid ?>">Nouveau tuteur</label>
                                            <select name="rp_tutor_user_id" id="rp-tutor-<?= $uid ?>">
                                                <option value="">— Aucun —</option>
                                                <?php foreach ($rpTutorChoices as $tuOpt): ?>
                                                <option value="<?= (int) $tuOpt['id'] ?>" <?= (int) ($row['tutor_id'] ?? 0) === (int) $tuOpt['id'] ? 'selected' : '' ?>><?= htmlspecialchars((string) $tuOpt['label'], ENT_QUOTES, 'UTF-8') ?></option>
                                                <?php endforeach; ?>
                                            </select>
                                            <button type="submit" class="is-primary">Enregistrer</button>
                                        </form>
                                    </div>
                                </details>
                                <form method="post" action="<?= htmlspecialchars(url('back-office/roleplay-followup/' . $uid . '/validate'), ENT_QUOTES, 'UTF-8') ?>">
                                    <input type="hidden" name="_csrf_token" value="<?= htmlspecialchars($rpCsrfToken, ENT_QUOTES, 'UTF-8') ?>">
                                    <button type="submit" title="Marquer l’étape actuelle comme validée par l’encadrement">Valider</button>
                                </form>
                                <form method="post" action="<?= htmlspecialchars(url('back-office/roleplay-followup/' . $uid . '/bilan'), ENT_QUOTES, 'UTF-8') ?>">
                                    <input type="hidden" name="_csrf_token" value="<?= htmlspecialchars($rpCsrfToken, ENT_QUOTES, 'UTF-8') ?>">
                                    <button type="submit" title="Marquer le bilan roleplay comme fait aujourd’hui (recalcule la prochaine échéance)">Bilan fait</button>
                                </form>
                                <a href="<?= htmlspecialchars(url('personnel/' . $uid . '/edit'), ENT_QUOTES, 'UTF-8') ?>">Dossier</a>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php endif; ?>
    </section>
</div>
<script>
(function () {
    var root = document.querySelector('.rp-followup-sheets');
    if (!root) return;

    function place(panel, anchor) {
        var r = anchor.getBoundingClientRect();
        var width = Math.min(320, Math.max(240, window.innerWidth - 16));
        var left = r.right - width;
        if (left < 8) left = 8;
        if (left + width > window.innerWidth - 8) left = Math.max(8, window.innerWidth - width - 8);
        panel.style.width = width + 'px';
        panel.style.left = left + 'px';
        panel.style.right = 'auto';
        var spaceBelow = window.innerHeight - r.bottom - 8;
        var spaceAbove = r.top - 8;
        var need = Math.min(Math.max(panel.offsetHeight || 220, 180), window.innerHeight * 0.72);
        if (spaceBelow < need && spaceAbove > spaceBelow) {
            panel.style.top = 'auto';
            panel.style.bottom = (window.innerHeight - r.top + 6) + 'px';
        } else {
            panel.style.bottom = 'auto';
            panel.style.top = (r.bottom + 6) + 'px';
        }
    }

    function restore(details, panel, marker) {
        if (marker && marker.parentNode) {
            marker.parentNode.insertBefore(panel, marker);
            marker.parentNode.removeChild(marker);
        }
        panel.classList.remove('is-ported');
        panel.style.position = '';
        panel.style.top = '';
        panel.style.bottom = '';
        panel.style.left = '';
        panel.style.right = '';
        panel.style.width = '';
        delete details._rpPopMarker;
    }

    root.querySelectorAll('.rp-followup-sheets__pop').forEach(function (details) {
        var panel = details.querySelector('.rp-followup-sheets__pop-panel');
        var summary = details.querySelector('summary');
        if (!panel || !summary) return;

        details.addEventListener('toggle', function () {
            if (!details.open) {
                restore(details, panel, details._rpPopMarker);
                return;
            }
            root.querySelectorAll('.rp-followup-sheets__pop[open]').forEach(function (other) {
                if (other !== details) other.removeAttribute('open');
            });
            var marker = document.createComment('rp-followup-pop');
            details._rpPopMarker = marker;
            details._rpPanel = panel;
            panel.parentNode.insertBefore(marker, panel);
            document.body.appendChild(panel);
            panel.classList.add('is-ported');
            place(panel, summary);
        });
    });

    function relocateOpen() {
        root.querySelectorAll('.rp-followup-sheets__pop[open]').forEach(function (details) {
            var panel = details._rpPanel;
            var summary = details.querySelector('summary');
            if (panel && summary) place(panel, summary);
        });
    }
    window.addEventListener('resize', relocateOpen);
    window.addEventListener('scroll', relocateOpen, true);
    root.addEventListener('scroll', relocateOpen);
})();
</script>
