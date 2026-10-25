<?php
declare(strict_types=1);
// Traduction du site par la communauté (/traduire-site) : App\Controllers\Web\SiteTranslationController::hub.
$t = static fn (string $key, array $r = []): string => htmlspecialchars(__('site.sitetr_' . $key, $r), ENT_QUOTES, 'UTF-8');
$h = static fn (mixed $v): string => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
$ready = (bool) ($sitetrReady ?? false);
$langs = is_array($sitetrLanguages ?? null) ? $sitetrLanguages : [];
$leaders = array_values(array_filter(
    is_array($sitetrLeaders ?? null) ? $sitetrLeaders : [],
    static fn (array $r): bool => !empty($r['public_credit'])
));
$user = is_array($sitetrUser ?? null) ? $sitetrUser : null;
$fmt = class_exists(\NumberFormatter::class) ? new \NumberFormatter(html_lang(), \NumberFormatter::DECIMAL) : null;
$num = static fn (int $n): string => $fmt !== null ? (string) $fmt->format($n) : (string) $n;
$blank = (int) ($langs[0]['blank'] ?? 0);
$total = (int) ($langs[0]['total'] ?? 0);
$honors = ['honor_1', 'honor_2', 'honor_3'];
?>
<article class="ctb">
    <header class="ctb-hero">
        <p class="ctb-kicker"><?= $t('kicker') ?></p>
        <h1 class="ctb-title"><?= $t('title') ?></h1>
        <p class="ctb-lead"><?= $t('lead') ?></p>
        <ol class="ctb-steps">
            <?php for ($i = 1; $i <= 3; $i++): ?>
            <li><span><?= sprintf('%02d', $i) ?></span><?= $t('step_' . $i) ?></li>
            <?php endfor; ?>
        </ol>
    </header>

    <section class="ctb-section" aria-labelledby="sitetr-tools-h">
        <h2 id="sitetr-tools-h" class="ctb-h2"><?= $t('tools_title') ?></h2>
        <ul class="ctb-tools">
            <li>
                <a class="ctb-tool" href="<?= $h(url('traduire-site/en?f=blank')) ?>">
                    <span class="ctb-tool__k"><?= $t('tool_site_k') ?></span>
                    <strong><?= $t('tool_blank') ?></strong>
                    <span><?= $t('tool_blank_meta', ['n' => $num($blank), 'total' => $num($total)]) ?></span>
                </a>
            </li>
            <li>
                <a class="ctb-tool" href="<?= $h(url('traduire-atak')) ?>">
                    <span class="ctb-tool__k"><?= $t('tool_game_k') ?></span>
                    <strong><?= $t('tool_game') ?></strong>
                    <span><?= $t('tool_game_meta') ?></span>
                </a>
            </li>
            <li>
                <a class="ctb-tool" href="<?= $h(url('contribuer?role=translator')) ?>#demande">
                    <span class="ctb-tool__k"><?= $t('tool_role_k') ?></span>
                    <strong><?= $t('tool_role') ?></strong>
                    <span><?= $t('tool_role_meta') ?></span>
                </a>
            </li>
        </ul>
    </section>

    <section class="ctb-section" aria-labelledby="sitetr-langs-h">
        <h2 id="sitetr-langs-h" class="ctb-h2"><?= $t('langs_title') ?></h2>
        <?php if (!$ready): ?>
        <p class="ctb-sub"><?= $t('not_ready') ?></p>
        <?php else: ?>
        <ul class="ctb-langs">
            <?php foreach ($langs as $l): ?>
            <?php $pct = $l['total'] > 0 ? (int) floor($l['done'] * 100 / $l['total']) : 0; ?>
            <li class="ctb-lang">
                <a href="<?= $h(url('traduire-site/' . $l['code'])) ?>" class="ctb-lang__link">
                    <span class="ctb-lang__code" translate="no"><?= $h(strtoupper($l['code'])) ?></span>
                    <span class="ctb-lang__name" translate="no"><?= $h($l['name']) ?></span>
                    <span class="ctb-pill ctb-pill--<?= $h($l['status']) ?>"><?= $t('status_' . $l['status']) ?></span>
                    <span class="ctb-bar" aria-hidden="true"><span style="width: <?= $pct ?>%"></span></span>
                    <span class="ctb-lang__meta"><?= $t('progress', ['done' => $num($l['done']), 'total' => $num($l['total']), 'pct' => $pct]) ?><?php if ($l['pending'] > 0): ?> · <?= $t('pending', ['n' => $num($l['pending'])]) ?><?php endif; ?><?php if ($l['code'] === 'en' && ($l['blank'] ?? 0) > 0): ?> · <?= $t('blank_left', ['n' => $num((int) $l['blank'])]) ?><?php endif; ?></span>
                </a>
            </li>
            <?php endforeach; ?>
        </ul>
        <p class="ctb-sub"><?= $t('new_language') ?> <a class="ctb-link" href="<?= $h(url('contribuer?role=translator')) ?>#demande"><?= $t('new_language_cta') ?></a></p>
        <?php endif; ?>
    </section>

    <section class="ctb-section ctb-howto" aria-labelledby="sitetr-how-h">
        <h2 id="sitetr-how-h" class="ctb-h2"><?= $t('how_title') ?></h2>
        <ul class="ctb-rules">
            <?php for ($i = 1; $i <= 5; $i++): ?>
            <li><?= $t('rule_' . $i) ?></li>
            <?php endfor; ?>
        </ul>
        <?php if ($user === null): ?>
        <div class="ctb-actions">
            <a class="ctb-btn ctb-btn--primary" href="<?= $h(url('login')) ?>"><?= htmlspecialchars(__('site.contrib_login'), ENT_QUOTES, 'UTF-8') ?></a>
            <a class="ctb-btn" href="<?= $h(url('register')) ?>"><?= htmlspecialchars(__('site.contrib_register'), ENT_QUOTES, 'UTF-8') ?></a>
        </div>
        <?php endif; ?>
        <p class="ctb-sub"><?= $t('mod_hint') ?> <a class="ctb-link" href="<?= $h(url('traduire-atak')) ?>"><?= $t('mod_hint_cta') ?></a></p>
    </section>

    <?php if ($leaders !== []): ?>
    <section class="ctb-section" aria-labelledby="sitetr-leaders-h">
        <h2 id="sitetr-leaders-h" class="ctb-h2"><?= $t('leaders_title') ?></h2>
        <p class="ctb-sub"><?= $t('leaders_lead') ?></p>
        <ol class="ctb-leaders">
            <?php foreach ($leaders as $i => $leader): ?>
            <?php $share = $total > 0 ? (int) floor((int) $leader['validated'] * 100 / $total) : 0; ?>
            <li class="ctb-leader<?= $i < 3 ? ' is-top' : '' ?>">
                <span class="ctb-leader__rank"><?= $i + 1 ?></span>
                <span class="ctb-leader__name" translate="no"><?= $h($leader['name']) ?></span>
                <?php if ($i < 3): ?><span class="ctb-pill ctb-pill--published"><?= $t($honors[$i]) ?></span><?php endif; ?>
                <span class="ctb-leader__meta"><?= $t('leaders_meta', ['n' => $num((int) $leader['validated']), 'pct' => $share]) ?></span>
            </li>
            <?php endforeach; ?>
        </ol>
    </section>
    <?php endif; ?>
</article>
