<?php
declare(strict_types=1);

$profiles = is_array($accessProfiles ?? null) ? $accessProfiles : \App\Services\Rbac\CommunityAccessProfiles::definitions();
$byKey = is_array($accessRolesByKey ?? null) ? $accessRolesByKey : [];
?>
<div class="eff-roles-page">
<section class="eff-page-head">
    <p class="eff-page-kicker">Accès à la communauté</p>
    <h1 class="eff-page-title">Trois niveaux d’accès</h1>
    <p class="eff-page-lead">
        Chaque membre a un seul niveau : Membre, Ressources humaines ou Gestionnaire.
        Les emplois du dossier (radio, médic, chef d’équipe…) décrivent la fonction, pas les droits.
    </p>
</section>

<div class="eff-role-grid eff-role-grid--cards" role="list" aria-label="Niveaux d’accès">
    <?php foreach ($profiles as $profile):
        $profileKey = (string) ($profile['key'] ?? '');
        $row = $byKey[$profileKey] ?? null;
        $rid = (int) ($row['id'] ?? 0);
        $membersUrl = effectifs_workspace_url() . ($rid > 0 ? '?role_id=' . $rid : '');
        ?>
        <article class="eff-role-card" role="listitem">
            <header class="eff-role-card__head">
                <div class="eff-role-card__titles">
                    <h2 class="eff-role-card__name"><?= htmlspecialchars((string) ($profile['name'] ?? ''), ENT_QUOTES, 'UTF-8') ?></h2>
                </div>
            </header>
            <p class="eff-role-card__desc"><?= htmlspecialchars((string) ($profile['description'] ?? ''), ENT_QUOTES, 'UTF-8') ?></p>
            <div class="eff-role-card__actions">
                <a class="eff-act" href="<?= htmlspecialchars($membersUrl, ENT_QUOTES, 'UTF-8') ?>">Voir les titulaires</a>
            </div>
        </article>
    <?php endforeach; ?>
</div>
</div>
