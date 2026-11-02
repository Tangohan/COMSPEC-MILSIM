<?php
/**
 * Carte tactique de la partie : ma position, amis, marqueurs ; poser, modifier, déplacer ou retirer mes marqueurs.
 *
 * @var array<string, array{0: string, 1: string, 2: string}> $markerKinds
 */
$h = static fn ($v): string => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
require __DIR__ . '/_head.php';
?>
    <section class="mm-mapwrap">
      <div class="mm-map" id="mm-map" aria-label="Carte"></div>
      <button type="button" class="mm-fullbtn" data-map-full aria-pressed="false">Plein écran</button>
      <div class="mm-mapchip" data-my-grid>Position : —</div>
      <div class="mm-fabs">
        <button type="button" class="mm-fab" data-map-me>MOI</button>
        <button type="button" class="mm-fab mm-fab--add" data-map-add>+ MARQUEUR</button>
      </div>
      <div class="mm-mapbar" data-map-hint hidden>Touchez la carte pour placer le marqueur <button type="button" class="mm-btn" data-map-cancel>ANNULER</button></div>
    </section>
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
