<?php
declare(strict_types=1);

/**
 * Back-office — Consignes et kit ATAK (/back-office/ressources/atak-consignes).
 * Zone vivante : _live.php, remplacée sans rechargement par back-office-atak-brief.js. Sans ce script, chaque action
 * reste un vrai formulaire (dépliant + envoi + retour à la page). Styles : back-office-atak-brief.css.
 *
 * @var array<string, mixed> $form données de la page (AdminAtakBriefController::viewData)
 */
$h = static fn (mixed $v): string => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
$briefBase = url('back-office/ressources/atak-consignes');
?>
<div class="bo-brief" data-brief data-page-url="<?= $h($briefBase) ?>" data-catalog-url="<?= $h($briefBase . '/catalogue') ?>">
  <p class="sr-only" role="status" aria-live="polite" data-brief-status></p>
  <div data-brief-live>
    <?php require __DIR__ . '/_live.php'; ?>
  </div>
  <dialog class="effk-md effk-md--chain bo-brief-dlg" data-brief-dialog aria-labelledby="bo-brief-dlg-title">
    <div class="effk-md__h">
      <div><span class="effk-k" data-brief-dlg-kicker></span><h2 id="bo-brief-dlg-title" data-brief-dlg-title></h2></div>
      <button type="button" class="effk-md__x" data-brief-close aria-label="Fermer">×</button>
    </div>
    <p class="bo-brief-err" role="alert" data-brief-error hidden></p>
    <div class="effk-md__b" data-brief-dlg-body></div>
  </dialog>
</div>
<script src="<?= $h(asset_url('assets/js/back-office-atak-brief.js')) ?>" defer></script>
