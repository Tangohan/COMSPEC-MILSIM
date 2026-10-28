<?php
declare(strict_types=1);
$loggedIn = (bool) \App\Core\Session::get('user_id');
$createHref = htmlspecialchars($loggedIn ? url('communities/create') : url('register'), ENT_QUOTES, 'UTF-8');
$t = static fn (string $key): string => htmlspecialchars(__('site.about_' . $key), ENT_QUOTES, 'UTF-8');
$img = static fn (string $path): string => htmlspecialchars(asset_url('assets/' . $path), ENT_QUOTES, 'UTF-8');
$svg = static fn (string $paths): string => '<svg viewBox="0 0 24 24" aria-hidden="true">' . $paths . '</svg>';

$ecosystem = [
    'athena' => ['icon' => '<rect x="3" y="4" width="18" height="13" rx="2"/><path d="M8 21h8M12 17v4M7 9h4M7 12h7"/>', 'href' => null],
    'atak' => ['icon' => '<rect x="6" y="2.5" width="12" height="19" rx="2.5"/><path d="M9 7l3-1.5L15 7v5l-3 1.5L9 12z"/><path d="M11 18.5h2"/>', 'href' => url('atak-natif')],
    'link' => ['icon' => '<path d="M10 14a4 4 0 0 0 5.7 0l3-3a4 4 0 0 0-5.7-5.7l-1 1"/><path d="M14 10a4 4 0 0 0-5.7 0l-3 3a4 4 0 0 0 5.7 5.7l1-1"/>', 'href' => null],
];
$roles = [
    'staff' => '<path d="M12 3l8 4-8 4-8-4z"/><path d="M4 12l8 4 8-4M4 17l8 4 8-4"/>',
    'train' => '<path d="M3 8l9-4 9 4-9 4z"/><path d="M7 10v5c0 1.5 2.2 3 5 3s5-1.5 5-3v-5M21 8v6"/>',
    'recruit' => '<circle cx="10" cy="8" r="3.5"/><path d="M3.5 20a6.5 6.5 0 0 1 13 0"/><path d="M19 8v6M16 11h6"/>',
    'op' => '<circle cx="12" cy="12" r="8"/><circle cx="12" cy="12" r="3"/><path d="M12 2v4M12 18v4M2 12h4M18 12h4"/>',
];
$principles = [
    1 => '<rect x="4" y="10" width="16" height="11" rx="2"/><path d="M8 10V7a4 4 0 0 1 8 0v3"/>',
    2 => '<path d="M12 3l7 3v6c0 4.5-3 7.5-7 9-4-1.5-7-4.5-7-9V6z"/><path d="M9 12l2 2 4-4"/>',
    3 => '<circle cx="5" cy="12" r="2"/><circle cx="19" cy="12" r="2"/><path d="M7 12h10"/><circle cx="12" cy="12" r="1"/>',
    4 => '<rect x="3" y="3" width="7" height="7" rx="1.5"/><rect x="14" y="3" width="7" height="7" rx="1.5"/><rect x="3" y="14" width="7" height="7" rx="1.5"/><path d="M17.5 14v7M14 17.5h7"/>',
];
?>
<article class="about">
    <header class="about-hero">
        <div class="about-hero__media" aria-hidden="true">
            <img src="<?= $img('images/night-team.jpg') ?>" alt="" decoding="async" fetchpriority="high">
        </div>
        <div class="about-hero__inner">
            <p class="about-kicker"><?= $t('kicker') ?></p>
            <h1 class="about-hero__title"><?= $t('title') ?></h1>
            <p class="about-hero__lead"><?= $t('lead') ?></p>
            <div class="about-actions">
                <a href="<?= $createHref ?>" class="hi-cta hi-cta-solid"><?= $t('cta_create') ?></a>
                <a href="<?= htmlspecialchars(url('communities'), ENT_QUOTES, 'UTF-8') ?>" class="hi-cta hi-cta-ghost"><?= $t('cta_registry') ?></a>
            </div>
        </div>
        <dl class="about-facts">
            <?php for ($i = 1; $i <= 4; $i++): ?>
            <div class="about-fact">
                <dt><?= $t('fact' . $i . '_v') ?></dt>
                <dd><?= $t('fact' . $i . '_l') ?></dd>
            </div>
            <?php endfor; ?>
        </dl>
    </header>

    <section class="about-section about-why">
        <div class="about-why__text">
            <p class="about-kicker"><?= $t('why_kicker') ?></p>
            <h2 class="about-h2"><?= $t('why_title') ?></h2>
            <p class="about-body"><?= $t('why_body') ?></p>
        </div>
        <div class="about-compare">
            <div class="about-compare__col about-compare__col--before">
                <h3><?= $t('before_t') ?></h3>
                <ul>
                    <?php for ($i = 1; $i <= 3; $i++): ?>
                    <li><?= $svg('<path d="M7 7l10 10M17 7L7 17"/>') ?><span><?= $t('before_' . $i) ?></span></li>
                    <?php endfor; ?>
                </ul>
            </div>
            <div class="about-compare__col about-compare__col--after">
                <h3><?= $t('after_t') ?></h3>
                <ul>
                    <?php for ($i = 1; $i <= 3; $i++): ?>
                    <li><?= $svg('<path d="M5 12.5l4.5 4.5L19 7.5"/>') ?><span><?= $t('after_' . $i) ?></span></li>
                    <?php endfor; ?>
                </ul>
            </div>
        </div>
    </section>

    <section class="about-section">
        <div class="about-head">
            <p class="about-kicker"><?= $t('eco_kicker') ?></p>
            <h2 class="about-h2"><?= $t('eco_title') ?></h2>
            <p class="about-body"><?= $t('eco_lead') ?></p>
        </div>
        <ul class="about-eco">
            <?php foreach ($ecosystem as $key => $item): ?>
            <li class="about-card about-card--eco">
                <span class="about-icon"><?= $svg($item['icon']) ?></span>
                <p class="about-card__role"><?= $t('eco_' . $key . '_r') ?></p>
                <h3 class="about-card__title"><?= $t('eco_' . $key . '_t') ?></h3>
                <p class="about-card__text"><?= $t('eco_' . $key . '_b') ?></p>
                <?php if ($item['href'] !== null): ?>
                <a class="about-card__link" href="<?= htmlspecialchars($item['href'], ENT_QUOTES, 'UTF-8') ?>"><?= $t('eco_' . $key . '_link') ?> <span aria-hidden="true">→</span></a>
                <?php endif; ?>
            </li>
            <?php endforeach; ?>
        </ul>
    </section>

    <section class="about-section">
        <div class="about-head">
            <p class="about-kicker"><?= $t('who_kicker') ?></p>
            <h2 class="about-h2"><?= $t('who_title') ?></h2>
            <p class="about-body"><?= $t('who_lead') ?></p>
        </div>
        <ul class="about-roles">
            <?php foreach ($roles as $key => $icon): ?>
            <li class="about-card">
                <span class="about-icon"><?= $svg($icon) ?></span>
                <h3 class="about-card__title"><?= $t('who_' . $key . '_t') ?></h3>
                <p class="about-card__text"><?= $t('who_' . $key . '_b') ?></p>
            </li>
            <?php endforeach; ?>
        </ul>
    </section>

    <section class="about-section">
        <div class="about-head">
            <p class="about-kicker"><?= $t('flow_kicker') ?></p>
            <h2 class="about-h2"><?= $t('flow_title') ?></h2>
        </div>
        <ol class="about-flow">
            <?php for ($i = 1; $i <= 5; $i++): ?>
            <li class="about-flow__step">
                <span class="about-flow__n"><?= sprintf('%02d', $i) ?></span>
                <h3><?= $t('flow_' . $i . '_t') ?></h3>
                <p><?= $t('flow_' . $i . '_b') ?></p>
            </li>
            <?php endfor; ?>
        </ol>
    </section>

    <section class="about-section about-field">
        <div class="about-field__text">
            <p class="about-kicker"><?= $t('field_kicker') ?></p>
            <h2 class="about-h2"><?= $t('field_title') ?></h2>
            <p class="about-body"><?= $t('field_body') ?></p>
            <a class="about-card__link" href="<?= htmlspecialchars(url('atak-natif'), ENT_QUOTES, 'UTF-8') ?>"><?= $t('eco_atak_link') ?> <span aria-hidden="true">→</span></a>
        </div>
        <figure class="about-field__shot">
            <img src="<?= $img('atak-native/capture-map.jpg') ?>" alt="<?= $t('field_alt') ?>" loading="lazy" decoding="async">
        </figure>
    </section>

    <section class="about-section">
        <div class="about-head">
            <p class="about-kicker"><?= $t('principles_kicker') ?></p>
            <h2 class="about-h2"><?= $t('principles_title') ?></h2>
        </div>
        <ul class="about-principles">
            <?php foreach ($principles as $i => $icon): ?>
            <li>
                <span class="about-icon about-icon--sm"><?= $svg($icon) ?></span>
                <div>
                    <h3><?= $t('pr' . $i . '_t') ?></h3>
                    <p><?= $t('pr' . $i . '_b') ?></p>
                </div>
            </li>
            <?php endforeach; ?>
        </ul>
    </section>

    <section class="about-end">
        <h2 class="about-h2"><?= $t('end_title') ?></h2>
        <p class="about-body"><?= $t('end_body') ?></p>
        <div class="about-actions">
            <a href="<?= $createHref ?>" class="hi-cta hi-cta-solid"><?= $t('cta_create') ?></a>
            <a href="<?= htmlspecialchars(url('contact'), ENT_QUOTES, 'UTF-8') ?>" class="hi-cta hi-cta-ghost"><?= $t('cta_contact') ?></a>
        </div>
    </section>
</article>
