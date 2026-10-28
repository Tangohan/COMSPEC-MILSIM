<?php
declare(strict_types=1);

/**
 * Gestion allégée du profil « Carte ATAK » : les raccourcis qui restent utiles sans effectifs ni recrutement
 * (Overwatch, matériel, relais, membres et accès, réglages du mod). Chaque tuile suit les droits et le profil.
 */

$bkH = static fn (string $v): string => htmlspecialchars($v, ENT_QUOTES, 'UTF-8');
$bkTenantId = (int) \App\Core\Session::get('tenant_id');
$bkType = function_exists('navigation_current_tenant_type')
    ? navigation_current_tenant_type()
    : \App\Services\Community\TenantTypeConfig::TYPE_FULL;
if ($bkType !== \App\Services\Community\TenantTypeConfig::TYPE_ATAK || $bkTenantId < 1) {
    return;
}

$bkAllowed = static function (string $path) use ($bkType): bool {
    if (!\App\Services\Community\TenantTypeConfig::uriAllowed($bkType, $path)) {
        return false;
    }

    return !function_exists('back_office_nav_href_permission_allowed') || back_office_nav_href_permission_allowed(url($path));
};

$bkMapOpen = \App\Support\AtakPlanAccess::allows($bkTenantId);

$bkGroups = [
    [
        'title' => 'Terrain',
        'items' => [
            ['label' => 'Overwatch', 'hint' => 'La carte web en temps réel.', 'path' => 'atak'],
            ['label' => 'Poste de situation', 'hint' => 'Qui est en liaison, alertes et derniers comptes rendus.', 'path' => 'back-office/atak'],
            ['label' => 'Contrôle de mission', 'hint' => 'Maintenance, relais imposés et liaison exigée.', 'path' => 'back-office/atak/controle-serveur'],
        ],
    ],
    [
        'title' => 'Matériel',
        'items' => [
            ['label' => 'Parc ATAK des membres', 'hint' => 'Téléphones, compromissions, certificats et blocages.', 'path' => 'back-office/atak/parc-atak'],
            ['label' => 'Parc de terminaux', 'hint' => 'Modèles de terminaux et réglages de réalisme.', 'path' => 'back-office/atak/realisme'],
            ['label' => 'Équipement des joueurs', 'hint' => 'Ce que chaque joueur porte, remonté par le mod.', 'path' => 'back-office/equipement-joueurs'],
            ['label' => 'Kits par rôle', 'hint' => 'Kits préparés et publiés comme tenues.', 'path' => 'back-office/kits-roles'],
        ],
    ],
    [
        'title' => 'Relais',
        'items' => [
            ['label' => 'Réseau de relais', 'hint' => 'Relais posés en jeu, état et couverture.', 'path' => 'back-office/atak/relays-network'],
        ],
    ],
    [
        'title' => 'Membres',
        'items' => [
            ['label' => 'Liste des membres', 'hint' => 'Comptes, statut et fiche de chaque joueur.', 'path' => 'back-office/ressources/effectifs'],
            ['label' => 'Invitations', 'hint' => 'Faire rejoindre de nouveaux joueurs.', 'path' => 'back-office/invitations'],
            ['label' => 'Rôles et droits', 'hint' => 'Membre, RH, Gestionnaire : qui peut faire quoi.', 'path' => 'back-office/ressources/effectifs/roles'],
        ],
    ],
    [
        'title' => 'Mod et serveur',
        'items' => [
            ['label' => 'Configuration ATAK', 'hint' => 'Cartes, fonctions des téléphones, roleplay.', 'path' => 'back-office/ressources/atak-config'],
            ['label' => 'Mod ATAK', 'hint' => 'Téléchargement du mod et clé du serveur.', 'path' => 'back-office/ressources/atak-mod'],
            ['label' => 'Serveurs de jeu', 'hint' => 'Serveurs Arma reliés à la communauté.', 'path' => 'back-office/ressources/serveurs-de-jeu'],
        ],
    ],
];

