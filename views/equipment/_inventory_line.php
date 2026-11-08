<?php
declare(strict_types=1);
/**
 * Contenu de la tenue, du JPC et du sac sur une carte. Variables : $inventoryBoxes (ArsenalLoadoutItems::inventory), $h.
 * Les noms d'objets viennent du jeu : échappés et marqués translate="no".
 */
?>
<?php if (!empty($inventoryBoxes)): ?>
<span class="eqx-card-inv">
    <?php foreach ($inventoryBoxes as $box): ?>
    <?php
    $boxNames = implode(', ', array_map(
        static fn (array $it): string => (string) $it['name'] . ((int) $it['qty'] > 1 ? ' ×' . (int) $it['qty'] : ''),
        $box['items']
    ));
    ?>
    <span class="eqx-card-inv__box">
        <b><?= $h((string) $box['title']) ?> (<?= (int) $box['count'] ?>)</b>
        <small translate="no"><?= $h($boxNames) ?></small>
    </span>
    <?php endforeach; ?>
</span>
<?php endif; ?>
