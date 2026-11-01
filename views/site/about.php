<?php
declare(strict_types=1);
/**
 * Page « À propos » (/a-propos) : ce qu'est ATHENA, pour qui, ce que ça change, le téléphone et les mods,
 * l'équipe, le contact. Chiffres réels de la plateforme (PublicHistoryStatsService), affichés seulement
 * s'ils existent. Thème clair / sombre selon le système, bascule mémorisée (assets/js/site-about.js).
 */
$isEn = str_starts_with((string) html_lang(), 'en');
$loggedIn = (bool) \App\Core\Session::get('user_id');
$e = static fn (string $v): string => htmlspecialchars($v, ENT_QUOTES, 'UTF-8');
$t = static fn (string $key, array $repl = []): string => htmlspecialchars(__('site.about_' . $key, $repl), ENT_QUOTES, 'UTF-8');
$img = static fn (string $path): string => htmlspecialchars(asset_url('assets/' . $path), ENT_QUOTES, 'UTF-8');
$svg = static fn (string $paths): string => '<svg viewBox="0 0 24 24" aria-hidden="true" focusable="false">' . $paths . '</svg>';
$createHref = $loggedIn ? url('communities/create') : url('register');

$fmt = static fn (int $n): string => $isEn ? number_format($n, 0, '.', ',') : number_format($n, 0, ',', "\u{202F}");

// Chiffres réels : seuls les compteurs non nuls sont montrés (jamais d'estimation).
$stats = is_array($platformStats ?? null) ? $platformStats : [];
$figures = [];
foreach (['communities', 'members', 'operations', 'play_hours', 'distance_km', 'academy_validated'] as $key) {
    $value = (int) ($stats[$key] ?? 0);
    if ($value > 0) {
        $figures[$key] = $value;
    }
}
$since = '';
$founded = substr((string) ($stats['founded_at'] ?? ''), 0, 10);
if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $founded) === 1) {
    try {
        $foundedAt = new DateTimeImmutable($founded);
        $since = class_exists(IntlDateFormatter::class)
            ? (string) (new IntlDateFormatter($isEn ? 'en_GB' : 'fr_FR', IntlDateFormatter::NONE, IntlDateFormatter::NONE, null, null, 'LLLL y'))->format($foundedAt)
            : $foundedAt->format('m/Y');
    } catch (Throwable) {
        $since = '';
    }
}

