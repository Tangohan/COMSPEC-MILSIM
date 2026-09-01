<?php

declare(strict_types=1);

/**
 * Moteur de configuration post-mise à jour des communautés (tenants historiques).
 * Tables de référentiel + état lazy (absence de ligne = PENDING).
 */
return static function (PDO $pdo): void {
    $colExists = static function (PDO $pdo, string $table, string $column): bool {
        $st = $pdo->prepare(
            'SELECT 1 FROM information_schema.COLUMNS
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ? LIMIT 1'
        );
        $st->execute([$table, $column]);

        return (bool) $st->fetchColumn();
    };

    if (!$colExists($pdo, 'tenants', 'configuration_schema_version')) {
        $pdo->exec(
            'ALTER TABLE tenants
             ADD COLUMN configuration_schema_version INT UNSIGNED NOT NULL DEFAULT 0'
        );
    }
    if (!$colExists($pdo, 'tenants', 'created_with_app_version')) {
        $pdo->exec(
            "ALTER TABLE tenants
             ADD COLUMN created_with_app_version VARCHAR(32) NULL DEFAULT NULL"
        );
    }

    $pdo->exec(
        <<<'SQL'
CREATE TABLE IF NOT EXISTS system_configuration_updates (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    code VARCHAR(64) NOT NULL,
    version VARCHAR(16) NOT NULL DEFAULT '1',
    title VARCHAR(191) NOT NULL,
    description TEXT NOT NULL,
    configuration_level ENUM('informative','recommended','required') NOT NULL DEFAULT 'recommended',
    configure_path VARCHAR(255) NOT NULL DEFAULT '',
    estimate_minutes SMALLINT UNSIGNED NULL,
    mandatory TINYINT(1) NOT NULL DEFAULT 0,
    blocking TINYINT(1) NOT NULL DEFAULT 0,
    dismissible TINYINT(1) NOT NULL DEFAULT 1,
    depends_on_json JSON NULL,
    sort_order INT NOT NULL DEFAULT 100,
    released_at DATETIME NULL,
    active TINYINT(1) NOT NULL DEFAULT 1,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_scu_code (code),
    KEY idx_scu_active_sort (active, sort_order)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL
    );

    $pdo->exec(
        <<<'SQL'
CREATE TABLE IF NOT EXISTS tenant_configuration_updates (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    tenant_id INT UNSIGNED NOT NULL,
    update_id INT UNSIGNED NOT NULL,
    status ENUM('PENDING','SEEN','IN_PROGRESS','COMPLETED','DISMISSED','NOT_APPLICABLE') NOT NULL DEFAULT 'PENDING',
    progress_step VARCHAR(64) NULL,
    progress_data JSON NULL,
    metadata JSON NULL,
    started_at DATETIME NULL,
    completed_at DATETIME NULL,
    dismissed_at DATETIME NULL,
    remind_at DATETIME NULL,
    completed_by_user_id INT UNSIGNED NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_tcu_tenant_update (tenant_id, update_id),
    KEY idx_tcu_tenant_status (tenant_id, status),
    KEY idx_tcu_remind (remind_at),
    CONSTRAINT fk_tcu_update FOREIGN KEY (update_id) REFERENCES system_configuration_updates (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL
    );

    // Seed / upsert des évolutions connues (le catalogue PHP reste la source d’éligibilité).
    $seeds = [
        [
            'code' => 'MILITARY_AFFILIATION_V1',
            'version' => '2',
            'title' => 'Rattachement militaire',
            'description' => 'Indiquez si votre communauté représente une unité réelle du référentiel (pays, service, commandement, régiment, commando…) ou un cadre fictif. La recherche couvre aussi les alias (ex. Hubert, 1RPIMA).',
            'configuration_level' => 'recommended',
            'configure_path' => 'back-office/organisation/parametres#affiliation',
            'estimate_minutes' => 3,
            'mandatory' => 0,
            'blocking' => 0,
            'dismissible' => 1,
            'sort_order' => 10,
        ],
        [
            'code' => 'TIMEZONE_V1',
            'version' => '1',
            'title' => 'Fuseau horaire',
            'description' => 'Indiquez le fuseau utilisé pour les événements, présences et communications de votre organisation.',
            'configuration_level' => 'recommended',
            'configure_path' => 'back-office/organisation/parametres#timezone',
            'estimate_minutes' => 1,
            'mandatory' => 0,
            'blocking' => 0,
            'dismissible' => 1,
            'sort_order' => 20,
        ],
        [
            'code' => 'GRADE_SYSTEM_V1',
            'version' => '1',
            'title' => 'Référentiel de grades',
            'description' => 'Choisissez le système de grades utilisé par vos effectifs (français, américain, ou personnalisé).',
            'configuration_level' => 'recommended',
            'configure_path' => 'back-office/referentiels/grades',
            'estimate_minutes' => 2,
            'mandatory' => 0,
            'blocking' => 0,
            'dismissible' => 1,
            'sort_order' => 30,
        ],
        [
            'code' => 'ORGANIZATION_STRUCTURE_V1',
            'version' => '1',
            'title' => 'Structure organisationnelle',
            'description' => 'Organisez vos effectifs en unités (compagnies, sections, groupes, équipes).',
            'configuration_level' => 'recommended',
            'configure_path' => 'back-office/organisation/structure',
            'estimate_minutes' => 5,
            'mandatory' => 0,
            'blocking' => 0,
            'dismissible' => 1,
            'sort_order' => 40,
        ],
        [
            'code' => 'MISSION_PLANNING_V1',
            'version' => '1',
            'title' => 'Planification de mission',
            'description' => 'Préparez l’organisation de combat avant la session (postes, affectations, documents d’ordre), puis suivez l’exécution une fois les joueurs connectés.',
            'configuration_level' => 'informative',
            'configure_path' => 'back-office/planification',
            'estimate_minutes' => 8,
            'mandatory' => 0,
            'blocking' => 0,
            'dismissible' => 1,
            'sort_order' => 45,
        ],
        [
            'code' => 'PUBLIC_PROFILE_V1',
            'version' => '1',
            'title' => 'Vitrine publique',
            'description' => 'Complétez la présentation publique de votre organisation (doctrine, spécialités, accroche).',
            'configuration_level' => 'informative',
            'configure_path' => 'back-office/community/presentation',
            'estimate_minutes' => 5,
            'mandatory' => 0,
            'blocking' => 0,
            'dismissible' => 1,
            'sort_order' => 50,
        ],
        [
            'code' => 'ENLISTMENT_CUSTOM_QUESTIONS_V1',
            'version' => '1',
            'title' => 'Questions et refus automatiques du dossier candidature',
            'description' => 'Ajoutez des listes déroulantes au formulaire d’enrôlement et, si besoin, des conditions de refus automatique selon les réponses.',
            'configuration_level' => 'informative',
            'configure_path' => 'back-office/community/presentation#pack-milsim-editor',
            'estimate_minutes' => 8,
            'mandatory' => 0,
            'blocking' => 0,
            'dismissible' => 1,
            'sort_order' => 55,
        ],
        [
            'code' => 'ATAK_CONFIGURATION_V1',
            'version' => '1',
            'title' => 'Configuration ATAK / Overwatch',
            'description' => 'Paramétrez la liaison opérationnelle ATAK (modules, accès, options) pour votre communauté.',
            'configuration_level' => 'recommended',
            'configure_path' => 'admin/atak-config',
            'estimate_minutes' => 4,
            'mandatory' => 0,
            'blocking' => 0,
            'dismissible' => 1,
            'sort_order' => 60,
        ],
        [
            'code' => 'SSE_PERSONS_V1',
            'version' => '1',
            'title' => 'Renseignement interpersonnel (SSE)',
            'description' => 'Activez le module de renseignement interpersonnel pour enregistrer des personnes sur le terrain (identité, photo du visage) et les consulter au poste de commandement.',
            'configuration_level' => 'informative',
            'configure_path' => 'admin/atak-config#bridge-modules',
            'estimate_minutes' => 3,
            'mandatory' => 0,
            'blocking' => 0,
            'dismissible' => 1,
            'sort_order' => 65,
        ],
        [
            'code' => 'SSE_PORTAL_V1',
            'version' => '1',
            'title' => 'Portail de renseignement classifié',
            'description' => 'Le commandement peut délivrer des codes d’accès temporaires au portail de renseignement interpersonnel (dossiers d’affaire, croisements, export). Vérifiez les rôles et créez un premier code si besoin.',
            'configuration_level' => 'recommended',
            'configure_path' => 'back-office/renseignement/codes',
            'estimate_minutes' => 5,
            'mandatory' => 0,
            'blocking' => 0,
            'dismissible' => 1,
            'sort_order' => 66,
        ],
        [
            'code' => 'SSE_DIGITAL_LAB_V1',
            'version' => '1',
            'title' => 'Laboratoire numérique (exploitation des supports)',
            'description' => 'Le portail SSE inclut désormais l’exploitation numérique : enregistrement des supports saisis, acquisitions simulées, artefacts et propositions de rapprochement. Ouvrez le laboratoire pour prendre connaissance du module.',
            'configuration_level' => 'informative',
            'configure_path' => 'atak/sse/exploitation-numerique',
            'estimate_minutes' => 5,
            'mandatory' => 0,
            'blocking' => 0,
            'dismissible' => 1,
            'sort_order' => 67,
        ],
        [
            'code' => 'SSE_DOMEX_QUEUE_V1',
            'version' => '1',
            'title' => 'File de renseignement numérique à exploiter',
            'description' => 'Les supports de mission (ordinateur, téléphone, radio…) peuvent désormais déposer des paquets de renseignement dans une file dédiée du laboratoire. Ouvrez « À exploiter » pour rattacher ou écarter ces contenus — un paquet n’est jamais une preuve.',
            'configuration_level' => 'informative',
            'configure_path' => 'atak/sse/exploitation-numerique/a-exploiter',
            'estimate_minutes' => 4,
            'mandatory' => 0,
            'blocking' => 0,
            'dismissible' => 1,
            'sort_order' => 68,
        ],
        [
            'code' => 'SSE_DOMEX_ZEUS_LIVE_V1',
            'version' => '1',
            'title' => 'Renseignement ajouté en cours de mission',
            'description' => 'Le chef de mission peut désormais ajouter un renseignement, changer le palier d’accès d’un support, ou poser un point sur la carte pendant la partie. Ces ajouts arrivent dans la file « À exploiter » — un point posé en mission apparaît sur la carte du bureau, pas sur celle des joueurs.',
            'configuration_level' => 'informative',
            'configure_path' => 'atak/sse/exploitation-numerique/a-exploiter',
            'estimate_minutes' => 3,
            'mandatory' => 0,
            'blocking' => 0,
            'dismissible' => 1,
            'sort_order' => 69,
        ],
        [
            'code' => 'ATAK_INTEL_SCRAMBLE_V1',
            'version' => '1',
            'title' => 'Données chiffrées ATAK',
            'description' => 'Décidez si le journal radio, les ordres et la carte masquent les informations lorsque le certificat d’un appareil est invalide, ou si un terminal est capturé. À activer dans le mode roleplay ATAK.',
            'configuration_level' => 'informative',
            'configure_path' => 'admin/atak/roleplay#intel-scramble',
            'estimate_minutes' => 3,
            'mandatory' => 0,
            'blocking' => 0,
            'dismissible' => 1,
            'sort_order' => 70,
        ],
        [
            'code' => 'ATAK_PHOTO_HUD_V1',
            'version' => '1',
            'title' => 'Bandeau d’identification des photos terrain',
            'description' => 'Les photos reçues du terrain peuvent porter un bandeau du type caméra-piéton (unité, indicatif, grille, horodatage). Vérifiez le libellé de votre unité et les informations à afficher.',
            'configuration_level' => 'recommended',
            'configure_path' => 'admin/atak-config#photo-hud',
            'estimate_minutes' => 2,
            'mandatory' => 0,
            'blocking' => 0,
            'dismissible' => 1,
            'sort_order' => 72,
        ],
        [
            'code' => 'AAR_CUSTOM_TEMPLATES_V1',
            'version' => '1',
            'title' => 'Modèles de debriefing',
            'description' => 'Les gestionnaires peuvent maintenant composer des questionnaires de compte rendu (questions courtes, listes, cases à cocher, texte libre) pour standardiser les retours d’opération.',
            'configuration_level' => 'informative',
            'configure_path' => 'back-office/atak/comptes-rendus/modeles',
            'estimate_minutes' => 8,
            'mandatory' => 0,
            'blocking' => 0,
            'dismissible' => 1,
            'sort_order' => 74,
        ],
        [
            'code' => 'SENIORITY_REAL_TENURE_V1',
            'version' => '1',
            'title' => 'Ancienneté réelle de l’organisation',
            'description' => 'Si votre unité existait avant Athena, indiquez la date de création. Vous pouvez aussi saisir, pour chaque membre, son arrivée réelle avant l’ouverture du site, depuis Effectifs.',
            'configuration_level' => 'recommended',
            'configure_path' => 'back-office/organisation/anciennete',
            'estimate_minutes' => 5,
            'mandatory' => 0,
            'blocking' => 0,
            'dismissible' => 1,
            'sort_order' => 76,
        ],
        [
            'code' => 'ORGANIZATION_CATALOG_V1',
            'version' => '1',
            'title' => 'Catalogue de l’organisation',
            'description' => 'Des modèles officiels (organigramme, grades, fonctions et rôles) peuvent être copiés dans votre communauté, sans rien partager avec une autre. Vous pouvez aussi enregistrer un modèle de votre organisation actuelle.',
            'configuration_level' => 'recommended',
            'configure_path' => 'back-office/organisation/catalogue',
            'estimate_minutes' => 5,
            'mandatory' => 0,
            'blocking' => 0,
            'dismissible' => 1,
            'sort_order' => 77,
        ],
        [
            'code' => 'OVERWATCH_GAME_AUTH_V1',
            'version' => '1',
            'title' => 'Fenêtre de connexion Overwatch',
            'description' => 'Les opérateurs s’identifient désormais avec leur compte Athena dans Arma. Vous pouvez personnaliser l’image, le message d’accueil, les méthodes de connexion et les fonctions Overwatch autorisées.',
            'configuration_level' => 'informative',
            'configure_path' => 'admin/atak-config#overwatch-game-experience',
            'estimate_minutes' => 5,
            'mandatory' => 0,
            'blocking' => 0,
            'dismissible' => 1,
            'sort_order' => 78,
        ],
        [
            'code' => 'OPERATIONS_WORKSPACE_V1',
            'version' => '1',
            'title' => 'Espaces opérationnels',
            'description' => 'Les opérations disposent désormais d’un dossier unique : plan, renseignement, ordres et vue terrain. Ouvrez un premier espace pour y rattacher votre prochaine mission.',
            'configuration_level' => 'informative',
            'configure_path' => 'operations',
            'estimate_minutes' => 8,
            'mandatory' => 0,
            'blocking' => 0,
            'dismissible' => 1,
            'sort_order' => 79,
        ],
    ];

    $upsert = $pdo->prepare(
        'INSERT INTO system_configuration_updates
            (code, version, title, description, configuration_level, configure_path, estimate_minutes,
             mandatory, blocking, dismissible, sort_order, released_at, active)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), 1)
         ON DUPLICATE KEY UPDATE
            version = VALUES(version),
            title = VALUES(title),
            description = VALUES(description),
            configuration_level = VALUES(configuration_level),
            configure_path = VALUES(configure_path),
            estimate_minutes = VALUES(estimate_minutes),
            mandatory = VALUES(mandatory),
            blocking = VALUES(blocking),
            dismissible = VALUES(dismissible),
            sort_order = VALUES(sort_order),
            active = 1,
            updated_at = NOW()'
    );

    foreach ($seeds as $row) {
        $upsert->execute([
            $row['code'],
            $row['version'],
            $row['title'],
            $row['description'],
            $row['configuration_level'],
            $row['configure_path'],
            $row['estimate_minutes'],
            $row['mandatory'],
            $row['blocking'],
            $row['dismissible'],
            $row['sort_order'],
        ]);
    }

    // Communautés déjà créées via le wizard v2 : marquer comme non concernées / terminées
    // uniquement ce qui est déjà satisfait — le service affine à la première visite.
    echo "configuration_updates : tables + seed OK.\n";
};
