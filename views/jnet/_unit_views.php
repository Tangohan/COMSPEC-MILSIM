<?php
declare(strict_types=1);
/**
 * Unités d'un espace en deux vues : cartes regroupées par grand commandement, ou organigramme.
 * Bascule sans JavaScript (boutons radio) ; le dernier choix est mémorisé par jnet_spaces.js.
 * @var list<array<string, mixed>> $viewNodes nœuds de JnetSpaceService::unitTree
 * @var string $viewId identifiant unique de la section
 * @var bool $viewGrouped true : un groupe par nœud de premier niveau (vue Organisation)
 */
$h = static fn (mixed $v): string => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
$viewNodes = is_array($viewNodes ?? null) ? $viewNodes : [];
$viewId = preg_replace('/[^a-z0-9-]/', '', (string) ($viewId ?? 'units')) ?: 'units';
// Cartes : niveau par niveau (escadrons, puis sections…), au plus $maxDepth niveaux sous le groupe ;
// les échelons plus fins restent dans l'organigramme et dans l'espace de chaque unité.
$maxDepth = 3;
$byLevel = static function (array $nodes, int $maxDepth): array {
    $out = [];
    $hidden = 0;
    $level = $nodes;
    for ($d = 1; $level !== []; $d++) {
        $next = [];
        foreach ($level as $n) {
            if ($d <= $maxDepth) {
                $out[] = $n;
            } else {
                $hidden++;
            }
            $next = array_merge($next, (array) ($n['children'] ?? []));
        }
        $level = $next;
    }

    return [$out, $hidden];
};
$sections = [];
$hiddenTotal = 0;
if (!empty($viewGrouped)) {
    foreach ($viewNodes as $top) {
        [$below, $hidden] = $byLevel((array) ($top['children'] ?? []), $maxDepth);
        $hiddenTotal += $hidden;
        $sections[] = [
            'label' => (string) ($top['label'] ?? ''),
            'href' => (string) ($top['href'] ?? ''),
            'units' => $below !== [] ? $below : [$top],
            'members' => (int) ($top['strength']['total'] ?? 0),
            'accent' => (string) ($top['accent'] ?? ''),
        ];
    }
} else {
    $sections[] = ['label' => '', 'href' => '', 'units' => $viewNodes, 'members' => 0, 'accent' => ''];
}

?>
<div class="jn-views" data-jn-views="<?= $h($viewId) ?>">
    <input class="jn-views__radio" type="radio" name="<?= $h($viewId) ?>-view" id="<?= $h($viewId) ?>-cards" value="cards" checked>
    <input class="jn-views__radio" type="radio" name="<?= $h($viewId) ?>-view" id="<?= $h($viewId) ?>-tree" value="tree">
    <div class="jn-views__switch" role="group" aria-label="Affichage des unités">
        <label for="<?= $h($viewId) ?>-cards">Cartes</label>
        <label for="<?= $h($viewId) ?>-tree">Organigramme</label>
    </div>

    <div class="jn-views__pane jn-views__pane--cards">
        <?php foreach ($sections as $sec): ?>
            <section class="jn-group"<?= $sec['accent'] !== '' ? ' style="--jn-unit: ' . $h($sec['accent']) . '"' : '' ?>>
                <?php if ($sec['label'] !== ''): ?>
                    <header class="jn-group__head">
                        <a href="<?= $h($sec['href']) ?>"><strong><?= $h($sec['label']) ?></strong></a>
                        <span><?= count($sec['units']) ?> unité<?= count($sec['units']) > 1 ? 's' : '' ?> · <?= $sec['members'] ?> membre<?= $sec['members'] > 1 ? 's' : '' ?></span>
                    </header>
                <?php endif; ?>
                <div class="jn-cards">
                    <?php foreach ($sec['units'] as $u): ?>
                        <?php require base_path('views/jnet/_unit_card.php'); ?>
                    <?php endforeach; ?>
                </div>
            </section>
        <?php endforeach; ?>
        <?php if ($hiddenTotal > 0): ?>
            <p class="jn-views__more">
                <span>Échelons plus fins non affichés : <?= $hiddenTotal ?></span>
                <label for="<?= $h($viewId) ?>-tree">Voir l’organigramme</label>
            </p>
        <?php endif; ?>
    </div>

    <div class="jn-views__pane jn-views__pane--tree">
        <?php $treeNodes = $viewNodes; require base_path('views/jnet/_unit_tree.php'); ?>
    </div>
</div>
