<?php
/**
 * Épinglés du salon actif, en tête du salon : seuls les éléments que ce lecteur voit (déjà revérifiés par le serveur).
 *
 * @var string $salon
 * @var list<array<string, mixed>> $pins
 * @var bool $canPin
 */

use App\Services\Jnet\JnetCommonRules;

$h = static fn (mixed $v): string => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
$top = array_values(array_filter($pins, static fn (array $p): bool => !empty($p['visible']) && (string) $p['salon'] === $salon));
?>
<?php if ($top !== []): ?>
    <section class="jc-pinned" aria-label="Épinglés de ce salon">
        <ul class="jc-pinned__list">
            <?php foreach ($top as $p): ?>
                <li class="jc-pinned__item">
                    <span class="jc-pinned__ic"><?= JnetCommonRules::icon('pin') ?></span>
                    <a class="jc-pinned__link" href="<?= $h((string) $p['href']) ?>">
                        <strong><?= $h((string) $p['title']) ?></strong>
                        <?php if (trim((string) $p['sub']) !== ''): ?><span><?= $h((string) $p['sub']) ?></span><?php endif; ?>
                    </a>
                    <?php $pinType = (string) $p['type']; $pinTarget = (string) (explode(':', (string) $p['ref'], 2)[1] ?? ''); $pinId = (int) $p['id']; require base_path('views/jnet/commun/_pin.php'); ?>
                </li>
            <?php endforeach; ?>
        </ul>
    </section>
<?php endif; ?>
