<?php

declare(strict_types=1);

use App\Core\Database;
use App\Repositories\RoleRepository;
use App\Repositories\UserRepository;
use App\Services\Rbac\CommunityAccessCollapseService;

/**
 * Trois profils d’accès communauté : Membre, Ressources humaines, Gestionnaire.
 * Convertit les attributions existantes ; ne vide pas le catalogue technique des habilitations
 * ni les emplois inscrits sur les dossiers.
 */
return static function (PDO $pdo): void {
    if (!class_exists(CommunityAccessCollapseService::class)) {
        echo "  [ATTENTION] community_access_profiles_v1 : classe absente.\n";

        return;
    }
    echo "Conversion des accès communauté (Membre / RH / Gestionnaire)...\n";
    $service = new CommunityAccessCollapseService(
        Database::getPdo(),
        new RoleRepository(),
        new UserRepository()
    );
    $service->collapseAllTenants();
    echo "community_access_profiles_v1 : OK.\n";
};
