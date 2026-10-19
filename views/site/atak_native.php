<?php
declare(strict_types=1);
$t = static fn (string $key): string => htmlspecialchars(__('site.atakn_' . $key), ENT_QUOTES, 'UTF-8');
$e = static fn (string $s): string => htmlspecialchars($s, ENT_QUOTES, 'UTF-8');
$asset = static fn (string $file): string => htmlspecialchars(asset_url('assets/atak-native/' . $file), ENT_QUOTES, 'UTF-8');
$isEn = str_starts_with((string) html_lang(), 'en');
// Fiche Steam Workshop du mod COMSPEC ATAK.
$steamHref = 'https://steamcommunity.com/sharedfiles/filedetails/?id=3684656708';
$translateHref = $e(url('traduire-atak'));
// Dernière version publiée (mêmes valeurs que GET /api/atak/version) et son « Résumé joueur » du changelog.
$release = \App\Support\AtakReleaseInfo::latest();
$versionName = \App\Support\AtakReleaseInfo::versionName();
$codename = $versionName !== null && preg_match('/-\s*(\S.*)$/', $versionName, $cm) === 1 ? trim($cm[1]) : '';
$notes = $release['atak_native'] !== null
    ? \App\Support\AtakReleaseInfo::playerNotes($release['atak_native'])[$isEn ? 'en' : 'fr']
    : ['title' => '', 'bullets' => []];
$clean = static fn (string $s): string => trim(str_replace(['`', '**', '|'], '', $s));

