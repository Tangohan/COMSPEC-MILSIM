<?php
declare(strict_types=1);
// Espace de traduction d'une langue du site (/traduire-site/{code}) : liste des textes à gauche, éditeur du texte
// choisi à droite. Rendu et interactions : public/assets/js/site-translate.js
// (API GET/POST /traduire-site/{code}/phrases, POST /traduire-site/propositions/{id}).
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
$kbd = static fn (string ...$keys): string => implode('+', array_map(static fn (string $k): string => '<kbd>' . htmlspecialchars($k, ENT_QUOTES, 'UTF-8') . '</kbd>', $keys));
?>
<article class="ctb ctb--wide" id="sitetr"
    data-api="<?= $h(url('traduire-site/' . $lang['code'] . '/phrases')) ?>"
    data-decide="<?= $h(url('traduire-site/propositions')) ?>"
    data-csrf="<?= $h(Csrf::token()) ?>"
    data-logged="<?= $user !== null ? '1' : '0' ?>"
    data-review="<?= $canReview ? '1' : '0' ?>"
    data-lang="<?= $h($lang['code']) ?>"
    data-start="<?= $h($start) ?>"
    data-total="<?= $total ?>"
    data-i18n="<?= $h((string) json_encode($js, JSON_UNESCAPED_UNICODE)) ?>">

    <header class="tw-head">
        <div class="tw-head__main">
            <p class="ctb-kicker"><a class="ctb-link" href="<?= $h(url('traduire-site')) ?>"><?= $t('back') ?></a></p>
            <h1 class="ctb-title ctb-title--sm"><span translate="no"><?= $h($lang['name']) ?></span> <span class="ctb-pill ctb-pill--<?= $h($lang['status']) ?>"><?= $t('status_' . $lang['status']) ?></span></h1>
            <p class="ctb-sub"><?= $lang['code'] === 'en' ? $t('ws_lead_en') : $t('ws_lead') ?></p>
        </div>
        <div class="tw-progress" aria-label="<?= $t('progress', ['done' => $done, 'total' => $total, 'pct' => $pct]) ?>">
            <p class="tw-progress__pct"><b id="tw-pct"><?= $pct ?></b> %</p>
            <div class="tw-progress__bar" aria-hidden="true"><span id="tw-bar" style="width: <?= $pct ?>%"></span></div>
            <ul class="tw-progress__stats">
                <li><i class="tw-dot is-done"></i><span id="tw-done-label"><?= $t('ws_stat_done', ['n' => $done]) ?></span></li>
                <li><i class="tw-dot"></i><?= $t('ws_stat_todo', ['n' => max(0, $total - $done)]) ?></li>
                <?php if ($blank > 0): ?><li><?= $t('blank_left', ['n' => $blank]) ?></li><?php endif; ?>
                <?php if ($pending > 0): ?><li><i class="tw-dot is-sugg"></i><?= $t('ws_stat_pending', ['n' => $pending]) ?></li><?php endif; ?>
            </ul>
        </div>
    </header>

    <?php if ($user === null): ?>
    <p class="ctb-msg"><?= $t('ws_login') ?> <a class="ctb-link" href="<?= $h(url('login')) ?>"><?= htmlspecialchars(__('site.contrib_login'), ENT_QUOTES, 'UTF-8') ?></a></p>
    <?php elseif ($canReview): ?>
    <p class="ctb-msg is-ok"><?= $t('ws_reviewer') ?></p>
    <?php else: ?>
    <p class="ctb-msg"><?= $t('ws_suggester') ?> <a class="ctb-link" href="<?= $h(url('contribuer?role=translator&lang=' . $lang['code'])) ?>#demande"><?= $t('ws_become') ?></a></p>
    <?php endif; ?>

    <div class="tw" id="tw">
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
            <ol class="tw-items" id="sitetr-list" translate="no" aria-busy="true"></ol>
            <nav class="tw-pager" id="sitetr-pager" aria-label="<?= $t('pager') ?>">
                <button type="button" class="tw-pager__btn" data-page="-1" aria-label="<?= $t('prev') ?>">‹</button>
                <span id="sitetr-page" aria-live="polite"></span>
                <button type="button" class="tw-pager__btn" data-page="1" aria-label="<?= $t('next') ?>">›</button>
            </nav>
        </aside>

        <section class="tw-editor" id="tw-editor" aria-live="polite" translate="no">
            <div class="tw-empty" id="tw-empty">
                <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M4 5h9M8.5 3v2M6 5c.8 3.5 3 6 6 7.5M11 5c-.6 3-2.8 6-6.5 8"/><path d="m12 21 4.5-10L21 21M13.6 18h5.8"/></svg>
                <p><?= $t('js_pick') ?></p>
            </div>
        </section>

        <aside class="tw-help">
            <details class="tw-card" open>
                <summary><?= $t('tools_title') ?></summary>
                <p><?= $t('ws_tools') ?> <a class="ctb-link" href="<?= $h(url('traduire-atak')) ?>"><?= $t('ws_tools_cta') ?></a></p>
            </details>
            <details class="tw-card" open>
                <summary><?= $t('ws_rules_title') ?></summary>
                <ul>
                    <li><?= $t('ws_rule_tokens') ?></li>
                    <li><?= $t('rule_3') ?></li>
                    <li><?= $t('ws_rule_tone') ?></li>
                    <li><?= $t('ws_rule_skip') ?></li>
                </ul>
            </details>
            <details class="tw-card" open>
                <summary><?= $t('ws_shortcuts_title') ?></summary>
                <dl class="tw-keys">
                    <div><dt><?= $kbd('Ctrl', '↵') ?></dt><dd><?= $t('ws_shortcut_save') ?></dd></div>
                    <div><dt><?= $kbd('Alt', '↑') ?> <?= $kbd('Alt', '↓') ?></dt><dd><?= $t('ws_shortcut_nav') ?></dd></div>
                    <div><dt><?= $kbd('Ctrl', '⇧', 'E') ?></dt><dd><?= $t('ws_shortcut_en') ?></dd></div>
                </dl>
            </details>
        </aside>
    </div>
</article>
