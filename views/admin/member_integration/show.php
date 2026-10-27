<?php
declare(strict_types=1);

use App\Support\MemberIntegrationCatalog;

/** @var array<string,mixed> $integration */
/** @var list<array<string,mixed>> $steps */
/** @var list<array<string,mixed>> $events */
/** @var list<array<string,mixed>> $referents */
/** @var list<array<string,mixed>> $appointments */
/** @var list<array<string,mixed>> $matricesAssigned */
/** @var list<array<string,mixed>> $matricesAll */
/** @var array<string,mixed> $dossier */
/** @var list<array<string,mixed>> $staff */
/** @var array<string,string> $statusLabels */
/** @var array<string,string> $stepTypeLabels */
/** @var array<string,string> $stepStatusLabels */
/** @var array<string,string> $responsibleLabels */
/** @var array<string,string> $appointmentStatusLabels */
/** @var array<string,string> $visibilityLabels */

$h = static fn (mixed $v): string => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
$fmtWhen = static function (mixed $v): string {
    $s = trim((string) $v);
    if ($s === '') {
        return '';
    }
    $ts = strtotime($s);

    return $ts === false ? $s : date('d/m/Y à H:i', $ts);
};
$fmtDay = static function (mixed $v): string {
    $s = trim((string) $v);
    if ($s === '') {
        return '';
    }
    $ts = strtotime($s);

    return $ts === false ? $s : date('d/m/Y', $ts);
};
$tagForStatus = static function (string $status): string {
    return match ($status) {
        MemberIntegrationCatalog::STATUS_COMPLETED,
        MemberIntegrationCatalog::STEP_COMPLETED,
        MemberIntegrationCatalog::STEP_SKIPPED => 'ok',
        MemberIntegrationCatalog::STATUS_CANCELLED,
        MemberIntegrationCatalog::STATUS_BLOCKED,
        MemberIntegrationCatalog::STEP_CANCELLED,
        MemberIntegrationCatalog::STEP_BLOCKED => 'bad',
        MemberIntegrationCatalog::STATUS_WAITING_MEMBER,
        MemberIntegrationCatalog::STATUS_WAITING_STAFF,
        MemberIntegrationCatalog::STEP_WAITING_MEMBER,
        MemberIntegrationCatalog::STEP_WAITING_STAFF => 'warn',
        MemberIntegrationCatalog::STEP_PENDING,
        MemberIntegrationCatalog::STATUS_TO_START => 'neut',
        default => 'info',
    };
};

$row = is_array($integration ?? null) ? $integration : [];
$steps = is_array($steps ?? null) ? $steps : [];
$events = is_array($events ?? null) ? $events : [];
$referents = is_array($referents ?? null) ? $referents : [];
$appointments = is_array($appointments ?? null) ? $appointments : [];
$matricesAssigned = is_array($matricesAssigned ?? null) ? $matricesAssigned : [];
$matricesAll = is_array($matricesAll ?? null) ? $matricesAll : [];
$staff = is_array($staff ?? null) ? $staff : [];
$statusLabels = is_array($statusLabels ?? null) ? $statusLabels : MemberIntegrationCatalog::statusLabels();
$stepTypeLabels = is_array($stepTypeLabels ?? null) ? $stepTypeLabels : MemberIntegrationCatalog::stepTypeLabels();
$stepStatusLabels = is_array($stepStatusLabels ?? null) ? $stepStatusLabels : MemberIntegrationCatalog::stepStatusLabels();
$responsibleLabels = is_array($responsibleLabels ?? null) ? $responsibleLabels : MemberIntegrationCatalog::responsibleLabels();
$appointmentStatusLabels = is_array($appointmentStatusLabels ?? null) ? $appointmentStatusLabels : MemberIntegrationCatalog::appointmentStatusLabels();
$visibilityLabels = is_array($visibilityLabels ?? null) ? $visibilityLabels : MemberIntegrationCatalog::visibilityLabels();

