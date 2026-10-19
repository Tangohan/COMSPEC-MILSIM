<?php
declare(strict_types=1);
// Workshop des langues (/traduire-atak/workshop) : packs de langue publiés depuis l'atelier, état de chacun face au
// mod actuel, abonnement Steam ou téléchargement direct. Données : AtakTranslationWorkshopController::catalog.
$e = static fn (string $s): string => htmlspecialchars($s, ENT_QUOTES, 'UTF-8');
$w = static fn (string $key, array $r = []): string => htmlspecialchars(__('site.atakw_' . $key, $r), ENT_QUOTES, 'UTF-8');
$cards = isset($cards) && is_array($cards) ? $cards : [];
$official = isset($official) && is_array($official) ? $official : [];
$sort = isset($sort) && is_string($sort) ? $sort : 'popular';
$query = isset($query) && is_string($query) ? $query : '';
$ready = !empty($ready);
$release = \App\Support\AtakReleaseInfo::latest();
$templateTotal = count(\App\Support\AtakTranslationTemplate::entries());
$en = str_starts_with((string) html_lang(), 'en');
$fmt = static fn (int $n): string => number_format($n, 0, ',', $en ? ',' : ' ');
$downloads = array_sum(array_map(static fn (array $c): int => (int) $c['downloads'], $cards));
$sorts = ['popular' => $w('sort_popular'), 'recent' => $w('sort_recent'), 'progress' => $w('sort_progress'), 'name' => $w('sort_name')];
$tabActive = 'workshop';
?>
<article class="atakn atakt atakw" id="atakw">
    <header class="atakn-hero atakt-hero">
        <p class="hi-kicker atakn-kicker"><?= $w('kicker') ?></p>
        <h1 class="atakn-title atakt-title"><?= $w('title') ?></h1>
        <p class="atakn-lead"><?= $w('lead') ?></p>
        <?php require __DIR__ . '/atak_translate_tabs.php'; ?>
    </header>

    <section class="atakw-stats" aria-label="<?= $w('stats_label') ?>">
        <div><strong><?= $fmt(count($cards)) ?></strong><span><?= $w('stat_packs') ?></span></div>
        <div><strong><?= $fmt($downloads) ?></strong><span><?= $w('stat_downloads') ?></span></div>
        <div><strong><?= $e((string) ($release['atak_native'] ?? '—')) ?></strong><span><?= $w('stat_version') ?></span></div>
        <div><strong><?= $fmt($templateTotal) ?></strong><span><?= $w('stat_strings') ?></span></div>
        <div class="atakw-official">
            <span><?= $w('official_label') ?></span>
            <p><?php foreach ($official as $code => $name): ?><em><b><?= $e(strtoupper((string) $code)) ?></b> <?= $e((string) $name) ?></em><?php endforeach; ?></p>
        </div>
    </section>

    <form class="atakw-bar" method="get" action="<?= $e(url('traduire-atak/workshop')) ?>" role="search">
        <label class="atakw-search">
            <svg viewBox="0 0 24 24" aria-hidden="true" class="tw-ico"><circle cx="11" cy="11" r="7"/><path d="M20 20l-3.5-3.5"/></svg>
            <input type="search" name="q" value="<?= $e($query) ?>" placeholder="<?= $w('search') ?>" aria-label="<?= $w('search') ?>">
        </label>
        <div class="atakw-sorts" role="group" aria-label="<?= $w('sort_label') ?>">
            <?php foreach ($sorts as $key => $label): ?>
            <button type="submit" name="tri" value="<?= $e($key) ?>" class="atakw-sort<?= $key === $sort ? ' is-active' : '' ?>" aria-pressed="<?= $key === $sort ? 'true' : 'false' ?>"><?= $label ?></button>
            <?php endforeach; ?>
        </div>
    </form>

    <?php if (!$ready): ?>
    <p class="atakw-empty"><?= $w('unavailable') ?></p>
    <?php elseif ($cards === []): ?>
    <div class="atakw-empty">
        <h2><?= $query !== '' ? $w('empty_search_title') : $w('empty_title') ?></h2>
        <p><?= $query !== '' ? $w('empty_search_lead') : $w('empty_lead') ?></p>
        <a class="hi-cta hi-cta-solid" href="<?= $e(url('traduire-atak')) ?>"><?= $w('empty_cta') ?></a>
    </div>
    <?php else: ?>
    <ul class="atakw-grid">
        <?php foreach ($cards as $c):
            $st = $c['status'];
            $pct = (int) $c['audit']['pct'];
            $href = url('traduire-atak/workshop/' . $c['id']);
            $steam = \App\Support\AtakTranslationWorkshop::steamUrl((string) $c['workshop_id']);
        ?>
        <li class="atakw-card">
            <a class="atakw-card__tile" href="<?= $e($href) ?>" aria-hidden="true" tabindex="-1">
                <span class="atakw-tile__brand">COMSPEC ATAK</span>
                <span class="atakw-tile__name"><?= $e((string) $c['name']) ?></span>
                <span class="atakw-tile__code"><?= $e(strtoupper((string) $c['code'])) ?></span>
                <span class="atakw-tile__v">v<?= (int) $c['version'] ?></span>
            </a>
            <div class="atakw-card__body">
                <h2><a href="<?= $e($href) ?>"><?= $e((string) $c['name']) ?></a></h2>
                <p class="atakw-card__by"><?= $c['author'] !== '' ? $w('by', ['name' => (string) $c['author']]) : $w('by_community') ?></p>
                <span class="atakw-status atakw-status--<?= $e($st['key']) ?>"><?= $w('status_' . $st['key'], ['count' => $fmt((int) ($st['key'] === 'recheck' ? $st['errors'] : $st['new']))]) ?></span>
                <div class="atakw-meter" role="img" aria-label="<?= $w('pct_label', ['pct' => $pct]) ?>"><i style="width: <?= $pct ?>%"></i></div>
                <p class="atakw-card__meta">
                    <span><?= $w('pct', ['pct' => $pct]) ?></span>
                    <span><?= $w('built_for', ['ver' => (string) $c['atak_version']]) ?></span>
                    <span><?= $w('downloads', ['count' => $fmt((int) $c['downloads'])]) ?></span>
                </p>
                <div class="atakw-card__actions">
                    <?php if ($steam !== ''): ?>
                    <a class="atakw-btn atakw-btn--steam" href="<?= $e($steam) ?>" target="_blank" rel="noopener"><?= $w('subscribe') ?></a>
                    <?php endif; ?>
                    <a class="atakw-btn<?= $steam === '' ? ' atakw-btn--solid' : '' ?>" href="<?= $e($href . '/telecharger') ?>" rel="nofollow" download><?= $w('download_short') ?></a>
                </div>
            </div>
        </li>
        <?php endforeach; ?>
    </ul>
    <?php endif; ?>

    <section class="atakw-how" aria-labelledby="atakw-how-h">
        <h2 class="atakt-h2" id="atakw-how-h"><span>01</span><?= $w('how_title') ?></h2>
        <div class="atakw-how__cols">
            <div>
                <h3><?= $w('how_players') ?></h3>
                <ol>
                    <?php for ($i = 1; $i <= 3; $i++): ?><li><?= $w('how_p' . $i) ?></li><?php endfor; ?>
                </ol>
            </div>
            <div>
                <h3><?= $w('how_translators') ?></h3>
                <ol>
                    <?php for ($i = 1; $i <= 3; $i++): ?><li><?= $w('how_t' . $i) ?></li><?php endfor; ?>
                </ol>
            </div>
            <div>
                <h3><?= $w('how_checks') ?></h3>
                <ul>
                    <?php for ($i = 1; $i <= 4; $i++): ?><li><span class="atakw-status atakw-status--<?= ['ok', 'outdated', 'recheck', 'official'][$i - 1] ?>"><?= $w('status_' . ['ok', 'outdated', 'recheck', 'official'][$i - 1], ['count' => 'N']) ?></span> <?= $w('how_c' . $i) ?></li><?php endfor; ?>
                </ul>
            </div>
        </div>
    </section>
</article>
