<?php
declare(strict_types=1);

/**
 * Aside commun des modules LMS (Formations, Recrutement).
 * Desktop : colonne collante (marque, rubriques par section, avancement, raccourcis).
 * Mobile : barre compacte « module · page courante » qui déplie le menu (bouton, Échap, focus).
 * Styles : public/assets/css/lms-nav.css. Comportement : public/assets/js/lms-nav.js.
 *
 * @var array{
 *   id: string,
 *   tone?: 'emerald'|'sky',
 *   kicker: string,
 *   title: string,
 *   lead?: string,
 *   aria?: string,
 *   sections: list<array{label: string, items: list<array{href: string, label: string, hint?: string, icon?: string, badge?: int|string|null, active?: bool}>}>,
 *   progress?: array{label: string, value: int, max: int, text?: string, caption?: string}|null,
 *   cards?: list<array{kicker: string, label: string, href: string, text?: string}>,
 *   foot?: array{href: string, label: string, sub?: string}|null
 * } $lmsNav
 */

$nav = is_array($lmsNav ?? null) ? $lmsNav : [];
$h = static fn (mixed $v): string => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
$navId = preg_replace('/[^a-z0-9-]/', '', strtolower((string) ($nav['id'] ?? 'lms'))) ?: 'lms';
$tone = in_array($nav['tone'] ?? '', ['emerald', 'sky'], true) ? (string) $nav['tone'] : 'emerald';
$sections = is_array($nav['sections'] ?? null) ? $nav['sections'] : [];

/* Rubrique active : valeur explicite, sinon le lien dont le chemin correspond le mieux à l’adresse courante. */
$reqPath = rtrim((string) (parse_url((string) ($_SERVER['REQUEST_URI'] ?? ''), PHP_URL_PATH) ?: ''), '/');
$explicit = false;
foreach ($sections as $sec) {
    foreach (($sec['items'] ?? []) as $it) {
        if (array_key_exists('active', $it) && $it['active'] === true) {
            $explicit = true;
        }
    }
}
$bestKey = null;
$bestLen = -1;
if (!$explicit && $reqPath !== '') {
    foreach ($sections as $si => $sec) {
        foreach (($sec['items'] ?? []) as $ii => $it) {
            if (array_key_exists('active', $it)) {
                continue;
            }
            $href = (string) ($it['href'] ?? '');
            if (str_contains($href, '#')) {
                continue;
            }
            $p = rtrim((string) (parse_url($href, PHP_URL_PATH) ?: ''), '/');
            if ($p === '') {
                continue;
            }
            if (($reqPath === $p || str_starts_with($reqPath, $p . '/')) && strlen($p) > $bestLen) {
                $bestLen = strlen($p);
                $bestKey = $si . ':' . $ii;
            }
        }
    }
}
$currentLabel = '';
foreach ($sections as $si => &$sec) {
    foreach (($sec['items'] ?? []) as $ii => $it) {
        $on = $explicit ? !empty($it['active']) : ($bestKey === $si . ':' . $ii);
        $sec['items'][$ii]['_on'] = $on;
        if ($on && $currentLabel === '') {
            $currentLabel = (string) $it['label'];
        }
    }
}
unset($sec);

