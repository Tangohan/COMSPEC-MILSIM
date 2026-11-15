<?php
declare(strict_types=1);
/**
 * Boîte « À trier » du bureau SSE : tout ce qui attend une décision humaine, toutes files confondues.
 * Lecture seule : chaque ligne ouvre l'élément dans sa page, qui garde la décision et ses droits.
 *
 * @var array{total: int, sources: array<string, array<string, mixed>>, items: list<array<string, mixed>>} $triage
 * @var list<array<string, mixed>> $triageItems
 * @var string $triageTab
 * @var string $lang
 */
use App\Services\Sse\SseTriageInbox;
use App\Support\SseTriageBadges;

ob_start();
$h = static fn (mixed $v): string => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
$lang = (string) ($lang ?? 'fr') === 'en' ? 'en' : 'fr';
$triage = is_array($triage ?? null) ? $triage : ['total' => 0, 'sources' => [], 'items' => []];
$triageItems = is_array($triageItems ?? null) ? $triageItems : [];
$triageTab = (string) ($triageTab ?? '');
$now = time();
$base = url('atak/sse/a-trier');
$sseExtraStyles = ($sseExtraStyles ?? '') . '<link rel="stylesheet" href="' . $h(asset_url('assets/css/sse-aside-badges.css')) . '">';
$age = static function (string $at) use ($now, $lang): string {
    $ts = $at !== '' ? strtotime($at . ' UTC') : false;
    if ($ts === false || $ts > $now) {
        return '';
    }
    $s = $now - $ts;
    if ($s < 3600) {
        return max(1, intdiv($s, 60)) . ' min';
    }
    if ($s < 86400) {
        return intdiv($s, 3600) . ' h';
    }

    return intdiv($s, 86400) . ($lang === 'en' ? ' d' : ' j');
};
?>
<div class="page-heading">
    <div>
        <h1>À trier</h1>
        <p>Tout ce qui attend une décision dans le bureau, les plus urgents et les plus anciens d’abord. Ouvrez une ligne pour la traiter dans sa page.</p>
    </div>
    <div class="page-reference"><strong><?= (int) $triage['total'] ?> à trier</strong></div>
</div>

<div class="sb-inbox">
    <?php if ($triage['sources'] !== []): ?>
    <nav class="sb-tabs" aria-label="Files à trier">
        <a href="<?= $h($base) ?>" <?= $triageTab === '' ? 'aria-current="true"' : '' ?>>Toutes <?= SseTriageBadges::badge('', (int) $triage['total'], 'neutral') ?></a>
        <?php foreach ($triage['sources'] as $key => $src): ?>
            <?php if ((int) $src['count'] < 1 && $triageTab !== $key) { continue; } ?>
            <a href="<?= $h($base . '?source=' . rawurlencode((string) $key)) ?>" <?= $triageTab === $key ? 'aria-current="true"' : '' ?>><?= $h($src['tab']) ?> <?= SseTriageBadges::badge((string) $src['nav'], (int) $src['count'], (string) $src['tone']) ?></a>
        <?php endforeach; ?>
    </nav>
    <?php endif; ?>

    <?php if ($triageItems === []): ?>
        <div class="sb-empty"><strong>Rien à trier</strong><p>Aucune décision n’attend dans les files que vous pouvez lire.</p></div>
    <?php else: ?>
        <ul class="sb-list">
            <?php foreach ($triageItems as $it): ?>
                <?php
                $days = SseTriageInbox::ageDays((string) ($it['at'] ?? ''), $now);
                $tone = (int) ($it['priority'] ?? 0) >= 2 ? 'bad' : ($days > SseTriageInbox::AMBER_DAYS ? 'warn' : 'neutral');
                ?>
                <li>
                    <a class="sb-row sb-row--<?= $h($tone) ?>" href="<?= $h((string) $it['href']) ?>">
                        <span class="sb-row__dot" aria-hidden="true"></span>
                        <span>
                            <span class="sb-row__title"><?= $h($it['title']) ?></span>
                            <?php if ((string) ($it['meta'] ?? '') !== ''): ?><span class="sb-row__meta"><?= $h($it['meta']) ?></span><?php endif; ?>
                        </span>
                        <span class="sb-row__src"><?= $h($it['source_label']) ?></span>
                        <span class="sb-row__age sb-row__age--<?= $h($tone) ?>"><?= $h($age((string) ($it['at'] ?? ''))) ?></span>
                    </a>
                </li>
            <?php endforeach; ?>
        </ul>
    <?php endif; ?>
</div>
<?php
$sseContent = ob_get_clean();
require __DIR__ . '/_layout.php';
