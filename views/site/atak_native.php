<?php
declare(strict_types=1);
$t = static fn (string $key): string => htmlspecialchars(__('site.atakn_' . $key), ENT_QUOTES, 'UTF-8');
$asset = static fn (string $file): string => htmlspecialchars(asset_url('assets/atak-native/' . $file), ENT_QUOTES, 'UTF-8');
$downloadHref = htmlspecialchars(url('atak/mod'), ENT_QUOTES, 'UTF-8');
$supportHref = htmlspecialchars(url('soutenir-atak'), ENT_QUOTES, 'UTF-8');

// Icônes reprises de mod/COMSPEC_ATAK_Native/tools/gen_assets.py (mêmes dessins que dans le téléphone).
$apps = [
    'map' => '<path d="M3 6l6-3 6 3 6-3v15l-6 3-6-3-6 3z"/><path d="M9 3v15M15 6v15"/>',
    'gps' => '<circle cx="6" cy="18" r="2"/><path d="M8 18h6a3.5 3.5 0 0 0 0-7H10a3.5 3.5 0 0 1 0-7h6"/><path d="M18 2.5l2 2-2 2"/>',
    'marker' => '<path d="M12 21s-6-6.2-6-11a6 6 0 0 1 12 0c0 4.8-6 11-6 11z"/><path d="M12 7v6M9 10h6"/>',
    'chat' => '<path d="M4 4h16v11H9l-5 4v-4H4z"/><path d="M8 9h8M8 12h5"/>',
    'fires' => '<path d="M4 20l6-6"/><path d="M10 14l2-6 6-4-4 6-6 2z"/><circle cx="18" cy="18" r="3"/><path d="M18 13v2M18 21v2M13 18h2M21 18h2"/>',
    'med' => '<path d="M9 3h6v6h6v6h-6v6H9v-6H3V9h6z"/>',
    'ew' => '<path d="M4 18a11 11 0 0 1 0-12M20 6a11 11 0 0 1 0 12M7.5 15a6 6 0 0 1 0-6M16.5 9a6 6 0 0 1 0 6"/><circle cx="12" cy="12" r="1.8"/><path d="M3 3l18 18"/>',
    'logi' => '<path d="M3 7l9-4 9 4v10l-9 4-9-4z"/><path d="M3 7l9 4 9-4M12 11v10"/>',
    'cam' => '<path d="M3 7h4l2-2.5h6L17 7h4v12H3z"/><circle cx="12" cy="13" r="3.5"/>',
    'mesh' => '<circle cx="12" cy="12" r="2"/><circle cx="4" cy="6" r="1.6"/><circle cx="20" cy="6" r="1.6"/><circle cx="5" cy="19" r="1.6"/><circle cx="19" cy="19" r="1.6"/><path d="M5.3 7l5.2 3.8M18.7 7l-5.2 3.8M6.3 18l4.4-4.6M17.7 18l-4.4-4.6M5.6 6h12.8"/>',
];
$shots = ['launch', 'map', 'gps', 'marker', 'tools', 'settings'];
?>
<article class="atakn">
    <header class="atakn-hero">
        <p class="hi-kicker atakn-kicker"><?= $t('kicker') ?></p>
        <h1 class="atakn-title"><?= $t('title') ?></h1>
        <p class="atakn-lead"><?= $t('lead') ?></p>
        <div class="site-page__cta atakn-cta">
            <a href="<?= $downloadHref ?>" class="hi-cta hi-cta-solid"><?= $t('cta_download') ?></a>
            <a href="#clip" class="hi-cta hi-cta-ghost"><?= $t('cta_clip') ?></a>
        </div>
        <dl class="atakn-stats">
            <div><dt>31</dt><dd><?= $t('stat_apps') ?></dd></div>
            <div><dt>3</dt><dd><?= $t('stat_modes') ?></dd></div>
            <div><dt>0&nbsp;€</dt><dd><?= $t('stat_price') ?></dd></div>
        </dl>
    </header>

    <section id="clip" class="atakn-clip" aria-label="<?= $t('clip_label') ?>">
        <div class="atakn-screen">
            <span class="atakn-corner atakn-corner--tl" aria-hidden="true"></span>
            <span class="atakn-corner atakn-corner--tr" aria-hidden="true"></span>
            <span class="atakn-corner atakn-corner--bl" aria-hidden="true"></span>
            <span class="atakn-corner atakn-corner--br" aria-hidden="true"></span>
            <video controls playsinline preload="metadata" poster="<?= $asset('atak-natif-poster.jpg') ?>" aria-label="<?= $t('clip_label') ?>">
                <source src="<?= $asset('atak-natif-clip.mp4') ?>" type="video/mp4">
            </video>
        </div>
        <p class="atakn-note"><?= $t('clip_note') ?></p>
    </section>

    <section class="atakn-block">
        <p class="hi-kicker atakn-kicker"><?= $t('apps_kicker') ?></p>
        <h2 class="atakn-h2"><?= $t('apps_title') ?></h2>
        <p class="atakn-sublead"><?= $t('apps_lead') ?></p>
        <ul class="atakn-apps">
            <?php foreach ($apps as $key => $icon): ?>
            <li class="atakn-app">
                <svg class="atakn-app__icon" viewBox="0 0 24 24" aria-hidden="true"><?= $icon ?></svg>
                <div>
                    <h3><?= $t('app_' . $key . '_t') ?></h3>
                    <p><?= $t('app_' . $key . '_b') ?></p>
                </div>
            </li>
            <?php endforeach; ?>
        </ul>
    </section>

    <section class="atakn-block">
        <p class="hi-kicker atakn-kicker"><?= $t('shots_kicker') ?></p>
        <h2 class="atakn-h2"><?= $t('shots_title') ?></h2>
        <div class="atakn-shots">
            <?php foreach ($shots as $shot): ?>
            <figure class="atakn-shot">
                <img src="<?= $asset('capture-' . $shot . '.jpg') ?>" alt="<?= $t('shot_' . $shot) ?>" loading="lazy" decoding="async">
                <figcaption><?= $t('shot_' . $shot) ?></figcaption>
            </figure>
            <?php endforeach; ?>
        </div>
    </section>

    <section class="atakn-block">
        <p class="hi-kicker atakn-kicker"><?= $t('sync_kicker') ?></p>
        <h2 class="atakn-h2"><?= $t('sync_title') ?></h2>
        <ol class="site-sse-steps">
            <?php for ($i = 1; $i <= 3; $i++): ?>
            <li class="site-sse-step">
                <span class="site-sse-step__n"><?= sprintf('%02d', $i) ?></span>
                <div>
                    <h3><?= $t('sync_' . $i . '_t') ?></h3>
                    <p><?= $t('sync_' . $i . '_b') ?></p>
                </div>
            </li>
            <?php endfor; ?>
        </ol>
    </section>

    <div class="atakn-block site-page__grid site-page__grid--2">
        <section class="site-prose">
            <h2><?= $t('free_title') ?></h2>
            <p><?= $t('free_b') ?></p>
        </section>
        <section class="site-prose">
            <h2><?= $t('credits_title') ?></h2>
            <p class="atakn-credit-team"><?= $t('credits_team') ?></p>
            <p><?= $t('credits_dev') ?></p>
            <p class="atakn-credit-small"><?= $t('credits_bce') ?></p>
        </section>
    </div>

    <section class="atakn-final">
        <h2 class="atakn-h2"><?= $t('final_title') ?></h2>
        <p class="atakn-sublead"><?= $t('final_b') ?></p>
        <div class="site-page__cta">
            <a href="<?= $downloadHref ?>" class="hi-cta hi-cta-solid"><?= $t('cta_download') ?></a>
            <a href="<?= $supportHref ?>" class="hi-cta hi-cta-ghost"><?= $t('cta_support') ?></a>
        </div>
    </section>
</article>
