<?php
/**
 * Salon « Renseignements » : fil des événements et fiches terrain SSE récents, groupé par jour. Derrière la porte du
 * renseignement (membre habilité + engagement de confidentialité) : sans elle, le salon est verrouillé et ne montre rien.
 *
 * @var array<string, mixed> $view
 * @var string $salon
 * @var bool $canPin
 */

use App\Services\Jnet\JnetCommonRules;

$h = static fn (mixed $v): string => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
if (!empty($view['locked'])) {
    require base_path('views/jnet/commun/_locked.php');

    return;
}
$days = is_array($view['days'] ?? null) ? $view['days'] : [];
?>
<div class="jc-body">
    <?php if ($days === []): ?>
        <div class="jc-empty">
            <p><strong>Aucun renseignement récent.</strong></p>
            <p>Les événements et fiches terrain du bureau SSE apparaîtront ici, du plus récent au plus ancien.</p>
            <p><a class="jn-btn jn-btn--small" href="<?= $h(url('atak/sse/fiches')) ?>">Ouvrir les fiches terrain</a></p>
        </div>
    <?php else: ?>
        <div class="jc-feed" data-jc-feed>
            <?php foreach ($days as $day): ?>
                <section class="jc-day">
                    <h2 class="jc-day__t"><span><?= $h((string) $day['label']) ?></span></h2>
                    <?php foreach ($day['items'] as $item): ?>
                        <article class="jc-note-item<?= (int) $item['pinId'] > 0 ? ' is-pinned' : '' ?>" id="r-<?= $h((string) $item['ref']) ?>">
                            <time class="jc-note-item__time" datetime="<?= $h((string) $item['at']) ?>"><?= (strtotime((string) $item['at']) ?: 0) > 0 ? $h(date('H:i', (int) strtotime((string) $item['at']))) : '' ?></time>
                            <div class="jc-note-item__main">
                                <div class="jc-note-item__meta"><span class="jc-tag"><?= $h((string) $item['kind']) ?></span><?php if (trim((string) $item['by']) !== ''): ?><span translate="no"><?= $h((string) $item['by']) ?></span><?php endif; ?></div>
                                <h3 class="jc-note-item__t"><a href="<?= $h((string) $item['href']) ?>"><?= $h((string) $item['title']) ?></a></h3>
                                <?php if (trim((string) $item['detail']) !== ''): ?><p class="jc-note-item__d" translate="no"><?= $h((string) $item['detail']) ?></p><?php endif; ?>
                            </div>
                            <div class="jc-msg__tools">
                                <?php $pinType = 'renseignement'; $pinTarget = (string) $item['ref']; $pinId = (int) $item['pinId']; require base_path('views/jnet/commun/_pin.php'); ?>
                            </div>
                        </article>
                    <?php endforeach; ?>
                </section>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>
