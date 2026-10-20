<?php
declare(strict_types=1);

/**
 * Fiche d'un compte rendu (/back-office/atak/comptes-rendus/{id}) : lecture, circuit de relecture, suivi des actions.
 *
 * @var array<string, mixed> $aarReport
 * @var array<string, mixed>|null $aarMission
 * @var array<string, mixed>|null $aarTemplate
 */
use App\Services\Aar\AarReportAdminService as Aar;

$h = static fn (mixed $v): string => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
$r = is_array($aarReport ?? null) ? $aarReport : [];
$base = url('back-office/atak/comptes-rendus');
$id = (int) ($r['id'] ?? 0);
$canManage = !empty($aarCanManage);
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
$openCount = count(array_filter($actions, static fn ($a) => $a['state'] === 'open'));
$mission = is_array($aarMission ?? null) ? $aarMission : null;
$rep = !empty($r['reported_at']) ? strtotime((string) $r['reported_at']) : false;
$end = !empty($r['mission_ended_at']) ? strtotime((string) $r['mission_ended_at']) : false;
$delay = $rep && $end && $rep >= $end ? ($rep - $end) / 3600 : null;

$sections = [];
if ($customRows !== []) {
    $sections['debrief'] = 'Questionnaire';
}
if ($summary !== '' || $summaryHeading !== '') {
    $sections['synthese'] = 'Synthèse';
}
if ($strengths !== [] || $weaknesses !== []) {
    $sections['points'] = 'Points relevés';
}
if ($lessons !== '') {
    $sections['enseignements'] = 'Enseignements';
}
if ($conclusion !== '') {
    $sections['conclusion'] = 'Conclusion';
}
$sections['actions'] = 'Actions';
$num = 0;

