<?php

declare(strict_types=1);

/**
 * Menu « Espaces » de la barre Athena : une carte par espace, rangées par groupe (App\Support\EspacesMenu).
 * Partagé par la barre des membres (athena_caverne_header.php), celle des pages d'accès (athena_header_guest.php)
 * et le menu mobile.
 *
 * Variables attendues :
 *   $espacesGroups  liste de groupes issue de EspacesMenu::groups()
 *   $espacesMode    'bar' (bouton + panneau déroulant) ou 'drawer' (cartes seules, dans le menu mobile)
 *   $espacesLabel   libellé du bouton ; $espacesAria  nom accessible du panneau
 */
$espH = static fn (string $v): string => htmlspecialchars($v, ENT_QUOTES, 'UTF-8');
$espT = static fn (string $fr): string => $espH(function_exists('i18n_phrase') ? i18n_phrase('nav', $fr) : $fr);
$espMode = ($espacesMode ?? 'bar') === 'drawer' ? 'drawer' : 'bar';
$espGroups = is_array($espacesGroups ?? null) ? $espacesGroups : [];
if ($espGroups === []) {
    return;
}
$espUid = $espMode === 'drawer' ? 'esp-d' : 'esp-b';
$espItemRole = $espMode === 'bar' ? ' role="menuitem" tabindex="-1"' : '';
$espCards = static function () use ($espGroups, $espH, $espT, $espUid, $espMode, $espItemRole): void {
    ?>
    <div class="esp-groups">
        <?php foreach ($espGroups as $group): ?>
            <?php $gid = $espUid . '-' . $espH((string) $group['id']); ?>
            <section class="esp-group" role="group" aria-labelledby="<?= $gid ?>">
                <p class="esp-group__title" id="<?= $gid ?>"><?= $espT((string) $group['label']) ?></p>
                <div class="esp-group__cards">
                    <?php foreach ($group['items'] as $item): ?>
                        <?php
                        $tone = (string) ($item['tone'] ?? '');
                        $on = !empty($item['active']);
                        $desc = (string) ($item['desc'] ?? '');
                        ?>
                        <a href="<?= $espH((string) $item['href']) ?>"
                           class="esp-card<?= $tone !== '' ? ' esp-card--' . $espH($tone) : '' ?><?= $on ? ' is-active' : '' ?>"
                           data-esp-key="<?= $espH((string) $item['abbr']) ?>"
                           title="<?= $espH($desc) ?>"<?= $espItemRole ?><?= $on ? ' aria-current="page"' : '' ?>>
                            <span class="esp-card__icon" aria-hidden="true">
                                <svg viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><?= $item['icon'] ?></svg>
                            </span>
                            <span class="esp-card__body">
                                <span class="esp-card__row">
                                    <strong class="esp-card__name"><?= $espT((string) $item['label']) ?></strong>
                                    <?php if ($on): ?>
                                        <span class="esp-pill esp-pill--here"><svg viewBox="0 0 24 24" width="12" height="12" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="m5 12.5 4.5 4.5L19 7.5"/></svg><?= $espT('Ici') ?></span>
                                    <?php elseif (!empty($item['status'])): ?>
                                        <span class="esp-pill"><?= $espT((string) $item['status']) ?></span>
                                    <?php endif; ?>
                                </span>
                                <?php if ($desc !== ''): ?><span class="esp-card__desc"><?= $espT($desc) ?></span><?php endif; ?>
                            </span>
                        </a>
                    <?php endforeach; ?>
                </div>
            </section>
        <?php endforeach; ?>
    </div>
    <?php
};
?>
<?php if ($espMode === 'drawer'): ?>
    <div class="esp esp--drawer">
        <?php $espCards(); ?>
    </div>
<?php else: ?>
    <div class="athena-header__menu relative hidden md:block">
        <button
            type="button"
            class="athena-header__menu-trigger"
            data-athena-toggle="espaces"
            aria-expanded="false"
            aria-haspopup="menu"
            aria-controls="athena-header-espaces"
        >
            <svg class="athena-header__btn-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linejoin="round" stroke-width="1.8" d="M4 4h6v6H4zM14 4h6v6h-6zM4 14h6v6H4zM14 14h6v6h-6z"/></svg>
            <span><?= $espH((string) ($espacesLabel ?? 'Espaces')) ?></span>
            <svg class="athena-header__chevron" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.4" d="m6 9 6 6 6-6"/>
            </svg>
        </button>
        <div class="athena-header__panel athena-header__panel--espaces esp hidden" id="athena-header-espaces" data-athena-panel="espaces" role="menu" aria-label="<?= $espH((string) ($espacesAria ?? 'Espaces')) ?>">
            <?php $espCards(); ?>
        </div>
    </div>
<?php endif; ?>
