<?php
declare(strict_types=1);

/**
 * Colonne latérale du rédacteur : avancement, mission, méthode, envoi.
 *
 * @var bool $isEdit
 * @var string $cancelUrl
 * @var string $submitLabel
 */
$h = static fn (mixed $v): string => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
$ico = require base_path('views/admin/aar_reports/partials/icons.php');
$checks = [
    'title' => 'Titre renseigné',
    'mission' => 'Mission liée',
    'summary' => 'Synthèse rédigée',
    'points' => 'Au moins un point relevé',
    'lessons' => 'Enseignements',
    'actions' => 'Au moins une action',
];
?>
<aside class="bo-aar-compose__side">
  <section class="ath-panel bo-aar-progress-card" aria-live="polite">
    <p class="bo-aar-side__t">Avancement</p>
    <p class="bo-aar-progress-card__pct"><b data-aar-pct>0</b> %</p>
    <span class="bo-aar-bar"><span data-aar-bar style="width:0%"></span></span>
    <ul class="bo-aar-checks">
      <?php foreach ($checks as $key => $label): ?>
        <li data-aar-check="<?= $h($key) ?>"><span class="bo-aar-checks__box"><?= $ico('check') ?></span><?= $h($label) ?></li>
      <?php endforeach; ?>
    </ul>
    <p class="bo-aar-muted">Seul le titre est obligatoire : un compte rendu peut être déposé puis complété.</p>
    <div class="bo-aar-side__submit">
      <button type="submit" class="ath-btn ath-btn--solid"><?= $h($submitLabel ?? 'Enregistrer') ?></button>
      <a class="ath-btn" href="<?= $h($cancelUrl ?? '#') ?>">Annuler</a>
    </div>
  </section>

  <section class="ath-panel" data-aar-mission-card hidden>
    <p class="bo-aar-side__t">Mission liée</p>
    <p class="bo-aar-mission-card__t" data-aar-mc-title></p>
    <dl class="bo-aar-kv">
      <div><dt>État</dt><dd data-aar-mc-status></dd></div>
      <div><dt>Début</dt><dd data-aar-mc-start></dd></div>
      <div><dt>Fin</dt><dd data-aar-mc-end></dd></div>
    </dl>
  </section>

  <section class="ath-panel bo-aar-method">
    <p class="bo-aar-side__t"><?= $ico('tip') ?> Méthode du retour d’expérience</p>
    <ol>
      <li><b>Qu’est-ce qui était prévu ?</b> L’intention et le plan.</li>
      <li><b>Que s’est-il passé ?</b> Les faits, dans l’ordre.</li>
      <li><b>Pourquoi ces écarts ?</b> Les causes, pas les coupables.</li>
      <li><b>Que fait-on maintenant ?</b> Des actions avec un responsable.</li>
    </ol>
    <?php if (empty($isEdit)): ?><p class="bo-aar-muted">Après le dépôt, le compte rendu passe « En attente » puis en relecture et en validation depuis sa fiche.</p><?php endif; ?>
  </section>
</aside>
