<?php

declare(strict_types=1);

/**
 * Rattrapage des niveaux d’accès de chaque communauté (Membre, RH, Gestionnaire).
 *
 * Les anciennes bascules de profil (Bureau des effectifs, Carte ATAK) et la première mise en place
 * du forum donnaient l’administration, les invitations, la gestion ATAK / effectifs et la modération
 * du forum à tous les niveaux, Membre compris. Ce rattrapage :
 *  - ajoute à chaque niveau son jeu standard d’habilitations s’il en manque ;
 *  - retire au seul niveau Membre ce qui relève de la RH ou du Gestionnaire.
 * Le niveau RH n’est pas réduit ici (réappliquer le profil depuis Paramètres → Profil le fait).
 * Passe une seule fois (marque dans athena_once_migrations) : ensuite, chaque communauté garde
 * les retouches faites par son Gestionnaire.
 */
return static function (PDO $pdo): void {
    $marker = 'tenant_access_profiles_alignment_v1';
    $pdo->exec('CREATE TABLE IF NOT EXISTS athena_once_migrations (
        name VARCHAR(120) NOT NULL,
        applied_at DATETIME NOT NULL,
        PRIMARY KEY (name)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci');
    $done = $pdo->prepare('SELECT 1 FROM athena_once_migrations WHERE name = ? LIMIT 1');
    $done->execute([$marker]);
    if ($done->fetchColumn()) {
        echo "  [OK] niveaux d’accès déjà alignés\n";

        return;
    }
    $st = $pdo->query("SELECT id, tenant_type FROM tenants WHERE id > 0");
    if ($st === false) {
        return;
    }
    $granted = 0;
    $revoked = 0;
    $tenants = 0;
    foreach ($st->fetchAll(PDO::FETCH_ASSOC) ?: [] as $row) {
        $tenantId = (int) ($row['id'] ?? 0);
        if ($tenantId < 1) {
            continue;
        }
        try {
            $r = \App\Services\Community\TenantSeedHelper::alignAccessProfilesForType(
                $pdo,
                $tenantId,
                (string) ($row['tenant_type'] ?? 'full'),
                false
            );
            $granted += $r['granted'];
            $revoked += $r['revoked'];
            $tenants++;
        } catch (Throwable $e) {
            echo "  [ATTENTION] communauté #{$tenantId} : " . $e->getMessage() . "\n";
        }
    }
    $pdo->prepare('INSERT IGNORE INTO athena_once_migrations (name, applied_at) VALUES (?, NOW())')->execute([$marker]);
    echo "  [OK] niveaux d’accès alignés sur {$tenants} communauté(s) : {$granted} habilitation(s) ajoutée(s), {$revoked} retirée(s) au niveau Membre\n";
};