$id = (int) ($row['id'] ?? 0);
$userId = (int) ($row['user_id'] ?? 0);
$score = is_array($dossier['score'] ?? null) ? $dossier['score'] : [];
$missing = is_array($score['missing_labels'] ?? null) ? $score['missing_labels'] : [];
$crit = is_array($score['sections_critiques'] ?? null) ? $score['sections_critiques'] : [];
$pct = max(0, min(100, (int) ($row['progress_percent'] ?? ($score['percent'] ?? 0))));
$intStatus = (string) ($row['status'] ?? '');
$intStatusLabel = $statusLabels[$intStatus] ?? 'En cours';
$isTerminal = MemberIntegrationCatalog::isTerminalStatus($intStatus);
$dossierComplete = !empty($row['dossier_complete']);
$primaryReferentId = (int) ($row['primary_referent_user_id'] ?? 0);
$primaryReferentName = '';
foreach ($referents as $ref) {
    if (!empty($ref['is_primary'])) {
        $primaryReferentId = (int) ($ref['user_id'] ?? $primaryReferentId);
        $primaryReferentName = trim((string) ($ref['display_name'] ?? $ref['email'] ?? ''));
        break;
    }
}
if ($primaryReferentName === '' && $primaryReferentId > 0) {
    foreach ($staff as $s) {
        if ((int) ($s['id'] ?? 0) === $primaryReferentId) {
            $primaryReferentName = trim((string) ($s['display_name'] ?? $s['email'] ?? ''));
            break;
        }
    }
}
$assignedMatrixIds = [];
foreach ($matricesAssigned as $m) {
    $assignedMatrixIds[(int) ($m['matrix_id'] ?? $m['id'] ?? 0)] = true;
}
$availableMatrices = [];
foreach ($matricesAll as $m) {
    $mid = (int) ($m['id'] ?? 0);
    if ($mid > 0 && empty($assignedMatrixIds[$mid])) {
        $availableMatrices[] = $m;
    }
}
$bilans = is_array($dossier['bilans'] ?? null) ? $dossier['bilans'] : [];
$recruitBilans = is_array($dossier['recruitment_bilans'] ?? null) ? $dossier['recruitment_bilans'] : [];
$canAssign = !empty($canAssign) && !$isTerminal;
$canNote = !empty($canNote);
$canManage = !empty($canManage);
$stepAction = url('back-office/integration-membres/' . $id . '/etape');
$ficheUrl = url('personnel/' . $userId);
$labelOf = static function (mixed $item): string {
    if (is_array($item)) {
        return trim((string) ($item['label'] ?? $item['title'] ?? $item['name'] ?? ''));
    }

    return trim((string) $item);
};

$requiredTotal = 0;
$requiredDone = 0;
$openSteps = [];
$nextStep = null;
foreach ($steps as $st) {
    $stStatus = (string) ($st['status'] ?? '');
    $stDone = MemberIntegrationCatalog::isStepDone($stStatus) || $stStatus === MemberIntegrationCatalog::STEP_CANCELLED;
    $isRequired = !empty($st['is_required']);
    if ($isRequired) {
        $requiredTotal++;
        if ($stDone) {
            $requiredDone++;
        }
    }
    if (!$stDone) {
        $openSteps[] = $st;
        if ($nextStep === null) {
            $nextStep = $st;
        }
    }
}
$openCount = count($openSteps);
$nextDue = $nextStep !== null ? $fmtWhen($nextStep['due_at'] ?? '') : '';
$started = $fmtDay($row['started_at'] ?? $row['created_at'] ?? '');
$arrived = $fmtDay($row['arrived_at'] ?? '');
$dossierGapCount = 0;
foreach ($crit as $c) {
    if ($labelOf($c) !== '') {
        $dossierGapCount++;
    }
}
foreach ($missing as $m) {
    if ($labelOf($m) !== '') {
        $dossierGapCount++;
    }
}
?>
<link href="<?= $h(asset_url('assets/css/member-integration.css')) ?>" rel="stylesheet">

