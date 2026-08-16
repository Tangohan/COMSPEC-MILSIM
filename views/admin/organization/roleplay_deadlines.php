<?php
$rpFeatureEnabled = !empty($rpFeatureEnabled);
$rpRows = is_array($rpRows ?? null) ? $rpRows : [];
$rpTotalActiveMembers = (int) ($rpTotalActiveMembers ?? count($rpRows));
$rpOverdueCounts = is_array($rpOverdueCounts ?? null) ? $rpOverdueCounts : [];
$rpPlannedCounts = is_array($rpPlannedCounts ?? null) ? $rpPlannedCounts : [];
$rpTimelineTableReady = !empty($rpTimelineTableReady);
$rpCsrfToken = (string) ($rpCsrfToken ?? '');
$rpDeadlineKinds = is_array($rpDeadlineKinds ?? null) ? $rpDeadlineKinds : [];
$err = \App\Core\Session::getFlash('error');
$ok = \App\Core\Session::getFlash('success');
$h = static fn (mixed $v): string => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
$todayStr = date('Y-m-d');
$soonHorizon = (new DateTimeImmutable('today'))->modify('+14 days')->format('Y-m-d');

$fmtDate = static function (?string $raw): string {
    $raw = trim((string) $raw);
    if ($raw === '') {
        return '—';
    }
    $ts = strtotime($raw);

    return $ts ? date('d/m/Y', $ts) : $raw;
};

$kindUi = [
    'entretien' => [
        'label' => 'Entretien',
        'field' => 'next_interview_date',
        'empty_label' => 'Non programmé',
        'ok_label' => 'Programmé',
        'soon_label' => 'Bientôt',
        'late_label' => 'En retard',
        'plan_action' => 'Programmer',
        'reschedule_action' => 'Reporter',
        'complete_action' => 'Entretien fait',
        'dialog_sub' => 'Fixer la date de l’entretien individuel ou le marquer comme réalisé.',
        'complete_confirm' => 'Confirmer que l’entretien a bien eu lieu.',
        'date_label' => 'Date d’entretien',
    ],
    'medical' => [
        'label' => 'Médical',
        'field' => 'medical_due_date',
        'empty_label' => 'Non planifié',
        'ok_label' => 'Visite prévue',
        'soon_label' => 'À planifier bientôt',
        'late_label' => 'Visite en retard',
        'plan_action' => 'Planifier la visite',
        'reschedule_action' => 'Reporter la visite',
        'complete_action' => 'Visite faite',
        'dialog_sub' => 'Planifier la visite médicale ou enregistrer qu’elle a été réalisée.',
        'complete_confirm' => 'Confirmer que la visite médicale a bien été réalisée.',
        'date_label' => 'Date de visite médicale',
    ],
    'rotation' => [
        'label' => 'Rotation',
        'field' => 'service_rotation_date',
        'empty_label' => 'Non planifiée',
        'ok_label' => 'Rotation prévue',
        'soon_label' => 'Rotation proche',
        'late_label' => 'Rotation en retard',
        'plan_action' => 'Planifier la rotation',
        'reschedule_action' => 'Reporter la rotation',
        'complete_action' => 'Rotation faite',
        'dialog_sub' => 'Planifier la rotation de service ou la marquer comme effectuée.',
        'complete_confirm' => 'Confirmer que la rotation de service a bien eu lieu.',
        'date_label' => 'Date de rotation',
    ],
];

