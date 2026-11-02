<?php
/**
 * Briefs air du pilote ou de l'équipage : 9-LINE reçus par son appareil, lus ligne par ligne, WILCO / UNABLE.
 *
 * @var list<string> $aircraft
 */
$h = static fn ($v): string => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
require __DIR__ . '/_head.php';
?>
    <section class="mm-card mm-card--air">
      <h1 class="mm-h1">Briefs air</h1>
      <p class="mm-muted">Reçus par <b translate="no"><?= $h(implode(', ', $aircraft)) ?></b> · mis à jour toutes les 5 secondes.</p>
    </section>
    <div class="mm-banner" data-brief-banner hidden>Nouveau brief reçu</div>
    <div class="mm-briefs" data-brief-list><p class="mm-muted">Chargement…</p></div>
    <div class="mm-sheet" data-brief-sheet hidden>
      <div class="mm-sheet__bar"><strong data-brief-title></strong><button type="button" class="mm-btn" data-brief-close>FERMER</button></div>
      <ol class="mm-lines" data-brief-lines></ol>
      <div class="mm-row mm-row--big">
        <button type="button" class="mm-btn mm-btn--send" data-brief-answer="WILCO">WILCO</button>
        <button type="button" class="mm-btn mm-btn--danger" data-brief-answer="UNABLE">UNABLE</button>
      </div>
      <p class="mm-state" data-brief-state></p>
    </div>
<?php require __DIR__ . '/_foot.php'; ?>
