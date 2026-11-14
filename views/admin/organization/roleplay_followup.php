<?php
declare(strict_types=1);

/*
 * Bureau de suivi roleplay : la liste dit qui a besoin de quoi, la fenêtre d'un membre regroupe tout le reste.
 * Une seule fenêtre pour toute la page (les menus d'étape et de tuteur n'y sont écrits qu'une fois).
 * Comportement : public/assets/js/roleplay-followup-board.js. Styles : public/assets/css/back-office-roleplay-suivi.css.
 * Aucune adresse e-mail n'est affichée ici.
 */

use App\Support\RoleplayFollowupBoard;

$rpFeatureEnabled = !empty($rpFeatureEnabled);
$rpBilansEnabled = !isset($rpBilansEnabled) || !empty($rpBilansEnabled);
$rpRows = is_array($rpRows ?? null) ? $rpRows : [];
$rpTrackedCount = (int) ($rpTrackedCount ?? 0);
$rpEligibleCount = (int) ($rpEligibleCount ?? 0);
$rpTotalActiveMembers = (int) ($rpTotalActiveMembers ?? count($rpRows));
$rpTimelineTableReady = !empty($rpTimelineTableReady);
$rpStagesOptions = is_array($rpStagesOptions ?? null) ? array_values(array_filter(array_map(static fn ($s): string => trim((string) $s), $rpStagesOptions), static fn (string $s): bool => $s !== '')) : [];
$rpTutorChoices = is_array($rpTutorChoices ?? null) ? $rpTutorChoices : [];
$rpCsrfToken = (string) ($rpCsrfToken ?? '');
$err = \App\Core\Session::getFlash('error');
$ok = \App\Core\Session::getFlash('success');
$h = static fn (mixed $v): string => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
$today = date('Y-m-d');

$fmtDate = static function (mixed $raw): string {
    $raw = trim((string) $raw);
    $ts = $raw !== '' ? strtotime($raw) : false;

    return $ts ? date('d/m/Y', $ts) : '';
};
$timelineStatusFr = static fn (?string $raw): string => match (trim((string) $raw)) {
    'planned' => 'Prévu',
    'completed' => 'Terminé',
    'blocked' => 'Bloqué',
    'cancelled' => 'Annulé',
    '' => '',
    default => trim((string) $raw),
};

