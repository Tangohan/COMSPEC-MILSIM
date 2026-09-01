<?php
declare(strict_types=1);

/**
 * Barre supérieure back-office ATHENA — fil d'Ariane, recherche, alertes, action.
 *
 * Variables optionnelles (depuis contrôleur ou vue) :
 * - $boPageGroup (string) — segment parent du fil d'Ariane
 * - $boPageTitle (string) — titre courant
 * - $boPageAction (string|null) — libellé bouton d'action
 * - $boPageActionUrl (string|null) — URL du bouton d'action
 * - $boTopAlerts (list<array{label:string,n:int,href?:string,dot?:string,fg?:string,bg?:string,bd?:string,title?:string}>)
 */

$h = static fn (string $v): string => htmlspecialchars($v, ENT_QUOTES, 'UTF-8');

$pageGroup = isset($boPageGroup) ? trim((string) $boPageGroup) : 'Administration';
$pageTitle = isset($boPageTitle) ? trim((string) $boPageTitle) : (isset($title) ? trim((string) $title) : 'Back-office');
$pageAction = isset($boPageAction) ? trim((string) $boPageAction) : '';
$pageActionUrl = isset($boPageActionUrl) ? trim((string) $boPageActionUrl) : '';

$topAlerts = [];
if (isset($boTopAlerts) && is_array($boTopAlerts)) {
    $topAlerts = $boTopAlerts;
} else {
    $boRecN = (int) ($boBadges['recruitments_submitted'] ?? 0);
    $boModN = (int) ($boBadges['forum_moderation_total'] ?? 0);
    if (!empty($boBadges['show_staff_recruitment']) && $boRecN > 0) {
        $topAlerts[] = [
            'label' => 'DOSSIERS',
            'n' => $boRecN,
            'dot' => '#1e6fbf',
            'fg' => '#1e4f80',
            'bg' => '#eaf2fb',
            'bd' => '#c9dcf0',
            'title' => $boRecN . ' candidature(s) à instruire',
            'href' => url('back-office/recruitments'),
        ];
    }
    if ($boModN > 0) {
        $topAlerts[] = [
            'label' => 'FORUM',
            'n' => $boModN,
            'dot' => '#c72e2e',
            'fg' => '#a32222',
            'bg' => '#fdecec',
            'bd' => '#f6cccc',
            'title' => $boModN . ' signalement(s) forum',
            'href' => url('back-office/forum-moderation'),
        ];
    }
}
?>
<header class="ath-topbar" role="banner">
    <div class="ath-topbar__inner">
        <nav class="ath-topbar__crumb" aria-label="Fil d'Ariane">
            <span><?= $h($pageGroup) ?></span>
            <span class="ath-topbar__crumb-sep" aria-hidden="true">/</span>
            <span class="ath-topbar__crumb-current"><?= $h($pageTitle) ?></span>
        </nav>
        <div class="ath-topbar__spacer" aria-hidden="true"></div>
        <label class="ath-topbar__search" for="ath-top-search">
            <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="#8c979b" stroke-width="2.2" aria-hidden="true"><circle cx="11" cy="11" r="7"></circle><path d="m20 20-3.2-3.2"></path></svg>
            <input id="ath-top-search" type="search" placeholder="Pages, membres, documents…" autocomplete="off" spellcheck="false" aria-label="Rechercher dans le back-office" readonly>
            <span class="ath-topbar__search-kbd" aria-hidden="true">⌘K</span>
        </label>
        <?php if ($topAlerts !== []): ?>
        <div class="ath-topbar__alerts" role="list">
            <?php foreach ($topAlerts as $alert): ?>
                <?php
                $href = trim((string) ($alert['href'] ?? ''));
                $tag = $h((string) ($alert['label'] ?? ''));
                $n = (int) ($alert['n'] ?? 0);
                if ($n <= 0) {
                    continue;
                }
                $style = sprintf(
                    'border-color:%s;background:%s;',
                    $h((string) ($alert['bd'] ?? '#e3e8ea')),
                    $h((string) ($alert['bg'] ?? '#fff'))
                );
                $labelStyle = 'color:' . $h((string) ($alert['fg'] ?? '#3c474c')) . ';';
                $dotStyle = 'background:' . $h((string) ($alert['dot'] ?? '#12d18e')) . ';';
                $titleAttr = !empty($alert['title']) ? ' title="' . $h((string) $alert['title']) . '"' : '';
                ?>
                <?php if ($href !== ''): ?>
                <a href="<?= $h($href) ?>" class="ath-topbar__alert" style="<?= $style ?>" role="listitem"<?= $titleAttr ?>>
                <?php else: ?>
                <div class="ath-topbar__alert" style="<?= $style ?>" role="listitem"<?= $titleAttr ?>>
                <?php endif; ?>
                    <span class="ath-topbar__alert-dot" style="<?= $dotStyle ?>" aria-hidden="true"></span>
                    <span class="ath-topbar__alert-label" style="<?= $labelStyle ?>"><?= $tag ?></span>
                    <span class="ath-topbar__alert-n" style="color:<?= $h((string) ($alert['dot'] ?? '#12d18e')) ?>"><?= $h($n > 99 ? '99+' : (string) $n) ?></span>
                <?php if ($href !== ''): ?>
                </a>
                <?php else: ?>
                </div>
                <?php endif; ?>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>
        <a href="<?= $h(url('dashboard')) ?>" class="ath-topbar__portal" title="Retour au tableau de bord">
            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M10.5 19.5 3 12m0 0 7.5-7.5M3 12h18"></path></svg>
            <span>Tableau de bord</span>
        </a>
        <?php if ($pageAction !== ''): ?>
        <div class="ath-topbar__divider" aria-hidden="true"></div>
        <?php if ($pageActionUrl !== ''): ?>
        <a href="<?= $h($pageActionUrl) ?>" class="ath-topbar__action"><?= $h($pageAction) ?></a>
        <?php else: ?>
        <button type="button" class="ath-topbar__action"><?= $h($pageAction) ?></button>
        <?php endif; ?>
        <?php endif; ?>
    </div>
</header>