$icon = static function (string $key): string {
    $a = 'viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"';
    $paths = [
        'overview' => '<rect x="3" y="3" width="7" height="9" rx="1"/><rect x="14" y="3" width="7" height="5" rx="1"/><rect x="14" y="12" width="7" height="9" rx="1"/><rect x="3" y="16" width="7" height="5" rx="1"/>',
        'catalogue' => '<path d="M4 19.5A2.5 2.5 0 0 1 6.5 17H20"/><path d="M6.5 2H20v20H6.5A2.5 2.5 0 0 1 4 19.5v-15A2.5 2.5 0 0 1 6.5 2z"/>',
        'mine' => '<path d="M2.5 9 12 4.5 21.5 9 12 13.5 2.5 9Z"/><path d="M6.5 11v4.5c0 1.4 2.5 3 5.5 3s5.5-1.6 5.5-3V11"/>',
        'calendar' => '<rect x="3.5" y="5" width="17" height="15.5" rx="1.5"/><path d="M3.5 9.5h17M8 3v3.2M16 3v3.2"/>',
        'cert' => '<circle cx="12" cy="8" r="6"/><path d="M15.477 12.89 17 22l-5-3-5 3 1.523-9.11"/>',
        'star' => '<path d="M12 2l3.09 6.26L22 9.27l-5 4.87 1.18 6.88L12 17.77l-6.18 3.25L7 14.14 2 9.27l6.91-1.01L12 2z"/>',
        'docs' => '<path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><path d="M14 2v6h6M16 13H8M16 17H8"/>',
        'html' => '<path d="m16 18 6-6-6-6M8 6l-6 6 6 6"/>',
        'studio' => '<path d="M12 20h9"/><path d="M16.5 3.5a2.12 2.12 0 0 1 3 3L7 19l-4 1 1-4Z"/>',
        'enroll' => '<path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M19 8v6M22 11h-6"/>',
        'reports' => '<path d="M18 20V10M12 20V4M6 20v-6"/>',
        'feedback' => '<path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/>',
        'search' => '<circle cx="11" cy="11" r="7"/><path d="m20 20-3.2-3.2"/>',
        'audit' => '<path d="M12 8v4l3 2"/><circle cx="12" cy="12" r="9"/>',
        'groups' => '<circle cx="9" cy="8.5" r="3"/><path d="M3.5 19.5c.6-3 2.8-4.8 5.5-4.8s4.9 1.8 5.5 4.8"/><circle cx="16.8" cy="9.2" r="2.4"/><path d="M16.2 14.6c2.3.1 3.9 1.7 4.3 4.4"/>',
        'charte' => '<path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/>',
        'gabarit' => '<rect x="3" y="3" width="18" height="18" rx="2"/><path d="M3 9h18M9 21V9"/>',
        'inbox' => '<path d="M22 12h-6l-2 3h-4l-2-3H2"/><path d="M5.45 5.11 2 12v6a2 2 0 0 0 2 2h16a2 2 0 0 0 2-2v-6l-3.45-6.89A2 2 0 0 0 16.76 4H7.24a2 2 0 0 0-1.79 1.11z"/>',
        'wall' => '<path d="M4.5 5h15A1.5 1.5 0 0 1 21 6.5v9a1.5 1.5 0 0 1-1.5 1.5H10l-4.5 3.5V17h-1A1.5 1.5 0 0 1 3 15.5v-9A1.5 1.5 0 0 1 4.5 5Z"/>',
        'chart' => '<path d="M3 3v18h18"/><path d="m7 15 4-4 3 3 5-6"/>',
        'offer' => '<path d="M3 11l18-8-8 18-2-8-8-2z"/>',
        'tag' => '<path d="M20.59 13.41 13.42 20.6a2 2 0 0 1-2.83 0L2 12V2h10l8.59 8.59a2 2 0 0 1 0 2.82z"/><circle cx="7" cy="7" r="1.5"/>',
        'clock' => '<circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 2"/>',
        'template' => '<rect x="3" y="4" width="18" height="16" rx="2"/><path d="M7 9h10M7 13h6"/>',
        'discord' => '<path d="M8 12h.01M16 12h.01"/><path d="M7.5 7.5c3-1.3 6-1.3 9 0 1.6 2.4 2.4 5 2.2 8-1.4 1.1-2.8 1.8-4.2 2.2l-.9-1.6M7.5 7.5C5.9 9.9 5.1 12.5 5.3 15.5c1.4 1.1 2.8 1.8 4.2 2.2l.9-1.6"/>',
        'key' => '<circle cx="7.5" cy="15.5" r="4.5"/><path d="m10.7 12.3 9.8-9.8M17 6l3 3M14.5 8.5l2 2"/>',
        'sitemap' => '<rect x="9" y="2.5" width="6" height="5" rx="1"/><rect x="2.5" y="16.5" width="6" height="5" rx="1"/><rect x="15.5" y="16.5" width="6" height="5" rx="1"/><path d="M12 7.5v4M5.5 16.5v-2.5h13v2.5"/>',
        'back' => '<path d="M19 12H5M12 19l-7-7 7-7"/>',
    ];

    return '<svg ' . $a . '>' . ($paths[$key] ?? '<circle cx="12" cy="12" r="3"/>') . '</svg>';
};