$counts = RoleplayFollowupBoard::counts($rpRows, $today);
$defaultFilter = $counts['a-traiter'] > 0 ? 'a-traiter' : 'tous';
$filters = [
    'a-traiter' => ['label' => 'À traiter', 'hint' => 'en retard ou sans tuteur', 'tone' => $counts['a-traiter'] > 0 ? 'warn' : ''],
    'retard' => ['label' => 'En retard', 'hint' => $rpBilansEnabled ? 'échéance ou bilan dépassé' : 'échéance dépassée', 'tone' => $counts['retard'] > 0 ? 'bad' : ''],
    'sans-tuteur' => ['label' => 'Sans tuteur', 'hint' => 'suivis, aucun tuteur', 'tone' => ''],
    'prets' => ['label' => 'Dossiers prêts', 'hint' => 'minimums de la fiche atteints', 'tone' => ''],
    'non-suivis' => ['label' => 'Pas encore suivis', 'hint' => 'rien de renseigné', 'tone' => ''],
    'tous' => ['label' => 'Tous', 'hint' => 'membres actifs', 'tone' => ''],
];
?>
<link rel="stylesheet" href="<?= $h(asset_url('assets/css/back-office-roleplay-suivi.css')) ?>">
<div class="rps-board" data-rps-board data-default-filter="<?= $h($defaultFilter) ?>">
    <?php if ($err): ?><div class="rps-flash rps-flash--bad" role="alert"><?= $h($err) ?></div><?php endif; ?>
    <?php if ($ok): ?><div class="rps-flash rps-flash--ok" role="status"><?= $h($ok) ?></div><?php endif; ?>

    <?php $rpGuideHere = 'suivi'; require base_path('views/admin/organization/partials/roleplay_parcours_guide.php'); ?>

    <?php if (!$rpFeatureEnabled): ?>
    <div class="rps-flash rps-flash--warn" role="status">
        <strong>Le suivi n’est pas activé.</strong>
        Tant qu’il est coupé, aucune fiche n’est évaluée : c’est pour cela que tout est vide ici (pas d’étape, pas de tuteur, rien de prévu) et que les dossiers ne sont ni « prêts » ni « à compléter ».
        <form method="post" action="<?= $h(url('back-office/roleplay-followup/activation')) ?>" class="rps-bilans" style="display:inline">
            <input type="hidden" name="_csrf_token" value="<?= $h($rpCsrfToken) ?>">
            <input type="hidden" name="enabled" value="1">
            <button type="submit" class="ath-btn ath-btn--solid">Activer le suivi</button>
        </form>
        <a href="<?= $h(url('back-office/roleplay-followup?onglet=immersion#activation-options')) ?>">Voir les réglages</a>
    </div>
    <?php endif; ?>
    <?php if (!$rpTimelineTableReady): ?>
    <div class="rps-flash rps-flash--warn" role="status">L’historique des événements roleplay n’est pas encore disponible. Demandez à l’équipe technique d’appliquer les mises à jour de la base pour activer cette fonctionnalité.</div>
    <?php endif; ?>

    <div class="rps-filters" role="group" aria-label="Filtrer les membres">
        <?php foreach ($filters as $key => $f): ?>
        <button type="button" class="rps-filter<?= $f['tone'] !== '' ? ' rps-filter--' . $h($f['tone']) : '' ?>" data-rps-filter="<?= $h($key) ?>" aria-pressed="<?= $key === $defaultFilter ? 'true' : 'false' ?>">
            <b><?= (int) $counts[$key] ?></b>
            <span><?= $h($f['label']) ?></span>
            <small><?= $h($f['hint']) ?></small>
        </button>
        <?php endforeach; ?>
    </div>

    <div class="rps-tools">
        <label class="rps-search">
            <span class="rps-sr">Chercher un membre</span>
            <input type="search" data-rps-search placeholder="Chercher un membre, un tuteur, une filière…" autocomplete="off">
        </label>
        <?php if ($rpStagesOptions !== []): ?>
        <label class="rps-select">
            <span class="rps-sr">Filtrer par étape</span>
            <select data-rps-stage>
                <option value="">Toutes les étapes</option>
                <?php foreach ($rpStagesOptions as $st): ?><option value="<?= $h($st) ?>"><?= $h($st) ?></option><?php endforeach; ?>
            </select>
        </label>
        <?php endif; ?>
        <span class="rps-tools__sp"></span>
        <form method="post" action="<?= $h(url('back-office/roleplay-followup/bilans')) ?>" class="rps-bilans">
            <input type="hidden" name="_csrf_token" value="<?= $h($rpCsrfToken) ?>">
            <input type="hidden" name="enabled" value="<?= $rpBilansEnabled ? '0' : '1' ?>">
            <button type="submit" class="ath-btn" title="<?= $h($rpBilansEnabled ? 'Plus d’échéance ni de retard de bilan sur ce bureau ni dans les rappels. Les bilans déjà faits sont conservés.' : 'Les échéances et retards de bilan reviennent sur ce bureau et dans les rappels.') ?>"><?= $rpBilansEnabled ? 'Désactiver les bilans' : 'Réactiver les bilans' ?></button>
        </form>
        <a class="ath-btn" href="<?= $h(url('back-office/roleplay-followup/echeances')) ?>">Échéances</a>
        <a class="ath-btn" href="<?= $h(url('back-office/users')) ?>">Gérer les membres</a>
    </div>

    <?php if ($rpRows === []): ?>
    <div class="rps-empty"><strong>Aucun membre actif pour cette communauté.</strong></div>
    <?php else: ?>
    <div class="rps-table" role="region" aria-label="Membres et suivi roleplay">
        <table>
            <thead>
                <tr>
                    <th>Membre</th>
                    <th>Où il en est</th>
                    <th>Tuteur</th>
                    <th>Prochaine action</th>
                    <th>Dossier</th>
                    <th><span class="rps-sr">Ouvrir</span></th>
                </tr>
            </thead>
            <tbody data-rps-rows>
                <?php foreach ($rpRows as $row):
                    $uid = (int) $row['user_id'];
                    $name = trim((string) ($row['display_name'] ?? '')) ?: trim((string) ($row['callsign'] ?? '')) ?: ('Compte n°' . $uid);
                    $stage = trim((string) ($row['stage'] ?? ''));
                    $pos = RoleplayFollowupBoard::stagePosition($stage, $rpStagesOptions);
                    $action = RoleplayFollowupBoard::nextAction($row, $today);
                    $rowFilters = RoleplayFollowupBoard::filtersOf($row, $today);
                    $tutor = trim((string) ($row['tutor_label'] ?? ''));
                    $track = trim((string) ($row['track'] ?? ''));
                    $latest = is_array($row['latest_timeline'] ?? null) ? $row['latest_timeline'] : null;
                    $sheet = [
                        'id' => $uid,
                        'name' => $name,
                        'stage' => $stage,
                        'status' => trim((string) ($row['status'] ?? '')),
                        'progress' => $row['progress'] !== null ? max(0, min(100, (int) $row['progress'])) : null,
                        'tutorId' => (int) ($row['tutor_id'] ?? 0),
                        'function' => trim((string) ($row['function'] ?? '')),
                        'track' => $track,
                        'origin' => match (trim((string) ($row['origin'] ?? ''))) { 'internal' => 'Interne', 'external' => 'Externe', default => '' },
                        'notes' => trim((string) ($row['notes'] ?? '')),
                        'interview' => $fmtDate($row['next_interview_date'] ?? ''),
                        'interviewLate' => !empty($row['next_interview_date']) && (string) $row['next_interview_date'] < $today,
                        'medical' => $fmtDate($row['medical_due_date'] ?? ''),
                        'medicalLate' => !empty($row['medical_due_date']) && (string) $row['medical_due_date'] < $today,
                        'rotation' => $fmtDate($row['service_rotation_date'] ?? ''),
                        'rotationLate' => !empty($row['service_rotation_date']) && (string) $row['service_rotation_date'] < $today,
                        'bilansOn' => $rpBilansEnabled,
                        'followupOn' => $rpFeatureEnabled,
                        'bilanNext' => $rpBilansEnabled ? $fmtDate($row['rp_bilan_next_due_at'] ?? '') : '',
                        'bilanLate' => $rpBilansEnabled && !empty($row['rp_bilan_overdue']),
                        'bilanLast' => $fmtDate($row['rp_last_review_at'] ?? ''),
                        'eligible' => !empty($row['eligible']),
                        'evaluated' => !empty($row['evaluated']),
                        'missing' => array_values(is_array($row['missing'] ?? null) ? $row['missing'] : []),
                        'eventTitle' => $latest ? (string) ($latest['title'] ?? '') : '',
                        'eventMeta' => $latest ? trim($timelineStatusFr((string) ($latest['status'] ?? 'planned')) . ' · ' . $fmtDate($latest['event_date'] ?? ''), ' ·') : '',
                        'dossier' => url('personnel/' . $uid . '/edit'),
                        'urls' => [
                            'stage' => url('back-office/roleplay-followup/' . $uid . '/stage'),
                            'tutor' => url('back-office/roleplay-followup/' . $uid . '/tutor'),
                            'validate' => url('back-office/roleplay-followup/' . $uid . '/validate'),
                            'bilan' => url('back-office/roleplay-followup/' . $uid . '/bilan'),
                        ],
                    ];
                ?>
                <tr data-filters="<?= $h(implode(' ', $rowFilters)) ?>"
                    data-stage="<?= $h($stage) ?>"
                    data-search="<?= $h(mb_strtolower($name . ' ' . (string) ($row['callsign'] ?? '') . ' ' . $tutor . ' ' . $track . ' ' . (string) ($row['function'] ?? ''))) ?>"
                    data-sheet="<?= $h((string) json_encode($sheet, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)) ?>">
                    <td>
                        <button type="button" class="rps-name" data-rps-open><?= $h($name) ?></button>
                        <?php if ($track !== '' || trim((string) ($row['function'] ?? '')) !== ''): ?>
                        <small class="rps-meta"><?= $h(implode(' · ', array_filter([trim((string) ($row['function'] ?? '')), $track]))) ?></small>
                        <?php endif; ?>
                    </td>
                    <td>
                        <?php if ($stage !== ''): ?>
                        <span class="rps-stage"><?= $h($stage) ?></span>
                        <?php if ($rpStagesOptions !== []): ?>
                        <span class="rps-steps" aria-label="<?= $h($pos > 0 ? 'Étape ' . $pos . ' sur ' . count($rpStagesOptions) : 'Étape hors de la liste') ?>">
                            <?php foreach ($rpStagesOptions as $i => $opt): ?><i class="<?= $i + 1 < $pos ? 'done' : ($i + 1 === $pos ? 'now' : '') ?>"></i><?php endforeach; ?>
                        </span>
                        <?php endif; ?>
                        <?php else: ?>
                        <span class="rps-none">Pas d’étape</span>
                        <?php endif; ?>
                    </td>
                    <td><?= $tutor !== '' ? $h($tutor) : '<span class="rps-none">Aucun</span>' ?></td>
                    <td>
                        <?php if ($action !== null): ?>
                        <span class="rps-due<?= $action['overdue'] ? ' is-late' : '' ?>"><?= $h($action['label']) ?> · <?= $h($fmtDate($action['date'])) ?></span>
                        <?php if ($action['overdue']): ?><small class="rps-meta rps-meta--late">En retard</small><?php endif; ?>
                        <?php else: ?>
                        <span class="rps-none">Rien de prévu</span>
                        <?php endif; ?>
                    </td>
                    <td>
                        <span class="rps-badge <?= !empty($row['eligible']) ? 'is-ok' : 'is-watch' ?>"><?= !empty($row['eligible']) ? 'Prêt' : ($rpFeatureEnabled ? 'À compléter' : 'Non évalué') ?></span>
                        <?php if (empty($row['eligible']) && $rpFeatureEnabled): ?>
                        <?php $rowMissing = is_array($row['missing'] ?? null) ? $row['missing'] : []; ?>
                        <small class="rps-meta rps-why"><?php if (empty($row['evaluated'])): ?>Jamais évalué : ouvrez le dossier et enregistrez-le<?php elseif ($rowMissing !== []): ?>Manque : <?= $h(implode(' · ', array_slice($rowMissing, 0, 2))) ?><?= count($rowMissing) > 2 ? $h(' · +' . (count($rowMissing) - 2)) : '' ?><?php else: ?>Évaluation à refaire<?php endif; ?></small>
                        <?php endif; ?>
                    </td>
                    <td class="rps-open"><button type="button" class="ath-btn" data-rps-open>Ouvrir</button></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        <p class="rps-nomatch" data-rps-nomatch hidden>Aucun membre pour ce filtre. <button type="button" class="rps-link" data-rps-filter="tous">Voir tout le monde</button></p>
    </div>
    <p class="rps-foot"><span data-rps-shown><?= count($rpRows) ?></span> sur <?= $rpTotalActiveMembers ?> membres actifs · <?= $rpTrackedCount ?> dossiers suivis · <?= $rpEligibleCount ?> prêts.</p>
    <?php endif; ?>