// Étapes du circuit de relecture.
$steps = [
    ['key' => 'pending', 'label' => 'Déposé', 'note' => $fmt($r['reported_at'] ?? null, 'd/m/Y H:i')],
    ['key' => 'in_review', 'label' => 'En relecture', 'note' => ''],
    ['key' => 'validated', 'label' => 'Validé', 'note' => $status === 'validated' ? $fmt($r['validated_at'] ?? null) . (trim((string) ($r['validator_name'] ?? '')) !== '' ? ' · ' . $r['validator_name'] : '') : ''],
];
$rank = ['pending' => 0, 'missing' => 0, 'in_review' => 1, 'validated' => 2][$status] ?? 0;
?>
<div class="bo-aar bo-aar-show">

  <section class="ath-page-head ath-rise" aria-labelledby="ath-page-title">
    <div class="ath-page-head__copy">
      <p class="ath-page-head__kicker"><a href="<?= $h($base) ?>">Comptes rendus</a> · <?= $h($r['reference_code'] ?? '') ?></p>
      <h1 class="ath-page-head__title" id="ath-page-title"><?= $h($op) ?> <span class="ath-tag ath-tag--<?= Aar::tone($status) ?> bo-aar-head-tag"><?= $h(Aar::statusLabel($status)) ?></span></h1>
      <p class="ath-page-head__sub">
        <?= $title !== '' && $title !== $op ? $h($title) . ' · ' : '' ?>
        Par <?= $h(trim((string) ($r['author_name'] ?? '')) ?: 'auteur inconnu') ?> · déposé le <?= $h($fmt($r['reported_at'] ?? null, 'd/m/Y à H:i')) ?>
        <?php if ($delay !== null): ?> · <span class="<?= $delay > Aar::TARGET_DELAY_HOURS ? 'bo-aar-late' : '' ?>"><?= $h(Aar::formatDelay($delay)) ?> après la fin de mission</span><?php endif; ?>
      </p>
    </div>
    <div class="ath-page-head__actions">
      <a class="ath-btn" href="<?= $h($base . '/' . $id . '/pdf') ?>">Exporter en PDF</a>
      <?php if ($canManage): ?><a class="ath-btn ath-btn--solid" href="<?= $h($base . '/' . $id . '/edit') ?>">Modifier</a><?php endif; ?>
    </div>
  </section>

  <section class="ath-panel bo-aar-flow" aria-label="Circuit de relecture">
    <?php if ($status === 'missing'): ?>
      <p class="bo-aar-flow__missing"><span class="ath-tag ath-tag--bad">Manquant</span> Ce retour est attendu mais n’a pas été rédigé.</p>
    <?php else: ?>
    <ol class="bo-aar-steps">
      <?php foreach ($steps as $i => $s): $state = $i < $rank ? 'done' : ($i === $rank ? 'current' : 'todo'); ?>
        <li class="bo-aar-steps__step is-<?= $state ?>"><span class="bo-aar-steps__dot"><?= $state === 'done' || ($state === 'current' && $status === 'validated') ? '✓' : $i + 1 ?></span><span><b><?= $h($s['label']) ?></b><?php if ($s['note'] !== '' && $s['note'] !== '—'): ?><small><?= $h($s['note']) ?></small><?php endif; ?></span></li>
      <?php endforeach; ?>
    </ol>
    <?php endif; ?>
    <?php if ($canManage): ?>
    <div class="bo-aar-flow__actions">
      <?php foreach (Aar::transitionsFrom($status) as $t): ?>
        <form method="post" action="<?= $h($base . '/' . $id . '/statut') ?>"><?= $csrf ?><input type="hidden" name="status" value="<?= $h($t['status']) ?>">
          <button type="submit" class="ath-btn<?= $t['status'] === 'validated' || ($t['status'] === 'in_review' && $status === 'pending') ? ' ath-btn--solid' : '' ?>"><?= $h($t['label']) ?></button>
        </form>
      <?php endforeach; ?>
    </div>
    <?php endif; ?>
  </section>

  <div class="bo-aar-read">
    <article class="ath-panel bo-aar-doc">
      <?php if ($customRows !== []): ?>
      <section class="bo-aar-sec" id="debrief">
        <p class="bo-aar-sec__k">Questionnaire<?= is_array($aarTemplate ?? null) ? ' · ' . $h($aarTemplate['title'] ?? '') : '' ?></p>
        <h2 class="bo-aar-sec__t">Réponses au debriefing</h2>
        <dl class="bo-aar-qa">
          <?php foreach ($customRows as $row): ?>
            <div><dt><?= $h($row['label'] ?? '') ?></dt><dd<?= !empty($row['empty']) ? ' class="bo-aar-muted"' : '' ?>><?= !empty($row['empty']) ? 'Non renseigné' : nl2br($h($row['display'] ?? '')) ?></dd></div>
          <?php endforeach; ?>
        </dl>
      </section>
      <?php endif; ?>

      <?php if (isset($sections['synthese'])): ?>
      <section class="bo-aar-sec" id="synthese">
        <p class="bo-aar-sec__k"><?= ++$num ?> · Synthèse</p>
        <h2 class="bo-aar-sec__t"><?= $h($summaryHeading !== '' ? $summaryHeading : 'Synthèse opérationnelle') ?></h2>
        <?php if ($summary !== ''): ?><p class="bo-aar-text"><?= nl2br($h($summary)) ?></p><?php endif; ?>
      </section>
      <?php endif; ?>

      <?php if (isset($sections['points'])): ?>
      <section class="bo-aar-sec" id="points">
        <p class="bo-aar-sec__k"><?= ++$num ?> · Points relevés</p>
        <div class="bo-aar-cols">
          <div>
            <h3 class="bo-aar-col-t bo-aar-ok">Ce qui a fonctionné <small><?= count($strengths) ?></small></h3>
            <?php if ($strengths === []): ?><p class="bo-aar-muted">Aucun point fort relevé.</p><?php else: ?>
            <ul class="bo-aar-points bo-aar-points--ok"><?php foreach ($strengths as $p): ?><li><?= $h($p) ?></li><?php endforeach; ?></ul><?php endif; ?>
          </div>
          <div>
            <h3 class="bo-aar-col-t bo-aar-warn">Ce qui doit progresser <small><?= count($weaknesses) ?></small></h3>
            <?php if ($weaknesses === []): ?><p class="bo-aar-muted">Aucun point faible relevé.</p><?php else: ?>
            <ul class="bo-aar-points bo-aar-points--warn"><?php foreach ($weaknesses as $p): ?><li><?= $h($p) ?></li><?php endforeach; ?></ul><?php endif; ?>
          </div>
        </div>
      </section>
      <?php endif; ?>

      <?php if (isset($sections['enseignements'])): ?>
      <section class="bo-aar-sec" id="enseignements">
        <p class="bo-aar-sec__k"><?= ++$num ?> · Enseignements</p>
        <h2 class="bo-aar-sec__t"><?= $h($lessonsCtx !== '' ? 'À retenir pour ' . $lessonsCtx : 'À retenir') ?></h2>
        <p class="bo-aar-text"><?= nl2br($h($lessons)) ?></p>
      </section>
      <?php endif; ?>

      <?php if (isset($sections['conclusion'])): ?>
      <section class="bo-aar-sec" id="conclusion">
        <p class="bo-aar-sec__k"><?= ++$num ?> · Conclusion</p>
        <h2 class="bo-aar-sec__t">Avis du commandement</h2>
        <p class="bo-aar-text"><?= nl2br($h($conclusion)) ?></p>
      </section>
      <?php endif; ?>

      <section class="bo-aar-sec" id="actions">
        <p class="bo-aar-sec__k"><?= ++$num ?> · Actions</p>
        <h2 class="bo-aar-sec__t">Actions décidées <small class="bo-aar-muted"><?= $openCount ?> en cours sur <?= count($actions) ?></small></h2>
        <?php if ($actions === []): ?>
          <p class="bo-aar-muted">Aucune action décidée.<?= $canManage ? ' <a href="' . $h($base . '/' . $id . '/edit') . '">En ajouter</a>' : '' ?></p>
        <?php else: ?>
        <ul class="bo-aar-actlist">
          <?php foreach ($actions as $a): ?>
            <li class="bo-aar-actlist__item is-<?= $a['state'] ?><?= $a['overdue'] ? ' is-late' : '' ?>">
              <?php if ($canManage): ?>
              <form method="post" action="<?= $h($base . '/' . $id . '/actions/basculer') ?>"><?= $csrf ?>
                <input type="hidden" name="state" value="<?= $h($a['state']) ?>"><input type="hidden" name="index" value="<?= (int) $a['index'] ?>">
                <button type="submit" class="bo-aar-box" aria-label="<?= $a['state'] === 'open' ? 'Clore' : 'Rouvrir' ?> : <?= $h($a['label']) ?>" title="<?= $a['state'] === 'open' ? 'Clore l’action' : 'Rouvrir l’action' ?>"><?= $a['state'] === 'closed' ? '✓' : '' ?></button>
              </form>
              <?php else: ?><span class="bo-aar-box" aria-hidden="true"><?= $a['state'] === 'closed' ? '✓' : '' ?></span><?php endif; ?>
              <span class="bo-aar-actlist__body">
                <b><?= $h($a['label']) ?></b>
                <small><?= $h($a['owner'] !== '' ? $a['owner'] : 'sans responsable') ?> · <?= $a['due_at'] ? 'échéance ' . $h($fmt($a['due_at'])) : 'sans échéance' ?><?= $a['state'] === 'closed' && !empty($a['closed_at']) ? ' · close le ' . $h($fmt($a['closed_at'])) : '' ?></small>
              </span>
              <?php if ($a['overdue']): ?><span class="ath-tag ath-tag--bad">En retard</span><?php elseif ($a['state'] === 'closed'): ?><span class="ath-tag ath-tag--ok">Close</span><?php else: ?><span class="ath-tag ath-tag--warn">En cours</span><?php endif; ?>
            </li>
          <?php endforeach; ?>
        </ul>
        <?php endif; ?>
      </section>

      <?php if (count($sections) === 1 && $customRows === []): ?>
        <p class="bo-aar-muted bo-aar-sec">Ce compte rendu n’a pas encore de contenu.<?= $canManage ? ' <a href="' . $h($base . '/' . $id . '/edit') . '">Le compléter</a>' : '' ?></p>
      <?php endif; ?>
    </article>

    <aside class="bo-aar-side">
      <nav class="ath-panel bo-aar-toc" aria-label="Sommaire">
        <p class="bo-aar-side__t">Sommaire</p>
        <?php foreach ($sections as $anchor => $label): ?><a href="#<?= $h($anchor) ?>"><?= $h($label) ?></a><?php endforeach; ?>
      </nav>

      <section class="ath-panel">
        <p class="bo-aar-side__t">Chronologie</p>
        <dl class="bo-aar-kv">
          <div><dt>Référence</dt><dd class="bo-aar-mono"><?= $h($r['reference_code'] ?? '') ?></dd></div>
          <div><dt>Créé</dt><dd><?= $h($fmt($r['created_at'] ?? null, 'd/m/Y H:i')) ?></dd></div>
          <div><dt>Déposé</dt><dd><?= $h($fmt($r['reported_at'] ?? null, 'd/m/Y H:i')) ?></dd></div>
          <div><dt>Dernière modification</dt><dd><?= $h($fmt($r['updated_at'] ?? null, 'd/m/Y H:i')) ?></dd></div>
          <?php if ($status === 'validated'): ?><div><dt>Validé</dt><dd><?= $h($fmt($r['validated_at'] ?? null)) ?><?= trim((string) ($r['validator_name'] ?? '')) !== '' ? ' par ' . $h($r['validator_name']) : '' ?></dd></div><?php endif; ?>
          <div><dt>Formulaire</dt><dd><?= !empty($r['is_custom']) ? $h(is_array($aarTemplate ?? null) ? ($aarTemplate['title'] ?? 'Questionnaire') : 'Questionnaire (modèle supprimé)') : 'Standard' ?></dd></div>
        </dl>
      </section>

      <section class="ath-panel">
        <p class="bo-aar-side__t">Mission</p>
        <?php if ($mission === null): ?>
          <p class="bo-aar-muted">Aucune mission liée : le délai de dépôt ne peut pas être mesuré.<?= $canManage ? ' <a href="' . $h($base . '/' . $id . '/edit') . '">Lier une mission</a>' : '' ?></p>
        <?php else: ?>
          <dl class="bo-aar-kv">
            <div><dt>Mission</dt><dd><?= $h($mission['title'] ?? '') ?></dd></div>
            <div><dt>État</dt><dd><?= $h($mission['status_label'] ?? '') ?></dd></div>
            <div><dt>Début</dt><dd><?= $h($fmt($mission['started_at'] ?? null, 'd/m/Y H:i')) ?></dd></div>
            <div><dt>Fin</dt><dd><?= $h($fmt($mission['ended_at'] ?? null, 'd/m/Y H:i')) ?></dd></div>
          </dl>
          <p class="bo-aar-side__links">
            <?php if (!empty($aarMissionPdfUrl)): ?><a class="ath-btn" href="<?= $h($aarMissionPdfUrl) ?>" target="_blank" rel="noopener">Synthèse de mission (PDF)</a><?php endif; ?>
            <?php if (!empty($mission['links']['replay'])): ?><a class="ath-btn" href="<?= $h($mission['links']['replay']) ?>">Rejouer la mission</a><?php endif; ?>
          </p>
        <?php endif; ?>
      </section>

      <?php if ($canManage): ?>
      <section class="ath-panel bo-aar-danger-zone">
        <p class="bo-aar-side__t">Supprimer</p>
        <p class="bo-aar-muted">Le compte rendu et ses actions sont effacés définitivement.</p>
        <form method="post" action="<?= $h($base . '/' . $id . '/supprimer') ?>" data-aar-confirm="Supprimer définitivement <?= $h($r['reference_code'] ?? '') ?> ?"><?= $csrf ?>
          <button type="submit" class="ath-btn bo-aar-danger">Supprimer le compte rendu</button>
        </form>
      </section>
      <?php endif; ?>
    </aside>
  </div>
</div>
<script src="<?= $h(asset_url('assets/js/back-office-aar.js')) ?>" defer></script>