$resolveState = static function (?string $dateRaw, string $today, string $soon) use ($kindUi): array {
    if ($dateRaw === null || $dateRaw === '') {
        return ['key' => 'empty', 'class' => 'is-empty'];
    }
    if ($dateRaw < $today) {
        return ['key' => 'late', 'class' => 'is-late'];
    }
    if ($dateRaw <= $soon) {
        return ['key' => 'soon', 'class' => 'is-soon'];
    }

    return ['key' => 'ok', 'class' => 'is-ok'];
};
?>
<style>
    .rp-deadlines-kpi {
        display: grid;
        grid-template-columns: repeat(2, minmax(0, 1fr));
        gap: 0.5rem;
    }
    @media (min-width: 640px) {
        .rp-deadlines-kpi { grid-template-columns: repeat(4, minmax(0, 1fr)); }
    }
    .rp-deadlines-kpi__card {
        border: 1px solid #e2e8f0;
        background: #fff;
        border-radius: 0.5rem;
        padding: 0.45rem 0.65rem;
    }
    .rp-deadlines-kpi__label {
        margin: 0;
        font-size: 0.5625rem;
        font-weight: 800;
        letter-spacing: 0.14em;
        text-transform: uppercase;
        color: #64748b;
    }
    .rp-deadlines-kpi__value {
        margin: 0.15rem 0 0;
        font-size: 1.125rem;
        font-weight: 900;
        font-variant-numeric: tabular-nums;
        color: #0f172a;
        line-height: 1.15;
    }
    .rp-deadlines-sheets {
        border: 1px solid #cbd5e1;
        background: #fff;
        overflow: auto;
        max-height: min(72vh, 56rem);
    }
    .rp-deadlines-sheets__table {
        width: 100%;
        min-width: 64rem;
        border-collapse: separate;
        border-spacing: 0;
        font-size: 0.8125rem;
        line-height: 1.35;
    }
    .rp-deadlines-sheets__table th,
    .rp-deadlines-sheets__table td {
        border-right: 1px solid #e2e8f0;
        border-bottom: 1px solid #e2e8f0;
        padding: 0.45rem 0.55rem;
        vertical-align: top;
    }
    .rp-deadlines-sheets__table th:last-child,
    .rp-deadlines-sheets__table td:last-child { border-right: 0; }
    .rp-deadlines-sheets__table thead th {
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
    .rp-deadlines-sheets__table tbody tr:nth-child(even) td { background: #f8fafc; }
    .rp-deadlines-sheets__table tbody tr:hover td { background: #eff6ff; }
    .rp-deadlines-sheets__meta {
        display: block;
        margin-top: 0.05rem;
        font-size: 0.6875rem;
        color: #64748b;
    }
    .rp-deadlines-cell {
        display: grid;
        gap: 0.4rem;
        min-width: 11rem;
    }
    .rp-deadlines-cell__head {
        display: flex;
        flex-wrap: wrap;
        align-items: center;
        gap: 0.35rem 0.45rem;
    }
    .rp-deadlines-cell__date {
        font-weight: 700;
        font-variant-numeric: tabular-nums;
        color: #0f172a;
    }
    .rp-deadlines-cell__date.is-empty { color: #94a3b8; font-weight: 600; }
    .rp-deadlines-cell__date.is-soon { color: #b45309; }
    .rp-deadlines-cell__date.is-late { color: #9f1239; }
    .rp-deadlines-state {
        display: inline-flex;
        align-items: center;
        min-height: 1.25rem;
        padding: 0 0.4rem;
        border: 1px solid transparent;
        border-radius: 0.25rem;
        font-size: 0.625rem;
        font-weight: 800;
        letter-spacing: 0.04em;
        text-transform: uppercase;
        white-space: nowrap;
    }
    .rp-deadlines-state.is-empty {
        border-color: #e2e8f0;
        background: #f8fafc;
        color: #64748b;
    }
    .rp-deadlines-state.is-ok {
        border-color: #a7f3d0;
        background: #ecfdf5;
        color: #047857;
    }
    .rp-deadlines-state.is-soon {
        border-color: #fcd34d;
        background: #fffbeb;
        color: #b45309;
    }
    .rp-deadlines-state.is-late {
        border-color: #fecdd3;
        background: #fff1f2;
        color: #9f1239;
    }
    .rp-deadlines-actions form.contents {
        display: contents;
    }
    .rp-deadlines-actions button,
    .rp-deadlines-actions .rp-deadlines-actions__link {
        display: inline-flex;
        align-items: center;
        height: 1.55rem;
        padding: 0 0.5rem;
        border-radius: 0.25rem;
        border: 1px solid #cbd5e1;
        background: #fff;
        color: #0f172a;
        font-size: 0.6875rem;
        font-weight: 700;
        cursor: pointer;
        font-family: inherit;
        line-height: 1;
        text-decoration: none;
    }
    .rp-deadlines-actions button:hover,
    .rp-deadlines-actions .rp-deadlines-actions__link:hover {
        background: #f8fafc;
        border-color: #94a3b8;
    }
    .rp-deadlines-actions button.is-primary {
        border-color: #0b8a5c;
        background: #ecfdf5;
        color: #047857;
    }
    .rp-deadlines-actions button.is-primary:hover {
        background: #d1fae5;
        border-color: #047857;
    }
    .rp-deadlines-actions button.is-late {
        border-color: #fecdd3;
        background: #fff1f2;
        color: #9f1239;
    }
    .rp-deadlines-actions button.is-complete {
        border-color: #86efac;
        background: #fff;
        color: #166534;
    }
    .rp-deadlines-dialog .ath-field {
        display: grid;
        gap: 0.25rem;
        margin: 0 0 0.75rem;
    }
    .rp-deadlines-dialog .ath-field__label {
        font-size: 0.6875rem;
        font-weight: 800;
        letter-spacing: 0.06em;
        text-transform: uppercase;
        color: #64748b;
    }
    .rp-deadlines-dialog .ath-field__input,
    .rp-deadlines-dialog .ath-field__textarea {
        width: 100%;
        box-sizing: border-box;
        border: 1px solid #cbd5e1;
        border-radius: 0.35rem;
        padding: 0.45rem 0.55rem;
        font: inherit;
        color: #0f172a;
        background: #fff;
    }
    .rp-deadlines-dialog .ath-field__textarea { min-height: 4.5rem; resize: vertical; }
    .rp-deadlines-dialog__actions {
        display: flex;
        flex-wrap: wrap;
        gap: 0.4rem;
        justify-content: flex-end;
    }
    .rp-deadlines-dialog__hint {
        margin: 0 0 0.75rem;
        padding: 0.55rem 0.65rem;
        border: 1px solid #e2e8f0;
        border-left: 3px solid #0b8a5c;
        background: #f8fafc;
        color: #334155;
        font-size: 0.75rem;
        line-height: 1.45;
    }
</style>

<div class="rp-deadlines-bureau flex min-h-0 w-full max-w-none flex-1 flex-col bg-slate-50">
    <div class="shrink-0 border-b border-slate-200 bg-white px-3 py-2.5 sm:px-4">
        <div class="flex flex-wrap items-center justify-between gap-2">
            <div class="min-w-0">
                <h1 class="truncate text-base font-black tracking-tight text-slate-900 sm:text-lg">Échéances</h1>
                <p class="text-xs text-slate-500">Entretien, médical et rotation pour tous les membres actifs — trié par prochaine échéance.</p>
            </div>
            <div class="flex flex-wrap items-center gap-1.5">
                <a href="<?= $h(url('back-office/roleplay-followup')) ?>" class="inline-flex h-8 items-center rounded border border-slate-300 bg-white px-2.5 text-xs font-semibold text-slate-700 hover:bg-slate-50">Bureau de suivi</a>
                <a href="<?= $h(url('back-office/roleplay/immersion')) ?>" class="inline-flex h-8 items-center rounded border border-slate-300 bg-white px-2.5 text-xs font-semibold text-slate-700 hover:bg-slate-50">Configurer le module</a>
            </div>
        </div>
    </div>

    <?php if ($err): ?>
    <div class="shrink-0 border-b border-rose-200 bg-rose-50 px-3 py-2 text-xs font-medium text-rose-900 sm:px-4"><?= $h($err) ?></div>
    <?php endif; ?>
    <?php if ($ok): ?>
    <div class="shrink-0 border-b border-emerald-200 bg-emerald-50 px-3 py-2 text-xs font-medium text-emerald-900 sm:px-4"><?= $h($ok) ?></div>
    <?php endif; ?>

    <div class="shrink-0 border-b border-slate-200 bg-slate-50/90 px-3 py-2.5 sm:px-4">
        <div class="rp-deadlines-kpi" role="group" aria-label="Indicateurs d’échéances">
            <div class="rp-deadlines-kpi__card" style="<?= $rpFeatureEnabled ? 'border-color:#a7f3d0;background:#ecfdf5;' : 'border-color:#fecdd3;background:#fff1f2;' ?>">
                <p class="rp-deadlines-kpi__label" style="<?= $rpFeatureEnabled ? 'color:#065f46;' : 'color:#9f1239;' ?>">Statut module</p>
                <p class="rp-deadlines-kpi__value" style="<?= $rpFeatureEnabled ? 'color:#064e3b;' : 'color:#881337;' ?>"><?= $rpFeatureEnabled ? 'Actif' : 'Désactivé' ?></p>
            </div>
            <div class="rp-deadlines-kpi__card">
                <p class="rp-deadlines-kpi__label">Membres actifs</p>
                <p class="rp-deadlines-kpi__value"><?= $rpTotalActiveMembers ?></p>
            </div>
            <div class="rp-deadlines-kpi__card">
                <p class="rp-deadlines-kpi__label">En retard</p>
                <p class="rp-deadlines-kpi__value" style="color:#9f1239;">
                    <?= (int) ($rpOverdueCounts['entretien'] ?? 0) + (int) ($rpOverdueCounts['medical'] ?? 0) + (int) ($rpOverdueCounts['rotation'] ?? 0) ?>
                </p>
            </div>
            <div class="rp-deadlines-kpi__card">
                <p class="rp-deadlines-kpi__label">Planifiées</p>
                <p class="rp-deadlines-kpi__value">
                    <?= (int) ($rpPlannedCounts['entretien'] ?? 0) + (int) ($rpPlannedCounts['medical'] ?? 0) + (int) ($rpPlannedCounts['rotation'] ?? 0) ?>
                </p>
            </div>
        </div>
    </div>

    <?php if (!$rpTimelineTableReady): ?>
    <div class="shrink-0 border-b border-amber-200 bg-amber-50 px-3 py-2 text-xs text-amber-950 sm:px-4" role="status">
        L’historique des événements roleplay n’est pas encore disponible. Les dates peuvent être mises à jour, mais le journal des actions restera incomplet.
    </div>
    <?php endif; ?>

    <div class="min-h-0 flex-1 px-0">
        <?php if ($rpRows === []): ?>
        <div class="m-4 rounded-lg border border-dashed border-slate-300 bg-white px-6 py-12 text-center sm:m-6">
            <p class="text-sm font-semibold text-slate-800">Aucun membre actif pour cette communauté.</p>
        </div>
        <?php else: ?>
        <div class="rp-deadlines-sheets" role="region" aria-label="Tableau des échéances roleplay">
            <table class="rp-deadlines-sheets__table text-left">
                <thead>
                    <tr>
                        <th>Membre</th>
                        <th>Entretien</th>
                        <th>Médical</th>
                        <th>Rotation</th>
                        <th>Dossier</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($rpRows as $row):
                        $uid = (int) ($row['user_id'] ?? 0);
                        $name = trim((string) ($row['display_name'] ?? ''));
                        if ($name === '') {
                            $name = trim((string) ($row['callsign'] ?? ''));
                        }
                        if ($name === '') {
                            $name = 'Compte n°' . $uid;
                        }
                        $stageLabel = trim((string) ($row['stage'] ?? ''));
                        $tutorLabel = trim((string) ($row['tutor_label'] ?? ''));
                        $actionUrl = url('back-office/roleplay-followup/' . $uid . '/deadline');
                    ?>
                    <tr>
                        <td>
                            <span class="font-semibold text-slate-900"><?= $h($name) ?></span>
                            <span class="rp-deadlines-sheets__meta">
                                Étape : <?= $h($stageLabel !== '' ? $stageLabel : '—') ?>
                                <?php if ($tutorLabel !== ''): ?> · Tuteur : <?= $h($tutorLabel) ?><?php endif; ?>
                            </span>
                        </td>
                        <?php foreach ($kindUi as $kindKey => $kindMeta):
                            $dateRaw = trim((string) ($row[$kindMeta['field']] ?? '')) ?: null;
                            $state = $resolveState($dateRaw, $todayStr, $soonHorizon);
                            $stateKey = (string) $state['key'];
                            $stateClass = (string) $state['class'];
                            $stateLabel = match ($stateKey) {
                                'late' => (string) $kindMeta['late_label'],
                                'soon' => (string) $kindMeta['soon_label'],
                                'ok' => (string) $kindMeta['ok_label'],
                                default => (string) $kindMeta['empty_label'],
                            };
                            $openLabel = $stateKey === 'empty'
                                ? (string) $kindMeta['plan_action']
                                : (string) $kindMeta['reschedule_action'];
                            $openClass = $stateKey === 'empty'
                                ? 'is-primary'
                                : ($stateKey === 'late' ? 'is-late' : '');
                        ?>
                        <td>
                            <div class="rp-deadlines-cell">
                                <div class="rp-deadlines-cell__head">
                                    <span class="rp-deadlines-state <?= $h($stateClass) ?>"><?= $h($stateLabel) ?></span>
                                    <span class="rp-deadlines-cell__date <?= $h($stateClass) ?>"><?= $h($fmtDate($dateRaw)) ?></span>
                                </div>
                                <div class="rp-deadlines-actions">
                                    <button
                                        type="button"
                                        class="<?= $h($openClass) ?>"
                                        data-rp-deadline-open
                                        data-user-id="<?= $uid ?>"
                                        data-member-name="<?= $h($name) ?>"
                                        data-kind="<?= $h($kindKey) ?>"
                                        data-kind-label="<?= $h($kindMeta['label']) ?>"
                                        data-date="<?= $h((string) ($dateRaw ?? '')) ?>"
                                        data-state="<?= $h($stateKey) ?>"
                                        data-dialog-sub="<?= $h($kindMeta['dialog_sub']) ?>"
                                        data-date-label="<?= $h($kindMeta['date_label']) ?>"
                                        data-complete-label="<?= $h($kindMeta['complete_action']) ?>"
                                        data-save-label="<?= $h($stateKey === 'empty' ? $kindMeta['plan_action'] : $kindMeta['reschedule_action']) ?>"
                                        data-action-url="<?= $h($actionUrl) ?>"
                                    >
                                        <?= $h($openLabel) ?>
                                    </button>
                                    <?php if ($stateKey !== 'empty'): ?>
                                    <form method="post" action="<?= $h($actionUrl) ?>" class="contents">
                                        <input type="hidden" name="_csrf_token" value="<?= $h($rpCsrfToken) ?>">
                                        <input type="hidden" name="deadline_kind" value="<?= $h($kindKey) ?>">
                                        <input type="hidden" name="deadline_action" value="complete">
                                        <button type="submit" class="is-complete" title="<?= $h($kindMeta['complete_confirm']) ?>">
                                            <?= $h($kindMeta['complete_action']) ?>
                                        </button>
                                    </form>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </td>
                        <?php endforeach; ?>
                        <td>
                            <div class="rp-deadlines-actions">
                                <a href="<?= $h(url('personnel/' . $uid . '/edit')) ?>" class="rp-deadlines-actions__link">Ouvrir le dossier</a>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php endif; ?>
    </div>
</div>

<dialog id="rp-deadline-dialog" class="ath-dialog rp-deadlines-dialog">
    <form method="post" action="#" id="rp-deadline-form" class="ath-dialog__form">
        <div class="ath-dialog__head ath-dialog__head--split">
            <div>
                <h2 class="ath-dialog__title" id="rp-deadline-title">Échéance</h2>
                <p class="ath-dialog__sub" id="rp-deadline-sub">Mettre à jour la date ou marquer l’étape comme réalisée.</p>
            </div>
            <button type="button" class="ath-dialog__close" data-rp-deadline-close aria-label="Fermer">✕</button>
        </div>
        <div class="ath-dialog__body">
            <input type="hidden" name="_csrf_token" value="<?= $h($rpCsrfToken) ?>">
            <input type="hidden" name="deadline_kind" id="rp-deadline-kind" value="">
            <p class="rp-deadlines-dialog__hint" id="rp-deadline-hint" hidden></p>
            <label class="ath-field">
                <span class="ath-field__label" id="rp-deadline-date-label">Date prévue</span>
                <input type="date" name="deadline_date" id="rp-deadline-date" class="ath-field__input">
            </label>
            <label class="ath-field">
                <span class="ath-field__label">Note (facultatif)</span>
                <textarea name="deadline_note" id="rp-deadline-note" class="ath-field__textarea" maxlength="500" placeholder="Précisions pour l’historique du dossier"></textarea>
            </label>
        </div>
        <div class="ath-dialog__foot">
            <div class="rp-deadlines-dialog__actions">
                <button type="button" class="ath-btn" data-rp-deadline-close>Annuler</button>
                <button type="submit" class="ath-btn" name="deadline_action" value="clear" id="rp-deadline-clear">Effacer</button>
                <button type="submit" class="ath-btn ath-btn--solid" name="deadline_action" value="complete" id="rp-deadline-complete">Marquer réalisé</button>
                <button type="submit" class="ath-btn ath-btn--solid" name="deadline_action" value="save" id="rp-deadline-save">Enregistrer</button>
            </div>
        </div>
    </form>
</dialog>

<script>
(function () {
    var dialog = document.getElementById('rp-deadline-dialog');
    var form = document.getElementById('rp-deadline-form');
    if (!dialog || !form || typeof dialog.showModal !== 'function') {
        return;
    }
    var titleEl = document.getElementById('rp-deadline-title');
    var subEl = document.getElementById('rp-deadline-sub');
    var hintEl = document.getElementById('rp-deadline-hint');
    var kindEl = document.getElementById('rp-deadline-kind');
    var dateEl = document.getElementById('rp-deadline-date');
    var dateLabelEl = document.getElementById('rp-deadline-date-label');
    var noteEl = document.getElementById('rp-deadline-note');
    var clearBtn = document.getElementById('rp-deadline-clear');
    var completeBtn = document.getElementById('rp-deadline-complete');
    var saveBtn = document.getElementById('rp-deadline-save');

    function openFromButton(btn) {
        var kindLabel = btn.getAttribute('data-kind-label') || 'Échéance';
        var member = btn.getAttribute('data-member-name') || '';
        var state = btn.getAttribute('data-state') || 'empty';
        var dialogSub = btn.getAttribute('data-dialog-sub') || 'Mettre à jour la date ou marquer l’étape comme réalisée.';
        var dateLabel = btn.getAttribute('data-date-label') || 'Date prévue';
        var completeLabel = btn.getAttribute('data-complete-label') || 'Marquer réalisé';
        var saveLabel = btn.getAttribute('data-save-label') || 'Enregistrer';
        form.action = btn.getAttribute('data-action-url') || '#';
        kindEl.value = btn.getAttribute('data-kind') || '';
        dateEl.value = btn.getAttribute('data-date') || '';
        noteEl.value = '';
        titleEl.textContent = kindLabel + (member ? ' — ' + member : '');
        subEl.textContent = dialogSub;
        dateLabelEl.textContent = dateLabel;
        completeBtn.textContent = completeLabel;
        saveBtn.textContent = saveLabel;
        clearBtn.hidden = state === 'empty';
        completeBtn.hidden = state === 'empty';
        if (hintEl) {
            if (state === 'late') {
                hintEl.hidden = false;
                hintEl.textContent = 'Cette échéance est dépassée. Reportez-la ou confirmez qu’elle a été réalisée.';
            } else if (state === 'soon') {
                hintEl.hidden = false;
                hintEl.textContent = 'Échéance dans les 14 prochains jours.';
            } else {
                hintEl.hidden = true;
                hintEl.textContent = '';
            }
        }
        dialog.showModal();
    }

    document.querySelectorAll('[data-rp-deadline-open]').forEach(function (btn) {
        btn.addEventListener('click', function () {
            openFromButton(btn);
        });
    });

    document.querySelectorAll('[data-rp-deadline-close]').forEach(function (btn) {
        btn.addEventListener('click', function () {
            dialog.close();
        });
    });

    dialog.addEventListener('click', function (e) {
        if (e.target === dialog) {
            dialog.close();
        }
    });
})();
</script>
