<?php
declare(strict_types=1);
$h = static fn (mixed $v): string => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
$success = \App\Core\Session::getFlash('success'); $error = \App\Core\Session::getFlash('error');
$labels = ['draft' => 'Brouillon', 'open' => 'Ouverte', 'commission' => 'En commission', 'published' => 'Publiée', 'archived' => 'Archivée'];
?>
<div class="adv-page">
  <?php if ($success): ?><p class="adv-flash is-ok"><?= $h($success) ?></p><?php endif; ?><?php if ($error): ?><p class="adv-flash is-bad"><?= $h($error) ?></p><?php endif; ?>
  <section class="adv-hero"><div><span class="adv-eyebrow">AVANCEMENT AU CHOIX</span><h2>Campagnes & commissions</h2><p>Ouvrez une fenêtre de volontariat, classez les candidatures puis publiez un tableau irréversible.</p></div><div class="adv-count"><strong><?= count($campaigns) ?></strong><span>campagnes</span></div></section>
  <section class="adv-panel"><div class="adv-panel__head"><div><span class="adv-eyebrow">NOUVELLE VAGUE</span><h3>Créer une campagne</h3></div><a class="ath-btn" href="<?= $h(url('back-office/organisation/grades')) ?>">Gérer les grades</a></div>
    <form class="adv-form" method="post" action="<?= $h(url('back-office/rh/avancement')) ?>"><?= \App\Core\Csrf::field() ?>
      <label>Grade visé<select name="grade_id" required><option value="">Sélectionner</option><?php foreach ($grades as $g): ?><option value="<?= (int) $g['id'] ?>"><?= $h($g['label']) ?></option><?php endforeach; ?></select></label>
      <label>Filière<select name="filiere_id"><option value="">Celle du grade</option><?php foreach ($filieres as $f): ?><option value="<?= (int) $f['id'] ?>"><?= $h($f['label']) ?></option><?php endforeach; ?></select></label>
      <label>Année<input type="number" name="year" value="<?= date('Y') ?>" required></label><label>Quota<input type="number" name="quota_slots" min="1" placeholder="Sans limite"></label>
      <label>Ouverture<input type="date" name="opens_at" required></label><label>Clôture<input type="date" name="closes_at" required></label>
      <button class="ath-btn ath-btn--solid" type="submit">Créer la campagne</button>
    </form>
  </section>
  <div class="adv-card-grid"><?php foreach ($campaigns as $c): ?><a class="adv-campaign-card" href="<?= $h(url('back-office/rh/avancement/campagnes/' . $c['id'])) ?>">
    <div><span class="adv-status is-<?= $h($c['status']) ?>"><?= $h($labels[$c['status']] ?? $c['status']) ?></span><span class="adv-mono"><?= (int) $c['year'] ?></span></div>
    <h3><?= $h($c['grade_label']) ?></h3><p><?= $h($c['filiere_label'] ?? 'Toutes filières') ?></p>
    <dl><div><dt>Candidatures</dt><dd><?= (int) $c['candidacy_count'] ?></dd></div><div><dt>Quota</dt><dd><?= $c['quota_slots'] !== null ? (int) $c['quota_slots'] : '∞' ?></dd></div><div><dt>Clôture</dt><dd><?= $h(date('d/m/Y', strtotime($c['closes_at']))) ?></dd></div></dl>
  </a><?php endforeach; ?><?php if (!$campaigns): ?><div class="adv-empty">Aucune campagne créée.</div><?php endif; ?></div>
</div>
