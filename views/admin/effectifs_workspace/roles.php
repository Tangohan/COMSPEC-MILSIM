<?php
declare(strict_types=1);

$roles = is_array($orgRoles ?? null) ? $orgRoles : [];
$canManageRoles = (bool) ($canManageRoles ?? false);

$countCommunity = 0;
$countIntra = 0;
foreach ($roles as $r) {
    $layer = (string) ($r['role_layer'] ?? '');
    if ($layer === 'community') {
        $countCommunity++;
    } elseif ($layer === 'intra') {
        $countIntra++;
    }
}
$totalRoles = count($roles);

$layerLabel = static function (string $raw): string {
    return match ($raw) {
        'community' => 'Communauté',
        'intra' => 'Intra-unité',
        default => $raw !== '' ? $raw : '—',
    };
};
$layerBadgeClass = static function (string $raw): string {
    return match ($raw) {
        'community' => 'eff-badge--community',
        'intra' => 'eff-badge--intra',
        default => 'eff-badge--muted',
    };
};
$initials = static function (string $name): string {
    $name = trim($name);
    if ($name === '') {
        return 'Rô';
    }
    $parts = preg_split('/\s+/u', $name, -1, PREG_SPLIT_NO_EMPTY);
    if ($parts !== false && count($parts) >= 2) {
        return mb_strtoupper(mb_substr($parts[0], 0, 1, 'UTF-8') . mb_substr($parts[1], 0, 1, 'UTF-8'), 'UTF-8');
    }

    return mb_strtoupper(mb_substr($name, 0, 2, 'UTF-8'), 'UTF-8');
};

$iconShield = '<svg width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M12 3l7 3v5c0 5-3.5 8.5-7 10-3.5-1.5-7-5-7-10V6l7-3z"/><path stroke-linecap="round" stroke-linejoin="round" d="M9.5 12l1.8 1.8L15 10"/></svg>';
$iconKey = '<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.85" aria-hidden="true"><circle cx="8" cy="15" r="4"/><path stroke-linecap="round" stroke-linejoin="round" d="M11.5 12.5L21 3m0 0h-4m4 0v4"/></svg>';
$iconUsers = '<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.85" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M17 21v-2a4 4 0 00-4-4H5a4 4 0 00-4 4v2"/><circle cx="9" cy="7" r="4"/><path stroke-linecap="round" stroke-linejoin="round" d="M23 21v-2a4 4 0 00-3-3.87M16 3.13a4 4 0 010 7.75"/></svg>';
$iconMap = '<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.85" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M3 6l6-2 6 2 6-2v14l-6 2-6-2-6 2V6z"/><path stroke-linecap="round" d="M9 4v14M15 6v14"/></svg>';
?>
<div class="eff-roles-page">
<section class="eff-page-head">
    <p class="eff-page-kicker">Gouvernance</p>
    <h1 class="eff-page-title">Rôles d’accès</h1>
    <p class="eff-page-lead">
        Les rôles d’accès définissent ce que chaque membre peut consulter ou modifier. Ils sont distincts des fonctions opérationnelles et des grades.
        Consultez la liste ci-dessous, puis ouvrez l’édition détaillée si vous êtes habilité.
    </p>
</section>

<div class="eff-metrics" aria-label="Indicateurs rôles">
    <div class="eff-metric">
        <p class="eff-metric__k">Rôles</p>
        <p class="eff-metric__v"><?= $totalRoles ?></p>
    </div>
    <div class="eff-metric">
        <p class="eff-metric__k">Communauté</p>
        <p class="eff-metric__v"><?= $countCommunity ?></p>
    </div>
    <div class="eff-metric">
        <p class="eff-metric__k">Intra-unité</p>
        <p class="eff-metric__v"><?= $countIntra ?></p>
    </div>
    <a class="eff-metric eff-metric--link" href="<?= htmlspecialchars(effectifs_workspace_url() . '?sans_role=1', ENT_QUOTES, 'UTF-8') ?>">
        <p class="eff-metric__k">À traiter</p>
        <p class="eff-metric__v eff-metric__v--link">Sans rôle</p>
    </a>
</div>

<?php if ($canManageRoles): ?>
<div class="eff-toolbar" role="toolbar" aria-label="Actions sur les rôles">
    <div class="eff-toolbar__lead">
        <p class="eff-toolbar__title">Pilotage</p>
        <p class="eff-toolbar__sub">Éditez les rôles et leurs droits. Gérez séparément les fonctions opérationnelles dans le référentiel dédié.</p>
    </div>
    <div class="eff-toolbar__actions">
        <a class="eff-btn eff-btn--primary" href="<?= htmlspecialchars(url('back-office/roles'), ENT_QUOTES, 'UTF-8') ?>">Gérer les rôles</a>
        <a class="eff-btn eff-btn--ghost" href="<?= htmlspecialchars(url('back-office/roles-functions'), ENT_QUOTES, 'UTF-8') ?>">Référentiel des fonctions</a>
        <a class="eff-btn eff-btn--ghost" href="<?= htmlspecialchars(url('back-office/ressources/effectifs/droits'), ENT_QUOTES, 'UTF-8') ?>">Profils de droits</a>
    </div>
