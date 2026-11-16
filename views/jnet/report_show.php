<?php
declare(strict_types=1);

/**
 * JNET — lecture d'un compte rendu post-op (/jnet/comptes-rendus/{id}). Même contenu que la fiche du back-office
 * (views/admin/aar_reports/show.php), dans la grammaire du JNET. Données : JnetReportsController::show.
 *
 * @var array<string, mixed> $jrReport
 * @var array<string, mixed>|null $jrMission
 * @var array<string, mixed>|null $jrTemplate
 * @var list<array<string, mixed>> $jrAnnexes
 * @var bool $jrCanManage
 */
use App\Services\Aar\AarReportAdminService as Aar;

$h = static fn (mixed $v): string => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
$r = is_array($jrReport ?? null) ? $jrReport : [];
$id = (int) ($r['id'] ?? 0);
$base = url('jnet/comptes-rendus');
$canManage = !empty($jrCanManage);
$csrf = '<input type="hidden" name="_csrf_token" value="' . $h($csrfToken ?? '') . '">';
$fmt = static function (mixed $raw, string $format = 'd/m/Y'): string {
    $t = is_string($raw) && $raw !== '' ? strtotime($raw) : false;

    return $t ? date($format, $t) : '—';
};
$status = (string) ($r['status'] ?? 'pending');
$op = trim((string) ($r['operation_label'] ?? '')) !== '' ? (string) $r['operation_label'] : (string) ($r['title'] ?? 'Compte rendu');
$title = trim((string) ($r['title'] ?? ''));
$summaryHeading = trim((string) ($r['summary_heading'] ?? ''));
$summary = trim((string) ($r['summary_text'] ?? ''));
$lessons = trim((string) ($r['lessons_learned'] ?? ''));
$lessonsCtx = trim((string) ($r['lessons_context'] ?? ''));
$conclusion = trim((string) ($r['conclusion_text'] ?? ''));
$strengths = is_array($r['strengths'] ?? null) ? $r['strengths'] : [];
$weaknesses = is_array($r['weaknesses'] ?? null) ? $r['weaknesses'] : [];
$customRows = !empty($r['is_custom']) && is_array($r['custom_rows'] ?? null) ? $r['custom_rows'] : [];
$actions = Aar::actions([$r]);
$openCount = count(array_filter($actions, static fn (array $a): bool => $a['state'] === 'open'));
$mission = is_array($jrMission ?? null) ? $jrMission : null;
$annexes = is_array($jrAnnexes ?? null) ? $jrAnnexes : [];
$annexKinds = \App\Repositories\AarAnnexRepository::KINDS;
$rep = !empty($r['reported_at']) ? strtotime((string) $r['reported_at']) : false;
$end = !empty($r['mission_ended_at']) ? strtotime((string) $r['mission_ended_at']) : false;
$delay = $rep && $end && $rep >= $end ? ($rep - $end) / 3600 : null;
$author = trim((string) ($r['author_name'] ?? ''));
$unit = trim((string) ($r['author_unit'] ?? ''));
$hasContent = $customRows !== [] || $summary !== '' || $summaryHeading !== '' || $strengths !== [] || $weaknesses !== [] || $lessons !== '' || $conclusion !== '';

// Historique : les dates que le compte rendu garde (création, dépôt, validation, dernière modification), dans l'ordre.
$history = array_values(array_filter([
    ['at' => $r['created_at'] ?? null, 'label' => 'Créé'],
    ['at' => $r['reported_at'] ?? null, 'label' => 'Déposé' . ($author !== '' ? ' par ' . $author : '')],
    $status === 'validated' ? ['at' => $r['validated_at'] ?? null, 'label' => 'Validé' . (trim((string) ($r['validator_name'] ?? '')) !== '' ? ' par ' . $r['validator_name'] : '')] : null,
    ['at' => $r['updated_at'] ?? null, 'label' => 'Dernière modification'],
], static fn ($e) => is_array($e) && is_string($e['at']) && $e['at'] !== ''));
usort($history, static fn (array $a, array $b): int => strcmp((string) $a['at'], (string) $b['at']));
$num = 0;
?>
<div class="jr jr-show" data-jr>

