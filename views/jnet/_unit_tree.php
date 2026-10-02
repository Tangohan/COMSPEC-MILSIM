<?php
declare(strict_types=1);
/**
 * Arbre de situation des unités (récursif). Attend $unitTree (JnetSpaceService::commandGroups).
 * Chaque ligne : unité (lien vers son espace), effectif disponible, activité, dernière activité.
 * @var list<array<string, mixed>> $unitTree
 */
$h = static fn (mixed $v): string => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
$renderNode = static function (array $u) use (&$renderNode, $h): void {
    $children = (array) ($u['children'] ?? []);
    $accent = (string) ($u['accent'] ?? '');
    $st = (array) ($u['strength'] ?? []);
    $total = (int) ($st['total'] ?? 0);
    $avail = (int) ($st['available'] ?? 0);
    $fill = max(0, min(100, (int) ($u['fill'] ?? 0)));
    $level = (int) ($u['level'] ?? 0);
    $style = $accent !== '' ? ' style="--jn-unit: ' . $h($accent) . '"' : '';
    $row = '<div class="jn-tree__row">'
        . '<a class="jn-tree__name" href="' . $h((string) $u['href']) . '">'
        . '<span class="jn-tree__swatch" aria-hidden="true"></span>'
        . '<span class="jn-tree__label">' . $h((string) $u['label']) . '</span></a>'
        . '<span class="jn-tree__strength" title="' . $avail . ' disponibles sur ' . $total . '">'
        . ($total > 0
            ? '<span class="jn-meter jn-meter--inline" aria-hidden="true"><span style="width: ' . $fill . '%"></span></span><span>' . $avail . ' / ' . $total . '</span>'
            : '<span class="jn-tree__muted">—</span>')
        . '</span>'
        . '<span class="jn-tree__activity">' . ((string) ($u['activity'] ?? '') !== '' ? $h((string) $u['activity']) : '<span class="jn-tree__muted">Aucune opération</span>') . '</span>'
        . '<span class="jn-tree__last">' . $h((string) ($u['lastActivity'] ?? '')) . '</span>'
        . '</div>';

    if ($children === []) {
        echo '<li class="jn-tree__node jn-tree__node--leaf"' . $style . '>' . $row . '</li>';

        return;
    }
    $open = $level < 1 ? ' open' : '';
    echo '<li class="jn-tree__node"' . $style . '><details' . $open . '>'
        . '<summary><span class="jn-tree__caret" aria-hidden="true"></span>' . $row
        . '<span class="sr-only">, ' . (int) ($u['descendants'] ?? 0) . ' sous-unités</span></summary>'
        . '<ul class="jn-tree__children">';
    foreach ($children as $c) {
        $renderNode((array) $c);
    }
    echo '</ul></details></li>';
};
?>
<div class="jn-tree" role="region" aria-label="Situation des unités">
    <div class="jn-tree__head" aria-hidden="true">
        <span>Unité</span><span>Disponibles</span><span>Activité</span><span>Dernier échange</span>
    </div>
    <ul class="jn-tree__root">
        <?php foreach ((array) ($unitTree ?? []) as $top) { $renderNode((array) $top); } ?>
    </ul>
</div>
