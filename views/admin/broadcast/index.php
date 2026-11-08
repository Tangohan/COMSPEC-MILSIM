<?php
declare(strict_types=1);

/**
 * Back-office — Centre de diffusion (/back-office/alerts).
 * Zone vivante : _live.php (filtres et liste), remplacée sans rechargement par back-office-broadcast.js.
 * Nouveau message : _compose.php, dans un dépliant ouvert en fenêtre par le script ; aperçu : _preview.php.
 * Sans script, chaque action reste un vrai formulaire (dépliant, envoi, retour à la page).
 * Styles : effectifs-kit.css (fenêtre), back-office-atak-brief.css (formulaires), back-office-broadcast.css.
 *
 * @var array<string, mixed> $form BroadcastCenterController::viewData() + options, droits et préremplissage
 */
$h = static fn (mixed $v): string => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
$bcBase = url('back-office/alerts');
$counts = is_array($form['counts'] ?? null) ? $form['counts'] : [];
$prefill = is_array($form['prefill'] ?? null) ? $form['prefill'] : null;
$allowed = is_array($form['allowed'] ?? null) ? $form['allowed'] : [];
$showUpcoming = !empty($form['showUpcoming']);
?>
<div class="bo-brief bo-bc" data-bc data-page-url="<?= $h($bcBase) ?>" data-preview-url="<?= $h($bcBase . '/apercu') ?>">
  <p class="sr-only" role="status" aria-live="polite" data-bc-status></p>

  <div class="bo-bc-top">
    <dl class="bo-bc-kpis">
      <div><dt>Actifs</dt><dd><?= (int) ($counts['active'] ?? 0) ?></dd></div>
      <div><dt>Programmés</dt><dd><?= (int) ($counts['scheduled'] ?? 0) ?></dd></div>
      <div><dt>En cours de suivi</dt><dd><?= (int) ($counts['tracked'] ?? 0) ?></dd></div>
      <div><dt>Au total</dt><dd><?= (int) ($counts['total'] ?? 0) ?></dd></div>
    </dl>
    <div class="bo-bc-top__a">
      <?php if ($allowed === []): ?>
        <p class="bo-brief-note-box is-warn">Votre rôle ne permet pas de diffuser depuis cette page. Demandez à la gestion de la communauté.</p>
      <?php else: ?>
        <details class="bo-brief-fold bo-bc-compose" id="nouveau" data-bc-fold data-bc-compose data-kicker="Centre de diffusion" data-title="Nouveau message"<?= $prefill !== null ? ' open' : '' ?>>
          <summary class="ath-btn ath-btn--solid">Nouveau message</summary>
          <div class="bo-brief-fold__b"><?php require __DIR__ . '/_compose.php'; ?></div>
        </details>
      <?php endif; ?>
    </div>
  </div>

  <div data-bc-live id="diffusions">
    <?php require __DIR__ . '/_live.php'; ?>
  </div>

  <section class="ath-panel bo-brief-sec bo-bc-more" aria-labelledby="bo-bc-more-t">
    <h2 id="bo-bc-more-t">Réglages et pages détaillées</h2>
    <ul class="bo-bc-links">
      <li><a href="<?= $h(url('back-office/ressources/atak-consignes')) ?>">Consignes et kit ATAK</a> : le kit obligatoire, l’ordre des notes et la vue du téléphone. Les notes y sont les mêmes qu’ici.</li>
      <li><a href="<?= $h(url('back-office/alerts/create')) ?>">Annonce du site avec image, bouton ou code promo</a> : le formulaire complet du bandeau.</li>
      <li><a href="<?= $h(url('back-office/atak/parc-atak')) ?>">Parc ATAK</a> : message à un joueur précis, depuis sa fiche.</li>
      <li><a href="<?= $h(url('back-office/integrations')) ?>">Intégrations externes</a> : salon Discord des annonces.</li>
    </ul>
    <?php if (in_array('site', $allowed, true)): ?>
      <form method="post" action="<?= $h($bcBase . '/affichage-a-venir') ?>" class="bo-bc-upcoming">
        <?= \App\Core\Csrf::field() ?>
        <input type="hidden" name="show_upcoming" value="<?= $showUpcoming ? '0' : '1' ?>">
        <p class="bo-brief-hint">Annonces du site programmées : <?= $showUpcoming ? 'visibles par les membres avant leur date, dans « À venir »' : 'cachées aux membres jusqu’à leur date' ?>.</p>
        <button type="submit" class="ath-btn ath-btn--sm"><?= $showUpcoming ? 'Les cacher avant leur date' : 'Les montrer avant leur date' ?></button>
      </form>
    <?php endif; ?>
  </section>

  <dialog class="effk-md effk-md--chain bo-brief-dlg bo-bc-dlg" data-bc-dialog aria-labelledby="bo-bc-dlg-title">
    <div class="effk-md__h">
      <div><span class="effk-k" data-bc-dlg-kicker></span><h2 id="bo-bc-dlg-title" data-bc-dlg-title></h2></div>
      <button type="button" class="effk-md__x" data-bc-close aria-label="Fermer">×</button>
    </div>
    <p class="bo-brief-err" role="alert" data-bc-error hidden></p>
    <div class="effk-md__b" data-bc-dlg-body></div>
  </dialog>
</div>
<script src="<?= $h(asset_url('assets/js/back-office-broadcast.js')) ?>" defer></script>
