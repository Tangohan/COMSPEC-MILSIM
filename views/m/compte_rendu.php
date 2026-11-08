<?php

declare(strict_types=1);
/**
 * CR de fin d'opération ouvert dans le téléphone en jeu (app CR fin d'op), à remplir ou à lire sur le vrai téléphone.
 *
 * @var array{id: string, mode: string, title: string, intro: string, read: string, done: bool, questions: list<array<string, mixed>>, vals: array<string, string>}|null $sheet
 */
$h = static fn ($v): string => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
require __DIR__ . '/_head.php';
?>
<?php if ($sheet === null): ?>
    <section class="mm-card">
      <h1 class="mm-h1">Aucun CR fin d’op ouvert</h1>
      <p>Quand le commandement impose le compte rendu de fin d’opération, il s’ouvre dans l’app CR fin d’op de votre téléphone en jeu.</p>
      <p class="mm-muted">Dans cette app, appuyez sur « Sur mon téléphone » et scannez le nouveau QR code pour le remplir ici.</p>
    </section>
<?php elseif ($sheet['done']): ?>
    <section class="mm-card mm-card--ok">
      <h1 class="mm-h1" translate="no"><?= $h($sheet['title']) ?></h1>
      <p>Ce compte rendu est déjà remis.</p>
    </section>
<?php else: ?>
    <form class="mm-form" data-dbf-form data-mode="<?= $h($sheet['mode']) ?>" novalidate>
      <div class="mm-form__head" style="--mm-c: #e8c547">
        <h2><span>CR FIN D’OP</span> <b translate="no"><?= $h($sheet['title']) ?></b></h2>
      </div>
      <?php if ($sheet['intro'] !== ''): ?><p class="mm-intro" translate="no"><?= nl2br($h($sheet['intro'])) ?></p><?php endif; ?>
      <?php if ($sheet['mode'] === 'read'): ?>
        <div class="mm-read" translate="no"><?= str_replace(['&lt;br/&gt;', '&lt;br&gt;'], '<br>', nl2br($h($sheet['read']))) ?></div>
        <button type="submit" class="mm-btn mm-btn--send">LU ET COMPRIS</button>
      <?php else: ?>
        <?php foreach ($sheet['questions'] as $q): $v = $sheet['vals'][$q['id']] ?? ''; ?>
          <div class="mm-field" data-field="<?= $h($q['id']) ?>">
            <label class="mm-label" translate="no"><?= $h($q['label']) ?><?php if ($q['required']): ?> <b class="mm-req">*</b><?php endif; ?></label>
            <?php if ($q['help'] !== ''): ?><small class="mm-muted" translate="no"><?= $h($q['help']) ?></small><?php endif; ?>
            <?php if ($q['type'] === 'textarea'): ?>
              <textarea class="mm-input" name="a[<?= $h($q['id']) ?>]" rows="4" maxlength="1500"><?= $h($v) ?></textarea>
            <?php elseif ($q['type'] === 'select'): ?>
              <div class="mm-choices mm-choices--seg">
                <?php foreach ($q['options'] as $o): ?>
                  <button type="button" class="mm-choice<?= $o === $v ? ' is-on' : '' ?>" data-choice="<?= $h($o) ?>" translate="no"><?= $h($o) ?></button>
                <?php endforeach; ?>
                <input type="hidden" name="a[<?= $h($q['id']) ?>]" value="<?= $h($v) ?>">
              </div>
            <?php elseif ($q['type'] === 'checkbox'): ?>
              <div class="mm-choices mm-choices--seg">
                <button type="button" class="mm-choice<?= $v === 'Oui' ? ' is-on' : '' ?>" data-choice="Oui">Oui</button>
                <button type="button" class="mm-choice<?= $v === 'Non' ? ' is-on' : '' ?>" data-choice="Non">Non</button>
                <input type="hidden" name="a[<?= $h($q['id']) ?>]" value="<?= $h($v) ?>">
              </div>
            <?php else: ?>
              <input class="mm-input" name="a[<?= $h($q['id']) ?>]" maxlength="300" value="<?= $h($v) ?>">
            <?php endif; ?>
          </div>
        <?php endforeach; ?>
        <button type="submit" class="mm-btn mm-btn--send">REMETTRE LE CR</button>
      <?php endif; ?>
    </form>
    <section class="mm-card" data-dbf-sent hidden>
      <h2 class="mm-h1">CR confié à votre ATAK</h2>
      <p class="mm-state" data-state>En attente de votre ATAK…</p>
      <p class="mm-muted">Votre téléphone en jeu le remet au commandement dès qu’il le reçoit (moins de 30 secondes, téléphone allumé et relié à Athena).</p>
    </section>
<?php endif; ?>
<?php require __DIR__ . '/_foot.php'; ?>
