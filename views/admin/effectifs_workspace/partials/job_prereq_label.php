<?php
declare(strict_types=1);
// Libellé d'un critère : $prereqParts = {label, value, unit, target} (libellé fixe traduit, cible saisie par la communauté).
?>
<?php if (($prereqParts['value'] ?? '') !== ''): ?><b><?= $h($prereqParts['value']) ?> <?= $h($prereqParts['unit']) ?></b> <?php endif ?><span><?= $h($prereqParts['label'] ?? '') ?></span><?php if (($prereqParts['target'] ?? '') !== ''): ?> <span>·</span> <span<?= !empty($prereqParts['content']) ? ' translate="no"' : '' ?>><?= $h($prereqParts['target']) ?></span><?php endif ?>
