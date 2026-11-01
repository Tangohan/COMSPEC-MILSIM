<?php
declare(strict_types=1);

/**
 * Bilan de santé du site (administrateurs du site uniquement).
 *
 * @var list<array{key: string, label: string, value: string, tone: string, hint: string, href: string}> $adminHealthRows
 * @var array{ok: int, warn: int, bad: int, tone: string} $adminHealthSummary
 */
$healthRows = is_array($adminHealthRows ?? null) ? $adminHealthRows : [];
$healthSum = is_array($adminHealthSummary ?? null) ? $adminHealthSummary : ['ok' => 0, 'warn' => 0, 'bad' => 0, 'tone' => 'ok'];
$hh = static fn (string $v): string => htmlspecialchars($v, ENT_QUOTES, 'UTF-8');
$bad = (int) $healthSum['bad'];
$warn = (int) $healthSum['warn'];
$verdict = match (true) {
    $bad > 0 && $warn > 0 => $bad . ' point(s) à traiter, ' . $warn . ' à surveiller',
    $bad > 0 => $bad . ' point(s) à traiter',
    $warn > 0 => $warn . ' point(s) à surveiller',
    default => 'Tout fonctionne normalement',
};
$toneWord = ['ok' => 'Bon', 'warn' => 'À surveiller', 'bad' => 'À traiter', 'info' => 'Information'];
?>
<section id="hub-sante" class="pa-card pa-health pa-health--<?= $hh((string) $healthSum['tone']) ?> scroll-mt-24" aria-labelledby="pa-health-title">
    <div class="pa-health__head">
        <h2 id="pa-health-title" class="pa-card__title">État du site</h2>
        <p class="pa-health__verdict"><?= $hh($verdict) ?></p>
    </div>
    <p class="pa-card__help pa-health__foot">Chaque ligne ouvre l’outil qui la traite. Les actions des administrateurs sont dans le <a href="<?= $hh(url('admin/audit') . '?action_domain=platform') ?>">journal du site</a>.</p>
    <ul class="pa-health__list">
        <?php foreach ($healthRows as $r): ?>
        <li class="pa-health__row pa-health__row--<?= $hh($r['tone']) ?>">
            <a href="<?= $hh(url($r['href'])) ?>" class="pa-health__link">
                <span class="pa-health__label"><?= $hh($r['label']) ?><span class="ath-sr-only"> : <?= $hh($toneWord[$r['tone']] ?? '') ?></span></span>
                <span class="pa-health__value"><?= $hh($r['value']) ?></span>
                <?php if ($r['hint'] !== ''): ?>
                <span class="pa-health__hint"><?= $hh($r['hint']) ?></span>
                <?php endif; ?>
            </a>
        </li>
        <?php endforeach; ?>
    </ul>
</section>