<header class="jr-head">
    <h1 class="ja-disp ja-disp--md"><?= $h($op) ?></h1>
    <p class="jr-head__meta">
        <span class="ja-mono"><?= $h($r['reference_code'] ?? '') ?></span>
        <span class="ja-pill jr-pill jr-pill--<?= $h(Aar::tone($status)) ?>"><?= $h(Aar::statusLabel($status)) ?></span>
    </p>
    <p class="ja-lead">
        <?= $title !== '' && $title !== $op ? $h($title) . ' · ' : '' ?>Par <?= $h($author !== '' ? $author : 'auteur inconnu') ?><?= $unit !== '' ? ' (' . $h($unit) . ')' : '' ?> · déposé le <?= $h($fmt($r['reported_at'] ?? null, 'd/m/Y à H:i')) ?>
    </p>
    <div class="jr-head__actions">
        <?php if ($canManage && Aar::transitionsFrom($status) !== []): ?>
            <button type="button" class="ja-b1 jr-btn" data-jr-open="jr-status" aria-haspopup="dialog">Traiter</button>
        <?php endif; ?>
        <a class="ja-b2 jr-btn" href="<?= $h($jrReplyUrl ?? url('jnet/courrier/nouveau')) ?>">Répondre par message</a>
        <a class="ja-b2 jr-btn" href="<?= $h(url('back-office/atak/comptes-rendus/' . $id . '/pdf')) ?>">PDF</a>
        <a class="ja-b2 jr-btn jr-cross" href="<?= $h($jrBackOfficeUrl ?? url('back-office/atak/comptes-rendus/' . $id)) ?>">Ouvrir dans le back-office</a>
    </div>
</header>

<section class="ja-stats jr-stats" style="--n:4" aria-label="Chiffres clés">
    <div class="ja-stat"><b><?= $h(Aar::statusLabel($status)) ?></b><span>statut</span></div>
    <div class="ja-stat"><b><?= count($strengths) ?> <small>/ <?= count($weaknesses) ?></small></b><span>points forts / à progresser</span></div>
    <a class="ja-stat<?= $openCount > 0 ? ' jr-stat--warn' : '' ?>" href="#actions"><b><?= $openCount ?> <small>/ <?= count($actions) ?></small></b><span>actions en cours</span></a>
    <div class="ja-stat<?= $delay !== null && $delay > Aar::TARGET_DELAY_HOURS ? ' jr-stat--warn' : '' ?>"><b><?= $h(Aar::formatDelay($delay)) ?></b><span>après la fin de mission</span></div>
</section>

