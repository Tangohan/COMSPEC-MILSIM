<?php
declare(strict_types=1);
$loggedIn = (bool) \App\Core\Session::get('user_id');
$createHref = $loggedIn ? url('communities/create') : url('register');
$sseGateHref = url('atak/sse');
?>
<article class="site-page site-page--wide">
    <header class="site-page__hero">
        <p class="hi-kicker text-emerald-400/90"><?= htmlspecialchars(__('site.sse_kicker'), ENT_QUOTES, 'UTF-8') ?></p>
        <h1 class="hi-display hi-display-md mt-4 text-white"><?= htmlspecialchars(__('site.sse_title'), ENT_QUOTES, 'UTF-8') ?></h1>
        <p class="hi-body mt-5 max-w-3xl text-white/65"><?= htmlspecialchars(__('site.sse_lead'), ENT_QUOTES, 'UTF-8') ?></p>
        <div class="site-page__cta" style="margin-top:1.5rem">
            <a href="<?= htmlspecialchars($sseGateHref, ENT_QUOTES, 'UTF-8') ?>" class="hi-cta hi-cta-solid"><?= htmlspecialchars(__('site.sse_cta_portal'), ENT_QUOTES, 'UTF-8') ?></a>
            <a href="#fonctionnement" class="hi-cta hi-cta-ghost"><?= htmlspecialchars(__('site.sse_cta_how'), ENT_QUOTES, 'UTF-8') ?></a>
        </div>
    </header>

    <div class="site-page__grid site-page__grid--2">
        <section class="site-prose">
            <h2><?= htmlspecialchars(__('site.sse_what_t'), ENT_QUOTES, 'UTF-8') ?></h2>
            <p><?= htmlspecialchars(__('site.sse_what_b'), ENT_QUOTES, 'UTF-8') ?></p>
        </section>
        <section class="site-prose">
            <h2><?= htmlspecialchars(__('site.sse_loop_t'), ENT_QUOTES, 'UTF-8') ?></h2>
            <p><?= htmlspecialchars(__('site.sse_loop_b'), ENT_QUOTES, 'UTF-8') ?></p>
        </section>
    </div>

    <section id="fonctionnement" class="site-sse-block">
        <p class="hi-kicker text-emerald-400/80"><?= htmlspecialchars(__('site.sse_flow_kicker'), ENT_QUOTES, 'UTF-8') ?></p>
        <h2 class="site-sse-block__title"><?= htmlspecialchars(__('site.sse_flow_title'), ENT_QUOTES, 'UTF-8') ?></h2>
        <ol class="site-sse-steps">
            <?php for ($i = 1; $i <= 5; $i++): ?>
            <li class="site-sse-step">
                <span class="site-sse-step__n"><?= sprintf('%02d', $i) ?></span>
                <div>
                    <h3><?= htmlspecialchars(__('site.sse_flow_' . $i . '_t'), ENT_QUOTES, 'UTF-8') ?></h3>
                    <p><?= htmlspecialchars(__('site.sse_flow_' . $i . '_b'), ENT_QUOTES, 'UTF-8') ?></p>
                </div>
            </li>
            <?php endfor; ?>
        </ol>
    </section>

    <section class="site-sse-block">
        <p class="hi-kicker text-emerald-400/80"><?= htmlspecialchars(__('site.sse_mod_kicker'), ENT_QUOTES, 'UTF-8') ?></p>
        <h2 class="site-sse-block__title"><?= htmlspecialchars(__('site.sse_mod_title'), ENT_QUOTES, 'UTF-8') ?></h2>
        <p class="site-sse-block__lead"><?= htmlspecialchars(__('site.sse_mod_lead'), ENT_QUOTES, 'UTF-8') ?></p>
        <div class="site-page__grid site-page__grid--2">
            <section class="site-prose">
                <h2><?= htmlspecialchars(__('site.sse_mod_field_t'), ENT_QUOTES, 'UTF-8') ?></h2>
                <p><?= htmlspecialchars(__('site.sse_mod_field_b'), ENT_QUOTES, 'UTF-8') ?></p>
            </section>
            <section class="site-prose">
                <h2><?= htmlspecialchars(__('site.sse_mod_scenario_t'), ENT_QUOTES, 'UTF-8') ?></h2>
                <p><?= htmlspecialchars(__('site.sse_mod_scenario_b'), ENT_QUOTES, 'UTF-8') ?></p>
            </section>
        </div>
    </section>

    <section class="site-sse-block">
        <p class="hi-kicker text-emerald-400/80"><?= htmlspecialchars(__('site.sse_modules_kicker'), ENT_QUOTES, 'UTF-8') ?></p>
        <h2 class="site-sse-block__title"><?= htmlspecialchars(__('site.sse_modules_title'), ENT_QUOTES, 'UTF-8') ?></h2>
        <p class="site-sse-block__lead"><?= htmlspecialchars(__('site.sse_modules_lead'), ENT_QUOTES, 'UTF-8') ?></p>
        <div class="site-sse-modules">
            <?php
            $modKeys = ['terminal', 'seek', 'digital', 'site', 'graph', 'evidence', 'mission', 'zeus'];
            foreach ($modKeys as $key):
            ?>
            <article class="site-sse-module">
                <h3><?= htmlspecialchars(__('site.sse_mod_' . $key . '_t'), ENT_QUOTES, 'UTF-8') ?></h3>
                <p><?= htmlspecialchars(__('site.sse_mod_' . $key . '_b'), ENT_QUOTES, 'UTF-8') ?></p>
            </article>
            <?php endforeach; ?>
        </div>
    </section>

    <section class="site-sse-block">
        <p class="hi-kicker text-emerald-400/80"><?= htmlspecialchars(__('site.sse_desk_kicker'), ENT_QUOTES, 'UTF-8') ?></p>
        <h2 class="site-sse-block__title"><?= htmlspecialchars(__('site.sse_desk_title'), ENT_QUOTES, 'UTF-8') ?></h2>
        <p class="site-sse-block__lead"><?= htmlspecialchars(__('site.sse_desk_lead'), ENT_QUOTES, 'UTF-8') ?></p>
        <div class="site-page__grid site-page__grid--2">
            <?php for ($i = 1; $i <= 4; $i++): ?>
            <section class="site-prose">
                <h2><?= htmlspecialchars(__('site.sse_desk_' . $i . '_t'), ENT_QUOTES, 'UTF-8') ?></h2>
                <p><?= htmlspecialchars(__('site.sse_desk_' . $i . '_b'), ENT_QUOTES, 'UTF-8') ?></p>
            </section>
            <?php endfor; ?>
        </div>
    </section>

    <section class="site-sse-block site-sse-block--rule">
        <h2 class="site-sse-block__title"><?= htmlspecialchars(__('site.sse_rule_t'), ENT_QUOTES, 'UTF-8') ?></h2>
        <p class="site-sse-block__lead"><?= htmlspecialchars(__('site.sse_rule_b'), ENT_QUOTES, 'UTF-8') ?></p>
    </section>

    <div class="site-page__cta">
        <a href="<?= htmlspecialchars($sseGateHref, ENT_QUOTES, 'UTF-8') ?>" class="hi-cta hi-cta-solid"><?= htmlspecialchars(__('site.sse_cta_portal'), ENT_QUOTES, 'UTF-8') ?></a>
        <a href="<?= htmlspecialchars($createHref, ENT_QUOTES, 'UTF-8') ?>" class="hi-cta hi-cta-ghost"><?= htmlspecialchars(__('site.sse_cta_create'), ENT_QUOTES, 'UTF-8') ?></a>
        <a href="<?= htmlspecialchars(url('contact'), ENT_QUOTES, 'UTF-8') ?>" class="hi-cta hi-cta-ghost"><?= htmlspecialchars(__('site.sse_cta_contact'), ENT_QUOTES, 'UTF-8') ?></a>
    </div>
</article>
