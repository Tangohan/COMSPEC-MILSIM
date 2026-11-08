<?php

declare(strict_types=1);
/**
 * Carte tactique de la partie (la carte Overwatch : mêmes fonds, calques et symboles) : ma position, amis, marqueurs,
 * tracés, appareils, appuis ; poser, modifier, déplacer ou retirer mes marqueurs.
 *
 * @var array<string, array{0: string, 1: string, 2: string}> $markerKinds
 */
$h = static fn ($v): string => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
require __DIR__ . '/_head.php';
?>
<?php $mapCanAdd = true; $mapMeLabel = 'Moi'; require __DIR__ . '/_map.php'; ?>
    <div class="mm-sheet mm-sheet--low" data-marker-sheet hidden>
      <div class="mm-sheet__bar"><strong data-marker-title>Nouveau marqueur</strong><button type="button" class="mm-btn" data-marker-close>FERMER</button></div>
      <div class="mm-choices mm-choices--seg" data-marker-kinds>
        <?php foreach ($markerKinds as $k => [, , $label]): ?>
          <button type="button" class="mm-choice" data-choice="<?= $h($k) ?>"><?= $h($label) ?></button>
        <?php endforeach; ?>
      </div>
      <input class="mm-input" data-marker-text maxlength="60" placeholder="Texte (facultatif)">
      <p class="mm-muted mm-mono" data-marker-grid></p>
      <div class="mm-row">
        <button type="button" class="mm-btn mm-btn--send" data-marker-save>POSER</button>
        <button type="button" class="mm-btn" data-marker-move hidden>DÉPLACER</button>
        <button type="button" class="mm-btn mm-btn--danger" data-marker-delete hidden>RETIRER</button>
      </div>
      <p class="mm-muted">Le marqueur apparaît dans les ATAK de la partie au bout de quelques secondes.</p>
    </div>
<?php require __DIR__ . '/_foot.php'; ?>
