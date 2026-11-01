<?php
declare(strict_types=1);
// Contribuer (/contribuer) : App\Controllers\Web\ContributorController, contenu public App\Services\Community\ContributorShowcase.
// Textes : lang/*/site.php (contrib_*). Style : public/assets/css/site-contribute-page.css ; thème et filtres : public/assets/js/site-contribute.js.
// Données publiques uniquement : pseudo public ou indicatif, rôles cités, mur des donateurs, auteurs des packs publiés.
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
$howIcons = [
    'test' => $icons['tester'],
    'translate' => $icons['translator'],
    'bug' => '<path d="M8 8a4 4 0 0 1 8 0v1H8z"/><rect x="7" y="9" width="10" height="10" rx="5"/><path d="M12 12v7M3 13h4M17 13h4M4 7l3 2M20 7l-3 2M4 19l3-2M20 19l-3-2"/>',
    'idea' => '<path d="M9 18h6M10 21h4"/><path d="M12 3a6 6 0 0 0-3.6 10.8c.7.6 1.1 1.3 1.1 2.2h5c0-.9.4-1.6 1.1-2.2A6 6 0 0 0 12 3z"/>',
    'support' => '<path d="M12 20.5s-7.5-4.6-9.2-9.4C1.6 7.6 4 4.5 7.3 4.5c2 0 3.5 1.1 4.7 2.8 1.2-1.7 2.7-2.8 4.7-2.8 3.3 0 5.7 3.1 4.5 6.6-1.7 4.8-9.2 9.4-9.2 9.4z"/>',
];
$roleLabel = static function (string $role): string {
    $label = __('site.contrib_role_' . $role);

    return $label === 'site.contrib_role_' . $role ? '' : $label;
};
$roleLine = static function (array $roles) use ($roleLabel): string {
    return implode(', ', array_filter(array_map(static fn ($r): string => $roleLabel((string) $r), $roles)));
};
/** Anneau de l'écusson : un segment par rôle, aux couleurs du rôle. */
$ring = static function (array $roles): string {
    $roles = array_values(array_filter($roles, 'is_string'));
    if ($roles === []) {
        return 'var(--cb-line)';
    }
    $n = count($roles);
    $parts = [];
    foreach ($roles as $i => $role) {
        $role = preg_replace('/[^a-z]/', '', $role) ?? '';
        $parts[] = 'var(--cb-r-' . $role . ') ' . round($i * 100 / $n, 2) . '% ' . round(($i + 1) * 100 / $n, 2) . '%';
    }

    return 'conic-gradient(' . implode(', ', $parts) . ')';
};
$patch = static function (array $p, string $size = '') use ($h, $ring): string {
    $roles = is_array($p['roles'] ?? null) ? $p['roles'] : [];

    return '<span class="cb-patch' . ($size !== '' ? ' cb-patch--' . $size : '') . '" style="--hue: ' . (int) ($p['hue'] ?? 150) . '; --ring: ' . $h($ring($roles)) . '" aria-hidden="true">'
        . '<span class="cb-patch__core" translate="no">' . $h($p['monogram'] ?? '?') . '</span></span>';
};
$monthYear = static function (?string $date): string {
    $ts = $date !== null && $date !== '' ? strtotime($date) : false;

    return $ts !== false ? date('m/Y', $ts) : '';
};
$tierKey = static fn (string $tier): string => in_array($tier, ['soutien', 'allie', 'pilier'], true) ? $tier : 'soutien';
$figures = [];
foreach (['contributors', 'texts', 'languages', 'donors', 'communities'] as $k) {
    $n = (int) ($stats[$k] ?? 0);
    if ($n > 0) {
        $figures[] = ['n' => $n, 'label' => $plural('stat_' . $k, $n)];
    }
}
$heroPeople = array_slice($people, 0, 6);
$statusLabel = static fn (string $s): string => htmlspecialchars(__('site.contrib_status_' . $s), ENT_QUOTES, 'UTF-8');
$flashOk = Session::getFlash('success');
$flashErr = Session::getFlash('error');
?>
<article class="cb" data-cb>
    <header class="cb-hero" aria-labelledby="cb-title">
        <div class="cb-wrap cb-hero__grid">
            <div class="cb-hero__copy">
                <h1 id="cb-title" class="cb-hero__title"><?= $t('hero_title') ?></h1>
                <p class="cb-hero__lead"><?= $t('hero_lead') ?></p>
                <div class="cb-actions">
                    <a class="cb-btn cb-btn--solid" href="#demande"><?= $t('hero_join') ?></a>
                    <a class="cb-btn cb-btn--ghost" href="#mur"><?= $t('hero_wall') ?></a>
                </div>
            </div>
            <div class="cb-board" aria-hidden="true">
                <?php foreach ($heroPeople as $p): ?>
                <span class="cb-board__slot"><?= $patch($p, 'lg') ?><span class="cb-board__name" translate="no"><?= $h($p['name']) ?></span></span>
                <?php endforeach; ?>
                <?php for ($i = count($heroPeople); $i < 3; $i++): ?>
                <span class="cb-board__slot cb-board__slot--empty"><span class="cb-patch cb-patch--lg cb-patch--empty"><span class="cb-patch__core">+</span></span><span class="cb-board__name"><?= $t('hero_empty_patch') ?></span></span>
                <?php endfor; ?>
            </div>
        </div>
        <button type="button" class="cb-theme" data-cb-theme-toggle hidden
                data-label-dark="<?= $t('theme_to_dark') ?>"
                data-label-light="<?= $t('theme_to_light') ?>"
                aria-label="<?= $t('theme_to_dark') ?>" title="<?= $t('theme_to_dark') ?>">
            <svg class="cb-theme__moon" viewBox="0 0 24 24" aria-hidden="true" focusable="false"><path d="M20 14.5A8 8 0 0 1 9.5 4a8 8 0 1 0 10.5 10.5z"/></svg>
            <svg class="cb-theme__sun" viewBox="0 0 24 24" aria-hidden="true" focusable="false"><circle cx="12" cy="12" r="4"/><path d="M12 2v2.5M12 19.5V22M2 12h2.5M19.5 12H22M4.9 4.9l1.8 1.8M17.3 17.3l1.8 1.8M4.9 19.1l1.8-1.8M17.3 6.7l1.8-1.8"/></svg>
        </button>
    </header>

    <?php if (is_string($flashOk) && $flashOk !== ''): ?><div class="cb-wrap"><p class="cb-msg is-ok" role="status"><?= $h($flashOk) ?></p></div><?php endif; ?>
    <?php if (is_string($flashErr) && $flashErr !== ''): ?><div class="cb-wrap"><p class="cb-msg is-bad" role="alert"><?= $h($flashErr) ?></p></div><?php endif; ?>

    <?php if ($figures !== []): ?>
    <section class="cb-figures" aria-label="<?= $t('wall_title') ?>">
        <ul class="cb-wrap cb-figures__list">
            <?php foreach ($figures as $f): ?>
            <li><strong><?= $h(number_format($f['n'], 0, ',', ' ')) ?></strong> <span><?= $f['label'] ?></span></li>
            <?php endforeach; ?>
        </ul>
    </section>
    <?php endif; ?>

    <section class="cb-section" id="mur" aria-labelledby="cb-wall-h">
        <div class="cb-wrap">
            <h2 id="cb-wall-h" class="cb-h2"><?= $t('wall_title') ?></h2>
            <p class="cb-sub"><?= $t('wall_lead') ?></p>
            <?php if ($people === []): ?>
            <div class="cb-empty">
                <span class="cb-patch cb-patch--lg cb-patch--empty" aria-hidden="true"><span class="cb-patch__core">+</span></span>
                <div>
                    <h3 class="cb-h3"><?= $t('wall_empty_title') ?></h3>
                    <p class="cb-sub"><?= $t('wall_empty') ?></p>
                    <div class="cb-actions"><a class="cb-btn cb-btn--solid" href="#demande"><?= $t('hero_join') ?></a></div>
                </div>
            </div>
            <?php else: ?>
            <div class="cb-filter" role="group" aria-label="<?= $t('wall_filter') ?>" data-cb-filter hidden>
                <button type="button" class="cb-chip" aria-pressed="true" data-role=""><?= $t('wall_all') ?> <span><?= count($people) ?></span></button>
                <?php foreach (array_keys($icons) as $role): ?>
                    <?php if ((int) ($roleCounts[$role] ?? 0) > 0): ?>
                    <button type="button" class="cb-chip cb-chip--<?= $h($role) ?>" aria-pressed="false" data-role="<?= $h($role) ?>"><?= $t('role_' . $role) ?> <span><?= (int) $roleCounts[$role] ?></span></button>
                    <?php endif; ?>
                <?php endforeach; ?>
            </div>
            <ul class="cb-wall" data-cb-wall>
                <?php foreach ($people as $p): ?>
                <li class="cb-person" data-roles="<?= $h(implode(' ', $p['roles'])) ?>">
                    <?= $patch($p) ?>
                    <strong class="cb-person__name" translate="no"><?= staff_name((string) $p['name'], (int) $p['user_id']) ?></strong>
                    <span class="cb-person__roles"><?= $h($roleLine($p['roles'])) ?></span>
                    <?php if (($p['languages'] ?? []) !== []): ?><span class="cb-person__langs" translate="no"><?= $h(implode(' ', $p['languages'])) ?></span><?php endif; ?>
                    <?php if (!empty($p['is_new'])): ?><span class="cb-tag cb-tag--new"><?= $t('new') ?></span><?php endif; ?>
                    <?php if ((int) ($p['texts'] ?? 0) > 0): ?><span class="cb-person__meta"><?= $plural('texts', (int) $p['texts']) ?></span><?php endif; ?>
                </li>
                <?php endforeach; ?>
            </ul>
            <p class="cb-sub cb-wall__none" data-cb-none hidden><?= $t('wall_none_for_role') ?></p>
            <?php endif; ?>
        </div>
    </section>

    <?php if ($activeList !== [] || $newcomers !== []): ?>
    <section class="cb-section cb-section--alt" aria-label="<?= $t('active_title') ?>">
        <div class="cb-wrap cb-split">
            <?php if ($activeList !== []): ?>
            <div>
                <h2 class="cb-h2"><?= $t('active_title') ?></h2>
                <p class="cb-sub"><?= $t('active_lead') ?></p>
                <ol class="cb-rank">
                    <?php foreach ($activeList as $i => $a): ?>
                    <li>
                        <span class="cb-rank__n"><?= $i + 1 ?></span>
                        <?= $patch($a, 'sm') ?>
                        <span class="cb-rank__who"><strong translate="no"><?= staff_name((string) $a['name'], (int) $a['user_id']) ?></strong><span><?= $plural('texts', (int) $a['texts']) ?></span></span>
                    </li>
                    <?php endforeach; ?>
                </ol>
            </div>
            <?php endif; ?>
            <?php if ($newcomers !== []): ?>
            <div>
                <h2 class="cb-h2"><?= $t('newcomers_title') ?></h2>
                <p class="cb-sub"><?= $t('newcomers_lead') ?></p>
                <ul class="cb-rank cb-rank--plain">
                    <?php foreach ($newcomers as $n): ?>
                    <?php $my = $monthYear($n['since'] ?? null); ?>
                    <li>
                        <?= $patch($n, 'sm') ?>
                        <span class="cb-rank__who"><strong translate="no"><?= staff_name((string) $n['name'], (int) $n['user_id']) ?></strong><span><?= $h($roleLine($n['roles'])) ?><?php if ($my !== ''): ?>. <?= $t('since', ['date' => $my]) ?><?php endif; ?></span></span>
                    </li>
                    <?php endforeach; ?>
                </ul>
            </div>
            <?php endif; ?>
        </div>
    </section>
    <?php endif; ?>

    <?php if ($donors !== []): ?>
    <section class="cb-section" id="donateurs" aria-labelledby="cb-donors-h">
        <div class="cb-wrap">
            <div class="cb-head">
                <div>
                    <h2 id="cb-donors-h" class="cb-h2"><?= $t('donors_title') ?></h2>
                    <p class="cb-sub"><?= $t('donors_lead') ?></p>
                </div>
                <a class="cb-btn cb-btn--ghost" href="<?= $h(url('soutenir-atak')) ?>"><?= $t('donors_cta') ?></a>
            </div>
            <ul class="cb-donors">
                <?php foreach ($donors as $d): ?>
                <li class="cb-donor cb-donor--<?= $h($tierKey($d['tier'])) ?>">
                    <svg viewBox="0 0 24 24" aria-hidden="true" focusable="false"><?= $howIcons['support'] ?></svg>
                    <strong translate="no"><?= $h($d['name']) ?></strong>
                    <span><?= $t('tier_' . $tierKey($d['tier'])) ?><?php if ($d['community'] !== ''): ?>, <span translate="no"><?= $h($d['community']) ?></span><?php endif; ?></span>
                </li>
                <?php endforeach; ?>
            </ul>
        </div>
    </section>
    <?php endif; ?>

    <?php if ($packs !== []): ?>
    <section class="cb-section cb-section--alt" aria-labelledby="cb-packs-h">
        <div class="cb-wrap">
            <div class="cb-head">
                <div>
                    <h2 id="cb-packs-h" class="cb-h2"><?= $t('packs_title') ?></h2>
                    <p class="cb-sub"><?= $t('packs_lead') ?></p>
                </div>
                <a class="cb-btn cb-btn--ghost" href="<?= $h(url('traduire-atak/workshop')) ?>"><?= $t('packs_all') ?></a>
            </div>
            <ul class="cb-packs">
                <?php foreach ($packs as $pk): ?>
                <li>
                    <a href="<?= $h(url('traduire-atak/workshop/' . rawurlencode((string) $pk['id']))) ?>">
                        <strong translate="no"><?= $h($pk['lang']) ?></strong>
                        <?php if ($pk['author'] !== ''): ?><span translate="no"><?= $t('packs_by', ['name' => $pk['author']]) ?></span><?php endif; ?>
                        <?php if ((int) $pk['downloads'] > 0): ?><em><?= $plural('packs_downloads', (int) $pk['downloads']) ?></em><?php endif; ?>
                    </a>
                </li>
                <?php endforeach; ?>
            </ul>
        </div>
    </section>
    <?php endif; ?>

    <section class="cb-section" id="participer" aria-labelledby="cb-how-h">
        <div class="cb-wrap">
            <h2 id="cb-how-h" class="cb-h2"><?= $t('how_title') ?></h2>
            <p class="cb-sub"><?= $t('how_lead') ?></p>
            <ul class="cb-ways">
                <li class="cb-way">
                    <svg viewBox="0 0 24 24" aria-hidden="true" focusable="false"><?= $howIcons['test'] ?></svg>
                    <h3 class="cb-h3"><?= $t('how_test') ?></h3>
                    <p><?= $t('how_test_desc') ?></p>
                    <a class="cb-link" href="<?= $h(url('contribuer?role=tester')) ?>#demande"><?= $t('how_test_go') ?></a>
                </li>
                <li class="cb-way">
                    <svg viewBox="0 0 24 24" aria-hidden="true" focusable="false"><?= $howIcons['translate'] ?></svg>
                    <h3 class="cb-h3"><?= $t('how_translate') ?></h3>
                    <p><?= $t('how_translate_desc') ?></p>
                    <a class="cb-link" href="<?= $h(url('traduire-site')) ?>"><?= $t('how_translate_go') ?></a>
                    <a class="cb-link" href="<?= $h(url('traduire-atak')) ?>"><?= $t('how_translate_mod') ?></a>
                </li>
                <li class="cb-way">
                    <svg viewBox="0 0 24 24" aria-hidden="true" focusable="false"><?= $howIcons['bug'] ?></svg>
                    <h3 class="cb-h3"><?= $t('how_bug') ?></h3>
                    <p><?= $t('how_bug_desc') ?></p>
                    <a class="cb-link" href="<?= $h(url('contact') . '?sujet=' . rawurlencode(__('site.contrib_how_bug_subject'))) ?>"><?= $t('how_bug_go') ?></a>
                </li>
                <li class="cb-way">
                    <svg viewBox="0 0 24 24" aria-hidden="true" focusable="false"><?= $howIcons['idea'] ?></svg>
                    <h3 class="cb-h3"><?= $t('how_idea') ?></h3>
                    <p><?= $t('how_idea_desc') ?></p>
                    <a class="cb-link" href="<?= $h(url('contact') . '?sujet=' . rawurlencode(__('site.contrib_how_idea_subject'))) ?>"><?= $t('how_idea_go') ?></a>
                </li>
                <li class="cb-way cb-way--warm">
                    <svg viewBox="0 0 24 24" aria-hidden="true" focusable="false"><?= $howIcons['support'] ?></svg>
                    <h3 class="cb-h3"><?= $t('how_support') ?></h3>
                    <p><?= $t('how_support_desc') ?></p>
                    <a class="cb-link" href="<?= $h(url('soutenir-atak')) ?>"><?= $t('how_support_go') ?></a>
                </li>
            </ul>
        </div>
    </section>

    <section class="cb-section cb-section--alt" aria-labelledby="cb-roles-h">
        <div class="cb-wrap">
            <h2 id="cb-roles-h" class="cb-h2"><?= $t('roles_title') ?></h2>
            <p class="cb-sub"><?= $t('roles_lead') ?></p>
            <ul class="cb-roles">
                <?php foreach ($icons as $role => $icon): ?>
                <li class="cb-role cb-role--<?= $h($role) ?>" id="role-<?= $h($role) ?>">
                    <svg viewBox="0 0 24 24" aria-hidden="true" focusable="false" class="cb-role__ico"><?= $icon ?></svg>
                    <h3 class="cb-h3"><?= $t('role_' . $role) ?></h3>
                    <p><?= $t('role_' . $role . '_desc') ?></p>
                    <p class="cb-role__discord"><?= $t('discord_role') ?> <strong translate="no"><?= $h(ContributorController::DISCORD_ROLES[$role]) ?></strong></p>
                    <?php if (isset($mine[$role])): ?>
                    <p class="cb-tag cb-tag--<?= $h($mine[$role]['status']) ?>"><?= $statusLabel((string) $mine[$role]['status']) ?></p>
                    <?php else: ?>
                    <a class="cb-link" href="<?= $h(url('contribuer?role=' . $role)) ?>#demande"><?= $t('role_apply') ?></a>
                    <?php endif; ?>
                </li>
                <?php endforeach; ?>
            </ul>
        </div>
    </section>

    <?php if ($user !== null && $mine !== []): ?>
    <section class="cb-section" id="mes-roles" aria-labelledby="cb-mine-h">
        <div class="cb-wrap">
            <h2 id="cb-mine-h" class="cb-h2"><?= $t('mine_title') ?></h2>
            <ul class="cb-mine">
                <?php foreach ($mine as $role => $row): ?>
                <li>
                    <span class="cb-mine__role"><?= $t('role_' . $role) ?><?php if ((string) $row['languages'] !== ''): ?> <span class="cb-mine__langs" translate="no"><?= $h(strtoupper(str_replace(',', ' ', (string) $row['languages']))) ?></span><?php endif; ?></span>
                    <span class="cb-tag cb-tag--<?= $h($row['status']) ?>"><?= $statusLabel((string) $row['status']) ?></span>
                    <?php if ((string) $row['admin_note'] !== '' && in_array((string) $row['status'], ['rejected', 'revoked'], true)): ?>
                    <span class="cb-mine__note" translate="no"><?= $h($row['admin_note']) ?></span>
                    <?php endif; ?>
                    <?php if ((string) $row['status'] === 'active' && $role === 'translator'): ?>
                    <a class="cb-link" href="<?= $h(url('traduire-site')) ?>"><?= $t('mine_open_space') ?></a>
                    <?php endif; ?>
                    <?php if ((string) $row['status'] === 'pending'): ?>
                    <form method="post" action="<?= $h(url('contribuer/retirer')) ?>" class="cb-inline">
                        <?= Csrf::field() ?>
                        <input type="hidden" name="role" value="<?= $h($role) ?>">
                        <button type="submit" class="cb-link"><?= $t('mine_withdraw') ?></button>
                    </form>
                    <?php endif; ?>
                </li>
                <?php endforeach; ?>
            </ul>
        </div>
    </section>
    <?php endif; ?>

    <section class="cb-section" id="demande" aria-labelledby="cb-apply-h">
        <div class="cb-wrap cb-apply">
            <h2 id="cb-apply-h" class="cb-h2"><?= $t('form_title') ?></h2>
            <?php if (!$ready): ?>
            <p class="cb-sub"><?= $t('not_ready') ?></p>
            <?php elseif ($user === null): ?>
            <p class="cb-sub"><?= $t('apply_login') ?></p>
            <div class="cb-actions">
                <a class="cb-btn cb-btn--solid" href="<?= $h(url('login')) ?>"><?= $t('login') ?></a>
                <a class="cb-btn cb-btn--ghost" href="<?= $h(url('register')) ?>"><?= $t('register') ?></a>
            </div>
            <?php else: ?>
            <p class="cb-sub"><?= $t('apply_lead') ?></p>
            <form method="post" action="<?= $h(url('contribuer/demande')) ?>" class="cb-form" id="cb-form">
                <?= Csrf::field() ?>
                <fieldset class="cb-field cb-field--wide">
                    <legend><?= $t('field_roles') ?></legend>
                    <small><?= $t('field_roles_help') ?></small>
                    <div class="cb-choices">
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
                        <label class="cb-choice cb-choice--<?= $h($role) ?>">
                            <input type="checkbox" name="roles[]" value="<?= $h($role) ?>"<?= $checked ? ' checked' : '' ?><?= $locked ? ' disabled' : '' ?>>
                            <span><strong><?= $t('role_' . $role) ?></strong><?php if ($hint !== ''): ?><em><?= $hint ?></em><?php endif; ?></span>
                        </label>
                        <?php endforeach; ?>
                    </div>
                </fieldset>
                <label class="cb-field cb-field--wide" id="cb-langs-field"><span><?= $t('field_languages') ?></span>
                    <input type="text" name="languages" maxlength="60" autocomplete="off" spellcheck="false" placeholder="es, de" value="<?= $h($prefLang) ?>">
                    <small><?= $t('field_languages_help') ?></small></label>
                <label class="cb-field"><span><?= $t('field_discord') ?></span>
                    <input type="text" name="discord" maxlength="64" autocomplete="off" spellcheck="false">
                    <small><?= $t('field_discord_help') ?></small></label>
                <label class="cb-field"><span><?= $t('field_public_name') ?></span>
                    <input type="text" name="public_name" maxlength="40" autocomplete="nickname" spellcheck="false" value="<?= $h($publicName) ?>" placeholder="<?= $h($callsign) ?>">
                    <small><?= $t('field_public_name_help') ?></small></label>
                <label class="cb-field cb-field--wide"><span><?= $t('field_motivation') ?></span>
                    <textarea name="motivation" rows="4" maxlength="1500"></textarea>
                    <small><?= $t('field_motivation_help') ?></small></label>
                <label class="cb-check cb-field--wide"><input type="checkbox" name="public_credit" value="1" checked> <?= $t('field_credit') ?></label>
                <div class="cb-actions cb-field--wide"><button type="submit" class="cb-btn cb-btn--solid"><?= $t('apply_submit') ?></button></div>
            </form>
            <?php endif; ?>
        </div>
    </section>

    <section class="cb-section cb-thanks" aria-labelledby="cb-thanks-h">
        <div class="cb-wrap">
            <h2 id="cb-thanks-h" class="cb-h2"><?= $t('thanks_title') ?></h2>
            <p class="cb-thanks__body"><?= $t('thanks_body') ?></p>
        </div>
    </section>
</article>