</div>
<?php endif; ?>

<?php if ($roles === []): ?>
    <div class="eff-empty">
        <div class="eff-empty__icon" aria-hidden="true"><?= $iconShield ?></div>
        <h2 class="eff-empty__title">Aucun rôle défini</h2>
        <p class="eff-empty__text">
            Cette communauté n’a pas encore de rôles. Créez-en pour attribuer des droits aux membres
            et clarifier qui peut administrer, former ou recruter.
        </p>
        <?php if ($canManageRoles): ?>
            <div class="eff-empty__actions">
                <a class="eff-btn eff-btn--primary" href="<?= htmlspecialchars(url('back-office/roles'), ENT_QUOTES, 'UTF-8') ?>">Créer un rôle</a>
                <a class="eff-btn eff-btn--ghost" href="<?= htmlspecialchars(url('back-office/roles-functions'), ENT_QUOTES, 'UTF-8') ?>">Ouvrir la toile</a>
            </div>
        <?php endif; ?>
    </div>
<?php else: ?>
    <div class="eff-list-meta">
        <p class="eff-list-count">
            <strong><?= $totalRoles ?></strong>
            rôle<?= $totalRoles > 1 ? 's' : '' ?> communautaire<?= $totalRoles > 1 ? 's' : '' ?>
        </p>
    </div>

    <div class="eff-role-grid eff-role-grid--cards" role="list" aria-label="Liste des rôles">
        <?php foreach ($roles as $role):
            $rid = (int) ($role['id'] ?? 0);
            $name = trim((string) ($role['name'] ?? ''));
            $layer = (string) ($role['role_layer'] ?? '');
            $desc = trim((string) ($role['description'] ?? ''));
            $isSystem = !empty($role['is_system']);
            $isLocked = !empty($role['is_locked']);
            $membersUrl = effectifs_workspace_url() . ($rid > 0 ? '?role_id=' . $rid : '');
            $permsUrl = $rid > 0 ? url('back-office/roles/' . $rid . '/permissions') : '';
            ?>
            <article class="eff-role-card" role="listitem">
                <header class="eff-role-card__head">
                    <span class="eff-avatar" aria-hidden="true"><?= htmlspecialchars($initials($name), ENT_QUOTES, 'UTF-8') ?></span>
                    <div class="eff-role-card__titles">
                        <h2 class="eff-role-card__name"><?= htmlspecialchars($name !== '' ? $name : 'Rôle sans intitulé', ENT_QUOTES, 'UTF-8') ?></h2>
                        <div class="eff-role-card__badges">
                            <span class="eff-badge <?= htmlspecialchars($layerBadgeClass($layer), ENT_QUOTES, 'UTF-8') ?>">
                                <span class="eff-badge__dot" aria-hidden="true"></span>
                                <?= htmlspecialchars($layerLabel($layer), ENT_QUOTES, 'UTF-8') ?>
                            </span>
                            <?php if ($isSystem): ?>
                                <span class="eff-badge eff-badge--ref">Référentiel</span>
                            <?php endif; ?>
                            <?php if ($isLocked): ?>
                                <span class="eff-badge eff-badge--locked">Protégé</span>
                            <?php endif; ?>
                        </div>
                    </div>
                </header>

                <p class="eff-role-card__desc<?= $desc === '' ? ' eff-role-card__desc--empty' : '' ?>">
                    <?= htmlspecialchars($desc !== '' ? $desc : 'Aucune description renseignée.', ENT_QUOTES, 'UTF-8') ?>
                </p>

                <footer class="eff-role-card__foot">
                    <div class="eff-actions">
                        <?php if ($rid > 0): ?>
                            <a class="eff-act" href="<?= htmlspecialchars($membersUrl, ENT_QUOTES, 'UTF-8') ?>">
                                <?= $iconUsers ?>
                                Membres
                            </a>
                        <?php endif; ?>
                        <?php if ($canManageRoles && $rid > 0): ?>
                            <a class="eff-act eff-act--primary" href="<?= htmlspecialchars($permsUrl, ENT_QUOTES, 'UTF-8') ?>">
                                <?= $iconKey ?>
                                Droits
                            </a>
                        <?php endif; ?>
                    </div>
                </footer>
            </article>
        <?php endforeach; ?>
    </div>

    <aside class="eff-hint-panel" aria-label="Rappel">
        <span class="eff-hint-panel__ico" aria-hidden="true"><?= $iconMap ?></span>
        <div>
            <p class="eff-hint-panel__title">Deux couches, un même principe</p>
            <p class="eff-hint-panel__text">
                Les rôles <strong>Communauté</strong> portent sur l’ensemble du groupe.
                Les rôles <strong>Intra-unité</strong> concernent le fonctionnement au sein d’une unité.
            </p>
        </div>
    </aside>
<?php endif; ?>
</div>
