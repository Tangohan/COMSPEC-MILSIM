<?php

declare(strict_types=1);
/**
 * Compte rendu rapide (app Comptes rendus de l'ATAK) : fiche choisie, remplie au pouce, confiée à l'ATAK en jeu.
 *
 * @var array<string, array<string, mixed>> $types
 * @var list<string> $priorities
 * @var string $myGrid
 */
$h = static fn ($v): string => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
$subSet = 'cr';
$subActive = 'new';
require __DIR__ . '/_head.php';
require __DIR__ . '/_sub.php';
?>
    <section class="mm-step" data-cr-pick>
      <h1 class="mm-h1">Nouveau compte rendu</h1>
      <div class="mm-types">
        <?php foreach ($types as $code => $t): ?>
          <button type="button" class="mm-type" data-cr-type="<?= $h($code) ?>" style="--mm-c: <?= $h($t['color']) ?>">
            <strong><?= $h($t['label']) ?></strong><span><?= $h($t['help']) ?></span>
          </button>
        <?php endforeach; ?>
      </div>
    </section>

    <?php foreach ($types as $code => $t): ?>
    <form class="mm-form" data-cr-form="<?= $h($code) ?>" hidden novalidate>
      <div class="mm-form__head" style="--mm-c: <?= $h($t['color']) ?>">
        <button type="button" class="mm-back" data-cr-back aria-label="Retour">‹</button>
        <h2><?= $h($t['label']) ?></h2>
      </div>
      <?php foreach ($t['fields'] as [$key, $kind, $label, $options, $required]): ?>
        <div class="mm-field" data-field="<?= $h($key) ?>">
          <label class="mm-label"><?= $h($label) ?><?php if ($required && $kind !== 'dtg'): ?> <b class="mm-req">*</b><?php endif; ?></label>
          <?php if (in_array($kind, ['big', 'seg', 'dir'], true)): ?>
            <div class="mm-choices mm-choices--<?= $h($kind) ?>">
              <?php foreach ($options as $o): ?>
                <button type="button" class="mm-choice" data-choice="<?= $h($o) ?>"><?= $h($o) ?></button>
              <?php endforeach; ?>
              <input type="hidden" name="f[<?= $h($key) ?>]" value="">
            </div>
          <?php elseif ($kind === 'combo'): ?>
            <select class="mm-input" name="f[<?= $h($key) ?>]">
              <option value="">—</option>
              <?php foreach ($options as $o): ?><option value="<?= $h($o) ?>"><?= $h($o) ?></option><?php endforeach; ?>
            </select>
          <?php elseif ($kind === 'memo'): ?>
            <textarea class="mm-input" name="f[<?= $h($key) ?>]" rows="3" maxlength="600"></textarea>
          <?php elseif ($kind === 'grid'): ?>
            <input class="mm-input mm-mono" name="f[<?= $h($key) ?>]" inputmode="numeric" maxlength="14" placeholder="0000 0000" value="<?= $h($myGrid) ?>">
            <div class="mm-row">
              <button type="button" class="mm-btn" data-grid-here>MA POSITION</button>
              <button type="button" class="mm-btn" data-grid-map>SUR LA CARTE</button>
            </div>
          <?php elseif ($kind === 'dtg'): ?>
            <p class="mm-muted">Heure du jeu, mise par votre ATAK à l’envoi.</p>
          <?php elseif ($kind === 'num'): ?>
            <input class="mm-input" name="f[<?= $h($key) ?>]" inputmode="numeric" pattern="[0-9]*" maxlength="4">
          <?php else: ?>
            <input class="mm-input" name="f[<?= $h($key) ?>]" maxlength="120">
          <?php endif; ?>
        </div>
      <?php endforeach; ?>
      <div class="mm-field">
        <label class="mm-label">Priorité</label>
        <div class="mm-choices mm-choices--seg">
          <?php foreach ($priorities as $p): ?>
            <button type="button" class="mm-choice<?= $p === $t['prio'] ? ' is-on' : '' ?>" data-choice="<?= $h($p) ?>"><?= $h($p) ?></button>
          <?php endforeach; ?>
          <input type="hidden" name="prio" value="<?= $h($t['prio']) ?>">
        </div>
      </div>
      <button type="submit" class="mm-btn mm-btn--send">ENVOYER PAR MON ATAK</button>
    </form>
    <?php endforeach; ?>

    <section class="mm-card" data-cr-sent hidden>
      <h2 class="mm-h1">Compte rendu confié à votre ATAK</h2>
      <p class="mm-state" data-state>En attente de votre ATAK…</p>
      <p class="mm-muted">Votre téléphone en jeu l’envoie à votre camp et à Athena dès qu’il le reçoit (moins de 30 secondes, téléphone allumé et relié à Athena).</p>
      <a class="mm-btn" href="<?= $h(url('m/cr/envoyes')) ?>">VOIR L’HISTORIQUE</a>
      <button type="button" class="mm-btn" data-cr-again>NOUVEAU COMPTE RENDU</button>
    </section>

    <div class="mm-sheet" data-map-sheet hidden>
      <div class="mm-sheet__bar"><strong>Touchez la position</strong><button type="button" class="mm-btn" data-map-close>FERMER</button></div>
      <div class="mm-map mm-map--pick" id="mm-pick-map"></div>
    </div>
<?php require __DIR__ . '/_foot.php'; ?>
