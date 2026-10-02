<?php
declare(strict_types=1);
/**
 * Rail « Mes espaces » : l’Organisation et la filiation des unités du lecteur.
 * @var list<array<string, mixed>> $spacesRail
 */
$h = static fn (mixed $v): string => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
$rail = is_array($spacesRail ?? null) ? $spacesRail : [];
?>
<nav class="jn-rail" aria-label="Mes espaces JNET">
    <p class="jn-rail__title">Mes espaces</p>
    <ul class="jn-rail__list">
        <?php foreach ($rail as $s): ?>
            <?php
            $depth = min(6, max(0, (int) ($s['depth'] ?? 0)));
            $accent = (string) ($s['accent'] ?? '');
            ?>
            <li>
                <a href="<?= $h((string) $s['href']) ?>"
                   class="jn-rail__item<?= !empty($s['active']) ? ' is-active' : '' ?>"
                   style="--jn-depth: <?= $depth ?>;<?= $accent !== '' ? ' --jn-unit: ' . $h($accent) . ';' : '' ?>"
                   <?= !empty($s['active']) ? 'aria-current="page"' : '' ?>>
                    <span class="jn-rail__swatch" aria-hidden="true"></span>
                    <span class="jn-rail__label" title="<?= $h((string) $s['label']) ?>"><?= $h((string) $s['label']) ?></span>
                    <?php if (!empty($s['mine'])): ?>
                        <span class="jn-rail__mine" title="Votre unité">●</span>
                    <?php endif; ?>
                </a>
            </li>
        <?php endforeach; ?>
    </ul>
    <a class="jn-rail__more" href="<?= $h(url('jnet') . '#jn-units') ?>">Toutes les unités →</a>
</nav>
