<?php

declare(strict_types=1);

use App\Services\Effectifs\Dossier\DossierCatalog as C;

/**
 * Fiche membre Effectifs : onglet « Suivi du dossier » (fil d'étapes unifié, résumé, historique, modales d'édition).
 * Lecture seule pour qui n'a que le droit de consulter ; les gestes passent par MemberDossierStepActions. Aucun surtitre.
 *
 * @var array<string, mixed> $suivi données de MemberDossierSuiviController::tabData()
 * @var int $id identifiant du membre
 * @var string $name nom du membre
 * @var string $csrfToken
 */
$suivi = is_array($suivi ?? null) ? $suivi : null;
if ($suivi === null) {
    return;
}
$h = static fn (mixed $v): string => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
$en = function_exists('locale') && locale() === 'en';
$tr = static fn (string $fr, string $en_) => $en ? $en_ : $fr;
$fd = static function (?string $v): string {
    $ts = $v !== null && $v !== '' ? strtotime($v) : false;

    return $ts === false ? '—' : date('d/m/Y', $ts);
};
$fdt = static function (?string $v): string {
    $ts = $v !== null && $v !== '' ? strtotime($v) : false;

    return $ts === false ? '—' : date('d/m/Y H:i', $ts);
};
$today = (string) $suivi['today'];
$daysLate = static function (?string $due) use ($today): int {
    return $due === null || $due === '' ? 0 : max(0, (int) floor((strtotime($today) - strtotime($due)) / 86400));
};
$base = effectifs_workspace_url('membres/' . $id . '/suivi');
$statusLabels = C::statusLabels();
$trackLabels = C::trackLabels();
$canManage = (bool) $suivi['canManage'];
$isSelf = (bool) $suivi['isSelf'];
$degraded = (bool) $suivi['degraded'];
$sum = $suivi['summary'];
$steps = $suivi['steps'];
$tracks = $suivi['tracks'];
$f = $suivi['filter'];
$events = $suivi['events'];
$ownerOptions = $suivi['ownerOptions'];
$anchor = static fn (string $key): string => 'etape-' . trim((string) preg_replace('/[^a-z0-9]+/i', '-', $key), '-');
$actionLabels = [
    C::ACTION_COMPLETE => 'Marquer terminée', C::ACTION_REOPEN => 'À refaire', C::ACTION_POSTPONE => 'Reporter l’échéance',
    C::ACTION_REASSIGN => 'Changer le responsable', C::ACTION_NOTE => 'Ajouter une note', C::ACTION_SKIP => 'Ignorer l’étape',
    C::ACTION_UNSKIP => 'Rétablir l’étape', C::ACTION_REMIND => 'Relancer le membre', C::ACTION_REMOVE => 'Retirer l’étape',
];
$next = $sum['next'];
$stepEvents = [];
foreach ($events as $e) {
    $stepEvents[$e['step_key']][] = $e;
}
?>
<section class="eff-fm-sec eff-sv" id="suivi" aria-labelledby="suivi-titre" data-sv-root data-sv-base="<?= $h($base) ?>">
    <header class="eff-fm-sec__head eff-sv__head">
        <div>
            <h2 id="suivi-titre">Suivi du dossier</h2>
            <p>Toutes les étapes de <?= $h($name) ?>, parcours par parcours : qui doit agir, pour quand, et ce qui a changé.</p>
        </div>
        <div class="eff-sv__head-actions">
            <a class="eff-sv-btn" href="<?= $h($base . '/export' . ($f['track'] !== '' || $f['status'] !== '' || $f['q'] !== '' ? '?' . http_build_query(['parcours' => $f['track'], 'statut' => $f['status'], 'q' => $f['q']]) : '')) ?>">Exporter en CSV</a>
            <?php if ($canManage && !$degraded && !$isSelf): ?>
                <button type="button" class="eff-sv-btn eff-sv-btn--primary" data-sv-act="add" data-sv-key="" data-sv-title="">Ajouter une étape</button>
            <?php endif; ?>
        </div>
    </header>

    <?php if ($degraded): ?>
        <div class="eff-sv-note eff-sv-note--warn" role="status">
            <strong>Suivi en lecture seule.</strong>
            Les étapes ci-dessous viennent des parcours existants, mais les modifications, les étapes personnalisées et l’historique seront disponibles après la mise à jour de la base (étape « Suivi du dossier des membres »).
        </div>
    <?php elseif ($isSelf && $canManage): ?>
        <div class="eff-sv-note" role="status">
            C’est votre propre dossier : vous pouvez y laisser des notes, mais un autre responsable doit valider ou modifier vos étapes.
        </div>
    <?php elseif (!$canManage): ?>
        <div class="eff-sv-note" role="status">
            Vous consultez ce suivi. Le droit « Modifier le suivi du dossier des membres » est nécessaire pour changer une étape.
        </div>
    <?php endif; ?>

    <?php if ($steps === []): ?>
        <div class="eff-sv-empty">
            <strong>Aucune étape pour ce membre.</strong>
            <span>Ni parcours d’intégration, ni demande en cours, ni campagne ouverte.<?= $canManage && !$degraded && !$isSelf ? ' Vous pouvez ajouter une étape personnalisée.' : '' ?></span>
        </div>
    <?php else: ?>
    <div class="eff-sv-sum" role="group" aria-label="Résumé du dossier">
        <div class="eff-sv-tile eff-sv-tile--progress">
            <h3 class="eff-sv-tile__title">Progression</h3>
            <p class="eff-sv-tile__big"><?= (int) $sum['done'] ?> <span><?= $tr('étapes sur', 'steps out of') ?> <?= (int) $sum['total'] ?></span></p>
            <div class="eff-sv-bar" role="progressbar" aria-valuemin="0" aria-valuemax="100" aria-valuenow="<?= (int) $sum['percent'] ?>" aria-label="Progression du dossier"><i style="width:<?= (int) $sum['percent'] ?>%"></i></div>
            <p class="eff-sv-tile__sub"><?= (int) $sum['percent'] ?> % <?= $tr('du dossier est terminé.', 'of the file is complete.') ?></p>
        </div>
        <div class="eff-sv-tile eff-sv-tile--next">
            <h3 class="eff-sv-tile__title">Prochaine action</h3>
            <?php if ($next !== null): ?>
                <p class="eff-sv-tile__next"><?= $h($next['title']) ?></p>
                <p class="eff-sv-tile__sub">
                    <?= $tr('À faire par', 'To be done by') ?> <strong><?= $h($next['owner_label']) ?></strong>
                    <?php if ($next['due_on'] !== null): ?> · <?= $tr('avant le', 'before') ?> <?= $h($fd($next['due_on'])) ?><?php endif; ?>
                    <?php if ($next['late']): ?> · <span class="eff-sv-late"><?= $tr('en retard de ' . $daysLate($next['due_on']) . ' j', $daysLate($next['due_on']) . ' d overdue') ?></span><?php endif; ?>
                </p>
                <button type="button" class="eff-sv-btn" data-sv-open="<?= $h($next['key']) ?>">Voir l’étape</button>
            <?php else: ?>
                <p class="eff-sv-tile__next">Rien à faire pour le moment.</p>
                <p class="eff-sv-tile__sub">Toutes les étapes ouvertes sont terminées ou ignorées.</p>
            <?php endif; ?>
        </div>
        <div class="eff-sv-tile<?= (int) $sum['late'] > 0 ? ' is-alert' : '' ?>">
            <h3 class="eff-sv-tile__title">En retard</h3>
            <p class="eff-sv-tile__big"><?= (int) $sum['late'] ?></p>
            <p class="eff-sv-tile__sub"><?= (int) $sum['late'] > 0 ? $tr('étape(s) dont l’échéance est dépassée.', 'step(s) past their due date.') : $tr('Aucune échéance dépassée.', 'No overdue step.') ?></p>
        </div>
        <div class="eff-sv-tile<?= (int) $sum['blocked'] > 0 ? ' is-warn' : '' ?>">
            <h3 class="eff-sv-tile__title">Bloquées</h3>
            <p class="eff-sv-tile__big"><?= (int) $sum['blocked'] ?></p>
            <p class="eff-sv-tile__sub"><?= (int) $sum['blocked'] > 0 ? $tr('étape(s) qui attendent une décision.', 'step(s) waiting for a decision.') : $tr('Rien ne bloque ce dossier.', 'Nothing blocks this file.') ?></p>
        </div>
    </div>

    <div class="eff-sv-tabs" role="tablist" aria-label="Contenu du suivi">
        <button type="button" role="tab" id="sv-tab-fil" aria-controls="sv-panel-fil" aria-selected="true" class="is-active" data-sv-tab="fil">Fil des étapes <span class="eff-sv-count"><?= count($steps) ?></span></button>
        <button type="button" role="tab" id="sv-tab-histo" aria-controls="sv-panel-histo" aria-selected="false" data-sv-tab="histo">Historique <span class="eff-sv-count"><?= (int) $suivi['eventTotal'] ?></span></button>
    </div>

    <div id="sv-panel-fil" role="tabpanel" aria-labelledby="sv-tab-fil" class="eff-sv-panel is-active" data-sv-panel="fil">
        <form class="eff-sv-filter" method="get" action="<?= $h(effectifs_workspace_url('membres/' . $id)) ?>" data-sv-filter>
            <input type="hidden" name="onglet" value="suivi">
            <label class="eff-sv-field eff-sv-field--grow">
                <span>Rechercher une étape</span>
                <input type="search" name="q" value="<?= $h($f['q']) ?>" placeholder="Intitulé, responsable…" autocomplete="off" data-sv-q>
            </label>
            <label class="eff-sv-field">
                <span>Parcours</span>
                <select name="parcours" data-sv-track>
                    <option value="">Tous les parcours</option>
                    <?php foreach ($trackLabels as $tk => $tl): ?>
                        <option value="<?= $h($tk) ?>"<?= $f['track'] === $tk ? ' selected' : '' ?>><?= $h($tl) ?></option>
                    <?php endforeach; ?>
                </select>
            </label>
            <label class="eff-sv-field">
                <span>Statut</span>
                <select name="statut" data-sv-status>
                    <option value="">Tous les statuts</option>
                    <option value="open"<?= $f['status'] === 'open' ? ' selected' : '' ?>>À faire (ouvertes)</option>
                    <?php foreach ($statusLabels as $sk => $sl): ?>
                        <option value="<?= $h($sk) ?>"<?= $f['status'] === $sk ? ' selected' : '' ?>><?= $h($sl) ?></option>
                    <?php endforeach; ?>
                </select>
            </label>
            <button type="submit" class="eff-sv-btn" data-sv-nojs>Filtrer</button>
        </form>
        <p class="eff-sv-result" data-sv-result aria-live="polite"></p>

        <?php if ($tracks === []): ?>
            <div class="eff-sv-empty"><strong>Aucune étape ne correspond à ces filtres.</strong></div>
        <?php endif; ?>
        <?php foreach ($tracks as $tk => $tr_): ?>
            <section class="eff-sv-track" data-sv-track-group="<?= $h($tk) ?>" aria-labelledby="sv-track-<?= $h($tk) ?>">
                <header class="eff-sv-track__head">
                    <div>
                        <h3 id="sv-track-<?= $h($tk) ?>"><?= $h($tr_['label']) ?></h3>
                        <p><?= $h($tr_['hint']) ?></p>
                    </div>
                    <span class="eff-sv-track__count"><?= (int) $tr_['done'] ?> / <?= (int) $tr_['total'] ?></span>
                </header>
                <ol class="eff-sv-steps">
                    <?php foreach ($tr_['steps'] as $s): ?>
                        <?php $late = $daysLate($s['due_on']); ?>
                        <li class="eff-sv-step is-<?= $h($s['status']) ?>" id="<?= $h($anchor($s['key'])) ?>" data-sv-step data-track="<?= $h($s['track']) ?>" data-status="<?= $h($s['status']) ?>" data-late="<?= $s['late'] ? '1' : '0' ?>" data-open="<?= C::isOpenState((string) $s['state']) ? '1' : '0' ?>" data-text="<?= $h(mb_strtolower($s['title'] . ' ' . $s['owner_label'] . ' ' . $s['description'])) ?>">
                            <span class="eff-sv-step__dot" aria-hidden="true"></span>
                            <div class="eff-sv-step__main">
                                <div class="eff-sv-step__top">
                                    <h4 class="eff-sv-step__title"><?= $h($s['title']) ?></h4>
                                    <span class="eff-sv-badge is-<?= $h($s['status']) ?>"><?= $h($statusLabels[$s['status']] ?? $s['status']) ?><?php if ($s['status'] === C::STATUS_OVERDUE && $late > 0): ?> · <?= $tr($late . ' j', $late . ' d') ?><?php endif; ?></span>
                                    <?php if ($s['late'] && $s['status'] === C::STATUS_BLOCKED): ?><span class="eff-sv-badge is-overdue"><?= $tr('Échéance dépassée', 'Past due') ?></span><?php endif; ?>
                                    <?php if ($s['source_type'] === 'custom'): ?><span class="eff-sv-badge is-custom">Ajoutée à la main</span><?php endif; ?>
                                    <?php if ($s['optional'] && $s['source_type'] !== 'custom'): ?><span class="eff-sv-badge is-optional">Facultative</span><?php endif; ?>
                                </div>
                                <?php if ($s['description'] !== ''): ?><p class="eff-sv-step__desc"><?= $h($s['description']) ?></p><?php endif; ?>
                                <?php $closed = in_array($s['status'], [C::STATUS_DONE, C::STATUS_SKIPPED], true); ?>
                                <dl class="eff-sv-meta">
                                    <div><dt>Responsable</dt><dd><?= $h($s['owner_label']) ?><?= $s['overridden']['owner'] ? ' ' . $tr('(modifié)', '(changed)') : '' ?></dd></div>
                                    <?php if (!$closed): ?>
                                        <div><dt>Ouverte le</dt><dd><?= $h($fd($s['opened_on'])) ?></dd></div>
                                        <div><dt>Échéance</dt><dd><?= $h($fd($s['due_on'])) ?><?= $s['overridden']['due'] ? ' ' . $tr('(reportée)', '(postponed)') : '' ?></dd></div>
                                    <?php elseif ($s['done_at'] !== null): ?>
                                        <div><dt>Réalisée le</dt><dd><?= $h($fd($s['done_at'])) ?></dd></div>
                                    <?php endif; ?>
                                </dl>
                                <?php if ($s['note'] !== ''): ?><p class="eff-sv-step__note"><strong>Note interne.</strong> <?= $h(mb_strimwidth($s['note'], 0, 160, '…')) ?></p><?php endif; ?>
                            </div>
                            <div class="eff-sv-step__side">
                                <button type="button" class="eff-sv-btn" data-sv-open="<?= $h($s['key']) ?>" aria-label="Détail de l’étape : <?= $h($s['title']) ?>">Détail</button>
                            </div>
                        </li>
                    <?php endforeach; ?>
                </ol>
            </section>
        <?php endforeach; ?>
    </div>

    <div id="sv-panel-histo" role="tabpanel" aria-labelledby="sv-tab-histo" class="eff-sv-panel" data-sv-panel="histo" hidden>
        <p class="eff-sv-lead">Chaque changement du suivi : qui l’a fait, quand, ce qui a changé et pourquoi.</p>
        <?php if ($events === []): ?>
            <div class="eff-sv-empty"><strong><?= $degraded ? 'L’historique sera disponible après la mise à jour de la base.' : 'Aucun changement enregistré pour le moment.' ?></strong></div>
        <?php else: ?>
            <ol class="eff-sv-log">
                <?php foreach ($events as $e): ?>
                    <li class="eff-sv-log__item">
                        <div class="eff-sv-log__when"><time datetime="<?= $h($e['created_at']) ?>"><?= $h($fdt($e['created_at'])) ?></time></div>
                        <div class="eff-sv-log__what">
                            <p><strong><?= $h($e['actor']) ?></strong> <?= $h($e['verb']) ?> <em><?= $h($e['step_title']) ?></em></p>
                            <?php if ($e['change'] !== ''): ?><p class="eff-sv-log__change"><?= $h($e['change']) ?></p><?php endif; ?>
                            <?php if ($e['reason'] !== ''): ?><p class="eff-sv-log__reason"><strong>Motif.</strong> <?= $h($e['reason']) ?></p><?php endif; ?>
                        </div>
                    </li>
                <?php endforeach; ?>
            </ol>
            <?php if ((int) $suivi['eventTotal'] > count($events)): ?>
                <p class="eff-sv-lead"><?= $tr('Les ' . count($events) . ' changements les plus récents sont affichés sur ' . (int) $suivi['eventTotal'] . '.', 'The ' . count($events) . ' most recent changes are shown out of ' . (int) $suivi['eventTotal'] . '.') ?></p>
            <?php endif; ?>
        <?php endif; ?>
    </div>

    <?php foreach ($steps as $s): ?>
        <?php $late = $daysLate($s['due_on']); ?>
        <template id="sv-tpl-<?= $h($anchor($s['key'])) ?>" data-title="<?= $h($s['title']) ?>">
            <div class="eff-sv-detail">
                <p class="eff-sv-detail__badges">
                    <span class="eff-sv-badge is-<?= $h($s['status']) ?>"><?= $h($statusLabels[$s['status']] ?? $s['status']) ?><?php if ($s['status'] === C::STATUS_OVERDUE && $late > 0): ?> · <?= $tr($late . ' j', $late . ' d') ?><?php endif; ?></span>
                    <span class="eff-sv-badge is-track"><?= $h($trackLabels[$s['track']] ?? $s['track']) ?></span>
                </p>
                <?php if ($s['description'] !== ''): ?><p class="eff-sv-detail__desc"><?= $h($s['description']) ?></p><?php endif; ?>
                <dl class="eff-sv-facts">
                    <div><dt>Qui doit agir</dt><dd><?= $h($s['owner_label']) ?><?= $s['overridden']['owner'] ? ' ' . $tr('(responsable modifié)', '(owner changed)') : '' ?></dd></div>
                    <div><dt>Ouverte le</dt><dd><?= $h($fd($s['opened_on'])) ?></dd></div>
                    <div><dt>Échéance</dt><dd><?= $h($fd($s['due_on'])) ?><?php if ($s['overridden']['due']): ?> <span class="eff-sv-faint"><?= $tr('(reportée, initialement ' . $fd($s['original_due_on']) . ')', '(postponed, was ' . $fd($s['original_due_on']) . ')') ?></span><?php endif; ?></dd></div>
                    <?php if ($s['done_at'] !== null): ?><div><dt>Réalisée le</dt><dd><?= $h($fd($s['done_at'])) ?></dd></div><?php endif; ?>
                    <div><dt>Source</dt><dd><?php if ($s['source_url'] !== ''): ?><a href="<?= $h($s['source_url']) ?>"><?= $h($s['source_label']) ?></a><?php else: ?><?= $h($s['source_label']) ?><?php endif; ?></dd></div>
                    <div><dt>Dernière modification</dt><dd><?= $h($fdt($s['updated_at'])) ?></dd></div>
                </dl>
                <?php if ($s['note'] !== ''): ?>
                    <div class="eff-sv-detail__note"><h4>Note interne</h4><p><?= nl2br($h($s['note'])) ?></p></div>
                <?php endif; ?>
                <?php if ($s['locked_reason'] !== ''): ?>
                    <p class="eff-sv-note eff-sv-note--info"><?= $h($s['locked_reason']) ?><?php if ($s['source_url'] !== ''): ?> <a href="<?= $h($s['source_url']) ?>">Ouvrir <?= $h($s['source_label']) ?></a><?php endif; ?></p>
                <?php endif; ?>
                <?php if ($canManage && !$degraded): ?>
                    <?php $acts = array_values(array_filter($s['actions'], static fn (string $a): bool => !$isSelf || $a === C::ACTION_NOTE)); ?>
                    <?php if ($acts !== []): ?>
                        <div class="eff-sv-detail__acts" role="group" aria-label="Actions sur l’étape">
                            <?php foreach ($acts as $a): ?>
                                <button type="button" class="eff-sv-btn<?= $a === C::ACTION_COMPLETE ? ' eff-sv-btn--primary' : ($a === C::ACTION_REMOVE ? ' eff-sv-btn--danger' : '') ?>" data-sv-act="<?= $h($a) ?>" data-sv-key="<?= $h($s['key']) ?>" data-sv-title="<?= $h($s['title']) ?>" data-sv-due="<?= $h((string) $s['due_on']) ?>" data-sv-owner="<?= (int) $s['owner_user_id'] ?>" data-sv-force="<?= $a === C::ACTION_COMPLETE && ($s['owner_kind'] === C::OWNER_MEMBER || in_array($s['step_type'] ?? '', ['document_read', 'stage_bilan', 'lms_optional', 'personnel_dossier', 'event_invite', 'matrix_assign'], true)) && $s['source_type'] === 'integration_step' ? '1' : '0' ?>"><?= $h($actionLabels[$a]) ?></button>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                <?php endif; ?>
                <?php $mine = $stepEvents[$s['key']] ?? []; ?>
                <?php if ($mine !== []): ?>
                    <div class="eff-sv-detail__log">
                        <h4>Derniers changements</h4>
                        <ul>
                            <?php foreach (array_slice($mine, 0, 5) as $e): ?>
                                <li><time datetime="<?= $h($e['created_at']) ?>"><?= $h($fdt($e['created_at'])) ?></time> <strong><?= $h($e['actor']) ?></strong> <?= $h($e['short']) ?><?= $e['change'] !== '' ? ' — ' . $h($e['change']) : '' ?><?= $e['reason'] !== '' ? ' (' . $h($e['reason']) . ')' : '' ?></li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                <?php endif; ?>
            </div>
        </template>
    <?php endforeach; ?>
    <?php endif; ?>
