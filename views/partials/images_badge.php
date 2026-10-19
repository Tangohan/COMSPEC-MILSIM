<?php
/**
 * Badge « images » : une pastille (compteur + libellé) avec une pile de vignettes derrière,
 * qui s'éventaillent au survol ou au focus clavier. Inspiré du composant « Images Badge »
 * d'Aceternity UI, réécrit en CSS natif (public/assets/css/images-badge.css), sans React ni JS.
 *
 * Variables attendues :
 * @var list<array{src?: ?string, initials?: string}> $ibItems  vignettes (3 à 5 conseillées), src validé par l'appelant
 * @var int|string $ibCount   valeur affichée en mono (ex. 12)
 * @var string     $ibLabel   libellé (ex. « communautés »), en français : traduit à l'affichage
 * @var string     $ibHref    cible du lien
 * @var string     $ibTone    'dark' (fond sombre) ou 'light'
 * @var string     $ibAria    libellé accessible complet (optionnel)
 */
$ibItems = array_slice(array_values($ibItems ?? []), 0, 5);
$ibTone = ($ibTone ?? 'dark') === 'light' ? 'light' : 'dark';
$ibN = count($ibItems);
$ibLabel = (string) ($ibLabel ?? '');
$ibAria = trim((string) ($ibAria ?? ''));
?>
<a class="ib ib--<?= $ibTone ?>" href="<?= htmlspecialchars((string) ($ibHref ?? '#'), ENT_QUOTES, 'UTF-8') ?>"<?= $ibAria !== '' ? ' aria-label="' . htmlspecialchars($ibAria, ENT_QUOTES, 'UTF-8') . '"' : '' ?> style="--ib-n: <?= $ibN ?>;">
  <?php if ($ibN > 0): ?>
  <span class="ib__stack" aria-hidden="true">
    <?php foreach ($ibItems as $ibI => $ibItem): ?>
      <?php
      // Décalage centré : -1, 0, 1 pour 3 vignettes ; -2 … 2 pour 5.
      $ibOffset = $ibI - ($ibN - 1) / 2;
      $ibSrc = trim((string) ($ibItem['src'] ?? ''));
      ?>
    <span class="ib__card" style="--ib-o: <?= rtrim(rtrim(number_format($ibOffset, 1, '.', ''), '0'), '.') ?>; --ib-i: <?= (int) $ibI ?>;">
      <?php if ($ibSrc !== ''): ?>
      <img src="<?= htmlspecialchars($ibSrc, ENT_QUOTES, 'UTF-8') ?>" alt="" loading="lazy" decoding="async">
      <?php else: ?>
      <span class="ib__initials"><?= htmlspecialchars((string) ($ibItem['initials'] ?? ''), ENT_QUOTES, 'UTF-8') ?></span>
      <?php endif; ?>
    </span>
    <?php endforeach; ?>
  </span>
  <?php endif; ?>
  <span class="ib__pill">
    <span class="ib__count"><?= htmlspecialchars((string) ($ibCount ?? ''), ENT_QUOTES, 'UTF-8') ?></span>
    <span class="ib__label"><?= htmlspecialchars($ibLabel, ENT_QUOTES, 'UTF-8') ?></span>
    <svg class="ib__arrow" viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path d="M3 8h9M8.5 4.5 12 8l-3.5 3.5"/></svg>
  </span>
</a>
