<?php
declare(strict_types=1);
// Espace de traduction d'une langue du site (/traduire-site/{code}) : file à gauche, phrase au centre,
// contexte et outils à côté. Rendu : public/assets/js/site-translate.js.
use App\Core\Csrf;

$t = static fn (string $key, array $r = []): string => htmlspecialchars(__('site.sitetr_' . $key, $r), ENT_QUOTES, 'UTF-8');
$h = static fn (mixed $v): string => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
$lang = is_array($sitetrLang ?? null) ? $sitetrLang : ['code' => 'xx', 'name' => '?', 'status' => 'draft', 'done' => 0, 'total' => 0, 'pending' => 0];
$user = is_array($sitetrUser ?? null) ? $sitetrUser : null;
$canReview = (bool) ($sitetrCanReview ?? false);
$js = [];
foreach (require base_path('lang/fr/site.php') as $k => $_) {
    if (is_string($k) && str_starts_with($k, 'sitetr_js_')) {
        $js[substr($k, 10)] = __('site.' . $k);
    }
}
$start = in_array((string) ($sitetrStart ?? ''), ['all', 'todo', 'blank', 'done', 'suggested', 'mine'], true)
    ? (string) $sitetrStart
    : 'todo';
foreach (['blank', 'todo', 'all', 'done', 'suggested', 'mine'] as $f) {
    $js['filter_' . $f] = __('site.sitetr_filter_' . $f);
}
$total = max(0, (int) $lang['total']);
$done = (int) $lang['done'];
$pending = (int) $lang['pending'];
$blank = (int) ($lang['blank'] ?? 0);
$pct = $total > 0 ? (int) floor($done * 100 / $total) : 0;
$reviewing = $start === 'done';
$code = (string) $lang['code'];
$kbd = static fn (string ...$keys): string => implode('+', array_map(static fn (string $k): string => '<kbd>' . htmlspecialchars($k, ENT_QUOTES, 'UTF-8') . '</kbd>', $keys));
?>
<article class="ctb ctb--wide ctb--studio" id="sitetr"
    data-api="<?= $h(url('traduire-site/' . $code . '/phrases')) ?>"
    data-decide="<?= $h(url('traduire-site/propositions')) ?>"
    data-csrf="<?= $h(Csrf::token()) ?>"
    data-logged="<?= $user !== null ? '1' : '0' ?>"
    data-review="<?= $canReview ? '1' : '0' ?>"
    data-lang="<?= $h($code) ?>"
    data-start="<?= $h($start) ?>"
    data-total="<?= $total ?>"
    data-atak="<?= $h(url('traduire-atak')) ?>"
    data-atak-label="<?= $t('ws_tools_cta') ?>"
    data-i18n="<?= $h((string) json_encode($js, JSON_UNESCAPED_UNICODE)) ?>">

    <header class="ctb-hero">
        <p class="ctb-kicker"><a class="ctb-link" href="<?= $h(url('traduire-site')) ?>"><?= $t('back') ?></a></p>
        <h1 class="ctb-title" translate="no"><?= $h($lang['name']) ?></h1>
        <p class="ctb-lead"><?= $code === 'en' ? $t('ws_lead_en') : $t('ws_lead') ?></p>
        <ol class="ctb-steps">
            <?php for ($i = 1; $i <= 3; $i++): ?>
            <li><span><?= sprintf('%02d', $i) ?></span><?= $t('step_' . $i) ?></li>
            <?php endfor; ?>
        </ol>
        <nav class="sitetr-steps" aria-label="<?= $t('steps_label') ?>">
            <a class="sitetr-step" href="<?= $h(url('traduire-site')) ?>"><?= $t('step_1') ?></a>
            <a class="sitetr-step<?= $reviewing ? '' : ' is-active' ?>" id="sitetr-step-work" href="<?= $h(url('traduire-site/' . $code)) ?>"<?= $reviewing ? '' : ' aria-current="page"' ?>><?= $t('step_2') ?></a>
            <a class="sitetr-step<?= $reviewing ? ' is-active' : '' ?>" id="sitetr-step-review" href="<?= $h(url('traduire-site/' . $code . '?f=done')) ?>"<?= $reviewing ? ' aria-current="page"' : '' ?>><?= $t('step_3') ?></a>
        </nav>
    </header>

    <?php if ($user === null): ?>
    <p class="ctb-msg"><?= $t('ws_login') ?> <a class="ctb-link" href="<?= $h(url('login')) ?>"><?= htmlspecialchars(__('site.contrib_login'), ENT_QUOTES, 'UTF-8') ?></a></p>
    <?php elseif ($canReview): ?>
    <p class="ctb-msg is-ok"><?= $t('ws_reviewer') ?></p>
    <?php else: ?>
    <p class="ctb-msg"><?= $t('ws_suggester') ?> <a class="ctb-link" href="<?= $h(url('contribuer?role=translator&lang=' . $code)) ?>#demande"><?= $t('ws_become') ?></a></p>
    <?php endif; ?>

    <section class="sitetr-studio" aria-label="<?= $t('step_2') ?>">
        <header class="sitetr-bar">
            <div class="sitetr-lang">
                <span class="sitetr-lang__code" translate="no"><?= $h(strtoupper($code)) ?></span>
                <span class="sitetr-lang__txt">
                    <strong translate="no"><?= $h($lang['name']) ?></strong>
                    <small><?= $t('status_' . $lang['status']) ?></small>
                </span>
            </div>
            <div class="sitetr-progress" aria-label="<?= $t('progress', ['done' => $done, 'total' => $total, 'pct' => $pct]) ?>">
                <div class="sitetr-progress__bar" aria-hidden="true"><i id="tw-bar" style="width: <?= $pct ?>%"></i></div>
                <span><b id="tw-pct"><?= $pct ?></b> %</span>
            </div>
            <ul class="sitetr-stats">
                <li><i class="tw-dot is-done"></i><span id="tw-done-label"><?= $t('ws_stat_done', ['n' => $done]) ?></span></li>
                <li><i class="tw-dot"></i><?= $t('ws_stat_todo', ['n' => max(0, $total - $done)]) ?></li>
                <?php if ($blank > 0): ?><li><?= $t('blank_left', ['n' => $blank]) ?></li><?php endif; ?>
                <?php if ($pending > 0): ?><li><i class="tw-dot is-sugg"></i><?= $t('ws_stat_pending', ['n' => $pending]) ?></li><?php endif; ?>
            </ul>
        </header>

        <div class="sitetr-body">
            <aside class="tw-list" aria-label="<?= $t('filter') ?>">
                <form class="tw-list__bar" id="sitetr-bar" role="search">
                    <label class="tw-search"><span class="sr-only"><?= $t('search') ?></span>
                        <svg viewBox="0 0 24 24" aria-hidden="true"><circle cx="11" cy="11" r="6.5"/><path d="m16 16 4.5 4.5"/></svg>
                        <input type="search" name="q" placeholder="<?= $t('search') ?>" autocomplete="off"></label>
                    <div class="tw-tabs" role="tablist" id="tw-tabs">
                        <?php foreach (['blank', 'todo', 'suggested', 'done', 'mine', 'all'] as $f): ?>
                        <button type="button" role="tab" class="tw-tab<?= $f === $start ? ' is-active' : '' ?>" data-f="<?= $f ?>" aria-selected="<?= $f === $start ? 'true' : 'false' ?>"<?= $f === 'mine' && $user === null ? ' hidden' : '' ?>><?= $t('filter_' . $f) ?> <b data-count="<?= $f ?>"></b></button>
                        <?php endforeach; ?>
                    </div>
                </form>
                <ol class="tw-items" id="sitetr-list" translate="no" aria-busy="true">
                    <li class="tw-items__empty"><?= $t('js_loading') ?></li>
                </ol>
                <nav class="tw-pager" id="sitetr-pager" aria-label="<?= $t('pager') ?>">
                    <button type="button" class="tw-pager__btn" data-page="-1" aria-label="<?= $t('prev') ?>">‹</button>
                    <span id="sitetr-page" aria-live="polite"></span>
                    <button type="button" class="tw-pager__btn" data-page="1" aria-label="<?= $t('next') ?>">›</button>
                </nav>
            </aside>

            <div class="sitetr-work">
                <section class="tw-editor" id="tw-editor" aria-live="polite" translate="no">
                    <div class="tw-empty" id="tw-empty">
                        <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M4 5h9M8.5 3v2M6 5c.8 3.5 3 6 6 7.5M11 5c-.6 3-2.8 6-6.5 8"/><path d="m12 21 4.5-10L21 21M13.6 18h5.8"/></svg>
                        <p><?= $t('js_pick') ?></p>
                    </div>
                </section>

                <aside class="tw-help">
                    <section class="tw-card">
                        <h2><?= $t('tools_title') ?></h2>
                        <div id="tw-tools">
                            <p class="tw-tool__idle"><?= $t('js_tools_idle') ?></p>
                        </div>
                    </section>
                    <section class="tw-card">
                        <h2><?= $t('ws_rules_title') ?></h2>
                        <ul>
                            <li><?= $t('ws_rule_tokens') ?></li>
                            <li><?= $t('rule_3') ?></li>
                            <li><?= $t('ws_rule_tone') ?></li>
                            <li><?= $t('ws_rule_skip') ?></li>
                        </ul>
                    </section>
                    <section class="tw-card">
                        <h2><?= $t('ws_shortcuts_title') ?></h2>
                        <dl class="tw-keys">
                            <div><dt><?= $kbd('Ctrl', '↵') ?></dt><dd><?= $t('ws_shortcut_save') ?></dd></div>
                            <div><dt><?= $kbd('Alt', '↑') ?> <?= $kbd('Alt', '↓') ?></dt><dd><?= $t('ws_shortcut_nav') ?></dd></div>
                            <div><dt><?= $kbd('Ctrl', '⇧', 'E') ?></dt><dd><?= $t('ws_shortcut_en') ?></dd></div>
                        </dl>
                    </section>
                </aside>
            </div>
        </div>
    </section>
</article>
