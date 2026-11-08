<?php
declare(strict_types=1);
/**
 * Contenu de la tenue, du JPC et du sac sur une carte : barre de répartition (décor, les nombres sont écrits dessous)
 * puis le détail de chaque contenant, aussi sur téléphone. Variables : $inventoryBoxes (ArsenalLoadoutItems::inventory), $h.
 * Les noms d'objets viennent du jeu : échappés et marqués translate="no". Icône seulement si elle existe ;
 * si son fichier manque, l'image est retirée et le nom reste.
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
    <span class="eqx-card-inv__box eqx-box--<?= $h(strtolower((string) $box['title'])) ?>">
        <b><?= $h((string) $box['title']) ?> (<?= (int) $box['count'] ?>)</b>
        <small class="eqx-inv-items" translate="no">
            <?php foreach ($box['items'] as $it): ?>
            <span class="eqx-inv-item"><?php if (!empty($it['icon'])): ?><img src="<?= $h((string) $it['icon']) ?>" alt="" width="20" height="20" loading="lazy" decoding="async" onerror="this.remove()"><?php endif; ?><?= $h((string) $it['name']) ?><?= (int) $it['qty'] > 1 ? ' ×' . (int) $it['qty'] : '' ?></span>
            <?php endforeach; ?>
        </small>
    </span>
    <?php endforeach; ?>
</span>
<?php endif; ?>