</div>

<dialog class="ath-dialog rps-sheet" id="rps-sheet" aria-labelledby="rps-sheet-name">
    <div class="rps-sheet__head">
        <div>
            <span class="rps-guide__kicker">Suivi du membre</span>
            <h2 id="rps-sheet-name"></h2>
            <p class="rps-meta" data-sheet-where></p>
        </div>
        <button type="button" class="ath-dialog__close" data-rps-close aria-label="Fermer">✕</button>
    </div>
    <div class="rps-sheet__body">
        <div class="rps-sheet__forms">
            <form method="post" data-sheet-form="stage">
                <input type="hidden" name="_csrf_token" value="<?= $h($rpCsrfToken) ?>">
                <label for="rps-stage">Étape</label>
                <div class="rps-inline">
                    <select name="rp_followup_stage" id="rps-stage">
                        <option value="">Non définie</option>
                        <?php foreach ($rpStagesOptions as $st): ?><option value="<?= $h($st) ?>"><?= $h($st) ?></option><?php endforeach; ?>
                    </select>
                    <button type="submit" class="ath-btn ath-btn--solid">Enregistrer</button>
                </div>
            </form>
            <form method="post" data-sheet-form="tutor">
                <input type="hidden" name="_csrf_token" value="<?= $h($rpCsrfToken) ?>">
                <label for="rps-tutor">Tuteur</label>
                <div class="rps-inline">
                    <select name="rp_tutor_user_id" id="rps-tutor">
                        <option value="">Aucun</option>
                        <?php foreach ($rpTutorChoices as $tu): ?><option value="<?= (int) $tu['id'] ?>"><?= $h($tu['label']) ?></option><?php endforeach; ?>
                    </select>
                    <button type="submit" class="ath-btn ath-btn--solid">Enregistrer</button>
                </div>
            </form>
            <div class="rps-sheet__acts">
                <form method="post" data-sheet-form="validate">
                    <input type="hidden" name="_csrf_token" value="<?= $h($rpCsrfToken) ?>">
                    <button type="submit" class="ath-btn" title="Marquer l’étape actuelle comme validée par l’encadrement">Valider l’étape</button>
                </form>
                <form method="post" data-sheet-form="bilan" data-sheet-bilan>
                    <input type="hidden" name="_csrf_token" value="<?= $h($rpCsrfToken) ?>">
                    <button type="submit" class="ath-btn" title="Marquer le bilan roleplay comme fait aujourd’hui (recalcule la prochaine échéance)">Bilan fait aujourd’hui</button>
                </form>
                <a class="ath-btn" data-sheet-dossier href="#">Ouvrir le dossier</a>
            </div>
        </div>
        <dl class="rps-sheet__facts">
            <div><dt>Avancement</dt><dd data-sheet="progress"></dd></div>
            <div><dt>Statut</dt><dd data-sheet="status"></dd></div>
            <div><dt>Fonction</dt><dd data-sheet="function"></dd></div>
            <div><dt>Filière</dt><dd data-sheet="track"></dd></div>
            <div><dt>Profil</dt><dd data-sheet="origin"></dd></div>
            <div><dt>Dossier</dt><dd data-sheet="eligible"></dd></div>
            <div class="wide" data-sheet-missing hidden><dt>Pour être prêt</dt><dd data-sheet="missing"></dd></div>
            <div><dt>Entretien</dt><dd data-sheet="interview"></dd></div>
            <div><dt>Visite médicale</dt><dd data-sheet="medical"></dd></div>
            <div><dt>Rotation</dt><dd data-sheet="rotation"></dd></div>
            <div data-sheet-bilan><dt>Prochain bilan</dt><dd data-sheet="bilanNext"></dd></div>
            <div data-sheet-bilan><dt>Dernier bilan</dt><dd data-sheet="bilanLast"></dd></div>
            <div><dt>Dernier événement</dt><dd data-sheet="event"></dd></div>
            <div class="wide"><dt>Notes</dt><dd data-sheet="notes"></dd></div>
        </dl>
    </div>
</dialog>
<script src="<?= $h(asset_url('assets/js/roleplay-followup-board.js')) ?>" defer></script>