// Ordre du schéma : le portail et le téléphone, reliés par la liaison au milieu.
$parts = [
    'portal' => ['icon' => '<rect x="3" y="4" width="18" height="13" rx="2"/><path d="M8 21h8M12 17v4M7 9h4M7 12h7"/>', 'href' => null],
    'link' => ['icon' => '<path d="M10 14a4 4 0 0 0 5.7 0l3-3a4 4 0 0 0-5.7-5.7l-1 1"/><path d="M14 10a4 4 0 0 0-5.7 0l-3 3a4 4 0 0 0 5.7 5.7l1-1"/>', 'href' => null],
    'phone' => ['icon' => '<rect x="6" y="2.5" width="12" height="19" rx="2.5"/><path d="M9 7l3-1.5L15 7v5l-3 1.5L9 12z"/><path d="M11 18.5h2"/>', 'href' => url('atak-natif')],
];
$roles = [
    'staff' => '<path d="M12 3l8 4-8 4-8-4z"/><path d="M4 12l8 4 8-4M4 17l8 4 8-4"/>',
    'train' => '<path d="M3 8l9-4 9 4-9 4z"/><path d="M7 10v5c0 1.5 2.2 3 5 3s5-1.5 5-3v-5M21 8v6"/>',
    'recruit' => '<circle cx="10" cy="8" r="3.5"/><path d="M3.5 20a6.5 6.5 0 0 1 13 0"/><path d="M19 8v6M16 11h6"/>',
    'op' => '<circle cx="12" cy="12" r="8"/><circle cx="12" cy="12" r="3"/><path d="M12 2v4M12 18v4M2 12h4M18 12h4"/>',
];
$mods = [
    'atak' => url('atak-natif'),
    'sse' => url('sse'),
    'lang' => url('traduire-atak'),
];
$principles = [
    1 => '<rect x="4" y="10" width="16" height="11" rx="2"/><path d="M8 10V7a4 4 0 0 1 8 0v3"/>',
    2 => '<path d="M12 3l7 3v6c0 4.5-3 7.5-7 9-4-1.5-7-4.5-7-9V6z"/><path d="M9 12l2 2 4-4"/>',
    3 => '<circle cx="5" cy="12" r="2"/><circle cx="19" cy="12" r="2"/><path d="M7 12h10"/><circle cx="12" cy="12" r="1"/>',
    4 => '<rect x="3" y="3" width="7" height="7" rx="1.5"/><rect x="14" y="3" width="7" height="7" rx="1.5"/><rect x="3" y="14" width="7" height="7" rx="1.5"/><path d="M17.5 14v7M14 17.5h7"/>',
];
?>
<article class="ap" data-ap>
    <header class="ap-hero" aria-labelledby="ap-title">
        <svg class="ap-hero__contours" viewBox="0 0 1200 600" preserveAspectRatio="xMidYMid slice" aria-hidden="true" focusable="false">
            <path d="M-50 470C170 410 280 520 480 460S780 300 990 350 1190 470 1260 430"/>
            <path d="M-50 420C150 360 260 470 470 410S760 250 980 300 1180 420 1260 380"/>
            <path d="M-50 360C140 300 250 410 460 350S740 190 970 240 1170 360 1260 320"/>
            <path d="M-50 300C130 240 240 350 450 290S720 130 960 180 1160 300 1260 260"/>
            <path d="M-50 240C120 180 230 290 440 230S700 70 950 120 1150 240 1260 200"/>
            <path d="M-50 180C110 120 220 230 430 170S690 10 940 60 1140 180 1260 140"/>
        </svg>
        <div class="ap-wrap ap-hero__grid">
            <div class="ap-hero__copy">
                <h1 id="ap-title" class="ap-hero__title"><?= $t('title') ?></h1>
                <p class="ap-hero__lead"><?= $t('lead') ?></p>
                <div class="ap-actions">
                    <a href="<?= $e($createHref) ?>" class="ap-btn ap-btn--solid"><?= $t('cta_create') ?></a>
                    <a href="<?= $e(url('communities')) ?>" class="ap-btn ap-btn--ghost"><?= $t('cta_registry') ?></a>
                </div>
            </div>
            <figure class="ap-hero__device">
                <img src="<?= $img('atak-native/v2/phone-s7.webp') ?>" alt="<?= $t('hero_alt') ?>" width="960" height="669" decoding="async" fetchpriority="high">
            </figure>
        </div>
        <button type="button" class="ap-theme" data-ap-theme-toggle hidden
                data-label-dark="<?= $t('theme_to_dark') ?>"
                data-label-light="<?= $t('theme_to_light') ?>"
                aria-label="<?= $t('theme_to_dark') ?>" title="<?= $t('theme_to_dark') ?>">
            <svg class="ap-theme__moon" viewBox="0 0 24 24" aria-hidden="true" focusable="false"><path d="M20 14.5A8 8 0 0 1 9.5 4a8 8 0 1 0 10.5 10.5z"/></svg>
            <svg class="ap-theme__sun" viewBox="0 0 24 24" aria-hidden="true" focusable="false"><circle cx="12" cy="12" r="4"/><path d="M12 2v2.5M12 19.5V22M2 12h2.5M19.5 12H22M4.9 4.9l1.8 1.8M17.3 17.3l1.8 1.8M4.9 19.1l1.8-1.8M17.3 6.7l1.8-1.8"/></svg>
        </button>
    </header>

    <?php if ($figures !== []): ?>
    <section class="ap-stats" aria-labelledby="ap-stats-title">
        <div class="ap-wrap">
            <div class="ap-stats__head">
                <h2 id="ap-stats-title" class="ap-stats__title"><?= $t('stats_title') ?></h2>
                <?php if ($since !== ''): ?>
                <p class="ap-stats__since"><?= $t('stats_since', ['date' => $since]) ?></p>
                <?php endif; ?>
            </div>
            <dl class="ap-stats__list">
                <?php foreach ($figures as $key => $value): ?>
                <div class="ap-stat">
                    <dt><?= $t('stat_' . $key) ?></dt>
                    <dd><?= $e($fmt($value)) ?></dd>
                </div>
                <?php endforeach; ?>
            </dl>
            <p class="ap-stats__note"><?= $t('stats_note') ?> <a href="<?= $e(url('notre-histoire')) ?>"><?= $t('stats_link') ?></a></p>
        </div>
    </section>
    <?php endif; ?>

    <section class="ap-section" aria-labelledby="ap-what">
        <div class="ap-wrap">
            <div class="ap-head">
                <h2 id="ap-what" class="ap-h2"><?= $t('what_title') ?></h2>
                <p class="ap-lead"><?= $t('what_lead') ?></p>
            </div>
            <ul class="ap-chain">
                <?php foreach ($parts as $key => $part): ?>
                <li class="ap-chain__part ap-chain__part--<?= $e($key) ?>">
                    <span class="ap-icon"><?= $svg($part['icon']) ?></span>
                    <h3 class="ap-h3"><?= $t('part_' . $key . '_t') ?></h3>
                    <p class="ap-chain__role"><?= $t('part_' . $key . '_r') ?></p>
                    <p class="ap-text"><?= $t('part_' . $key . '_b') ?></p>
                    <?php if ($part['href'] !== null): ?>
                    <a class="ap-link" href="<?= $e($part['href']) ?>"><?= $t('mod_atak_link') ?></a>
                    <?php endif; ?>
                </li>
                <?php endforeach; ?>
            </ul>
        </div>
    </section>

    <section class="ap-section ap-section--alt" aria-labelledby="ap-who">
        <div class="ap-wrap ap-split">
            <div class="ap-head">
                <h2 id="ap-who" class="ap-h2"><?= $t('who_title') ?></h2>
                <p class="ap-lead"><?= $t('who_lead') ?></p>
            </div>
            <ul class="ap-roles">
                <?php foreach ($roles as $key => $icon): ?>
                <li class="ap-role">
                    <span class="ap-icon ap-icon--sm"><?= $svg($icon) ?></span>
                    <div>
                        <h3 class="ap-h3"><?= $t('who_' . $key . '_t') ?></h3>
                        <p class="ap-text"><?= $t('who_' . $key . '_b') ?></p>
                    </div>
                </li>
                <?php endforeach; ?>
            </ul>
        </div>
    </section>

    <section class="ap-section" aria-labelledby="ap-change">
        <div class="ap-wrap">
            <div class="ap-head">
                <h2 id="ap-change" class="ap-h2"><?= $t('change_title') ?></h2>
                <p class="ap-lead"><?= $t('change_lead') ?></p>
            </div>
            <div class="ap-compare">
                <div class="ap-compare__col ap-compare__col--before">
                    <h3 class="ap-h3"><?= $t('before_t') ?></h3>
                    <ul>
                        <?php for ($i = 1; $i <= 3; $i++): ?>
                        <li><?= $svg('<path d="M7 7l10 10M17 7L7 17"/>') ?><span><?= $t('before_' . $i) ?></span></li>
                        <?php endfor; ?>
                    </ul>
                </div>
                <div class="ap-compare__col ap-compare__col--after">
                    <h3 class="ap-h3"><?= $t('after_t') ?></h3>
                    <ul>
                        <?php for ($i = 1; $i <= 3; $i++): ?>
                        <li><?= $svg('<path d="M5 12.5l4.5 4.5L19 7.5"/>') ?><span><?= $t('after_' . $i) ?></span></li>
                        <?php endfor; ?>
                    </ul>
                </div>
            </div>

            <h3 class="ap-flow__title"><?= $t('flow_title') ?></h3>
            <ol class="ap-flow">
                <?php for ($i = 1; $i <= 5; $i++): ?>
                <li class="ap-flow__step">
                    <span class="ap-flow__n" aria-hidden="true"><?= $i ?></span>
                    <h4><?= $t('flow_' . $i . '_t') ?></h4>
                    <p><?= $t('flow_' . $i . '_b') ?></p>
                </li>
                <?php endfor; ?>
            </ol>
        </div>
    </section>

    <section class="ap-section ap-section--alt" aria-labelledby="ap-mods">
        <div class="ap-wrap ap-mods">
            <div class="ap-mods__copy">
                <h2 id="ap-mods" class="ap-h2"><?= $t('mods_title') ?></h2>
                <p class="ap-lead"><?= $t('mods_lead') ?></p>
                <ul class="ap-mods__list">
                    <?php foreach ($mods as $key => $href): ?>
                    <li>
                        <h3 class="ap-h3"><?= $t('mod_' . $key . '_t') ?></h3>
                        <p class="ap-text"><?= $t('mod_' . $key . '_b') ?></p>
                        <a class="ap-link" href="<?= $e($href) ?>"><?= $t('mod_' . $key . '_link') ?></a>
                    </li>
                    <?php endforeach; ?>
                </ul>
            </div>
            <figure class="ap-mods__shot">
                <img src="<?= $img('atak-native/v2/screen-apps.webp') ?>" alt="<?= $t('mods_shot_alt') ?>" width="1100" height="628" loading="lazy" decoding="async">
            </figure>
        </div>
    </section>

    <section class="ap-section" aria-labelledby="ap-principles">
        <div class="ap-wrap">
            <div class="ap-head">
                <h2 id="ap-principles" class="ap-h2"><?= $t('principles_title') ?></h2>
            </div>
            <ul class="ap-principles">
                <?php foreach ($principles as $i => $icon): ?>
                <li>
                    <span class="ap-icon ap-icon--sm"><?= $svg($icon) ?></span>
                    <h3 class="ap-h3"><?= $t('pr' . $i . '_t') ?></h3>
                    <p class="ap-text"><?= $t('pr' . $i . '_b') ?></p>
                </li>
                <?php endforeach; ?>
            </ul>
        </div>
    </section>

    <section class="ap-section ap-section--alt" aria-labelledby="ap-team">
        <div class="ap-wrap ap-team">
            <div>
                <h2 id="ap-team" class="ap-h2"><?= $t('team_title') ?></h2>
                <p class="ap-lead"><?= $t('team_body') ?></p>
                <p class="ap-text"><?= $t('team_free') ?></p>
            </div>
            <ul class="ap-team__links">
                <li><a class="ap-link" href="<?= $e(url('contribuer')) ?>"><?= $t('team_contrib') ?></a></li>
                <li><a class="ap-link" href="<?= $e(url('soutenir-atak')) ?>"><?= $t('team_support') ?></a></li>
                <li><a class="ap-link" href="<?= $e(url('notre-histoire')) ?>"><?= $t('team_history') ?></a></li>
            </ul>
        </div>
    </section>

    <section class="ap-contact" aria-labelledby="ap-contact">
        <div class="ap-wrap ap-contact__inner">
            <h2 id="ap-contact" class="ap-h2"><?= $t('contact_title') ?></h2>
            <p class="ap-lead"><?= $t('contact_body') ?></p>
            <div class="ap-actions">
                <a href="<?= $e(url('contact')) ?>" class="ap-btn ap-btn--solid"><?= $t('cta_contact') ?></a>
                <a href="<?= $e($createHref) ?>" class="ap-btn ap-btn--ghost"><?= $t('cta_create') ?></a>
            </div>
        </div>
    </section>
</article>