<div class="jr-read">
    <article class="jr-doc">
        <?php if ($customRows !== []): ?>
        <section class="jr-sec" id="questionnaire" aria-labelledby="jr-q-t">
            <header class="jr-sh"><span class="jr-sh__n ja-mono" aria-hidden="true"><?= str_pad((string) ++$num, 2, '0', STR_PAD_LEFT) ?></span><div><h2 class="ja-disp ja-disp--sm" id="jr-q-t">Questionnaire</h2><p>Réponses au debriefing<?= is_array($jrTemplate ?? null) ? ' « ' . $h($jrTemplate['title'] ?? '') . ' »' : '' ?>.</p></div></header>
            <dl class="jr-qa">
                <?php foreach ($customRows as $row): ?>
                    <div><dt><?= $h($row['label'] ?? '') ?></dt><dd<?= !empty($row['empty']) ? ' class="jr-muted"' : '' ?>><?= !empty($row['empty']) ? 'Non renseigné' : nl2br($h($row['display'] ?? '')) ?></dd></div>
                <?php endforeach; ?>
            </dl>
        </section>
        <?php endif; ?>

        <?php if ($summary !== '' || $summaryHeading !== ''): ?>
        <section class="jr-sec" id="synthese" aria-labelledby="jr-s-t">
            <header class="jr-sh"><span class="jr-sh__n ja-mono" aria-hidden="true"><?= str_pad((string) ++$num, 2, '0', STR_PAD_LEFT) ?></span><div><h2 class="ja-disp ja-disp--sm" id="jr-s-t"><?= $h($summaryHeading !== '' ? $summaryHeading : 'Synthèse') ?></h2><p>Ce qui était prévu et ce qui s’est passé.</p></div></header>
            <?php if ($summary !== ''): ?><p class="jr-text"><?= nl2br($h($summary)) ?></p><?php endif; ?>
        </section>
        <?php endif; ?>

        <?php if ($strengths !== [] || $weaknesses !== []): ?>
        <section class="jr-sec" id="points" aria-labelledby="jr-p-t">
            <header class="jr-sh"><span class="jr-sh__n ja-mono" aria-hidden="true"><?= str_pad((string) ++$num, 2, '0', STR_PAD_LEFT) ?></span><div><h2 class="ja-disp ja-disp--sm" id="jr-p-t">Points relevés</h2><p>Ce qui a fonctionné et ce qui doit progresser.</p></div></header>
            <div class="jr-cols">
                <div>
                    <h3 class="jr-col-t jr-ok">Ce qui a fonctionné <small><?= count($strengths) ?></small></h3>
                    <?php if ($strengths === []): ?><p class="jr-muted">Aucun point fort relevé.</p><?php else: ?>
                    <ul class="jr-points"><?php foreach ($strengths as $p): ?><li><?= $h($p) ?></li><?php endforeach; ?></ul><?php endif; ?>
                </div>
                <div>
                    <h3 class="jr-col-t jr-warn">Ce qui doit progresser <small><?= count($weaknesses) ?></small></h3>
                    <?php if ($weaknesses === []): ?><p class="jr-muted">Aucun point à progresser relevé.</p><?php else: ?>
                    <ul class="jr-points"><?php foreach ($weaknesses as $p): ?><li><?= $h($p) ?></li><?php endforeach; ?></ul><?php endif; ?>
                </div>
            </div>
        </section>
        <?php endif; ?>

        <?php if ($lessons !== ''): ?>
        <section class="jr-sec" id="enseignements" aria-labelledby="jr-l-t">
            <header class="jr-sh"><span class="jr-sh__n ja-mono" aria-hidden="true"><?= str_pad((string) ++$num, 2, '0', STR_PAD_LEFT) ?></span><div><h2 class="ja-disp ja-disp--sm" id="jr-l-t">Enseignements</h2><p><?= $h($lessonsCtx !== '' ? 'À retenir pour ' . $lessonsCtx . '.' : 'Ce qu’on retient pour la suite.') ?></p></div></header>
            <p class="jr-text"><?= nl2br($h($lessons)) ?></p>
        </section>
        <?php endif; ?>

        <?php if ($conclusion !== ''): ?>
        <section class="jr-sec" id="conclusion" aria-labelledby="jr-c-t">
            <header class="jr-sh"><span class="jr-sh__n ja-mono" aria-hidden="true"><?= str_pad((string) ++$num, 2, '0', STR_PAD_LEFT) ?></span><div><h2 class="ja-disp ja-disp--sm" id="jr-c-t">Conclusion</h2><p>L’avis du commandement.</p></div></header>
            <p class="jr-text"><?= nl2br($h($conclusion)) ?></p>
        </section>
        <?php endif; ?>

        <?php if (!$hasContent): ?>
            <div class="ja-empty jr-sec"><p>Ce compte rendu n’a pas encore de contenu.</p></div>
        <?php endif; ?>

        <section class="jr-sec" id="actions" aria-labelledby="jr-a-t">
            <header class="jr-sh"><span class="jr-sh__n ja-mono" aria-hidden="true"><?= str_pad((string) ++$num, 2, '0', STR_PAD_LEFT) ?></span><div><h2 class="ja-disp ja-disp--sm" id="jr-a-t">Actions décidées</h2><p><?= $openCount ?> en cours sur <?= count($actions) ?>, avec leur responsable et leur échéance.</p></div></header>
            <?php if ($actions === []): ?>
                <div class="ja-empty"><p>Aucune action décidée.</p></div>
            <?php else: ?>
                <ul class="ja-rows jr-rows">
                    <?php foreach ($actions as $a): ?>
                        <li class="ja-row jr-row jr-row--static jr-row--act">
                            <span class="n"><?= $a['state'] === 'closed' ? '✓' : '○' ?></span>
                            <span class="t"><b><?= $h($a['label']) ?></b><small><?= $h($a['owner'] !== '' ? $a['owner'] : 'sans responsable') ?> · <?= $a['due_at'] ? 'échéance ' . $h($fmt($a['due_at'])) : 'sans échéance' ?><?= $a['state'] === 'closed' && !empty($a['closed_at']) ? ' · close le ' . $h($fmt($a['closed_at'])) : '' ?></small></span>
                            <span class="m"><span class="ja-pill jr-pill <?= $a['state'] === 'closed' ? 'jr-pill--ok' : ($a['overdue'] ? 'jr-pill--bad' : 'jr-pill--warn') ?>"><?= $a['state'] === 'closed' ? 'Close' : ($a['overdue'] ? 'En retard' : 'En cours') ?></span></span>
                            <span class="ar">
                                <?php if ($canManage): ?>
                                    <form method="post" action="<?= $h($base . '/' . $id . '/actions/basculer') ?>"><?= $csrf ?>
                                        <input type="hidden" name="state" value="<?= $h($a['state']) ?>"><input type="hidden" name="index" value="<?= (int) $a['index'] ?>">
                                        <button type="submit" class="ja-b2 jr-btn jr-btn--sm" aria-label="<?= $a['state'] === 'open' ? 'Clore' : 'Rouvrir' ?> : <?= $h($a['label']) ?>"><?= $a['state'] === 'open' ? 'Clore' : 'Rouvrir' ?></button>
                                    </form>
                                <?php endif; ?>
                            </span>
                        </li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>
        </section>

        <?php if ($annexes !== []): ?>
        <section class="jr-sec" id="annexes" aria-labelledby="jr-x-t">
            <header class="jr-sh"><span class="jr-sh__n ja-mono" aria-hidden="true"><?= str_pad((string) ++$num, 2, '0', STR_PAD_LEFT) ?></span><div><h2 class="ja-disp ja-disp--sm" id="jr-x-t">Pièces jointes</h2><p>Comptes rendus du terrain, BDA et annexes rédigées, joints au compte rendu.</p></div></header>
            <ul class="ja-rows jr-rows">
                <?php foreach ($annexes as $i => $ax):
                    $isNote = ($ax['kind'] ?? '') === 'note';
                    $axTitle = $isNote
                        ? (trim((string) ($ax['title'] ?? '')) !== '' ? (string) $ax['title'] : 'Annexe')
                        : (trim((string) ($ax['src_number'] ?? '')) !== '' ? (string) $ax['src_number'] : 'CR-' . (int) $ax['source_id']);
                    $axText = $isNote ? (string) ($ax['body'] ?? '') : trim((string) ($ax['src_summary'] ?? '') . "\n" . (string) ($ax['src_details'] ?? ''));
                    $dlg = 'jr-annex-' . (int) ($ax['id'] ?? $i);
                ?>
                    <li>
                        <button type="button" class="ja-row jr-row" data-jr-open="<?= $h($dlg) ?>" aria-haspopup="dialog">
                            <span class="n"><?= $h(str_pad((string) ($i + 1), 2, '0', STR_PAD_LEFT)) ?></span>
                            <span class="t"><b><?= $h($axTitle) ?></b><small><?= $h($annexKinds[$ax['kind']] ?? 'Annexe') ?><?= !$isNote && !empty($ax['src_at']) ? ' · ' . $h($fmt($ax['src_at'], 'd/m/Y H:i')) : '' ?></small></span>
                            <span class="m"></span>
                            <span class="ar" aria-hidden="true">→</span>
                        </button>
                        <dialog class="jr-dlg" id="<?= $h($dlg) ?>" aria-labelledby="<?= $h($dlg) ?>-t">
                            <div class="jr-dlg__head">
                                <h3 id="<?= $h($dlg) ?>-t"><?= $h($axTitle) ?></h3>
                                <button type="button" class="jr-dlg__x" data-jr-close aria-label="Fermer">×</button>
                            </div>
                            <p class="jr-text"><?= $axText !== '' ? nl2br($h($axText)) : '<span class="jr-muted">Contenu indisponible (rapport supprimé).</span>' ?></p>
                            <div class="jr-dlg__foot">
                                <?php if (!$isNote && (int) ($ax['source_id'] ?? 0) > 0): ?><a class="ja-b2 jr-btn" href="<?= $h(url('back-office/remontees/cr/' . (int) $ax['source_id'])) ?>">Ouvrir le rapport du terrain</a><?php endif; ?>
                                <button type="button" class="ja-b2 jr-btn" data-jr-close>Fermer</button>
                            </div>
                        </dialog>
                    </li>
                <?php endforeach; ?>
            </ul>
        </section>
        <?php endif; ?>
    </article>

    <aside class="jr-side" aria-label="Contexte du compte rendu">
        <section class="jr-card" aria-labelledby="jr-m-t">
            <h2 class="jr-card__t" id="jr-m-t">Mission</h2>
            <?php if ($mission === null): ?>
                <p class="jr-muted">Aucune mission liée : le délai de dépôt ne peut pas être mesuré.</p>
            <?php else: ?>
                <dl class="jr-kv">
                    <div><dt>Mission</dt><dd><?= $h($mission['title'] ?? '') ?></dd></div>
                    <div><dt>État</dt><dd><?= $h($mission['status_label'] ?? '') ?></dd></div>
                    <div><dt>Début</dt><dd><?= $h($fmt($mission['started_at'] ?? null, 'd/m/Y H:i')) ?></dd></div>
                    <div><dt>Fin</dt><dd><?= $h($fmt($mission['ended_at'] ?? null, 'd/m/Y H:i')) ?></dd></div>
                </dl>
                <p class="jr-card__links">
                    <?php if (!empty($mission['links']['replay'])): ?><a class="ja-b2 jr-btn" href="<?= $h($mission['links']['replay']) ?>">Revoir sur la carte</a><?php endif; ?>
                    <?php if (!empty($jrMissionPdfUrl)): ?><a class="ja-b2 jr-btn" href="<?= $h($jrMissionPdfUrl) ?>">Synthèse de mission (PDF)</a><?php endif; ?>
                </p>
            <?php endif; ?>
        </section>
        <section class="jr-card" aria-labelledby="jr-h-t">
            <h2 class="jr-card__t" id="jr-h-t">Historique</h2>
            <ol class="jr-timeline">
                <?php foreach ($history as $e): ?>
                    <li><time datetime="<?= $h(str_replace(' ', 'T', (string) $e['at'])) ?>"><?= $h($fmt($e['at'], 'd/m/Y H:i')) ?></time><span><?= $h($e['label']) ?></span></li>
                <?php endforeach; ?>
            </ol>
            <dl class="jr-kv">
                <div><dt>Formulaire</dt><dd><?= !empty($r['is_custom']) ? $h(is_array($jrTemplate ?? null) ? ($jrTemplate['title'] ?? 'Questionnaire') : 'Questionnaire') : 'Standard' ?></dd></div>
            </dl>
        </section>
    </aside>