$progress = is_array($nav['progress'] ?? null) ? $nav['progress'] : null;
$cards = is_array($nav['cards'] ?? null) ? $nav['cards'] : [];
$foot = is_array($nav['foot'] ?? null) ? $nav['foot'] : null;
$panelId = 'lms-nav-panel-' . $navId;
?>
<aside class="lms-nav lms-nav--<?= $h($tone) ?>" data-lms-nav aria-label="<?= $h($nav['aria'] ?? ($nav['title'] ?? 'Navigation du module')) ?>">
    <div class="lms-nav__bar">
        <span class="lms-nav__bar-mark" aria-hidden="true"></span>
        <span class="lms-nav__bar-copy">
            <span class="lms-nav__bar-kicker"><?= $h($nav['title'] ?? '') ?></span>
            <span class="lms-nav__bar-title"><?= $h($currentLabel !== '' ? $currentLabel : 'Menu du module') ?></span>
        </span>
        <button type="button" class="lms-nav__toggle" data-lms-nav-toggle aria-expanded="false" aria-controls="<?= $h($panelId) ?>">
            <svg class="lms-nav__toggle-open" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" aria-hidden="true"><path d="M4 7h16M4 12h16M4 17h16"/></svg>
            <svg class="lms-nav__toggle-close" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" aria-hidden="true"><path d="M6 6l12 12M18 6 6 18"/></svg>
            <span>Menu</span>
        </button>
    </div>

    <div class="lms-nav__panel" id="<?= $h($panelId) ?>">
        <div class="lms-nav__brand">
            <span class="lms-nav__brand-mark" aria-hidden="true"><span></span></span>
            <span class="lms-nav__brand-copy">
                <span class="lms-nav__kicker"><?= $h($nav['kicker'] ?? '') ?></span>
                <span class="lms-nav__title"><?= $h($nav['title'] ?? '') ?></span>
            </span>
        </div>
        <?php if (!empty($nav['lead'])): ?>
        <p class="lms-nav__lead"><?= $h($nav['lead']) ?></p>
        <?php endif; ?>

        <nav class="lms-nav__nav" aria-label="<?= $h($nav['aria'] ?? 'Rubriques') ?>">
            <?php foreach ($sections as $sec): ?>
                <?php if (($sec['items'] ?? []) === []) { continue; } ?>
            <div class="lms-nav__section">
                <p class="lms-nav__section-title"><?= $h($sec['label'] ?? '') ?></p>
                <ul class="lms-nav__list" role="list">
                    <?php foreach ($sec['items'] as $it): ?>
                    <?php
                    $on = !empty($it['_on']);
                    $badge = $it['badge'] ?? null;
                    $badgeStr = ($badge === null || $badge === '' || $badge === 0 || $badge === '0') ? '' : (string) $badge;
                    ?>
                    <li>
                        <a href="<?= $h($it['href']) ?>" class="lms-nav__item<?= $on ? ' is-active' : '' ?>"<?= $on ? ' aria-current="page"' : '' ?><?= !empty($it['hint']) ? ' title="' . $h($it['hint']) . '"' : '' ?>>
                            <span class="lms-nav__icon"><?= $icon((string) ($it['icon'] ?? '')) ?></span>
                            <span class="lms-nav__copy">
                                <span class="lms-nav__label"><?= $h($it['label']) ?></span>
                                <?php if (!empty($it['hint'])): ?>
                                <span class="lms-nav__hint"><?= $h($it['hint']) ?></span>
                                <?php endif; ?>
                            </span>
                            <?php if ($badgeStr !== ''): ?>
                            <span class="lms-nav__badge"><?= $h($badgeStr) ?></span>
                            <?php endif; ?>
                        </a>
                    </li>
                    <?php endforeach; ?>
                </ul>
            </div>
            <?php endforeach; ?>
        </nav>

        <?php if ($progress !== null): ?>
            <?php
            $max = max(0, (int) ($progress['max'] ?? 0));
            $val = max(0, min($max, (int) ($progress['value'] ?? 0)));
            $pct = $max > 0 ? (int) round($val * 100 / $max) : 0;
            ?>
        <div class="lms-nav__progress">
            <div class="lms-nav__progress-head">
                <span class="lms-nav__progress-label"><?= $h($progress['label'] ?? '') ?></span>
                <span class="lms-nav__progress-value"><?= $h($progress['caption'] ?? ($max > 0 ? $pct . ' %' : '—')) ?></span>
            </div>
            <div class="lms-nav__progress-track" role="progressbar" aria-valuemin="0" aria-valuemax="<?= $max ?>" aria-valuenow="<?= $val ?>" aria-label="<?= $h($progress['label'] ?? '') ?>">
                <span style="width: <?= $pct ?>%"></span>
            </div>
            <?php if (!empty($progress['text'])): ?>
            <p class="lms-nav__progress-text"><?= $h($progress['text']) ?></p>
            <?php endif; ?>
        </div>
        <?php endif; ?>

        <?php if ($cards !== []): ?>
        <div class="lms-nav__cards">
            <?php foreach ($cards as $c): ?>
            <a class="lms-nav__card" href="<?= $h($c['href']) ?>">
                <span class="lms-nav__card-kicker"><?= $h($c['kicker'] ?? '') ?></span>
                <span class="lms-nav__card-label"><?= $h($c['label'] ?? '') ?></span>
                <?php if (!empty($c['text'])): ?><span class="lms-nav__card-text"><?= $h($c['text']) ?></span><?php endif; ?>
            </a>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>

        <?php if ($foot !== null): ?>
        <a class="lms-nav__foot" href="<?= $h($foot['href']) ?>">
            <span class="lms-nav__icon"><?= $icon('back') ?></span>
            <span class="lms-nav__copy">
                <span class="lms-nav__label"><?= $h($foot['label']) ?></span>
                <?php if (!empty($foot['sub'])): ?><span class="lms-nav__hint"><?= $h($foot['sub']) ?></span><?php endif; ?>
            </span>
        </a>
        <?php endif; ?>
    </div>
</aside>
<?php if (empty($GLOBALS['__lmsNavJs'])): $GLOBALS['__lmsNavJs'] = true; ?>
<script src="<?= $h(asset_url('assets/js/lms-nav.js')) ?>" defer></script>
<?php endif; ?>
