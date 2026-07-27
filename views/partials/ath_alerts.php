<?php
declare(strict_types=1);

/**
 * Bandeau d’alertes ATHENA (maquette).
 *
 * @var list<array{tag: string, dot: string, msg: string, time?: string, cta?: string, href?: string}> $athAlerts
 */

use App\Support\AthUi;

if (empty($athAlerts) || !is_array($athAlerts)) {
    return;
}

$h = static fn (string $v): string => htmlspecialchars($v, ENT_QUOTES, 'UTF-8');
?>
<div class="ath-alerts ath-rise" role="list">
    <?php foreach ($athAlerts as $alert): ?>
        <?php
        $tag = trim((string) ($alert['tag'] ?? ''));
        $msg = trim((string) ($alert['msg'] ?? ''));
        if ($tag === '' && $msg === '') {
            continue;
        }
        $dot = trim((string) ($alert['dot'] ?? '#12d18e'));
        $time = trim((string) ($alert['time'] ?? ''));
        $cta = trim((string) ($alert['cta'] ?? ''));
        $href = trim((string) ($alert['href'] ?? ''));
        ?>
        <div class="ath-alerts__row ath-row" role="listitem">
            <span class="ath-alerts__dot" style="background:<?= $h($dot) ?>" aria-hidden="true"></span>
            <?php if ($tag !== ''): ?>
            <span class="ath-alerts__tag" style="color:<?= $h($dot) ?>"><?= $h(mb_strtoupper($tag, 'UTF-8')) ?></span>
            <?php endif; ?>
            <span class="ath-alerts__msg"><?= $h($msg) ?></span>
            <span class="ath-alerts__spacer" aria-hidden="true"></span>
            <?php if ($time !== ''): ?>
            <span class="ath-alerts__time"><?= $h($time) ?></span>
            <?php endif; ?>
            <?php if ($cta !== ''): ?>
                <?php if ($href !== ''): ?>
                <a href="<?= $h($href) ?>" class="ath-alerts__cta"><?= $h($cta) ?></a>
                <?php else: ?>
                <span class="ath-alerts__cta"><?= $h($cta) ?></span>
                <?php endif; ?>
            <?php endif; ?>
        </div>
    <?php endforeach; ?>
</div>