</div>

<?php if ($canManage && Aar::transitionsFrom($status) !== []): ?>
<dialog class="jr-dlg" id="jr-status" aria-labelledby="jr-status-t">
    <div class="jr-dlg__head">
        <h2 id="jr-status-t">Traiter <?= $h($r['reference_code'] ?? '') ?></h2>
        <button type="button" class="jr-dlg__x" data-jr-close aria-label="Fermer">×</button>
    </div>
    <p class="jr-muted">Statut actuel : <?= $h(Aar::statusLabel($status)) ?>. L’auteur est prévenu quand le compte rendu est validé ou renvoyé.</p>
    <div class="jr-dlg__choices">
        <?php foreach (Aar::transitionsFrom($status) as $t): ?>
            <form method="post" action="<?= $h($base . '/' . $id . '/statut') ?>"><?= $csrf ?>
                <input type="hidden" name="status" value="<?= $h($t['status']) ?>">
                <button type="submit" class="<?= $t['status'] === 'validated' || ($t['status'] === 'in_review' && $status === 'pending') ? 'ja-b1' : 'ja-b2' ?> jr-btn jr-btn--wide"><?= $h($t['label']) ?></button>
            </form>
        <?php endforeach; ?>
    </div>
    <div class="jr-dlg__foot"><button type="button" class="ja-b2 jr-btn" data-jr-close>Annuler</button></div>
</dialog>
<?php endif; ?>
</div>
