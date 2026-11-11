<?php
/**
 * Aide clavier (touche « ? ») du poste TOC web et de l'éditeur.
 *
 * @var list<array{0: string, 1: string}> $tocKeys
 * @var string $tocHelpTitle
 */
$h = static fn ($v) => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
?>
<div class="toc-overlay" id="toc-help-overlay" hidden role="dialog" aria-modal="true" aria-labelledby="toc-help-title">
  <div class="toc-dialog">
    <div class="toc-sec"><h2 id="toc-help-title"><?= $h($tocHelpTitle ?? 'Raccourcis clavier') ?></h2><span><button type="button" class="toc-btn is-small" id="toc-help-close">Fermer <kbd>Échap</kbd></button></span></div>
    <div class="toc-keys">
      <?php foreach ($tocKeys ?? [] as [$key, $what]): ?>
        <kbd><?= $h($key) ?></kbd><span><?= $h($what) ?></span>
      <?php endforeach; ?>
    </div>
  </div>
</div>