</section>

<dialog class="eff-sv-dialog eff-sv-drawer" id="sv-drawer" aria-labelledby="sv-drawer-title" data-sv-dialog>
    <div class="eff-sv-dialog__body">
        <header class="eff-sv-dialog__head">
            <h2 id="sv-drawer-title">Détail de l’étape</h2>
            <button type="button" class="eff-sv-close" data-sv-close aria-label="Fermer">×</button>
        </header>
        <div class="eff-sv-dialog__scroll" data-sv-drawer-body></div>
    </div>
</dialog>

<?php if ($canManage && !$degraded): ?>
    <?php
    $reasonField = static fn (string $label, bool $required, string $hint = ''): string => '<label class="eff-sv-field"><span>' . $label . '</span><textarea name="reason" rows="3" maxlength="500"' . ($required ? ' required minlength="3"' : '') . ' data-sv-reason></textarea>' . ($hint !== '' ? '<small>' . $hint . '</small>' : '') . '</label>';
    $ownerSelect = static function (array $options) use ($h): string {
        $out = '<label class="eff-sv-field"><span>Responsable</span><select name="owner_user_id" data-sv-owner-select><option value="0">Responsable par défaut</option>';
        foreach ($options as $o) {
            $out .= '<option value="' . (int) $o['id'] . '">' . $h($o['label']) . '</option>';
        }

        return $out . '</select></label>';
    };
    $dialogs = [
        C::ACTION_COMPLETE => ['Marquer l’étape terminée', 'L’étape passe à « Terminée ». Si elle revenait au membre ou se valide d’elle-même, le motif est obligatoire : il est conservé dans l’historique.', $reasonField('Motif', false, 'Obligatoire quand vous validez à la place du membre.'), 'Marquer terminée'],
        C::ACTION_REOPEN => ['Remettre l’étape à faire', 'L’étape redevient ouverte et réapparaît dans les actions à mener.', $reasonField('Motif (facultatif)', false), 'Remettre à faire'],
        C::ACTION_POSTPONE => ['Reporter l’échéance', 'Choisissez la nouvelle date. Le motif est conservé dans l’historique.', '<label class="eff-sv-field"><span>Nouvelle échéance</span><input type="date" name="due_on" required min="' . $h($today) . '" data-sv-due-input></label>' . $reasonField('Motif du report', true), 'Reporter'],
        C::ACTION_REASSIGN => ['Changer le responsable', 'Désignez la personne qui doit agir sur cette étape.', $ownerSelect($ownerOptions) . $reasonField('Motif (facultatif)', false), 'Enregistrer'],
        C::ACTION_NOTE => ['Ajouter une note interne', 'La note reste réservée à l’encadrement : le membre ne la voit pas.', '<label class="eff-sv-field"><span>Note</span><textarea name="note" rows="5" maxlength="2000" required></textarea></label>', 'Enregistrer la note'],
        C::ACTION_SKIP => ['Ignorer l’étape', 'Seule une étape facultative peut être ignorée. Elle ne compte plus dans la progression.', $reasonField('Motif', true), 'Ignorer l’étape'],
        C::ACTION_UNSKIP => ['Rétablir l’étape', 'L’étape ignorée redevient à faire.', $reasonField('Motif (facultatif)', false), 'Rétablir'],
        C::ACTION_REMIND => ['Relancer le membre', 'Une notification est envoyée au membre. Une même étape n’est relancée qu’une fois par jour.', '', 'Envoyer la relance'],
        C::ACTION_REMOVE => ['Retirer l’étape', 'L’étape personnalisée disparaît du dossier. L’historique garde sa trace.', $reasonField('Motif (facultatif)', false), 'Retirer l’étape'],
    ];
    ?>
    <?php foreach ($dialogs as $act => [$title, $lead, $fields, $button]): ?>
        <dialog class="eff-sv-dialog" id="sv-dlg-<?= $h($act) ?>" aria-labelledby="sv-dlg-<?= $h($act) ?>-title" data-sv-dialog>
            <form method="post" action="<?= $h($base . '/' . $act) ?>" class="eff-sv-dialog__body" data-sv-form>
                <input type="hidden" name="_csrf_token" value="<?= $h($csrfToken) ?>">
                <input type="hidden" name="key" value="" data-sv-key-input>
                <header class="eff-sv-dialog__head">
                    <h2 id="sv-dlg-<?= $h($act) ?>-title"><?= $h($title) ?></h2>
                    <button type="button" class="eff-sv-close" data-sv-close aria-label="Fermer">×</button>
                </header>
                <div class="eff-sv-dialog__scroll">
                    <p class="eff-sv-dialog__step" data-sv-step-title></p>
                    <p class="eff-sv-lead"><?= $h($lead) ?></p>
                    <?= $fields ?>
                </div>
                <footer class="eff-sv-dialog__foot">
                    <button type="button" class="eff-sv-btn" data-sv-close>Annuler</button>
                    <button type="submit" class="eff-sv-btn eff-sv-btn--primary<?= $act === C::ACTION_REMOVE ? ' eff-sv-btn--danger' : '' ?>"><?= $h($button) ?></button>
                </footer>
            </form>
        </dialog>
    <?php endforeach; ?>
    <dialog class="eff-sv-dialog" id="sv-dlg-add" aria-labelledby="sv-dlg-add-title" data-sv-dialog>
        <form method="post" action="<?= $h($base . '/add') ?>" class="eff-sv-dialog__body" data-sv-form>
            <input type="hidden" name="_csrf_token" value="<?= $h($csrfToken) ?>">
            <header class="eff-sv-dialog__head">
                <h2 id="sv-dlg-add-title">Ajouter une étape au dossier</h2>
                <button type="button" class="eff-sv-close" data-sv-close aria-label="Fermer">×</button>
            </header>
            <div class="eff-sv-dialog__scroll">
                <p class="eff-sv-lead">Une étape propre à ce membre, par exemple une visite médicale ou une remise de matériel. Vous pourrez la retirer tant qu’elle n’est pas terminée.</p>
                <label class="eff-sv-field"><span>Intitulé</span><input type="text" name="title" maxlength="180" required minlength="3" placeholder="Ex. Visite médicale d’aptitude"></label>
                <label class="eff-sv-field"><span>Parcours</span>
                    <select name="track" required>
                        <?php foreach ($trackLabels as $tk => $tl): ?><option value="<?= $h($tk) ?>"><?= $h($tl) ?></option><?php endforeach; ?>
                    </select>
                </label>
                <label class="eff-sv-field"><span>Échéance (facultative)</span><input type="date" name="due_on" min="<?= $h($today) ?>"></label>
                <?= $ownerSelect($ownerOptions) ?>
            </div>
            <footer class="eff-sv-dialog__foot">
                <button type="button" class="eff-sv-btn" data-sv-close>Annuler</button>
                <button type="submit" class="eff-sv-btn eff-sv-btn--primary">Ajouter l’étape</button>
            </footer>
        </form>
    </dialog>
<?php endif; ?>
