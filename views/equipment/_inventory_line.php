<?php
declare(strict_types=1);
/**
 * Contenu de la tenue, du JPC et du sac sur une carte : barre de répartition (décor, les nombres sont écrits dessous)
 * puis le détail de chaque contenant, aussi sur téléphone. Variables : $inventoryBoxes (ArsenalLoadoutItems::inventory), $h.
 * Les noms d'objets viennent du jeu : échappés et marqués translate="no".
 */
?>
<?php if (!empty($inventoryBoxes)): ?>
<span class="eqx-card-inv">
    <span class="eqx-load" aria-hidden="true">
        <?php foreach ($inventoryBoxes as $box): ?>
        <i class="eqx-box--<?= $h(strtolower((string) $box['title'])) ?>" style="flex-grow:<?= max(1, (int) $box['count']) ?>"></i>
        <?php endforeach; ?>
    </span>
    <?php foreach ($inventoryBoxes as $box): ?>
    <?php
    $boxNames = implode(', ', array_map(
        static fn (array $it): string => (string) $it['name'] . ((int) $it['qty'] > 1 ? ' ×' . (int) $it['qty'] : ''),
        $box['items']
    ));
    ?>
    <span class="eqx-card-inv__box eqx-box--<?= $h(strtolower((string) $box['title'])) ?>">
        <b><?= $h((string) $box['title']) ?> (<?= (int) $box['count'] ?>)</b>
        <small translate="no"><?= $h($boxNames) ?></small>
    </span>
    <?php endforeach; ?>
</span>
<?php endif; ?>