<div class="mi-admin mi-show">
    <section class="mi-show-hero ath-rise">
        <div class="mi-show-hero__top">
            <div class="mi-show-chips">
                <span class="ath-tag ath-tag--<?= $h($tagForStatus($intStatus)) ?>"><?= $h($intStatusLabel) ?></span>
                <span class="ath-tag ath-tag--<?= $dossierComplete ? 'ok' : 'warn' ?>">
                    <?= $dossierComplete ? 'Dossier complet' : 'Dossier à compléter' ?>
                </span>
                <?php if ($requiredTotal > 0): ?>
                    <span class="ath-tag ath-tag--neut"><?= $requiredDone ?> sur <?= $requiredTotal ?> étapes obligatoires</span>
                <?php endif; ?>
            </div>
            <div class="mi-show-hero__actions">
                <a class="ath-btn ath-btn--accent" href="<?= $h($ficheUrl) ?>">Fiche personnelle</a>
                <?php if ($canAssign && $nextStep !== null): ?>
                    <form method="post" action="<?= $h($stepAction) ?>" class="mi-show-hero__quick">
                        <?= \App\Core\Csrf::field() ?>
                        <input type="hidden" name="step_id" value="<?= (int) ($nextStep['id'] ?? 0) ?>">
                        <button class="ath-btn" type="submit">Valider l’étape en cours</button>
                    </form>
                <?php endif; ?>
            </div>
        </div>

        <div class="mi-show-progress-meta">
            <span>Avancement des étapes obligatoires</span>
            <span><?= $pct ?> %</span>
        </div>
        <div class="mi-progress" role="progressbar" aria-valuemin="0" aria-valuemax="100" aria-valuenow="<?= $pct ?>" aria-label="Avancement du parcours">
            <span style="width:<?= $pct ?>%"></span>
        </div>

        <dl class="mi-show-facts">
            <div>
                <dt>Référent</dt>
                <dd><?= $primaryReferentName !== '' ? $h($primaryReferentName) : 'Non attribué' ?></dd>
            </div>
            <div>
                <dt>Restant</dt>
                <dd><?= $openCount === 0 ? 'Rien à valider' : ($openCount . ' étape' . ($openCount > 1 ? 's' : '')) ?></dd>
            </div>
            <div>
                <dt>Arrivée</dt>
                <dd><?= $arrived !== '' ? $h($arrived) : ($started !== '' ? $h($started) : '—') ?></dd>
            </div>
            <div>
                <dt>Groupes</dt>
                <dd><?= count($matricesAssigned) > 0 ? count($matricesAssigned) . ' groupe' . (count($matricesAssigned) > 1 ? 's' : '') : 'Aucun' ?></dd>
            </div>
        </dl>

        <?php if ($nextStep !== null && !$isTerminal): ?>
            <div class="mi-show-next">
                <p class="mi-show-next__kicker">À faire maintenant</p>
                <p class="mi-show-next__title"><?= $h($nextStep['title'] ?? 'Prochaine étape') ?></p>
                <p class="mi-show-next__meta">
                    <?= !empty($nextStep['is_required']) ? 'Obligatoire' : 'Facultative' ?>
                    <?php
                    $nextType = $stepTypeLabels[(string) ($nextStep['step_type'] ?? '')] ?? '';
                    $nextResp = $responsibleLabels[(string) ($nextStep['responsible_kind'] ?? '')] ?? '';
                    $nextStatus = $stepStatusLabels[(string) ($nextStep['status'] ?? '')] ?? 'À faire';
                    ?>
                    <?php if ($nextType !== ''): ?> · <?= $h($nextType) ?><?php endif; ?>
                    <?php if ($nextResp !== ''): ?> · <?= $h($nextResp) ?><?php endif; ?>
                    · <?= $h($nextStatus) ?>
                    <?php if ($nextDue !== ''): ?> · Prévue le <?= $h($nextDue) ?><?php endif; ?>
                </p>
            </div>
        <?php elseif ($isTerminal): ?>
            <div class="mi-show-next mi-show-next--done">
                <p class="mi-show-next__kicker">Parcours</p>
                <p class="mi-show-next__title"><?= $intStatus === MemberIntegrationCatalog::STATUS_COMPLETED ? 'Arrivée terminée' : 'Parcours arrêté' ?></p>
                <p class="mi-show-next__meta">Vous pouvez encore lire le journal et le dossier. Rouvrir remet le suivi en cours.</p>
            </div>
        <?php endif; ?>
    </section>

    <div class="mi-show-grid">
        <div class="ath-stack">
            <section class="ath-form ath-rise">
                <div class="ath-form__head">
                    <span class="ath-form__title">Étapes du parcours</span>
                    <span class="ath-form__hint">Validez lorsqu’elle est réellement faite. Forcer demande un motif visible pour l’encadrement.</span>
                </div>
                <?php if ($steps === []): ?>
                    <div class="mi-empty">
                        <strong>Aucune étape pour l’instant</strong>
                        <p>Ce parcours n’a pas encore d’étapes. Vérifiez le modèle, ou rouvrez le suivi si besoin.</p>
                    </div>
                <?php else: ?>
                    <ol class="mi-show-path">
                        <?php foreach ($steps as $index => $st): ?>
                            <?php
                            $stId = (int) ($st['id'] ?? 0);
                            $stStatus = (string) ($st['status'] ?? '');
                            $stDone = MemberIntegrationCatalog::isStepDone($stStatus) || $stStatus === MemberIntegrationCatalog::STEP_CANCELLED;
                            $stCurrent = !$stDone && $nextStep !== null && (int) ($nextStep['id'] ?? 0) === $stId;
                            $stLabel = $stepStatusLabels[$stStatus] ?? 'À faire';
                            $stType = $stepTypeLabels[(string) ($st['step_type'] ?? '')] ?? '';
                            $stResp = $responsibleLabels[(string) ($st['responsible_kind'] ?? '')] ?? '';
                            $due = $fmtWhen($st['due_at'] ?? '');
                            $doneAt = $fmtWhen($st['completed_at'] ?? '');
                            $desc = trim((string) ($st['description'] ?? ''));
                            $classes = 'mi-show-step';
                            if ($stDone) {
                                $classes .= ' is-done';
                            }
                            if ($stCurrent) {
                                $classes .= ' is-current';
                            }
                            ?>
                            <li class="<?= $h($classes) ?>">
                                <div class="mi-show-step__rail" aria-hidden="true">
                                    <span class="mi-show-step__num"><?= $stDone ? '✓' : ($index + 1) ?></span>
                                </div>
                                <article class="mi-show-step__card">
                                    <div class="ath-item__head">
                                        <div>
                                            <div class="ath-item__name"><?= $h($st['title'] ?? '') ?></div>
                                            <p class="ath-item__meta">
                                                <?= !empty($st['is_required']) ? 'Obligatoire' : 'Facultative' ?>
                                                <?php if ($stType !== ''): ?> · <?= $h($stType) ?><?php endif; ?>
                                                <?php if ($stResp !== ''): ?> · <?= $h($stResp) ?><?php endif; ?>
                                                <?php if ($due !== '' && !$stDone): ?> · Prévue le <?= $h($due) ?><?php endif; ?>
                                                <?php if ($doneAt !== ''): ?> · Faite le <?= $h($doneAt) ?><?php endif; ?>
                                            </p>
                                        </div>
                                        <div class="ath-item__tags">
                                            <span class="ath-tag ath-tag--<?= $h($tagForStatus($stStatus)) ?>"><?= $h($stLabel) ?></span>
                                        </div>
                                    </div>
                                    <?php if ($desc !== ''): ?>
                                        <p class="mi-show-step__desc"><?= nl2br($h($desc)) ?></p>
                                    <?php endif; ?>
                                    <?php if ($canAssign && !$stDone): ?>
                                        <form method="post" action="<?= $h($stepAction) ?>" class="mi-show-step__actions">
                                            <?= \App\Core\Csrf::field() ?>
                                            <input type="hidden" name="step_id" value="<?= $stId ?>">
                                            <button class="ath-btn ath-btn--accent" type="submit">Valider</button>
                                        </form>
                                        <details class="mi-show-force">
                                            <summary>Valider malgré tout</summary>
                                            <form method="post" action="<?= $h($stepAction) ?>" class="mi-show-force__form">
                                                <?= \App\Core\Csrf::field() ?>
                                                <input type="hidden" name="step_id" value="<?= $stId ?>">
                                                <input type="hidden" name="force" value="1">
                                                <label class="ath-field">
                                                    <span class="ath-field__label">Motif visible pour l’encadrement</span>
                                                    <input class="ath-field__input" name="reason" required placeholder="Expliquez pourquoi cette étape est forcée">
                                                </label>
                                                <button class="ath-btn" type="submit">Forcer la validation</button>
                                            </form>
                                        </details>
                                    <?php endif; ?>
                                </article>
                            </li>
                        <?php endforeach; ?>
                    </ol>
                <?php endif; ?>
            </section>

            <section class="ath-form ath-rise">
                <div class="ath-form__head">
                    <span class="ath-form__title">Journal</span>
                    <span class="ath-form__hint">Les notes internes restent visibles de l’encadrement seulement.</span>
                </div>
                <?php if ($events === []): ?>
                    <div class="mi-empty">
                        <strong>Rien n’a encore été consigné</strong>
                        <p>Les validations, rendez-vous et notes apparaîtront ici.</p>
                    </div>
                <?php else: ?>
                    <ol class="mi-show-journal">
                        <?php foreach ($events as $ev): ?>
                            <?php
                            $vis = (string) ($ev['visibility'] ?? MemberIntegrationCatalog::VISIBILITY_STAFF);
                            $visLabel = $visibilityLabels[$vis] ?? 'Interne';
                            $actor = trim((string) ($ev['actor_display_name'] ?? $ev['actor_callsign'] ?? ''));
                            $when = $fmtWhen($ev['created_at'] ?? '');
                            ?>
                            <li>
                                <span class="ath-tag ath-tag--<?= $vis === MemberIntegrationCatalog::VISIBILITY_MEMBER ? 'info' : 'neut' ?>"><?= $h($visLabel) ?></span>
                                <span class="mi-show-journal__body"><?= $h($ev['message'] ?? $ev['body'] ?? '') ?></span>
                                <span class="mi-muted">
                                    <?php if ($actor !== ''): ?><?= $h($actor) ?> · <?php endif; ?>
                                    <?= $h($when) ?>
                                </span>
                            </li>
                        <?php endforeach; ?>
                    </ol>
                <?php endif; ?>
                <?php if ($canNote): ?>
                    <form method="post" action="<?= $h(url('back-office/integration-membres/' . $id . '/note')) ?>" class="mi-show-note">
                        <?= \App\Core\Csrf::field() ?>
                        <label class="ath-field">
                            <span class="ath-field__label">Note</span>
                            <textarea class="ath-field__textarea" name="message" required rows="3" placeholder="Constat, relance, observation…"></textarea>
                        </label>
                        <label class="ath-check">
                            <input type="checkbox" name="visible_member" value="1">
                            Visible par le membre
                        </label>
                        <div class="ath-form__actions">
                            <button class="ath-btn ath-btn--accent" type="submit">Enregistrer</button>
                        </div>
                    </form>
                <?php endif; ?>
            </section>
        </div>

        <aside class="ath-stack mi-show-aside">
            <section class="ath-form ath-rise">
                <div class="ath-form__head">
                    <span class="ath-form__title">Dossier personnel</span>
                    <span class="ath-form__hint"><?= (int) ($score['score'] ?? $score['percent'] ?? 0) ?> % complété<?= $dossierGapCount > 0 ? ' · ' . $dossierGapCount . ' point' . ($dossierGapCount > 1 ? 's' : '') . ' à traiter' : '' ?></span>
                </div>
                <?php if ($crit === [] && $missing === [] && $bilans === [] && $recruitBilans === []): ?>
                    <p class="mi-muted">Rien de bloquant pour l’instant.</p>
                <?php endif; ?>
                <?php if ($crit !== []): ?>
                    <p class="mi-show-side-label">Sections encore nécessaires</p>
                    <ul class="mi-show-bullets">
                        <?php foreach ($crit as $c): $lab = $labelOf($c); if ($lab === '') { continue; } ?>
                            <li><?= $h($lab) ?></li>
                        <?php endforeach; ?>
                    </ul>
                <?php endif; ?>
                <?php if ($missing !== []): ?>
                    <p class="mi-show-side-label">Éléments manquants</p>
                    <ul class="mi-show-bullets">
                        <?php foreach ($missing as $m): $lab = $labelOf($m); if ($lab === '') { continue; } ?>
                            <li><?= $h($lab) ?></li>
                        <?php endforeach; ?>
                    </ul>
                <?php endif; ?>
                <?php if ($bilans !== []): ?>
                    <p class="mi-show-side-label">Bilans d’étape</p>
                    <ul class="mi-show-bullets">
                        <?php foreach ($bilans as $b): ?>
                            <li><?= $h($b['stage_label'] ?? $b['title'] ?? 'Bilan') ?><?php $w = $fmtWhen($b['created_at'] ?? ''); if ($w !== ''): ?> · <?= $h($w) ?><?php endif; ?></li>
                        <?php endforeach; ?>
                    </ul>
                <?php endif; ?>
                <?php if ($recruitBilans !== []): ?>
                    <p class="mi-show-side-label">Bilans de candidature</p>
                    <ul class="mi-show-bullets">
                        <?php foreach ($recruitBilans as $b): ?>
                            <li><?= $h($b['feedback_scope'] ?? 'Bilan') ?><?php $w = $fmtWhen($b['submitted_at'] ?? $b['created_at'] ?? ''); if ($w !== ''): ?> · <?= $h($w) ?><?php endif; ?></li>
                        <?php endforeach; ?>
                    </ul>
                <?php endif; ?>
                <div class="ath-form__actions">
                    <a class="ath-btn" href="<?= $h($ficheUrl) ?>">Ouvrir la fiche</a>
                </div>
            </section>

            <section class="ath-form ath-rise">
                <div class="ath-form__head">
                    <span class="ath-form__title">Référents</span>
                    <span class="ath-form__hint">La personne principale suit l’arrivée au quotidien.</span>
                </div>
                <?php if ($referents === []): ?>
                    <p class="mi-muted">Aucun référent pour l’instant.</p>
                <?php else: ?>
                    <ul class="ath-list">
                        <?php foreach ($referents as $ref): ?>
                            <li>
                                <span>
                                    <span class="ath-list__name"><?= $h($ref['display_name'] ?? $ref['email'] ?? '') ?></span>
                                    <?php if (!empty($ref['is_primary'])): ?>
                                        <span class="ath-list__meta">Référent principal</span>
                                    <?php endif; ?>
                                </span>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                <?php endif; ?>
                <?php if ($canAssign): ?>
                    <form method="post" action="<?= $h(url('back-office/integration-membres/' . $id . '/referents')) ?>" class="mi-show-side-form">
                        <?= \App\Core\Csrf::field() ?>
                        <label class="ath-field">
                            <span class="ath-field__label">Référent principal</span>
                            <select class="ath-field__select" name="primary_referent_user_id">
                                <option value="0">Aucun</option>
                                <?php foreach ($staff as $s): ?>
                                    <?php $sid = (int) ($s['id'] ?? 0); ?>
                                    <option value="<?= $sid ?>" <?= $sid === $primaryReferentId ? 'selected' : '' ?>><?= $h($s['display_name'] ?? $s['email'] ?? '') ?></option>
                                <?php endforeach; ?>
                            </select>
                        </label>
                        <div class="ath-form__actions">
                            <button class="ath-btn" type="submit">Mettre à jour</button>
                        </div>
                    </form>
                <?php endif; ?>
            </section>

            <section class="ath-form ath-rise">
                <div class="ath-form__head">
                    <span class="ath-form__title">Groupes de suivi</span>
                    <span class="ath-form__hint">Le membre apparaît dans les groupes choisis pour l’encadrement.</span>
                </div>
                <?php if ($matricesAssigned === []): ?>
                    <p class="mi-muted">Aucun groupe pour l’instant.</p>
                <?php else: ?>
                    <ul class="mi-show-bullets">
                        <?php foreach ($matricesAssigned as $m): ?>
                            <li><?= $h($m['name'] ?? '') ?></li>
                        <?php endforeach; ?>
                    </ul>
                <?php endif; ?>
                <?php if ($canAssign && $availableMatrices !== []): ?>
                    <form method="post" action="<?= $h(url('back-office/integration-membres/' . $id . '/groupe')) ?>" class="mi-show-side-form">
                        <?= \App\Core\Csrf::field() ?>
                        <input type="hidden" name="action" value="assign">
                        <label class="ath-field">
                            <span class="ath-field__label">Ajouter à un groupe</span>
                            <select class="ath-field__select" name="matrix_id">
                                <?php foreach ($availableMatrices as $m): ?>
                                    <option value="<?= (int) ($m['id'] ?? 0) ?>"><?= $h($m['name'] ?? '') ?></option>
                                <?php endforeach; ?>
                            </select>
                        </label>
                        <div class="ath-form__actions">
                            <button class="ath-btn" type="submit">Ajouter</button>
                        </div>
                    </form>
                <?php endif; ?>
            </section>

            <section class="ath-form ath-rise">
                <div class="ath-form__head">
                    <span class="ath-form__title">Rendez-vous</span>
                    <span class="ath-form__hint">Une invitation est envoyée au membre.</span>
                </div>
                <?php if ($appointments === []): ?>
                    <p class="mi-muted">Aucun rendez-vous pour l’instant.</p>
                <?php else: ?>
                    <ul class="ath-list">
                        <?php foreach ($appointments as $a): ?>
                            <?php
                            $aStatus = (string) ($a['status'] ?? '');
                            $aStatusLabel = $appointmentStatusLabels[$aStatus] ?? 'Planifié';
                            $when = $fmtWhen($a['starts_at'] ?? '');
                            ?>
                            <li>
                                <span>
                                    <span class="ath-list__name"><?= $h($a['title'] ?? '') ?></span>
                                    <span class="ath-list__meta">
                                        <?= $h($aStatusLabel) ?><?php if ($when !== ''): ?> · <?= $h($when) ?><?php endif; ?>
                                        <?php if (trim((string) ($a['location'] ?? '')) !== ''): ?> · <?= $h($a['location']) ?><?php endif; ?>
                                    </span>
                                </span>
                                <a class="ath-btn" href="<?= $h(url('back-office/integration-membres/rendez-vous/' . (int) ($a['id'] ?? 0) . '/calendrier')) ?>">Calendrier</a>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                <?php endif; ?>
                <?php if ($canAssign): ?>
                    <details class="mi-show-compose">
                        <summary>Planifier un rendez-vous</summary>
                        <form method="post" action="<?= $h(url('back-office/integration-membres/' . $id . '/rendez-vous')) ?>" class="mi-show-side-form">
                            <?= \App\Core\Csrf::field() ?>
                            <label class="ath-field">
                                <span class="ath-field__label">Titre</span>
                                <input class="ath-field__input" name="title" required>
                            </label>
                            <div class="ath-form__grid">
                                <label class="ath-field">
                                    <span class="ath-field__label">Début</span>
                                    <input class="ath-field__input" type="datetime-local" name="starts_at" required>
                                </label>
                                <label class="ath-field">
                                    <span class="ath-field__label">Fin</span>
                                    <input class="ath-field__input" type="datetime-local" name="ends_at" required>
                                </label>
                            </div>
                            <label class="ath-field">
                                <span class="ath-field__label">Lieu</span>
                                <input class="ath-field__input" name="location">
                            </label>
                            <label class="ath-field">
                                <span class="ath-field__label">Message personnel</span>
                                <textarea class="ath-field__textarea" name="personal_message" rows="2"></textarea>
                            </label>
                            <div class="ath-form__actions">
                                <button class="ath-btn ath-btn--accent" type="submit">Inviter le membre</button>
                            </div>
                        </form>
                    </details>
                <?php endif; ?>
            </section>

            <?php if ($canManage): ?>
                <section class="ath-form ath-rise mi-show-danger">
                    <div class="ath-form__head">
                        <span class="ath-form__title">Clôturer ou reprendre</span>
                        <span class="ath-form__hint">L’annulation arrête le suivi. La réouverture le remet en cours.</span>
                    </div>
                    <?php if (!$isTerminal): ?>
                        <details class="mi-show-compose">
                            <summary>Annuler le parcours</summary>
                            <form method="post" action="<?= $h(url('back-office/integration-membres/' . $id . '/annuler')) ?>" class="mi-show-side-form">
                                <?= \App\Core\Csrf::field() ?>
                                <label class="ath-field">
                                    <span class="ath-field__label">Motif d’annulation</span>
                                    <input class="ath-field__input" name="reason" required>
                                </label>
                                <div class="ath-form__actions">
                                    <button class="ath-btn ath-btn--danger" type="submit">Confirmer l’annulation</button>
                                </div>
                            </form>
                        </details>
                    <?php else: ?>
                        <form method="post" action="<?= $h(url('back-office/integration-membres/' . $id . '/rouvrir')) ?>">
                            <?= \App\Core\Csrf::field() ?>
                            <div class="ath-form__actions" style="border-top:0;margin-top:0;padding-top:0;">
                                <button class="ath-btn" type="submit">Rouvrir le parcours</button>
                            </div>
                        </form>
                    <?php endif; ?>
                </section>
            <?php endif; ?>
        </aside>
    </div>
</div>
