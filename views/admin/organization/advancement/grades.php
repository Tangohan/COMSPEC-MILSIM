<?php
declare(strict_types=1);
$h = static fn (mixed $v): string => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
$success = \App\Core\Session::getFlash('success');
$error = \App\Core\Session::getFlash('error');
?>
<div class="adv-page">
  <?php if ($success): ?><p class="adv-flash is-ok"><?= $h($success) ?></p><?php endif; ?>
  <?php if ($error): ?><p class="adv-flash is-bad"><?= $h($error) ?></p><?php endif; ?>

  <section class="adv-hero">
    <div><span class="adv-eyebrow">RÉFÉRENTIEL COMMUNAUTÉ</span><h2>Échelle de grades</h2>
      <p>Chaque grade appartient exclusivement à votre communauté. L’archivage préserve toutes les attributions passées.</p>
    </div>
    <div class="adv-count"><strong><?= count($grades) ?></strong><span>grades</span></div>
  </section>

  <section class="adv-panel">
    <div class="adv-panel__head"><div><span class="adv-eyebrow">FILIÈRES</span><h3>Branches configurables</h3></div></div>
    <form class="adv-form adv-form--inline" method="post" action="<?= $h(url('back-office/organisation/grades/filieres')) ?>">
      <?= \App\Core\Csrf::field() ?>
      <label>Code<input name="code" required placeholder="ENLISTED"></label>
      <label>Libellé<input name="label" required placeholder="Militaires du rang"></label>
      <label>Ordre<input name="sort_order" type="number" value="10"></label>
      <button class="ath-btn ath-btn--solid" type="submit">Ajouter la filière</button>
    </form>
    <div class="adv-chip-row">
      <?php foreach ($filieres as $f): ?><span class="adv-chip"><?= $h($f['code']) ?> · <?= $h($f['label']) ?></span><?php endforeach; ?>
    </div>
  </section>

  <section class="adv-panel">
    <div class="adv-panel__head"><div><span class="adv-eyebrow">NOUVEAU GRADE</span><h3>Règles d’accès</h3></div>
      <a class="ath-btn" href="<?= $h(url('back-office/rh/avancement')) ?>">Voir les campagnes</a>
    </div>
    <form class="adv-form" method="post" action="<?= $h(url('back-office/organisation/grades')) ?>">
      <?= \App\Core\Csrf::field() ?>
      <label>Code<input name="code" required></label><label>Libellé<input name="label" required></label>
      <label>Libellé court<input name="short_label" required></label>
      <label>Filière<select name="filiere_id"><option value="">Sans filière</option><?php foreach ($filieres as $f): ?><option value="<?= (int) $f['id'] ?>"><?= $h($f['label']) ?></option><?php endforeach; ?></select></label>
      <label>Ordre hiérarchique<input name="rank_order" type="number" min="0" required></label>
      <label>Temps minimum (mois)<input name="min_time_in_previous_grade_months" type="number" min="0"></label>
      <label>Qualification requise<select name="required_qualification_id"><option value="">Aucune</option><?php foreach ($qualifications as $q): ?><option value="<?= (int) $q['id'] ?>"><?= $h($q['name']) ?></option><?php endforeach; ?></select></label>
      <div class="adv-checks"><label><input type="checkbox" name="advancement_seniority_enabled" value="1"> Ancienneté</label><label><input type="checkbox" name="advancement_choice_enabled" value="1"> Choix</label></div>
      <button class="ath-btn ath-btn--solid" type="submit">Créer le grade</button>
    </form>
  </section>

  <section class="adv-panel adv-panel--table">
    <table class="adv-table"><thead><tr><th>Code</th><th>Grade</th><th>Filière</th><th>Ordre</th><th>Voies</th><th>Temps mini</th><th>Qualification</th><th>Statut</th><th></th></tr></thead>
    <tbody><?php foreach ($grades as $grade): ?><tr class="<?= $grade['archived_at'] ? 'is-muted' : '' ?>">
      <td class="adv-mono"><?= $h($grade['code']) ?></td><td><strong><?= $h($grade['label']) ?></strong><small><?= $h($grade['short_label']) ?></small></td>
      <td><?= $h($grade['filiere_label'] ?? '—') ?></td><td><?= (int) $grade['rank_order'] ?></td>
      <td><span class="adv-status"><?= !empty($grade['advancement_seniority_enabled']) ? 'Ancienneté ' : '' ?><?= !empty($grade['advancement_choice_enabled']) ? 'Choix' : '' ?></span></td>
      <td><?= $grade['min_time_in_previous_grade_months'] !== null ? (int) $grade['min_time_in_previous_grade_months'] . ' mois' : '—' ?></td>
      <td><?= $h($grade['qualification_label'] ?? '—') ?></td><td><?= $grade['archived_at'] ? 'Archivé' : 'Actif' ?></td>
      <td><?php if (!$grade['archived_at']): ?><form method="post" action="<?= $h(url('back-office/organisation/grades/' . $grade['id'] . '/archive')) ?>"><?= \App\Core\Csrf::field() ?><button class="adv-link is-danger">Archiver</button></form><?php endif; ?></td>
    </tr><?php endforeach; ?></tbody></table>
  </section>
</div>
