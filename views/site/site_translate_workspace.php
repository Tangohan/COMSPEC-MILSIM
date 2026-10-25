<?php
declare(strict_types=1);
// Espace de traduction d'une langue du site (/traduire-site/{code}) : la liste est rendue par
// public/assets/js/site-translate.js (API GET/POST /traduire-site/{code}/phrases, /traduire-site/propositions/{id}).
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
$pct = $lang['total'] > 0 ? (int) floor($lang['done'] * 100 / $lang['total']) : 0;
?>
<article class="ctb ctb--wide" id="sitetr"
    data-api="<?= $h(url('traduire-site/' . $lang['code'] . '/phrases')) ?>"
    data-decide="<?= $h(url('traduire-site/propositions')) ?>"
    data-csrf="<?= $h(Csrf::token()) ?>"
    data-logged="<?= $user !== null ? '1' : '0' ?>"
    data-review="<?= $canReview ? '1' : '0' ?>"
    data-lang="<?= $h($lang['code']) ?>"
    data-i18n="<?= $h((string) json_encode($js, JSON_UNESCAPED_UNICODE)) ?>">
    <header class="ctb-hero ctb-hero--compact">
        <p class="ctb-kicker"><a class="ctb-link" href="<?= $h(url('traduire-site')) ?>"><?= $t('back') ?></a></p>
        <h1 class="ctb-title ctb-title--sm"><span translate="no"><?= $h($lang['name']) ?></span> <span class="ctb-pill ctb-pill--<?= $h($lang['status']) ?>"><?= $t('status_' . $lang['status']) ?></span></h1>
        <p class="ctb-lead"><?= $lang['code'] === 'en' ? $t('ws_lead_en') : $t('ws_lead') ?></p>
        <p class="ctb-sub"><?= $t('progress', ['done' => $lang['done'], 'total' => $lang['total'], 'pct' => $pct]) ?></p>
        <?php if ($user === null): ?>
        <p class="ctb-msg"><?= $t('ws_login') ?> <a class="ctb-link" href="<?= $h(url('login')) ?>"><?= htmlspecialchars(__('site.contrib_login'), ENT_QUOTES, 'UTF-8') ?></a></p>
        <?php elseif ($canReview): ?>
        <p class="ctb-msg is-ok"><?= $t('ws_reviewer') ?></p>
        <?php else: ?>
        <p class="ctb-msg"><?= $t('ws_suggester') ?> <a class="ctb-link" href="<?= $h(url('contribuer?role=translator&lang=' . $lang['code'])) ?>#demande"><?= $t('ws_become') ?></a></p>
        <?php endif; ?>
    </header>

    <div class="ctb-ws">
        <form class="ctb-ws__bar" id="sitetr-bar" role="search">
            <label class="ctb-ws__search"><span class="sr-only"><?= $t('search') ?></span>
                <input type="search" name="q" placeholder="<?= $t('search') ?>" autocomplete="off"></label>
            <label><span class="sr-only"><?= $t('filter') ?></span>
                <select name="f">
                    <?php foreach (['todo', 'all', 'done', 'suggested', 'mine'] as $f): ?>
                    <option value="<?= $f ?>"><?= $t('filter_' . $f) ?></option>
                    <?php endforeach; ?>
                </select></label>
            <span class="ctb-ws__count" id="sitetr-count" aria-live="polite"></span>
        </form>
        <ol class="ctb-ws__list" id="sitetr-list" translate="no" aria-busy="true"></ol>
        <nav class="ctb-ws__pager" id="sitetr-pager" aria-label="<?= $t('pager') ?>">
            <button type="button" class="ctb-btn" data-page="-1"><?= $t('prev') ?></button>
            <span id="sitetr-page"></span>
            <button type="button" class="ctb-btn" data-page="1"><?= $t('next') ?></button>
        </nav>
    </div>
</article>
