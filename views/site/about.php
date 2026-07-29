<?php
declare(strict_types=1);
$loggedIn = (bool) \App\Core\Session::get('user_id');
$createHref = $loggedIn ? url('communities/create') : url('register');
?>
<article class="site-page">
    <header class="site-page__hero">
        <p class="hi-kicker text-emerald-400/90"><?= htmlspecialchars(__('site.about_kicker'), ENT_QUOTES, 'UTF-8') ?></p>
        <h1 class="hi-display hi-display-md mt-4 text-white"><?= htmlspecialchars(__('site.about_title'), ENT_QUOTES, 'UTF-8') ?></h1>
        <p class="hi-body mt-5 max-w-2xl text-white/65"><?= htmlspecialchars(__('site.about_lead'), ENT_QUOTES, 'UTF-8') ?></p>
    </header>

    <div class="site-page__grid">
        <section class="site-prose">
            <h2><?= htmlspecialchars(__('site.about_p1_t'), ENT_QUOTES, 'UTF-8') ?></h2>
            <p><?= htmlspecialchars(__('site.about_p1_b'), ENT_QUOTES, 'UTF-8') ?></p>
        </section>
        <section class="site-prose">
            <h2><?= htmlspecialchars(__('site.about_p2_t'), ENT_QUOTES, 'UTF-8') ?></h2>
            <p><?= htmlspecialchars(__('site.about_p2_b'), ENT_QUOTES, 'UTF-8') ?></p>
        </section>
        <section class="site-prose">
            <h2><?= htmlspecialchars(__('site.about_p3_t'), ENT_QUOTES, 'UTF-8') ?></h2>
            <p><?= htmlspecialchars(__('site.about_p3_b'), ENT_QUOTES, 'UTF-8') ?></p>
        </section>
        <section class="site-prose">
            <h2><?= htmlspecialchars(__('site.about_p4_t'), ENT_QUOTES, 'UTF-8') ?></h2>
            <ul>
                <li><?= htmlspecialchars(__('site.about_p4_i1'), ENT_QUOTES, 'UTF-8') ?></li>
                <li><?= htmlspecialchars(__('site.about_p4_i2'), ENT_QUOTES, 'UTF-8') ?></li>
                <li><?= htmlspecialchars(__('site.about_p4_i3'), ENT_QUOTES, 'UTF-8') ?></li>
                <li><?= htmlspecialchars(__('site.about_p4_i4'), ENT_QUOTES, 'UTF-8') ?></li>
            </ul>
        </section>
    </div>

    <div class="site-page__cta">
        <a href="<?= htmlspecialchars($createHref, ENT_QUOTES, 'UTF-8') ?>" class="hi-cta hi-cta-solid"><?= htmlspecialchars(__('site.about_cta_create'), ENT_QUOTES, 'UTF-8') ?></a>
        <a href="<?= htmlspecialchars(url('contact'), ENT_QUOTES, 'UTF-8') ?>" class="hi-cta hi-cta-ghost"><?= htmlspecialchars(__('site.about_cta_contact'), ENT_QUOTES, 'UTF-8') ?></a>
    </div>
</article>
