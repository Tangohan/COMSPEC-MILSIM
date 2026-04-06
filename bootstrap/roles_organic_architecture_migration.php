<?php

declare(strict_types=1);

/**
 * Refonte organique des rôles : semantic_tier, affichage, postes, audit, packs.
 * Idempotent — safe à ré-exécuter.
 */
function run_roles_organic_architecture_migration(PDO $pdo): void
{
    $hasColumn = function (string $table, string $column) use ($pdo): bool {
        $st = $pdo->prepare(
            'SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ? LIMIT 1'
        );
        $st->execute([$table, $column]);

        return (bool) $st->fetchColumn();
    };

    $hasTable = function (string $table) use ($pdo): bool {
        $st = $pdo->prepare(
            'SELECT 1 FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? LIMIT 1'
        );
        $st->execute([$table]);

        return (bool) $st->fetchColumn();
    };

    // --- roles.name width ---
    try {
        $pdo->exec('ALTER TABLE roles MODIFY COLUMN name VARCHAR(160) NOT NULL');
    } catch (\Throwable $_) {
    }

    if (!$hasColumn('roles', 'semantic_tier')) {
        try {
            $pdo->exec("ALTER TABLE roles ADD COLUMN semantic_tier ENUM('authority','function','status') NOT NULL DEFAULT 'function' AFTER role_layer");
        } catch (Throwable $e) {
            echo '  [ATTENTION] roles.semantic_tier : ' . $e->getMessage() . "\n";
        }
    }
    if (!$hasColumn('roles', 'is_visual_only')) {
        try {
            $pdo->exec('ALTER TABLE roles ADD COLUMN is_visual_only TINYINT(1) NOT NULL DEFAULT 0 AFTER semantic_tier');
        } catch (Throwable $e) {
            echo '  [ATTENTION] roles.is_visual_only : ' . $e->getMessage() . "\n";
        }
    }
    if (!$hasColumn('roles', 'display_priority')) {
        try {
            $pdo->exec('ALTER TABLE roles ADD COLUMN display_priority INT NOT NULL DEFAULT 0 AFTER is_visual_only');
        } catch (Throwable $e) {
            echo '  [ATTENTION] roles.display_priority : ' . $e->getMessage() . "\n";
        }
    }
    if (!$hasColumn('roles', 'display_weight')) {
        try {
            $pdo->exec('ALTER TABLE roles ADD COLUMN display_weight INT NOT NULL DEFAULT 0 AFTER display_priority');
        } catch (Throwable $e) {
            echo '  [ATTENTION] roles.display_weight : ' . $e->getMessage() . "\n";
        }
    }
    if (!$hasColumn('roles', 'display_group')) {
        try {
            $pdo->exec('ALTER TABLE roles ADD COLUMN display_group INT NOT NULL DEFAULT 2 AFTER display_weight');
        } catch (Throwable $e) {
            echo '  [ATTENTION] roles.display_group : ' . $e->getMessage() . "\n";
        }
    }
    if (!$hasColumn('roles', 'parent_role_id')) {
        try {
            $pdo->exec('ALTER TABLE roles ADD COLUMN parent_role_id INT UNSIGNED DEFAULT NULL AFTER display_group');
        } catch (Throwable $e) {
            echo '  [ATTENTION] roles.parent_role_id : ' . $e->getMessage() . "\n";
        }
    }
    if (!$hasColumn('roles', 'badge_style')) {
        try {
            $pdo->exec('ALTER TABLE roles ADD COLUMN badge_style JSON DEFAULT NULL AFTER parent_role_id');
        } catch (Throwable $e) {
            echo '  [ATTENTION] roles.badge_style : ' . $e->getMessage() . "\n";
        }
    }
    if (!$hasColumn('roles', 'is_system_critical')) {
        try {
            $pdo->exec('ALTER TABLE roles ADD COLUMN is_system_critical TINYINT(1) NOT NULL DEFAULT 0 AFTER badge_style');
        } catch (Throwable $e) {
            echo '  [ATTENTION] roles.is_system_critical : ' . $e->getMessage() . "\n";
        }
    }

    try {
        $pdo->exec('ALTER TABLE roles ADD CONSTRAINT roles_parent_fk FOREIGN KEY (parent_role_id) REFERENCES roles (id) ON DELETE SET NULL');
    } catch (\Throwable $_) {
    }

    if (!$hasColumn('users', 'preferred_display_role_id')) {
        try {
            $pdo->exec('ALTER TABLE users ADD COLUMN preferred_display_role_id INT UNSIGNED DEFAULT NULL AFTER role_id');
        } catch (Throwable $e) {
            echo '  [ATTENTION] users.preferred_display_role_id : ' . $e->getMessage() . "\n";
        }
    }

    // --- positions ---
    if (!$hasTable('positions')) {
        try {
            $pdo->exec("CREATE TABLE positions (
                id INT UNSIGNED NOT NULL AUTO_INCREMENT,
                tenant_id INT UNSIGNED NOT NULL,
                name VARCHAR(160) NOT NULL,
                description VARCHAR(500) DEFAULT NULL,
                is_temporary TINYINT(1) NOT NULL DEFAULT 0,
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME DEFAULT NULL,
                PRIMARY KEY (id),
                KEY idx_positions_tenant (tenant_id),
                CONSTRAINT positions_tenant_fk FOREIGN KEY (tenant_id) REFERENCES tenants (id) ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
        } catch (Throwable $e) {
            echo '  [ATTENTION] positions table : ' . $e->getMessage() . "\n";
        }
    }

    if (!$hasTable('user_positions')) {
        try {
            $pdo->exec("CREATE TABLE user_positions (
                id INT UNSIGNED NOT NULL AUTO_INCREMENT,
                tenant_id INT UNSIGNED NOT NULL,
                user_id INT UNSIGNED NOT NULL,
                position_id INT UNSIGNED NOT NULL,
                starts_at DATE NOT NULL,
                ends_at DATE DEFAULT NULL,
                assigned_by INT UNSIGNED DEFAULT NULL,
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY (id),
                KEY idx_up_user (user_id),
                KEY idx_up_position (position_id),
                KEY idx_up_tenant (tenant_id),
                CONSTRAINT up_tenant_fk FOREIGN KEY (tenant_id) REFERENCES tenants (id) ON DELETE CASCADE,
                CONSTRAINT up_position_fk FOREIGN KEY (position_id) REFERENCES positions (id) ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
        } catch (Throwable $e) {
            echo '  [ATTENTION] user_positions table : ' . $e->getMessage() . "\n";
        }
    }

    if (!$hasTable('role_assignments_log')) {
        try {
            $pdo->exec("CREATE TABLE role_assignments_log (
                id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                tenant_id INT UNSIGNED NOT NULL,
                user_id INT UNSIGNED NOT NULL,
                role_id INT UNSIGNED NOT NULL,
                action ENUM('assign','revoke') NOT NULL,
                assigned_by INT UNSIGNED DEFAULT NULL,
                assigned_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                revoked_at DATETIME DEFAULT NULL,
                reason VARCHAR(500) DEFAULT NULL,
                PRIMARY KEY (id),
                KEY idx_ral_tenant_user (tenant_id, user_id),
                KEY idx_ral_role (role_id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
        } catch (Throwable $e) {
            echo '  [ATTENTION] role_assignments_log : ' . $e->getMessage() . "\n";
        }
    }

    if (!$hasTable('role_sets')) {
        try {
            $pdo->exec("CREATE TABLE role_sets (
                id INT UNSIGNED NOT NULL AUTO_INCREMENT,
                tenant_id INT UNSIGNED NOT NULL,
                name VARCHAR(160) NOT NULL,
                description VARCHAR(500) DEFAULT NULL,
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY (id),
                KEY idx_role_sets_tenant (tenant_id),
                CONSTRAINT role_sets_tenant_fk FOREIGN KEY (tenant_id) REFERENCES tenants (id) ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
        } catch (Throwable $e) {
            echo '  [ATTENTION] role_sets : ' . $e->getMessage() . "\n";
        }
    }

    if (!$hasTable('role_set_roles')) {
        try {
            $pdo->exec("CREATE TABLE role_set_roles (
                role_set_id INT UNSIGNED NOT NULL,
                role_id INT UNSIGNED NOT NULL,
                PRIMARY KEY (role_set_id, role_id),
                CONSTRAINT rsr_set_fk FOREIGN KEY (role_set_id) REFERENCES role_sets (id) ON DELETE CASCADE,
                CONSTRAINT rsr_role_fk FOREIGN KEY (role_id) REFERENCES roles (id) ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
        } catch (Throwable $e) {
            echo '  [ATTENTION] role_set_roles : ' . $e->getMessage() . "\n";
        }
    }

    // --- Data: canonical labels (all tenants + site) ---
    $canonical = [
        'community_owner' => [
            'name' => 'Gestionnaire d’organisation',
            'description' => 'Détient l’autorité stratégique sur l’entité. Gouvernance globale, hors périmètre technique plateforme.',
        ],
        'tenant_admin' => [
            'name' => 'Gestionnaire administratif d’organisation',
            'description' => 'Administration opérationnelle quotidienne : membres, contenus et paramètres internes.',
        ],
        'hr' => [
            'name' => 'Gestionnaire des ressources humaines de l’organisation',
            'description' => 'Suivi des effectifs, recrutements, statuts, parcours et conformité interne.',
        ],
        'forum_moderator' => [
            'name' => 'Officier responsable de la communication',
            'description' => 'Supervision des échanges, diffusion des annonces, modération et structuration du discours collectif.',
        ],
    ];
    foreach ($canonical as $slug => $lab) {
        try {
            $st = $pdo->prepare('UPDATE roles SET name = ?, description = ? WHERE slug = ?');
            $st->execute([$lab['name'], $lab['description'], $slug]);
        } catch (\Throwable $_) {
        }
    }
    try {
        $pdo->exec("UPDATE roles SET name = 'Gestionnaire de la plateforme', description = 'Administration transverse du système : accès global, maintenance, sécurité et supervision technique.' WHERE tenant_id IS NULL AND slug = 'site_super_admin'");
    } catch (\Throwable $_) {
    }

    // semantic_tier + display_group + flags for known slugs
    $tierMap = [
        'community_owner' => ['authority', 1, 0],
        'tenant_admin' => ['authority', 1, 0],
        'deputy_commander' => ['authority', 1, 0],
        'technical_admin' => ['authority', 1, 0],
        'site_super_admin' => ['authority', 1, 0],
    ];
    foreach ($tierMap as $slug => [$tier, $dg, $vis]) {
        try {
            $st = $pdo->prepare('UPDATE roles SET semantic_tier = ?, display_group = ?, is_visual_only = ? WHERE slug = ?');
            $st->execute([$tier, $dg, $vis, $slug]);
        } catch (\Throwable $_) {
        }
    }

    $functionSlugs = [
        'member', 'officer', 'forum_moderator', 'hr', 'recruiter', 'invite', 'instructor', 'medic', 'logistics', 'rto', 'probation',
        'operations_officer', 'training_officer', 'intelligence_officer', 'logistics_officer', 'discipline_officer',
        'recruitment_officer', 'security_officer', 'auditor_internal',
    ];
    $ph = implode(',', array_fill(0, count($functionSlugs), '?'));
    try {
        $st = $pdo->prepare("UPDATE roles SET semantic_tier = 'function', display_group = 2, is_visual_only = 0 WHERE slug IN ({$ph})");
        $st->execute($functionSlugs);
    } catch (\Throwable $_) {
    }

    $statusSlugs = [
        'founder', 'veteran', 'certified_instructor', 'elite_member', 'disciplinary_watch', 'probation_member',
        'suspended_status', 'honorary_member',
    ];
    $ph2 = implode(',', array_fill(0, count($statusSlugs), '?'));
    try {
        $st = $pdo->prepare("UPDATE roles SET semantic_tier = 'status', display_group = 3, is_visual_only = 1 WHERE slug IN ({$ph2})");
        $st->execute($statusSlugs);
    } catch (\Throwable $_) {
    }

    // Critical roles
    try {
        $pdo->exec("UPDATE roles SET is_system_critical = 1 WHERE slug IN ('community_owner','site_super_admin')");
    } catch (\Throwable $_) {
    }

    // --- Seed new org roles per tenant ---
    $tenants = $pdo->query('SELECT id FROM tenants')->fetchAll(PDO::FETCH_COLUMN) ?: [];
    $newFunctionRoles = [
        ['deputy_commander', 'Chef adjoint d’organisation', 'Adjoint à la direction : coordination et relais de gouvernance.', 'community'],
        ['operations_officer', 'Officier opérations', 'Planification et conduite des activités opérationnelles.', 'intra'],
        ['training_officer', 'Officier formation', 'Pilotage des parcours, qualifications et exercices.', 'intra'],
        ['intelligence_officer', 'Officier renseignement', 'Veille, synthèse et diffusion d’informations pertinentes.', 'intra'],
        ['logistics_officer', 'Officier logistique', 'Soutien matériel, stocks et chaîne d’approvisionnement.', 'intra'],
        ['discipline_officer', 'Officier discipline', 'Application du règlement intérieur et suivi des incidents.', 'intra'],
        ['recruitment_officer', 'Officier recrutement', 'Pipeline des candidatures et intégration des nouveaux membres.', 'intra'],
        ['security_officer', 'Officier sécurité', 'Sensibilisation, bonnes pratiques et coordination sécurité.', 'intra'],
        ['technical_admin', 'Administrateur technique local', 'Paramètres techniques et outils au sein de la communauté.', 'community'],
        ['auditor_internal', 'Contrôleur interne', 'Contrôles internes et recommandations d’amélioration.', 'intra'],
    ];
    $newStatusRoles = [
        ['founder', 'Fondateur', 'Reconnaissance historique de la création de l’entité.', 'intra'],
        ['veteran', 'Ancien de l’unité', 'Ancienneté et engagement reconnu au sein de l’organisation.', 'intra'],
        ['certified_instructor', 'Instructeur certifié', 'Compétence pédagogique validée.', 'intra'],
        ['elite_member', 'Membre d’élite', 'Performance ou engagement remarquable.', 'intra'],
        ['disciplinary_watch', 'Sous surveillance', 'Signal interne de suivi disciplinaire.', 'intra'],
        ['probation_member', 'En période probatoire', 'Intégration en cours sous surveillance renforcée.', 'intra'],
        ['suspended_status', 'Suspendu', 'Compte ou participation suspendue — visibilité sans accès effectif.', 'intra'],
        ['honorary_member', 'Membre d’honneur', 'Reconnaissance pour membres externes ou retraités.', 'intra'],
    ];

    $ins = $pdo->prepare(
        'INSERT INTO roles (tenant_id, name, slug, description, is_system, is_locked, role_layer, semantic_tier, is_visual_only, display_group, display_priority, created_at)
         VALUES (?, ?, ?, ?, 1, 0, ?, ?, ?, ?, ?, NOW())'
    );

    foreach ($tenants as $tid) {
        $tenantId = (int) $tid;
        foreach ($newFunctionRoles as $row) {
            [$slug, $name, $desc, $layer] = $row;
            $chk = $pdo->prepare('SELECT 1 FROM roles WHERE tenant_id = ? AND slug = ? LIMIT 1');
            $chk->execute([$tenantId, $slug]);
            if ($chk->fetchColumn()) {
                continue;
            }
            $tier = in_array($slug, ['deputy_commander', 'technical_admin'], true) ? 'authority' : 'function';
            $dg = $tier === 'authority' ? 1 : 2;
            $vis = 0;
            try {
                $ins->execute([$tenantId, $name, $slug, $desc, $layer, $tier, $vis, $dg, 50]);
            } catch (\Throwable $_) {
            }
        }
        foreach ($newStatusRoles as $row) {
            [$slug, $name, $desc, $layer] = $row;
            $chk = $pdo->prepare('SELECT 1 FROM roles WHERE tenant_id = ? AND slug = ? LIMIT 1');
            $chk->execute([$tenantId, $slug]);
            if ($chk->fetchColumn()) {
                continue;
            }
            try {
                $ins->execute([$tenantId, $name, $slug, $desc, $layer, 'status', 1, 3, 80]);
            } catch (\Throwable $_) {
            }
        }
    }

    // Default pack "État-major opérations" if empty
    foreach ($tenants as $tid) {
        $tenantId = (int) $tid;
        $cnt = $pdo->prepare('SELECT COUNT(*) FROM role_sets WHERE tenant_id = ?');
        $cnt->execute([$tenantId]);
        if ((int) $cnt->fetchColumn() > 0) {
            continue;
        }
        $slugIds = ['operations_officer', 'intelligence_officer', 'logistics_officer'];
        $ids = [];
        foreach ($slugIds as $s) {
            $q = $pdo->prepare('SELECT id FROM roles WHERE tenant_id = ? AND slug = ? LIMIT 1');
            $q->execute([$tenantId, $s]);
            $rid = $q->fetchColumn();
            if ($rid) {
                $ids[] = (int) $rid;
            }
        }
        if ($ids === []) {
            continue;
        }
        try {
            $pdo->prepare('INSERT INTO role_sets (tenant_id, name, description, created_at) VALUES (?, ?, ?, NOW())')
                ->execute([$tenantId, 'État-major opérations', 'Pack : opérations, renseignement et logistique.']);
            $setId = (int) $pdo->lastInsertId();
            $insR = $pdo->prepare('INSERT IGNORE INTO role_set_roles (role_set_id, role_id) VALUES (?, ?)');
            foreach ($ids as $rid) {
                $insR->execute([$setId, $rid]);
            }
        } catch (\Throwable $_) {
        }
    }

    try {
        $pdo->exec(
            "UPDATE roles c INNER JOIN roles p ON p.tenant_id = c.tenant_id AND p.slug = 'community_owner'
             SET c.parent_role_id = p.id
             WHERE c.slug = 'deputy_commander' AND (c.parent_role_id IS NULL OR c.parent_role_id = 0)"
        );
    } catch (\Throwable $_) {
    }

    echo "  [OK] roles_organic_architecture_migration\n";
}
