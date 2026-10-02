<?php
declare(strict_types=1);
/**
 * Arbre des unités (récursif). Attend $treeNodes (JnetSpaceService::unitTree).
 * Les deux premiers niveaux sont ouverts, les suivants repliés.
 * @var list<array<string, mixed>> $treeNodes
 */
$jnTreeRender = static function (array $nodes) use (&$jnTreeRender): void {
    $h = static fn (mixed $v): string => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
    echo '<ul class="jn-tree__list">';
    foreach ($nodes as $n) {
        $st = (array) ($n['strength'] ?? []);
        $total = (int) ($st['total'] ?? 0);
        $avail = (int) ($st['available'] ?? 0);
        $fill = max(0, min(100, (int) ($n['fill'] ?? 0)));
        $accent = (string) ($n['accent'] ?? '');
        $children = (array) ($n['children'] ?? []);
        $level = (int) ($n['level'] ?? 1);
        $style = $accent !== '' ? ' style="--jn-unit: ' . $h($accent) . '"' : '';
        $activity = (string) ($n['activity'] ?? '');
        $last = (string) ($n['lastActivity'] ?? '');

        $row = '<span class="jn-tree__row"' . $style . '>'
            . '<a class="jn-tree__name" href="' . $h((string) $n['href']) . '" title="Ouvrir l’espace ' . $h((string) $n['label']) . '">'
            . '<span class="jn-tree__swatch" aria-hidden="true"></span>'
            . '<span class="jn-tree__label">' . $h((string) $n['label']) . '</span>'
            . ($children !== [] ? '<span class="jn-tree__count">' . (int) ($n['descendantCount'] ?? count($children)) . ' sous-unité' . ((int) ($n['descendantCount'] ?? 0) > 1 ? 's' : '') . '</span>' : '')
            . '</a>'
            . '<span class="jn-tree__strength" title="' . ($total > 0 ? $avail . ' disponibles sur ' . $total : 'Aucun membre affecté') . '">'
            . '<span class="jn-meter"><span style="width: ' . $fill . '%"></span></span>'
            . '<span class="jn-tree__num">' . ($total > 0 ? $avail . '/' . $total : '—') . '</span>'
            . '</span>'
            . '<span class="jn-tree__activity' . ($activity === '' ? ' is-idle' : '') . '">' . ($activity !== '' ? $h($activity) : 'Pas d’opération') . '</span>'
            . '<span class="jn-tree__last">' . ($last !== '' ? $h($last) : '') . '</span>'
            . '</span>';

        echo '<li class="jn-tree__item" style="--jn-level: ' . max(1, min(8, $level)) . '">';
        if ($children !== []) {
            // La ligne est le <summary> : la flèche (ou la ligne hors lien) déplie, le nom ouvre l’espace.
            echo '<details class="jn-tree__branch"' . ($level <= 2 ? ' open' : '') . '>';
            echo '<summary class="jn-tree__summary" title="Afficher ou masquer les sous-unités">' . $row . '</summary>';
            $jnTreeRender($children);
            echo '</details>';
        } else {
            echo '<div class="jn-tree__leaf">' . $row . '</div>';
        }
        echo '</li>';
    }
    echo '</ul>';
};
?>
<div class="jn-tree">
    <div class="jn-tree__legend" aria-hidden="true">
        <span>Unité</span><span>Disponibles</span><span>Activité</span><span>Dernier échange</span>
    </div>
    <?php $jnTreeRender(is_array($treeNodes ?? null) ? $treeNodes : []); ?>
</div>
