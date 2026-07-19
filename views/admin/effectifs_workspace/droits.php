<?php
declare(strict_types=1);
$canManageRoles = (bool) ($canManageRoles ?? false);
$canAccessManagement = (bool) ($canAccessManagement ?? false);
?>
<section class="eff-page-head">
    <p class="eff-page-kicker">Habilitations</p>
    <h1 class="eff-page-title">Droits d’accès</h1>
    <p class="eff-page-lead">
        Appliquez des profils de permissions cohérents, ou ouvrez la gestion avancée des accès.
        Les libellés restent métier : aucun identifiant technique n’est exposé ici.
    </p>
</section>

<div class="eff-cards">
    <?php if ($canManageRoles): ?>
    <a class="eff-card" href="<?= htmlspecialchars(url('back-office/roles/presets'), ENT_QUOTES, 'UTF-8') ?>">
        <h3>Profils de permissions prêts</h3>
        <p>Appliquez en une fois un ensemble cohérent de droits sur un rôle existant (membre, formation, recrutement…).</p>
        <p class="eff-card-cta">Ouvrir →</p>
    </a>
    <a class="eff-card" href="<?= htmlspecialchars(url('back-office/roles'), ENT_QUOTES, 'UTF-8') ?>">
        <h3>Rôles et habilitations</h3>
        <p>Consultez chaque rôle et le détail des autorisations associées.</p>
        <p class="eff-card-cta">Ouvrir →</p>
    </a>
    <?php endif; ?>
    <?php if ($canAccessManagement): ?>
    <a class="eff-card" href="<?= htmlspecialchars(url('back-office/access-management'), ENT_QUOTES, 'UTF-8') ?>">
        <h3>Gestion avancée des accès</h3>
        <p>Pilotage fin des permissions pour les situations particulières de votre communauté.</p>
        <p class="eff-card-cta">Ouvrir →</p>
    </a>
    <?php endif; ?>
    <a class="eff-card" href="<?= htmlspecialchars(effectifs_workspace_url(), ENT_QUOTES, 'UTF-8') ?>">
        <h3>Revenir au tableur</h3>
        <p>Vérifiez rapidement qui porte quels rôles sur la liste des effectifs.</p>
        <p class="eff-card-cta">Tableur →</p>
    </a>
</div>
