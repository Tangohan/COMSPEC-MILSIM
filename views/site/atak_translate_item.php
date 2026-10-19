<?php
declare(strict_types=1);
// Fiche d'un pack du Workshop des langues (/traduire-atak/workshop/{id}) : abonnement Steam, téléchargement direct,
// contrôle du paquet face au mod actuel, journal des versions. Données : AtakTranslationWorkshopController::item.
$e = static fn (string $s): string => htmlspecialchars($s, ENT_QUOTES, 'UTF-8');
$w = static fn (string $key, array $r = []): string => htmlspecialchars(__('site.atakw_' . $key, $r), ENT_QUOTES, 'UTF-8');
$c = isset($card) && is_array($card) ? $card : [];
$releases = isset($releases) && is_array($releases) ? $releases : [];
$contributors = isset($contributors) && is_array($contributors) ? $contributors : [];
$currentAtak = isset($currentAtak) && is_string($currentAtak) ? $currentAtak : '';
$en = str_starts_with((string) html_lang(), 'en');
$fmt = static fn (int $n): string => number_format($n, 0, ',', $en ? ',' : ' ');
$date = static function (string $d) use ($en): string {
    $t = strtotime($d);

    return $t === false ? '' : date($en ? 'M j, Y' : 'd/m/Y', $t);
};
$a = $c['audit'];
$st = $c['status'];
$total = max(1, (int) $a['total']);
$seg = static fn (int $n): string => number_format($n * 100 / $total, 2, '.', '');
$steam = \App\Support\AtakTranslationWorkshop::steamUrl((string) $c['workshop_id']);
$steamClient = \App\Support\AtakTranslationWorkshop::steamClientUrl((string) $c['workshop_id']);
$self = url('traduire-atak/workshop/' . $c['id']);
$tabActive = 'workshop';
?>
<article class="atakn atakt atakw atakw-item" id="atakw">
    <header class="atakn-hero atakt-hero atakw-item__hero">
        <?php require __DIR__ . '/atak_translate_tabs.php'; ?>
        <p class="atakw-back"><a href="<?= $e(url('traduire-atak/workshop')) ?>">‹ <?= $w('back') ?></a></p>
        <?php if (!empty($hidden)): ?>
        <p class="atakw-flag"><?= !empty($blocked) ? $w('hidden_blocked') : $w('hidden_unlisted') ?></p>
        <?php endif; ?>
        <div class="atakw-head">
            <div class="atakw-card__tile atakw-head__tile" aria-hidden="true">
                <span class="atakw-tile__brand">COMSPEC ATAK</span>
                <span class="atakw-tile__name"><?= $e((string) $c['name']) ?></span>
                <span class="atakw-tile__code"><?= $e(strtoupper((string) $c['code'])) ?></span>
                <span class="atakw-tile__v">v<?= (int) $c['version'] ?></span>
            </div>
            <div class="atakw-head__txt">
                <p class="hi-kicker atakn-kicker"><?= $w('item_kicker') ?></p>
                <h1 class="atakn-title atakw-item__title"><?= $e((string) $c['name']) ?></h1>
                <p class="atakw-card__by"><?= $c['author'] !== '' ? $w('by', ['name' => (string) $c['author']]) : $w('by_community') ?></p>
                <span class="atakw-status atakw-status--<?= $e($st['key']) ?>"><?= $w('status_' . $st['key'], ['count' => $fmt((int) ($st['key'] === 'recheck' ? $st['errors'] : $st['new']))]) ?></span>
                <dl class="atakw-facts">
                    <div><dt><?= $w('fact_version') ?></dt><dd>v<?= (int) $c['version'] ?></dd></div>
                    <div><dt><?= $w('fact_built') ?></dt><dd>ATAK <?= $e((string) $c['atak_version']) ?></dd></div>
                    <div><dt><?= $w('fact_pct') ?></dt><dd><?= (int) $a['pct'] ?> %</dd></div>
                    <div><dt><?= $w('fact_downloads') ?></dt><dd><?= $fmt((int) $c['downloads']) ?></dd></div>
                    <div><dt><?= $w('fact_updated') ?></dt><dd><?= $e($date((string) $c['released_at'])) ?></dd></div>
                    <?php if ($c['game'] !== ''): ?><div><dt><?= $w('fact_game') ?></dt><dd><?= $e((string) $c['game']) ?></dd></div><?php endif; ?>
                </dl>
            </div>
            <aside class="atakw-get" aria-label="<?= $w('get_label') ?>">
                <?php if ($steam !== ''): ?>
                <a class="atakw-btn atakw-btn--steam atakw-btn--big" href="<?= $e($steam) ?>" target="_blank" rel="noopener">
                    <svg viewBox="0 0 24 24" aria-hidden="true" class="tw-ico"><path d="M12 5v14M5 12h14"/></svg><?= $w('subscribe') ?></a>
                <p class="atakw-get__note"><?= $w('subscribe_note') ?> <a href="<?= $e($steamClient) ?>"><?= $w('open_in_steam') ?></a></p>
                <?php else: ?>
                <p class="atakw-get__note atakw-get__note--nosteam"><?= $w('no_steam') ?></p>
                <?php endif; ?>
                <a class="atakw-btn <?= $steam === '' ? 'atakw-btn--solid atakw-btn--big' : '' ?>" href="<?= $e($self . '/telecharger') ?>" rel="nofollow" download>
                    <svg viewBox="0 0 24 24" aria-hidden="true" class="tw-ico"><path d="M12 3v12M7 10l5 5 5-5M4 20h16"/></svg><?= $w('download_zip', ['ver' => (int) $c['version']]) ?></a>
                <p class="atakw-get__note"><?= $w('download_note', ['ver' => $currentAtak]) ?></p>
            </aside>
        </div>
    </header>

    <div class="atakw-cols">
        <div class="atakw-main">
            <?php if ($c['description'] !== ''): ?>
            <section class="atakt-panel atakw-desc">
                <p><?= nl2br($e((string) $c['description'])) ?></p>
                <?php if ($c['note'] !== ''): ?><p class="atakw-note"><b><?= $w('note_label', ['ver' => (int) $c['version']]) ?></b> <?= $e((string) $c['note']) ?></p><?php endif; ?>
            </section>
            <?php endif; ?>

            <section class="atakt-panel atakw-audit" aria-labelledby="atakw-audit-h">
                <h2 class="atakt-h2" id="atakw-audit-h"><span>01</span><?= $w('audit_title') ?></h2>
                <p class="atakt-help"><?= $w('audit_lead', ['ver' => $currentAtak, 'count' => $fmt((int) $a['total'])]) ?></p>
                <div class="atakw-bars" role="img" aria-label="<?= $w('pct_label', ['pct' => (int) $a['pct']]) ?>">
                    <i class="is-ok" style="width: <?= $seg((int) $a['translated']) ?>%"></i><i class="is-bad" style="width: <?= $seg((int) $a['errors']) ?>%"></i>
                </div>
                <ul class="atakw-checks">
                    <li class="is-ok"><b><?= $fmt((int) $a['translated']) ?></b> <?= $w('audit_ok') ?></li>
                    <li class="<?= (int) $st['new'] > 0 ? 'is-warn' : 'is-ok' ?>"><b><?= $fmt((int) $st['new']) ?></b> <?= $w('audit_new', ['ver' => (int) $c['version']]) ?></li>
                    <li class="<?= (int) $a['errors'] > 0 ? 'is-bad' : 'is-ok' ?>"><b><?= $fmt((int) $a['errors']) ?></b> <?= $w('audit_errors') ?></li>
                    <li class="is-muted"><b><?= $fmt((int) $a['missing']) ?></b> <?= $w('audit_missing') ?></li>
                    <li class="is-muted"><b><?= $fmt((int) $a['warnings']) ?></b> <?= $w('audit_warnings') ?></li>
                    <?php if ((int) $a['obsolete'] > 0): ?><li class="is-muted"><b><?= $fmt((int) $a['obsolete']) ?></b> <?= $w('audit_obsolete') ?></li><?php endif; ?>
                    <?php if ($st['key'] === 'official'): ?><li class="is-warn"><?= $w('audit_official') ?></li><?php endif; ?>
                </ul>
                <?php if (!empty($a['samples'])): ?>
                <details class="atakt-rules">
                    <summary><?= $w('audit_samples') ?></summary>
                    <ul class="atakw-samples">
                        <?php foreach ($a['samples'] as $s): ?>
                        <li><code><?= $e((string) $s['fr']) ?></code><span><?= $e((string) $s['text']) ?></span><small><?= $w('issue_' . ((string) $s['issue'] === 'err_newline' ? 'newline' : 'placeholders'), ['expected' => (string) ($s['vars']['expected'] ?? ''), 'found' => (string) ($s['vars']['found'] ?? '')]) ?></small></li>
                        <?php endforeach; ?>
                    </ul>
                </details>
                <?php endif; ?>
            </section>

            <section class="atakt-panel" aria-labelledby="atakw-install-h">
                <h2 class="atakt-h2" id="atakw-install-h"><span>02</span><?= $w('install_title') ?></h2>
                <ol class="site-sse-steps atakt-steps">
                    <?php for ($i = 1; $i <= 3; $i++): ?>
                    <li class="site-sse-step">
                        <span class="site-sse-step__n"><?= sprintf('%02d', $i) ?></span>
                        <div><h3><?= $w('install_' . $i . '_t') ?></h3><p><?= $w('install_' . $i . '_b') ?></p></div>
                    </li>
                    <?php endfor; ?>
                </ol>
            </section>
        </div>

        <aside class="atakw-side">
            <section class="atakt-panel">
                <h2 class="atakw-side__h"><?= $w('changelog_title') ?></h2>
                <ol class="atakw-log">
                    <?php foreach ($releases as $r): ?>
                    <li>
                        <b>v<?= (int) $r['version'] ?></b>
                        <span><?= $e($date((string) $r['created_at'])) ?> · ATAK <?= $e((string) $r['atak_version']) ?> · <?= $w('log_strings', ['count' => $fmt((int) $r['translated'])]) ?></span>
                        <?php if ((string) $r['note'] !== ''): ?><p><?= $e((string) $r['note']) ?></p><?php endif; ?>
                    </li>
                    <?php endforeach; ?>
                </ol>
            </section>
            <?php if ($contributors !== []): ?>
            <section class="atakt-panel">
                <h2 class="atakw-side__h"><?= $w('people_title') ?></h2>
                <p class="atakw-people"><?php foreach (array_slice($contributors, 0, 24) as $p): ?><span><?= $e((string) $p['name']) ?></span><?php endforeach; ?></p>
            </section>
            <?php endif; ?>
            <section class="atakt-panel">
                <h2 class="atakw-side__h"><?= $w('help_title') ?></h2>
                <p class="atakt-help"><?= $w('help_lead') ?></p>
                <a class="hi-cta hi-cta-ghost" href="<?= $e(url('traduire-atak')) ?>"><?= $w('help_cta') ?></a>
            </section>
            <?php if (!empty($isAdmin)): ?>
            <form class="atakt-panel atakw-mod" method="post" action="<?= $e($self . '/moderer') ?>">
                <input type="hidden" name="_csrf_token" value="<?= $e(\App\Core\Csrf::token()) ?>">
                <h2 class="atakw-side__h"><?= $w('mod_title') ?></h2>
                <p class="atakt-help"><?= $w('mod_lead') ?></p>
                <?php if (!empty($blocked)): ?>
                <button type="submit" name="action" value="unblock" class="atakw-btn"><?= $w('mod_unblock') ?></button>
                <?php else: ?>
                <button type="submit" name="action" value="block" class="atakw-btn atakw-btn--danger"><?= $w('mod_block') ?></button>
                <?php endif; ?>
            </form>
            <?php endif; ?>
        </aside>
    </div>
</article>