foreach ($bkGroups as $gi => $group) {
    $bkGroups[$gi]['items'] = array_values(array_filter($group['items'], static fn (array $it): bool => $bkAllowed($it['path'])));
    if ($bkGroups[$gi]['items'] === []) {
        unset($bkGroups[$gi]);
    }
}
if ($bkGroups === []) {
    return;
}
?>
<style>
.bo-atak-kit{margin:0 0 18px;padding:18px;border-radius:14px}
.bo-atak-kit__head{display:flex;flex-wrap:wrap;align-items:baseline;justify-content:space-between;gap:6px 16px;margin-bottom:12px}
.bo-atak-kit__kicker{font-family:var(--ath-mono,monospace);font-size:10.5px;letter-spacing:.14em;color:var(--ath-subtle);text-transform:uppercase}
.bo-atak-kit__title{margin:2px 0 0;font-size:18px;font-weight:700;color:var(--ath-ink)}
.bo-atak-kit__lead{margin:4px 0 0;font-size:13px;color:var(--ath-muted)}
.bo-atak-kit__plan{margin:0 0 14px;padding:10px 12px;border-radius:10px;font-size:13px;line-height:1.45;background:var(--ath-danger-bg,#fdecec);color:var(--ath-danger,#c72e2e);border:1px solid currentColor}
.bo-atak-kit__groups{display:grid;grid-template-columns:repeat(auto-fit,minmax(210px,1fr));gap:14px}
.bo-atak-kit__group-title{margin:0 0 6px;font-family:var(--ath-mono,monospace);font-size:10.5px;letter-spacing:.12em;text-transform:uppercase;color:var(--ath-accent)}
.bo-atak-kit__list{display:flex;flex-direction:column;gap:6px;margin:0;padding:0;list-style:none}
.bo-atak-kit__link{display:block;padding:9px 11px;border-radius:9px;border:1px solid var(--ath-line);background:var(--ath-bg);text-decoration:none;color:var(--ath-ink);transition:border-color .15s ease,transform .15s ease}
.bo-atak-kit__link:hover,.bo-atak-kit__link:focus-visible{border-color:var(--ath-accent);transform:translateY(-1px)}
.bo-atak-kit__link strong{display:block;font-size:13.5px;font-weight:650}
.bo-atak-kit__link span{display:block;margin-top:2px;font-size:12px;color:var(--ath-muted)}
</style>
<section class="bo-atak-kit ath-card ath-rise" aria-labelledby="bo-atak-kit-title">
    <div class="bo-atak-kit__head">
        <div>
            <div class="bo-atak-kit__kicker">Profil Carte ATAK · Gestion allégée</div>
            <h2 class="bo-atak-kit__title" id="bo-atak-kit-title">L’essentiel pour vos joueurs</h2>
            <p class="bo-atak-kit__lead">Overwatch, matériel, relais et membres : tout ce qui sert sans effectifs ni recrutement.</p>
        </div>
    </div>
    <?php if (!$bkMapOpen): ?>
    <p class="bo-atak-kit__plan" role="status">Votre formule n’inclut pas la carte complète : Overwatch et les photos, vidéos et drones du jeu sont fermés. Positions, comptes rendus et briefing restent reçus si la formule a la liaison de base. Contactez l’équipe Athena pour ouvrir la carte.</p>
    <?php endif; ?>
    <div class="bo-atak-kit__groups">
        <?php foreach ($bkGroups as $group): ?>
        <div>
            <h3 class="bo-atak-kit__group-title"><?= $bkH((string) $group['title']) ?></h3>
            <ul class="bo-atak-kit__list">
                <?php foreach ($group['items'] as $item): ?>
                <li>
                    <a class="bo-atak-kit__link" href="<?= $bkH(url((string) $item['path'])) ?>">
                        <strong><?= $bkH((string) $item['label']) ?></strong>
                        <span><?= $bkH((string) $item['hint']) ?></span>
                    </a>
                </li>
                <?php endforeach; ?>
            </ul>
        </div>
        <?php endforeach; ?>
    </div>
</section>