// Icônes au trait (24 px), dans l'esprit de celles du téléphone (mod/COMSPEC_ATAK_Native/tools/gen_assets.py).
$apps = [
    'map' => '<path d="M3 6l6-3 6 3 6-3v15l-6 3-6-3-6 3z"/><path d="M9 3v15M15 6v15"/>',
    'chat' => '<path d="M4 4h16v11H9l-5 4v-4H4z"/><path d="M8 9h8M8 12h5"/>',
    'teams' => '<circle cx="8" cy="8" r="3"/><circle cx="16.5" cy="9" r="2.5"/><path d="M2.5 19c.6-3.3 2.8-5 5.5-5s4.9 1.7 5.5 5"/><path d="M14 14.4c.8-.3 1.6-.4 2.5-.4 2.3 0 4 1.4 4.5 4.5"/>',
    'reports' => '<path d="M6 3h9l4 4v14H6z"/><path d="M15 3v4h4"/><path d="M9 11h7M9 14.5h7M9 18h4"/>',
    'fires' => '<path d="M4 20l6-6"/><path d="M10 14l2-6 6-4-4 6-6 2z"/><circle cx="18" cy="18" r="3"/><path d="M18 13v2M18 21v2M13 18h2M21 18h2"/>',
    'med' => '<path d="M9 3h6v6h6v6h-6v6H9v-6H3V9h6z"/>',
    'drone' => '<circle cx="5.5" cy="5.5" r="2.5"/><circle cx="18.5" cy="5.5" r="2.5"/><circle cx="5.5" cy="18.5" r="2.5"/><circle cx="18.5" cy="18.5" r="2.5"/><path d="M7.5 7.5l3 3M16.5 7.5l-3 3M7.5 16.5l3-3M16.5 16.5l-3-3"/><rect x="10" y="10" width="4" height="4" rx="1"/>',
    'mesh' => '<path d="M12 21V9"/><path d="M8 21l4-12 4 12"/><path d="M9.5 15h5"/><path d="M7.5 6.5a6 6 0 0 1 9 0M5 4a9.5 9.5 0 0 1 14 0"/><circle cx="12" cy="9" r="1.2"/>',
    'cam' => '<path d="M3 7h4l2-2.5h6L17 7h4v12H3z"/><circle cx="12" cy="13" r="3.5"/>',
    'ew' => '<path d="M4 18a11 11 0 0 1 0-12M20 6a11 11 0 0 1 0 12M7.5 15a6 6 0 0 1 0-6M16.5 9a6 6 0 0 1 0 6"/><circle cx="12" cy="12" r="1.8"/><path d="M3 3l18 18"/>',
    'logi' => '<path d="M3 7l9-4 9 4v10l-9 4-9-4z"/><path d="M3 7l9 4 9-4M12 11v10"/>',
    'music' => '<path d="M9 18V5l11-2v13"/><circle cx="6.5" cy="18" r="2.5"/><circle cx="17.5" cy="16" r="2.5"/>',
];
// Modèles du téléphone (fn_pageSettings.sqf), le S7 durci par défaut en tête. Rendus : public/assets/atak-native/v2.
$devices = ['s7', 'iphone', 'rugged', 'sonim', 'plate', 'flip', 'ribbed', 'keys'];
$faq = range(1, 7);
$icon = static fn (string $paths): string => '<svg class="atx-ico" viewBox="0 0 24 24" aria-hidden="true">' . $paths . '</svg>';
?>
<article class="atx" id="atx" data-theme="night">
    <script>
        // Thème avant le premier rendu : choix mémorisé, sinon celui du système (le site reste sombre par défaut).
        (function () {
            var el = document.getElementById('atx'), pref = null;
            try { pref = localStorage.getItem('athena.atak.theme'); } catch (e) {}
            if (pref !== 'day' && pref !== 'night') {
                pref = window.matchMedia && window.matchMedia('(prefers-color-scheme: light)').matches ? 'day' : 'night';
            }
            el.setAttribute('data-theme', pref);
        })();
    </script>

    <header class="atx-hero">
        <div class="atx-hero__grid" aria-hidden="true"></div>
        <div class="atx-hero__sweep" aria-hidden="true"></div>
        <div class="atx-wrap atx-hero__inner">
            <div class="atx-hero__copy">
                <p class="atx-kicker atx-rise"><?= $t('kicker') ?></p>
                <h1 class="atx-title atx-rise" style="--d:1"><span><?= $t('title_a') ?></span> <span class="atx-title__accent"><?= $t('title_b') ?></span></h1>
                <p class="atx-lead atx-rise" style="--d:2"><?= $t('lead') ?></p>
                <div class="atx-cta atx-rise" style="--d:3">
                    <a href="<?= $e($steamHref) ?>" class="atx-btn atx-btn--solid" target="_blank" rel="noopener noreferrer">
                        <svg class="atx-ico" viewBox="0 0 24 24" aria-hidden="true"><path d="M12 3v12M7 10l5 5 5-5M4 19h16"/></svg>
                        <?= $t('cta_steam') ?>
                    </a>
                    <a href="#apps" class="atx-btn atx-btn--ghost"><?= $t('cta_apps') ?></a>
                </div>
                <?php if ($release['atak_native'] !== null): ?>
                <p class="atx-version atx-rise" style="--d:4">
                    <span class="atx-dot" aria-hidden="true"></span>
                    <span><?= $e(sprintf(__('site.atakn_version'), $release['atak_native'])) ?><?php if ($codename !== ''): ?> · <?= $isEn ? '“' . $e($codename) . '”' : '« ' . $e($codename) . ' »' ?><?php endif; ?></span>
                    <?php if ($release['link'] !== null): ?><span class="atx-version__sep"><?= $e(sprintf(__('site.atakn_version_link'), $release['link'])) ?></span><?php endif; ?>
                </p>
                <?php endif; ?>
            </div>
            <figure class="atx-hero__phone atx-rise" style="--d:2" aria-label="<?= $t('hero_alt') ?>">
                <div class="atx-device">
                    <div class="atx-device__screen">
                        <img src="<?= $asset('v2/screen-apps.webp') ?>" alt="" class="is-1" decoding="async">
                        <img src="<?= $asset('v2/screen-map.webp') ?>" alt="" class="is-2" decoding="async">
                        <img src="<?= $asset('v2/screen-tools.webp') ?>" alt="" class="is-3" decoding="async">
                        <span class="atx-device__glare" aria-hidden="true"></span>
                    </div>
                    <img class="atx-device__frame" src="<?= $asset('v2/frame-s7.webp') ?>" alt="<?= $t('hero_alt') ?>" width="1200" height="836" decoding="async" fetchpriority="high">
                </div>
            </figure>
        </div>
        <div class="atx-wrap">
            <dl class="atx-stats">
                <div class="atx-reveal"><dt>30+</dt><dd><?= $t('stat_apps') ?></dd></div>
                <div class="atx-reveal" style="--d:1"><dt><?= count($devices) ?></dt><dd><?= $t('stat_devices') ?></dd></div>
                <div class="atx-reveal" style="--d:2"><dt>2+</dt><dd><?= $t('stat_langs') ?></dd></div>
                <div class="atx-reveal" style="--d:3"><dt>0&nbsp;€</dt><dd><?= $t('stat_price') ?></dd></div>
            </dl>
        </div>
    </header>

    <nav class="atx-subnav" aria-label="<?= $t('nav_label') ?>">
        <div class="atx-wrap atx-subnav__inner">
            <div class="atx-subnav__links">
                <?php foreach (['apps' => 'nav_apps', 'terminaux' => 'nav_devices', 'nouveautes' => 'nav_news', 'installer' => 'nav_install', 'langues' => 'nav_langs', 'faq' => 'nav_faq'] as $anchor => $label): ?>
                <a href="#<?= $anchor ?>"><?= $t($label) ?></a>
                <?php endforeach; ?>
            </div>
            <button type="button" class="atx-theme" data-atx-theme aria-label="<?= $t('theme_toggle') ?>" title="<?= $t('theme_toggle') ?>">
                <svg class="atx-ico atx-theme__sun" viewBox="0 0 24 24" aria-hidden="true"><circle cx="12" cy="12" r="4"/><path d="M12 2v2M12 20v2M4.9 4.9l1.4 1.4M17.7 17.7l1.4 1.4M2 12h2M20 12h2M4.9 19.1l1.4-1.4M17.7 6.3l1.4-1.4"/></svg>
                <svg class="atx-ico atx-theme__moon" viewBox="0 0 24 24" aria-hidden="true"><path d="M20 14.5A8 8 0 0 1 9.5 4a8 8 0 1 0 10.5 10.5z"/></svg>
            </button>
        </div>
    </nav>

    <section id="apps" class="atx-section">
        <div class="atx-wrap">
            <div class="atx-head atx-reveal">
                <p class="atx-kicker"><?= $t('apps_kicker') ?></p>
                <h2 class="atx-h2"><?= $t('apps_title') ?></h2>
                <p class="atx-sub"><?= $t('apps_lead') ?></p>
            </div>
            <ul class="atx-apps">
                <?php $i = 0; foreach ($apps as $key => $paths): ?>
                <li class="atx-app atx-reveal" style="--d:<?= $i++ % 4 ?>">
                    <span class="atx-app__tile"><?= $icon($paths) ?></span>
                    <h3><?= $t('app_' . $key . '_t') ?></h3>
                    <p><?= $t('app_' . $key . '_b') ?></p>
                </li>
                <?php endforeach; ?>
            </ul>
            <p class="atx-more atx-reveal"><?= $t('apps_more') ?></p>
        </div>
    </section>

    <section id="terminaux" class="atx-section atx-section--alt">
        <div class="atx-wrap">
            <div class="atx-head atx-reveal">
                <p class="atx-kicker"><?= $t('devices_kicker') ?></p>
                <h2 class="atx-h2"><?= $t('devices_title') ?></h2>
                <p class="atx-sub"><?= $t('devices_lead') ?></p>
            </div>
            <ul class="atx-devices">
                <?php $i = 0; foreach ($devices as $model): ?>
                <li class="atx-dev<?= $model === 's7' ? ' atx-dev--main' : '' ?> atx-reveal" style="--d:<?= $i++ % 4 ?>">
                    <div class="atx-dev__stage">
                        <img src="<?= $asset('v2/phone-' . $model . '.webp') ?>" alt="<?= $t('dev_' . $model . '_t') ?>" loading="lazy" decoding="async">
                    </div>
                    <div class="atx-dev__copy">
                        <h3><?= $t('dev_' . $model . '_t') ?><?php if ($model === 's7'): ?> <span class="atx-badge"><?= $t('badge_default') ?></span><?php endif; ?></h3>
                        <p><?= $t('dev_' . $model . '_b') ?></p>
                    </div>
                </li>
                <?php endforeach; ?>
                <li class="atx-dev atx-dev--soon atx-reveal" style="--d:<?= $i % 4 ?>">
                    <div class="atx-dev__stage" aria-hidden="true">
                        <span class="atx-ghost"><span class="atx-ghost__cam"></span></span>
                    </div>
                    <div class="atx-dev__copy">
                        <h3><?= $t('dev_s23_t') ?> <span class="atx-badge atx-badge--soon"><?= $t('badge_soon') ?></span></h3>
                        <p><?= $t('dev_s23_b') ?></p>
                    </div>
                </li>
            </ul>
            <p class="atx-note atx-reveal"><?= $t('devices_note') ?></p>
        </div>
    </section>

    <section id="nouveautes" class="atx-section">
        <div class="atx-wrap atx-news">
            <div class="atx-head atx-reveal">
                <p class="atx-kicker"><?= $t('news_kicker') ?></p>
                <h2 class="atx-h2"><?= $t('news_title') ?></h2>
                <?php if ($release['atak_native'] !== null): ?>
                <p class="atx-news__ver">
                    <span class="atx-news__num"><?= $e($release['atak_native']) ?></span>
                    <?php if ($codename !== ''): ?><span class="atx-news__code"><?= $e($codename) ?></span><?php endif; ?>
                </p>
                <?php endif; ?>
                <a class="atx-link" href="<?= $e(url('nouveautes')) ?>"><?= $t('news_all') ?> →</a>
            </div>
            <div class="atx-news__card atx-reveal">
                <?php if ($notes['title'] !== '' || $notes['bullets'] !== []): ?>
                <h3><?= $e($clean($notes['title'])) ?></h3>
                <ul>
                    <?php foreach ($notes['bullets'] as $bullet): ?>
                    <li><?= $e($clean($bullet)) ?></li>
                    <?php endforeach; ?>
                </ul>
                <?php else: ?>
                <p><?= $t('news_empty') ?></p>
                <?php endif; ?>
            </div>
        </div>
    </section>

    <section id="installer" class="atx-section atx-section--alt">
        <div class="atx-wrap">
            <div class="atx-head atx-reveal">
                <p class="atx-kicker"><?= $t('install_kicker') ?></p>
                <h2 class="atx-h2"><?= $t('install_title') ?></h2>
            </div>
            <ol class="atx-steps">
                <?php for ($n = 1; $n <= 4; $n++): ?>
                <li class="atx-step atx-reveal" style="--d:<?= $n - 1 ?>">
                    <span class="atx-step__n"><?= sprintf('%02d', $n) ?></span>
                    <h3><?= $t('install_' . $n . '_t') ?></h3>
                    <p><?= $t('install_' . $n . '_b') ?></p>
                </li>
                <?php endfor; ?>
            </ol>
            <div class="atx-install__aside">
                <div class="atx-panel atx-reveal">
                    <h3><?= $t('req_title') ?></h3>
                    <ul class="atx-req">
                        <li><a href="https://steamcommunity.com/sharedfiles/filedetails/?id=450814997" target="_blank" rel="noopener noreferrer">CBA_A3</a><span class="atx-req__must"><?= $t('req_cba') ?></span></li>
                        <li><a href="https://steamcommunity.com/sharedfiles/filedetails/?id=463939057" target="_blank" rel="noopener noreferrer">ACE3</a><span><?= $t('req_ace') ?></span></li>
                        <li><a href="https://steamcommunity.com/sharedfiles/filedetails/?id=751965892" target="_blank" rel="noopener noreferrer">ACRE2</a><span><?= $t('req_acre') ?></span></li>
                    </ul>
                </div>
                <div class="atx-panel atx-reveal" style="--d:1">
                    <h3><?= $t('keys_title') ?></h3>
                    <ul class="atx-keys">
                        <li><span><kbd>Ctrl</kbd>+<kbd>U</kbd></span><?= $t('key_wear') ?></li>
                        <li><span><kbd>Ctrl</kbd>+<kbd><?= $isEn ? 'Shift' : 'Maj' ?></kbd>+<kbd>U</kbd></span><?= $t('key_hand') ?></li>
                        <li><span><kbd>Ctrl</kbd>+<kbd>PgUp</kbd>/<kbd>PgDn</kbd></span><?= $t('key_zoom') ?></li>
                    </ul>
                </div>
            </div>
            <p class="atx-note atx-reveal"><?= $t('install_after') ?></p>
        </div>
    </section>

    <section id="langues" class="atx-section">
        <div class="atx-wrap atx-langs">
            <div class="atx-head atx-reveal">
                <p class="atx-kicker"><?= $t('langs_kicker') ?></p>
                <h2 class="atx-h2"><?= $t('langs_title') ?></h2>
                <p class="atx-sub"><?= $t('langs_lead') ?></p>
                <p class="atx-note"><?= $t('langs_where') ?></p>
            </div>
            <ul class="atx-langs__list">
                <li class="atx-lang atx-reveal"><span class="atx-lang__code">EN</span><span><?= $t('lang_en') ?></span><svg class="atx-ico atx-lang__ok" viewBox="0 0 24 24" aria-hidden="true"><path d="M5 12.5l4.5 4.5L19 7.5"/></svg></li>
                <li class="atx-lang atx-reveal" style="--d:1"><span class="atx-lang__code">FR</span><span><?= $t('lang_fr') ?></span><svg class="atx-ico atx-lang__ok" viewBox="0 0 24 24" aria-hidden="true"><path d="M5 12.5l4.5 4.5L19 7.5"/></svg></li>
                <li class="atx-lang atx-reveal" style="--d:2">
                    <span class="atx-lang__code">+</span>
                    <div>
                        <strong><?= $t('lang_community') ?></strong>
                        <p><?= $t('lang_community_b') ?></p>
                        <a href="<?= $e(url('traduire-atak/workshop')) ?>" class="atx-link"><?= $t('cta_workshop') ?> →</a>
                    </div>
                </li>
                <li class="atx-lang atx-lang--yours atx-reveal" style="--d:3">
                    <span class="atx-lang__code">+</span>
                    <div>
                        <strong><?= $t('lang_yours') ?></strong>
                        <p><?= $t('lang_yours_b') ?></p>
                        <a href="<?= $translateHref ?>" class="atx-btn atx-btn--solid atx-btn--sm"><?= $t('cta_translate') ?></a>
                    </div>
                </li>
            </ul>
        </div>
    </section>

    <section id="clip" class="atx-section atx-section--alt">
        <div class="atx-wrap atx-clip">
            <div class="atx-head atx-reveal">
                <p class="atx-kicker"><?= $t('clip_kicker') ?></p>
                <h2 class="atx-h2"><?= $t('clip_title') ?></h2>
            </div>
            <div class="atx-screen atx-reveal">
                <video controls playsinline preload="metadata" poster="<?= $asset('atak-natif-poster.jpg') ?>" aria-label="<?= $t('clip_label') ?>">
                    <source src="<?= $asset('atak-natif-clip.mp4') ?>" type="video/mp4">
                </video>
            </div>
            <p class="atx-note"><?= $t('clip_note') ?></p>
        </div>
    </section>

    <section class="atx-section">
        <div class="atx-wrap atx-trio">
            <div class="atx-panel atx-panel--lg atx-reveal">
                <p class="atx-kicker"><?= $t('athena_kicker') ?></p>
                <h2 class="atx-h3"><?= $t('athena_title') ?></h2>
                <p><?= $t('athena_b') ?></p>
            </div>
            <div class="atx-panel atx-reveal" style="--d:1">
                <h2 class="atx-h3"><?= $t('free_title') ?></h2>
                <p><?= $t('free_b') ?></p>
                <a class="atx-link" href="<?= $e(url('soutenir-atak')) ?>"><?= $t('cta_support') ?> →</a>
            </div>
            <div class="atx-panel atx-reveal" style="--d:2">
                <h2 class="atx-h3"><?= $t('credits_title') ?></h2>
                <p class="atx-credit"><?= $t('credits_team') ?></p>
                <p class="atx-credit"><?= $t('credits_dev') ?></p>
                <p class="atx-credit-small"><?= $t('credits_bce') ?></p>
                <p class="atx-credit-small"><?= $t('credits_translations') ?></p>
            </div>
        </div>
    </section>

    <section id="faq" class="atx-section atx-section--alt">
        <div class="atx-wrap atx-faq">
            <div class="atx-head atx-reveal">
                <p class="atx-kicker"><?= $t('faq_kicker') ?></p>
                <h2 class="atx-h2"><?= $t('faq_title') ?></h2>
            </div>
            <div class="atx-faq__list">
                <?php foreach ($faq as $n): ?>
                <details class="atx-qa atx-reveal"<?= $n === 1 ? ' open' : '' ?>>
                    <summary><?= $t('faq_' . $n . '_q') ?></summary>
                    <p><?= $t('faq_' . $n . '_a') ?></p>
                </details>
                <?php endforeach; ?>
            </div>
        </div>
    </section>

    <section class="atx-final">
        <div class="atx-wrap atx-reveal">
            <h2 class="atx-h2"><?= $t('final_title') ?></h2>
            <p class="atx-sub"><?= $t('final_b') ?></p>
            <div class="atx-cta">
                <a href="<?= $e($steamHref) ?>" class="atx-btn atx-btn--solid" target="_blank" rel="noopener noreferrer"><?= $t('cta_steam') ?></a>
                <a href="<?= $translateHref ?>" class="atx-btn atx-btn--ghost"><?= $t('cta_translate') ?></a>
                <a href="<?= $e(url('atak/mod')) ?>" class="atx-btn atx-btn--ghost"><?= $t('cta_space') ?></a>
            </div>
        </div>
    </section>
</article>
