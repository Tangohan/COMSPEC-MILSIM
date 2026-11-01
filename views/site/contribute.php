<?php
declare(strict_types=1);
// Contribuer (/contribuer) : App\Controllers\Web\ContributorController, contenu public App\Services\Community\ContributorShowcase.
// Même famille que la page de dons : composants « don-* » de public/assets/css/site-support.css, complétés par
// public/assets/css/site-contribute-page.css (« ctr-* » : mur, rôles, formulaire). Animations : public/assets/js/site-contribute.js.
// Textes : lang/*/site.php (contrib_*). Données publiques uniquement : pseudo public ou indicatif, rôles cités,
// mur des donateurs, auteurs des packs publiés ; jamais d'e-mail, de nom réel ni de montant.
use App\Controllers\Web\ContributorController;
use App\Core\Csrf;
use App\Core\Session;

$t = static fn (string $key, array $r = []): string => htmlspecialchars(__('site.contrib_' . $key, $r), ENT_QUOTES, 'UTF-8');
$h = static fn (mixed $v): string => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
$plural = static fn (string $key, int $n): string => $t($key . ($n > 1 ? '_many' : '_one'), ['n' => number_format($n, 0, ',', ' ')]);
$user = is_array($contribUser ?? null) ? $contribUser : null;
$ready = (bool) ($contribReady ?? false);
$mine = is_array($contribMine ?? null) ? $contribMine : [];
$show = is_array($contribShowcase ?? null) ? $contribShowcase : [];
$people = is_array($show['people'] ?? null) ? $show['people'] : [];
$newcomers = is_array($show['newcomers'] ?? null) ? $show['newcomers'] : [];
$activeList = is_array($show['active'] ?? null) ? $show['active'] : [];
$donors = is_array($show['donors'] ?? null) ? $show['donors'] : [];
$packs = is_array($show['packs'] ?? null) ? $show['packs'] : [];
$stats = is_array($show['stats'] ?? null) ? $show['stats'] : [];
$roleCounts = is_array($show['roles'] ?? null) ? $show['roles'] : [];
$want = (string) ($contribWant ?? '');
$prefLang = (string) ($contribLang ?? '');
$publicName = (string) ($contribPublicName ?? '');
$callsign = (string) ($contribCallsign ?? '');
$icons = [
    'translator' => '<path d="M4 5h9M8.5 3v2M6 5c0 4 3 7 6.5 8M11 5c-.6 3.4-3.2 6.6-7 8"/><path d="M13 20l4-9 4 9M14.5 17h5"/>',
    'tester' => '<path d="M9 3h6M10 3v6l-5.5 9.5A1.7 1.7 0 0 0 6 21h12a1.7 1.7 0 0 0 1.5-2.5L14 9V3"/><path d="M7.5 15h9"/>',
    'content' => '<rect x="3" y="5" width="18" height="14" rx="2"/><path d="M10 9.5v5l4.5-2.5z"/>',
    'developer' => '<path d="M8 8l-4 4 4 4M16 8l4 4-4 4M13.5 5l-3 14"/>',
    'support' => '<path d="M4 13a8 8 0 0 1 16 0v4a2 2 0 0 1-2 2h-1v-6h3M4 13v4a2 2 0 0 0 2 2h1v-6H4"/>',
];
$heart = '<path d="M12 20s-7-4.4-7-10a4 4 0 0 1 7-2.6A4 4 0 0 1 19 10c0 5.6-7 10-7 10Z"/>';
$howIcons = [
    'test' => $icons['tester'],
    'translate' => $icons['translator'],
    'bug' => '<path d="M8 8a4 4 0 0 1 8 0v1H8z"/><rect x="7" y="9" width="10" height="10" rx="5"/><path d="M12 12v7M3 13h4M17 13h4M4 7l3 2M20 7l-3 2M4 19l3-2M20 19l-3-2"/>',
    'idea' => '<path d="M9 18h6M10 21h4"/><path d="M12 3a6 6 0 0 0-3.6 10.8c.7.6 1.1 1.3 1.1 2.2h5c0-.9.4-1.6 1.1-2.2A6 6 0 0 0 12 3z"/>',
    'support' => $heart,
];
$roleLabel = static function (string $role): string {
    $label = __('site.contrib_role_' . $role);

    return $label === 'site.contrib_role_' . $role ? '' : $label;
};
$roleLine = static function (array $roles) use ($roleLabel): string {
    return implode(', ', array_filter(array_map(static fn ($r): string => $roleLabel((string) $r), $roles)));
};
$mainRole = static function (array $p): string {
    $r = (string) (($p['roles'] ?? [])[0] ?? 'translator');

    return preg_replace('/[^a-z]/', '', $r) ?: 'translator';
};
$avatar = static function (array $p) use ($h, $mainRole): string {
    return '<span class="ctr-avatar ctr-avatar--' . $mainRole($p) . '" aria-hidden="true" translate="no">' . $h($p['monogram'] ?? '?') . '</span>';
};
$monthYear = static function (?string $date): string {
    $ts = $date !== null && $date !== '' ? strtotime($date) : false;

    return $ts !== false ? date('m/Y', $ts) : '';
};
$figures = [];
foreach (['contributors', 'texts', 'languages', 'donors', 'communities'] as $k) {
    $n = (int) ($stats[$k] ?? 0);
    if ($n > 0) {
        $figures[] = ['n' => $n, 'label' => $plural('stat_' . $k, $n)];
    }
}
$orbit = array_slice($people, 0, 6);
$donorGroups = [];
foreach ($donors as $d) {
    $tier = in_array($d['tier'] ?? '', ['soutien', 'allie', 'pilier'], true) ? (string) $d['tier'] : 'soutien';
    $donorGroups[$tier][] = $d;
}
$ways = [
    ['test', [[url('contribuer?role=tester') . '#demande', 'how_test_go']]],
    ['translate', [[url('traduire-site'), 'how_translate_go'], [url('traduire-atak'), 'how_translate_mod']]],
    ['bug', [[url('contact') . '?sujet=' . rawurlencode(__('site.contrib_how_bug_subject')), 'how_bug_go']]],
    ['idea', [[url('contact') . '?sujet=' . rawurlencode(__('site.contrib_how_idea_subject')), 'how_idea_go']]],
    ['support', [[url('soutenir-atak'), 'how_support_go']]],
];
$statusLabel = static fn (string $s): string => htmlspecialchars(__('site.contrib_status_' . $s), ENT_QUOTES, 'UTF-8');
$flashOk = Session::getFlash('success');
$flashErr = Session::getFlash('error');
?>
<div class="don ctr" data-ctr>
    <script>document.documentElement.classList.add('don-js');</script>

    <header class="don-hero">
        <div class="don-hero__grid" aria-hidden="true"></div>
        <div class="don-hero__glow" aria-hidden="true"></div>
        <div class="don-wrap don-hero__inner">
            <div class="don-hero__copy">
                <p class="don-kicker don-rise"><span class="don-live" aria-hidden="true"></span><?= $t('hero_kicker') ?></p>
                <h1 class="don-title don-rise" style="--d:1"><?= $t('hero_title_a') ?> <span class="don-title__accent"><?= $t('hero_title_b') ?></span></h1>
                <p class="don-lead don-rise" style="--d:2"><?= $t('hero_lead') ?></p>
                <div class="don-hero__cta don-rise" style="--d:3">
                    <a href="#demande" class="don-btn don-btn--solid"><?= $t('hero_join') ?></a>
                    <a href="#mur" class="don-btn don-btn--ghost"><?= $t('hero_wall') ?></a>
                </div>
            </div>
            <figure class="ctr-orbit don-rise" style="--d:2" aria-hidden="true">
                <span class="don-ring don-ring--1"></span>
                <span class="don-ring don-ring--2"></span>
                <span class="don-ring don-ring--3"></span>
                <span class="ctr-orbit__core"><svg class="don-ico" viewBox="0 0 24 24"><?= $heart ?></svg></span>
                <?php foreach ($orbit as $i => $p): ?>
                <span class="ctr-orbit__slot is-<?= $i + 1 ?>"><?= $avatar($p) ?><span class="ctr-orbit__name" translate="no"><?= $h($p['name']) ?></span></span>
                <?php endforeach; ?>
                <?php if ($people !== []): ?>
                <span class="don-chip don-chip--a"><span class="don-chip__dot"></span><?= $t('hero_chip_count', ['n' => max(count($people), (int) ($stats['contributors'] ?? 0))]) ?></span>
                <?php endif; ?>
                <span class="don-chip don-chip--c"><svg class="don-ico" viewBox="0 0 24 24"><?= $heart ?></svg><?= $t('hero_chip_join') ?></span>
            </figure>
        </div>
    </header>

    <?php if ((is_string($flashOk) && $flashOk !== '') || (is_string($flashErr) && $flashErr !== '')): ?>
    <div class="don-wrap">
        <?php if (is_string($flashOk) && $flashOk !== ''): ?><p class="ctr-msg is-ok" role="status"><?= $h($flashOk) ?></p><?php endif; ?>
        <?php if (is_string($flashErr) && $flashErr !== ''): ?><p class="ctr-msg is-bad" role="alert"><?= $h($flashErr) ?></p><?php endif; ?>
    </div>
    <?php endif; ?>

    <?php if ($figures !== []): ?>
    <div class="don-wrap">
        <dl class="don-stats ctr-stats don-reveal">
            <?php foreach ($figures as $f): ?>
            <div><dt data-don-count="<?= (int) $f['n'] ?>"><?= $h(number_format($f['n'], 0, ',', ' ')) ?></dt><dd><?= $f['label'] ?></dd></div>
            <?php endforeach; ?>
        </dl>
    </div>
    <?php endif; ?>

    <section id="mur" class="don-wrap don-section ctr-anchor" aria-labelledby="ctr-wall-h">
        <div class="don-head don-reveal">
            <p class="don-kicker"><?= $t('k_wall') ?></p>
            <h2 id="ctr-wall-h" class="don-h2"><?= $t('wall_title') ?></h2>
            <p class="don-sub"><?= $t('wall_lead') ?></p>
        </div>
        <?php if ($people === []): ?>
        <div class="ctr-empty don-reveal">
            <span class="ctr-avatar ctr-avatar--empty" aria-hidden="true">+</span>
            <div>
                <h3 class="don-card__title"><?= $t('wall_empty_title') ?></h3>
                <p class="don-card__body"><?= $t('wall_empty') ?></p>
                <a href="#demande" class="don-link"><?= $t('hero_join') ?></a>
            </div>
        </div>
        <?php else: ?>
        <div class="ctr-filter" role="group" aria-label="<?= $t('wall_filter') ?>" data-ctr-filter hidden>
            <button type="button" class="ctr-chip" aria-pressed="true" data-role=""><?= $t('wall_all') ?> <span><?= count($people) ?></span></button>
            <?php foreach (array_keys($icons) as $role): ?>
                <?php if ((int) ($roleCounts[$role] ?? 0) > 0): ?>
                <button type="button" class="ctr-chip ctr-chip--<?= $h($role) ?>" aria-pressed="false" data-role="<?= $h($role) ?>"><?= $t('role_' . $role) ?> <span><?= (int) $roleCounts[$role] ?></span></button>
                <?php endif; ?>
            <?php endforeach; ?>
        </div>
        <ul class="ctr-wall don-reveal" data-ctr-wall>
            <?php foreach ($people as $p): ?>
            <li class="ctr-person ctr-person--<?= $h($mainRole($p)) ?>" data-roles="<?= $h(implode(' ', $p['roles'])) ?>">
                <?= $avatar($p) ?>
                <span class="ctr-person__who">
                    <b translate="no"><?= staff_name((string) $p['name'], (int) $p['user_id']) ?></b>
                    <small><?= $h($roleLine($p['roles'])) ?><?php if (($p['languages'] ?? []) !== []): ?> <span translate="no">· <?= $h(implode(' ', $p['languages'])) ?></span><?php endif; ?></small>
                    <?php if ((int) ($p['texts'] ?? 0) > 0): ?><small class="ctr-person__meta"><?= $plural('texts', (int) $p['texts']) ?></small><?php endif; ?>
                </span>
                <?php if (!empty($p['is_new'])): ?><span class="ctr-new"><?= $t('new') ?></span><?php endif; ?>
            </li>
            <?php endforeach; ?>
        </ul>
        <p class="don-sub" data-ctr-none hidden><?= $t('wall_none_for_role') ?></p>
        <?php endif; ?>
    </section>

    <?php if ($activeList !== [] || $newcomers !== []): ?>
    <section class="don-wrap don-section" aria-labelledby="ctr-honor-h">
        <div class="don-head don-reveal">
            <p class="don-kicker"><?= $t('k_honor') ?></p>
            <h2 id="ctr-honor-h" class="don-h2"><?= $t('honor_title') ?></h2>
        </div>
        <div class="don-cards ctr-honor">
            <?php if ($activeList !== []): ?>
            <article class="don-card don-reveal">
                <h3 class="don-card__title"><?= $t('active_title') ?></h3>
                <p class="don-card__body"><?= $t('active_lead') ?></p>
                <ol class="ctr-rank">
                    <?php foreach ($activeList as $i => $a): ?>
                    <li>
                        <span class="ctr-rank__n is-<?= $i + 1 ?>"><?= $i + 1 ?></span>
                        <?= $avatar($a) ?>
                        <span class="ctr-person__who"><b translate="no"><?= staff_name((string) $a['name'], (int) $a['user_id']) ?></b><small><?= $plural('texts', (int) $a['texts']) ?></small></span>
                    </li>
                    <?php endforeach; ?>
                </ol>
                <span class="don-card__bar" aria-hidden="true"><span></span></span>
            </article>
            <?php endif; ?>
            <?php if ($newcomers !== []): ?>
            <article class="don-card don-reveal" style="--d:1">
                <h3 class="don-card__title"><?= $t('newcomers_title') ?></h3>
                <p class="don-card__body"><?= $t('newcomers_lead') ?></p>
                <ul class="ctr-rank">
                    <?php foreach ($newcomers as $n): ?>
                    <?php $my = $monthYear($n['since'] ?? null); ?>
                    <li>
                        <?= $avatar($n) ?>
                        <span class="ctr-person__who"><b translate="no"><?= staff_name((string) $n['name'], (int) $n['user_id']) ?></b><small><?= $h($roleLine($n['roles'])) ?><?php if ($my !== ''): ?> · <?= $t('since', ['date' => $my]) ?><?php endif; ?></small></span>
                    </li>
                    <?php endforeach; ?>
                </ul>
                <span class="don-card__bar" aria-hidden="true"><span></span></span>
            </article>
            <?php endif; ?>
        </div>
    </section>
    <?php endif; ?>

    <?php if ($donors !== []): ?>
    <section id="donateurs" class="don-wrap don-section" aria-labelledby="ctr-donors-h">
        <div class="don-head don-reveal">
            <p class="don-kicker"><?= $t('k_donors') ?></p>
            <h2 id="ctr-donors-h" class="don-h2"><?= $t('donors_title') ?></h2>
            <p class="don-sub"><?= $t('donors_lead') ?></p>
        </div>
        <div class="don-wall don-reveal">
            <?php foreach (['pilier', 'allie', 'soutien'] as $tier): if (empty($donorGroups[$tier])) { continue; } ?>
            <div class="don-wall__tier don-wall__tier--<?= $h($tier) ?>">
                <h3 class="don-wall__title"><svg viewBox="0 0 24 24" width="16" height="16" aria-hidden="true"><path d="M12 20.5s-7.5-4.6-7.5-10.2A4.3 4.3 0 0 1 12 7.6a4.3 4.3 0 0 1 7.5 2.7c0 5.6-7.5 10.2-7.5 10.2Z" fill="currentColor"/></svg><?= $t('donors_group_' . $tier) ?> <span><?= count($donorGroups[$tier]) ?></span></h3>
                <ul class="don-wall__list">
                    <?php foreach ($donorGroups[$tier] as $d): ?>
                    <li class="don-wall__item">
                        <span class="don-wall__avatar" aria-hidden="true"><?= $h(mb_strtoupper(mb_substr((string) $d['name'], 0, 1))) ?></span>
                        <span class="don-wall__who"><b translate="no"><?= $h($d['name']) ?></b><small translate="no"><?= $d['community'] !== '' ? $h($d['community']) : $t('donors_independent') ?></small></span>
                    </li>
                    <?php endforeach; ?>
                </ul>
            </div>
            <?php endforeach; ?>
        </div>
        <a href="<?= $h(url('soutenir-atak')) ?>" class="don-link"><?= $t('donors_cta') ?></a>
    </section>
    <?php endif; ?>

    <?php if ($packs !== []): ?>
    <section class="don-wrap don-section" aria-labelledby="ctr-packs-h">
        <div class="don-head don-reveal">
            <p class="don-kicker"><?= $t('k_packs') ?></p>
            <h2 id="ctr-packs-h" class="don-h2"><?= $t('packs_title') ?></h2>
            <p class="don-sub"><?= $t('packs_lead') ?></p>
        </div>
        <ul class="ctr-packs don-reveal">
            <?php foreach ($packs as $pk): ?>
            <li>
                <a href="<?= $h(url('traduire-atak/workshop/' . rawurlencode((string) $pk['id']))) ?>">
                    <b translate="no"><?= $h($pk['lang']) ?></b>
                    <?php if ($pk['author'] !== ''): ?><small translate="no"><?= $t('packs_by', ['name' => $pk['author']]) ?></small><?php endif; ?>
                    <?php if ((int) $pk['downloads'] > 0): ?><small><?= $plural('packs_downloads', (int) $pk['downloads']) ?></small><?php endif; ?>
                </a>
            </li>
            <?php endforeach; ?>
        </ul>
        <a href="<?= $h(url('traduire-atak/workshop')) ?>" class="don-link"><?= $t('packs_all') ?></a>
    </section>
    <?php endif; ?>

    <section id="participer" class="don-wrap don-section ctr-anchor" aria-labelledby="ctr-how-h">
        <div class="don-head don-reveal">
            <p class="don-kicker"><?= $t('k_how') ?></p>
            <h2 id="ctr-how-h" class="don-h2"><?= $t('how_title') ?></h2>
            <p class="don-sub"><?= $t('how_lead') ?></p>
        </div>
        <div class="don-cards ctr-ways">
            <?php foreach ($ways as $i => [$key, $links]): ?>
            <article class="don-card don-reveal<?= $key === 'support' ? ' ctr-way--warm' : '' ?>" style="--d:<?= $i % 3 ?>">
                <span class="don-card__icon"><svg class="don-ico" viewBox="0 0 24 24" aria-hidden="true"><?= $howIcons[$key] ?></svg></span>
                <h3 class="don-card__title"><?= $t('how_' . $key) ?></h3>
                <p class="don-card__body"><?= $t('how_' . $key . '_desc') ?></p>
                <p class="ctr-links"><?php foreach ($links as [$href, $label]): ?><a href="<?= $h($href) ?>" class="don-link"><?= $t($label) ?></a><?php endforeach; ?></p>
                <span class="don-card__bar" aria-hidden="true"><span></span></span>
            </article>
            <?php endforeach; ?>
        </div>
    </section>

    <section class="don-wrap don-section" aria-labelledby="ctr-roles-h">
        <div class="don-head don-reveal">
            <p class="don-kicker"><?= $t('k_roles') ?></p>
            <h2 id="ctr-roles-h" class="don-h2"><?= $t('roles_title') ?></h2>
            <p class="don-sub"><?= $t('roles_lead') ?></p>
        </div>
        <ul class="ctr-roles don-reveal">
            <?php foreach ($icons as $role => $icon): ?>
            <li class="ctr-role ctr-role--<?= $h($role) ?>" id="role-<?= $h($role) ?>">
                <span class="ctr-role__icon"><svg class="don-ico" viewBox="0 0 24 24" aria-hidden="true"><?= $icon ?></svg></span>
                <h3 class="don-card__title"><?= $t('role_' . $role) ?></h3>
                <p class="don-card__body"><?= $t('role_' . $role . '_desc') ?></p>
                <p class="ctr-role__discord"><?= $t('discord_role') ?> <b translate="no"><?= $h(ContributorController::DISCORD_ROLES[$role]) ?></b></p>
                <?php if (isset($mine[$role])): ?>
                <p class="ctr-status ctr-status--<?= $h($mine[$role]['status']) ?>"><?= $statusLabel((string) $mine[$role]['status']) ?></p>
                <?php else: ?>
                <a class="don-link" href="<?= $h(url('contribuer?role=' . $role)) ?>#demande"><?= $t('role_apply') ?></a>
                <?php endif; ?>
            </li>
            <?php endforeach; ?>
        </ul>
    </section>

    <?php if ($user !== null && $mine !== []): ?>
    <section id="mes-roles" class="don-wrap don-section ctr-anchor" aria-labelledby="ctr-mine-h">
        <div class="don-head">
            <h2 id="ctr-mine-h" class="don-h2"><?= $t('mine_title') ?></h2>
        </div>
        <ul class="ctr-mine">
            <?php foreach ($mine as $role => $row): ?>
            <li>
                <b><?= $t('role_' . $role) ?><?php if ((string) $row['languages'] !== ''): ?> <small translate="no"><?= $h(strtoupper(str_replace(',', ' ', (string) $row['languages']))) ?></small><?php endif; ?></b>
                <span class="ctr-status ctr-status--<?= $h($row['status']) ?>"><?= $statusLabel((string) $row['status']) ?></span>
                <?php if ((string) $row['admin_note'] !== '' && in_array((string) $row['status'], ['rejected', 'revoked'], true)): ?>
                <small translate="no"><?= $h($row['admin_note']) ?></small>
                <?php endif; ?>
                <?php if ((string) $row['status'] === 'active' && $role === 'translator'): ?>
                <a class="don-link" href="<?= $h(url('traduire-site')) ?>"><?= $t('mine_open_space') ?></a>
                <?php endif; ?>
                <?php if ((string) $row['status'] === 'pending'): ?>
                <form method="post" action="<?= $h(url('contribuer/retirer')) ?>" class="ctr-inline">
                    <?= Csrf::field() ?>
                    <input type="hidden" name="role" value="<?= $h($role) ?>">
                    <button type="submit" class="ctr-textbtn"><?= $t('mine_withdraw') ?></button>
                </form>
                <?php endif; ?>
            </li>
            <?php endforeach; ?>
        </ul>
    </section>
    <?php endif; ?>

    <section id="demande" class="don-wrap don-section ctr-anchor" aria-labelledby="ctr-apply-h">
        <div class="ctr-apply don-reveal">
            <p class="don-kicker"><?= $t('k_apply') ?></p>
            <h2 id="ctr-apply-h" class="don-h2"><?= $t('form_title') ?></h2>
            <?php if (!$ready): ?>
            <p class="don-sub"><?= $t('not_ready') ?></p>
            <?php elseif ($user === null): ?>
            <p class="don-sub"><?= $t('apply_login') ?></p>
            <div class="don-hero__cta">
                <a class="don-btn don-btn--solid" href="<?= $h(url('login')) ?>"><?= $t('login') ?></a>
                <a class="don-btn don-btn--ghost" href="<?= $h(url('register')) ?>"><?= $t('register') ?></a>
            </div>
            <?php else: ?>
            <p class="don-sub"><?= $t('apply_lead') ?></p>
            <form method="post" action="<?= $h(url('contribuer/demande')) ?>" class="ctr-form" id="ctr-form">
                <?= Csrf::field() ?>
                <fieldset class="ctr-field ctr-field--wide">
                    <legend><?= $t('field_roles') ?></legend>
                    <small><?= $t('field_roles_help') ?></small>
                    <div class="ctr-choices">
                        <?php foreach (array_keys($icons) as $role): ?>
                        <?php
                        $mineRow = $mine[$role] ?? null;
                        $mineStatus = is_array($mineRow) ? (string) ($mineRow['status'] ?? '') : '';
                        $locked = in_array($mineStatus, ['active', 'pending'], true);
                        $checked = !$locked && $want === $role;
                        $hint = match ($mineStatus) {
                            'active' => $t('already_active'),
                            'pending' => $t('already_pending'),
                            'rejected', 'revoked' => $t('reapply'),
                            default => '',
                        };
                        ?>
                        <label class="ctr-choice ctr-choice--<?= $h($role) ?>">
                            <input type="checkbox" name="roles[]" value="<?= $h($role) ?>"<?= $checked ? ' checked' : '' ?><?= $locked ? ' disabled' : '' ?>>
                            <span><b><?= $t('role_' . $role) ?></b><?php if ($hint !== ''): ?><em><?= $hint ?></em><?php endif; ?></span>
                        </label>
                        <?php endforeach; ?>
                    </div>
                </fieldset>
                <label class="ctr-field ctr-field--wide" id="ctr-langs-field"><span><?= $t('field_languages') ?></span>
                    <input type="text" name="languages" maxlength="60" autocomplete="off" spellcheck="false" placeholder="es, de" value="<?= $h($prefLang) ?>">
                    <small><?= $t('field_languages_help') ?></small></label>
                <label class="ctr-field"><span><?= $t('field_discord') ?></span>
                    <input type="text" name="discord" maxlength="64" autocomplete="off" spellcheck="false">
                    <small><?= $t('field_discord_help') ?></small></label>
                <label class="ctr-field"><span><?= $t('field_public_name') ?></span>
                    <input type="text" name="public_name" maxlength="40" autocomplete="nickname" spellcheck="false" value="<?= $h($publicName) ?>" placeholder="<?= $h($callsign) ?>">
                    <small><?= $t('field_public_name_help') ?></small></label>
                <label class="ctr-field ctr-field--wide"><span><?= $t('field_motivation') ?></span>
                    <textarea name="motivation" rows="4" maxlength="1500"></textarea>
                    <small><?= $t('field_motivation_help') ?></small></label>
                <label class="ctr-check ctr-field--wide"><input type="checkbox" name="public_credit" value="1" checked> <?= $t('field_credit') ?></label>
                <div class="ctr-field--wide"><button type="submit" class="don-btn don-btn--solid"><?= $t('apply_submit') ?></button></div>
            </form>
            <?php endif; ?>
        </div>
    </section>

    <section class="don-wrap don-section don-section--last" aria-labelledby="ctr-final-h">
        <div class="don-final don-reveal">
            <div class="don-final__radar" aria-hidden="true"><span></span></div>
            <p class="don-kicker"><?= $t('k_thanks') ?></p>
            <h2 id="ctr-final-h" class="don-final__title"><?= $t('final_title') ?></h2>
            <p class="ctr-final__body"><?= $t('thanks_body') ?></p>
            <div class="don-hero__cta">
                <a href="#demande" class="don-btn don-btn--solid"><?= $t('hero_join') ?></a>
                <a href="<?= $h(url('soutenir-atak')) ?>" class="don-btn don-btn--ghost"><?= $t('how_support_go') ?></a>
            </div>
        </div>
    </section>
</div>
