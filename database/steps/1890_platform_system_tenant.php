<?php

declare(strict_types=1);

/**
 * Étape « Communauté système « default », droits de base et premier administrateur » (Référentiels).
 *
 * Exécutée par run-migrations.php dans la portée du pipeline : $pdo, $root, $migrationRunner, $migrationFlush,
 * $migrationEnsurePdo et les helpers de database/steps/_context.php sont disponibles.
 */

$run_documents_seed = function (PDO $pdo, int $tenantId): void {
    $docPermSlugs = ['documents.view', 'documents.upload', 'documents.update', 'documents.archive', 'documents.download_sensitive'];
    $stmt = $pdo->prepare('SELECT id FROM permissions WHERE tenant_id = ? AND ' . \App\Support\SqlText::equalsLiteral($pdo, 'slug', 'documents.view') . ' LIMIT 1');
    $stmt->execute([$tenantId]);
    $docPermIds = [];
    if ($stmt->fetch()) {
        foreach ($docPermSlugs as $slug) {
            $s = $pdo->prepare('SELECT id FROM permissions WHERE tenant_id = ? AND ' . \App\Support\SqlText::equals($pdo, 'slug') . ' LIMIT 1');
            $s->execute([$tenantId, $slug]);
            $id = $s->fetch(PDO::FETCH_ASSOC)['id'] ?? null;
            if ($id) {
                $docPermIds[$slug] = (int) $id;
            }
        }
    } else {
        $docPerms = [
            ['documents.view', 'Voir les documents', 'documents'],
            ['documents.upload', 'Uploader des documents', 'documents'],
            ['documents.update', 'Modifier les documents', 'documents'],
            ['documents.archive', 'Archiver les documents', 'documents'],
            ['documents.download_sensitive', 'Télécharger documents sensibles', 'documents'],
        ];
        $insPerm = $pdo->prepare("INSERT INTO permissions (tenant_id, name, slug, module, created_at) VALUES (?, ?, ?, ?, NOW())");
        foreach ($docPerms as $p) {
            $insPerm->execute([$tenantId, $p[1], $p[0], $p[2]]);
            $docPermIds[$p[0]] = (int) $pdo->lastInsertId();
        }
    }
    $adminRole = $pdo->prepare('SELECT id FROM roles WHERE tenant_id = ? AND ' . \App\Support\SqlText::equalsLiteral($pdo, 'slug', 'tenant_admin') . ' LIMIT 1');
    $adminRole->execute([$tenantId]);
    $adminRoleId = (int) ($adminRole->fetch(PDO::FETCH_ASSOC)['id'] ?? 0);
    if ($adminRoleId) {
        $link = $pdo->prepare("INSERT IGNORE INTO role_permissions (role_id, permission_id) VALUES (?, ?)");
        foreach ($docPermIds as $pid) {
            $link->execute([$adminRoleId, $pid]);
        }
    }
    $memberRole = $pdo->prepare('SELECT id FROM roles WHERE tenant_id = ? AND ' . \App\Support\SqlText::equalsLiteral($pdo, 'slug', 'member') . ' LIMIT 1');
    $memberRole->execute([$tenantId]);
    $memberRoleId = (int) ($memberRole->fetch(PDO::FETCH_ASSOC)['id'] ?? 0);
    if ($memberRoleId && isset($docPermIds['documents.view'])) {
        $pdo->prepare("INSERT IGNORE INTO role_permissions (role_id, permission_id) VALUES (?, ?)")->execute([$memberRoleId, $docPermIds['documents.view']]);
    }
    $officerRole = $pdo->prepare('SELECT id FROM roles WHERE tenant_id = ? AND ' . \App\Support\SqlText::equalsLiteral($pdo, 'slug', 'officer') . ' LIMIT 1');
    $officerRole->execute([$tenantId]);
    $officerRoleId = (int) ($officerRole->fetch(PDO::FETCH_ASSOC)['id'] ?? 0);
    if (!$officerRoleId) {
        $pdo->prepare("INSERT INTO roles (tenant_id, name, slug, description, is_system, created_at) VALUES (?, 'Officier', 'officer', 'Encadrement, organisations, équipes', 1, NOW())")->execute([$tenantId]);
        $officerRoleId = (int) $pdo->lastInsertId();
    }
    if ($officerRoleId) {
        $link = $pdo->prepare("INSERT IGNORE INTO role_permissions (role_id, permission_id) VALUES (?, ?)");
        foreach (['documents.view', 'documents.upload', 'documents.update'] as $slug) {
            if (isset($docPermIds[$slug])) {
                $link->execute([$officerRoleId, $docPermIds[$slug]]);
            }
        }
    }
    echo "Permissions documents.* ajoutées et liées aux rôles.\n";

    $stmt = $pdo->prepare("SELECT 1 FROM document_categories WHERE tenant_id = ? LIMIT 1");
    $stmt->execute([$tenantId]);
    if (!$stmt->fetch()) {
        foreach ([['Doctrine / SOP', 'doctrine'], ['Manuel opérateur', 'manuel'], ['Fiche équipement', 'fiche-equipement'], ['Rapport mission', 'rapport'], ['Média pédagogique', 'media']] as $i => $c) {
            $pdo->prepare("INSERT INTO document_categories (tenant_id, name, slug, color, created_at) VALUES (?, ?, ?, ?, NOW())")->execute([$tenantId, $c[0], $c[1], ['emerald', 'blue', 'amber', 'slate', 'violet'][$i] ?? null]);
        }
        echo "Catégories documents par défaut créées.\n";
    }
    $stmt = $pdo->prepare("SELECT 1 FROM equipment_classes WHERE tenant_id = ? LIMIT 1");
    $stmt->execute([$tenantId]);
    if (!$stmt->fetch()) {
        foreach ([['Radio', 'radio', 'radio'], ['Optique', 'optic', 'optic'], ['Armement', 'weapon', 'weapon'], ['Véhicule', 'vehicle', 'vehicle'], ['Drone', 'drone', 'drone'], ['Médical', 'medical', 'medical']] as $c) {
            $pdo->prepare("INSERT INTO equipment_classes (tenant_id, name, slug, category, description, created_at) VALUES (?, ?, ?, ?, NULL, NOW())")->execute([$tenantId, $c[0], $c[1], $c[2]]);
        }
        echo "Classes d'équipement par défaut créées.\n";
    }
};

// ----- Seed : tenant + role + grade + admin (ou mise à jour) -----
$stmt = $pdo->query('SELECT id FROM tenants WHERE ' . \App\Support\SqlText::equalsLiteral($pdo, 'slug', 'default') . ' LIMIT 1');
if ($stmt && $stmt->fetch()) {
    echo "Seed déjà appliqué (tenant default existe).\n";
    $row = $pdo->query('SELECT id FROM tenants WHERE ' . \App\Support\SqlText::equalsLiteral($pdo, 'slug', 'default') . ' LIMIT 1')->fetch(PDO::FETCH_ASSOC);
    $tenantId = (int) $row['id'];

    $stmt = $pdo->query("SELECT 1 FROM personnel_admin_panels WHERE tenant_id = $tenantId LIMIT 1");
    if ($stmt && !$stmt->fetch()) {
        echo "Création des panneaux admin par défaut...\n";
        $panels = [
            ['État civil', 'etat-civil', 'Identité et état civil', 10],
            ['Affectation', 'affectation', 'Unité, poste, affectation', 20],
            ['Formation', 'formation', 'Parcours et qualifications', 30],
            ['Sécurité / Clearance', 'securite', 'Niveaux de sécurité et habilitations', 40],
            ['Santé / Aptitude', 'sante', 'Aptitude médicale et restrictions', 50],
            ['Références / Notes', 'references-notes', 'Références et notes administratives', 60],
        ];
        foreach ($panels as $p) {
            $pdo->prepare("INSERT INTO personnel_admin_panels (tenant_id, name, slug, description, display_order) VALUES (?, ?, ?, ?, ?)")
                ->execute([$tenantId, $p[0], $p[1], $p[2], $p[3]]);
        }
        $pdo->exec("INSERT IGNORE INTO tenant_matricule_config (tenant_id, prefix, format_pattern, next_number, updated_at) VALUES ($tenantId, 'ATH', '{prefix}-{seq:5}', 1, NOW())");
    }

    // S'assurer que la permission admin.access existe et est liée au rôle Administrator (menu Admin)
    $stmt = $pdo->prepare('SELECT id FROM permissions WHERE tenant_id = ? AND ' . \App\Support\SqlText::equalsLiteral($pdo, 'slug', 'admin.access') . ' LIMIT 1');
    $stmt->execute([$tenantId]);
    if (!$stmt->fetch()) {
        $pdo->prepare("INSERT INTO permissions (tenant_id, name, slug, module, created_at) VALUES (?, 'Accès administration', 'admin.access', 'admin', NOW())")
            ->execute([$tenantId]);
        $permId = (int) $pdo->lastInsertId();
        $adminRole = $pdo->prepare('SELECT id FROM roles WHERE tenant_id = ? AND ' . \App\Support\SqlText::equalsLiteral($pdo, 'slug', 'tenant_admin') . ' LIMIT 1');
        $adminRole->execute([$tenantId]);
        $adminRoleId = $adminRole->fetch(PDO::FETCH_ASSOC)['id'] ?? null;
        if ($adminRoleId && $permId) {
            $pdo->prepare("INSERT IGNORE INTO role_permissions (role_id, permission_id) VALUES (?, ?)")->execute([$adminRoleId, $permId]);
        }
        echo "Permission admin.access ajoutée et liée au rôle Administrator.\n";
    }

    // Permissions organisation (admin.system est réservé aux rôles site globaux)
    $stmt = $pdo->prepare('SELECT id FROM permissions WHERE tenant_id = ? AND ' . \App\Support\SqlText::equalsLiteral($pdo, 'slug', 'admin.organization') . ' LIMIT 1');
    $stmt->execute([$tenantId]);
    if (!$stmt->fetch()) {
        $pdo->prepare("INSERT INTO permissions (tenant_id, name, slug, module, scope, created_at) VALUES (?, 'Administration organisationnelle', 'admin.organization', 'admin', 'community', NOW())")->execute([$tenantId]);
    }
    $stmt = $pdo->prepare('SELECT id FROM roles WHERE tenant_id = ? AND ' . \App\Support\SqlText::equalsLiteral($pdo, 'slug', 'community_owner') . ' LIMIT 1');
    $stmt->execute([$tenantId]);
    if (!$stmt->fetch()) {
        $pdo->prepare("INSERT INTO roles (tenant_id, name, slug, description, is_system, is_locked, role_layer, created_at) VALUES (?, 'Propriétaire communauté', 'community_owner', 'Gouvernance complète de la communauté (sans administration plateforme)', 1, 1, 'community', NOW())")->execute([$tenantId]);
        $coId = (int) $pdo->lastInsertId();
        $ta = $pdo->prepare('SELECT id FROM roles WHERE tenant_id = ? AND ' . \App\Support\SqlText::equalsLiteral($pdo, 'slug', 'tenant_admin') . ' LIMIT 1');
        $ta->execute([$tenantId]);
        $taId = $ta->fetch(PDO::FETCH_ASSOC)['id'] ?? null;
        if ($taId) {
            $rp = $pdo->prepare('SELECT permission_id FROM role_permissions WHERE role_id = ?');
            $rp->execute([(int) $taId]);
            $link = $pdo->prepare('INSERT IGNORE INTO role_permissions (role_id, permission_id) VALUES (?, ?)');
            while ($row = $rp->fetch(PDO::FETCH_ASSOC)) {
                $pid = (int) $row['permission_id'];
                $chk = $pdo->prepare('SELECT slug FROM permissions WHERE id = ? LIMIT 1');
                $chk->execute([$pid]);
                $s = $chk->fetch(PDO::FETCH_ASSOC);
                if ($s && ($s['slug'] ?? '') === 'admin.system') {
                    continue;
                }
                $link->execute([$coId, $pid]);
            }
        }
        foreach (['admin.organization', 'admin.access'] as $permSlug) {
            $p = $pdo->prepare('SELECT id FROM permissions WHERE tenant_id = ? AND ' . \App\Support\SqlText::equals($pdo, 'slug') . ' LIMIT 1');
            $p->execute([$tenantId, $permSlug]);
            $permId = $p->fetch(PDO::FETCH_ASSOC)['id'] ?? null;
            if ($permId) {
                $pdo->prepare("INSERT IGNORE INTO role_permissions (role_id, permission_id) VALUES (?, ?)")->execute([$coId, $permId]);
            }
        }
        echo "Rôle community_owner créé (permissions communauté, sans admin.system tenant).\n";
    }
    $tenantAdminRole = $pdo->prepare('SELECT id FROM roles WHERE tenant_id = ? AND ' . \App\Support\SqlText::equalsLiteral($pdo, 'slug', 'tenant_admin') . ' LIMIT 1');
    $tenantAdminRole->execute([$tenantId]);
    $tenantAdminRoleId = $tenantAdminRole->fetch(PDO::FETCH_ASSOC)['id'] ?? null;
    $permOrg = $pdo->prepare('SELECT id FROM permissions WHERE tenant_id = ? AND ' . \App\Support\SqlText::equalsLiteral($pdo, 'slug', 'admin.organization') . ' LIMIT 1');
    $permOrg->execute([$tenantId]);
    $permOrgId = $permOrg->fetch(PDO::FETCH_ASSOC)['id'] ?? null;
    if ($tenantAdminRoleId && $permOrgId) {
        $pdo->prepare("INSERT IGNORE INTO role_permissions (role_id, permission_id) VALUES (?, ?)")->execute([$tenantAdminRoleId, $permOrgId]);
    }

    // Permissions Formation (LMS)
    $stmt = $pdo->prepare('SELECT id FROM permissions WHERE tenant_id = ? AND ' . \App\Support\SqlText::equalsLiteral($pdo, 'slug', 'training.view') . ' LIMIT 1');
    $stmt->execute([$tenantId]);
    if (!$stmt->fetch()) {
        foreach ([['training.view', 'Voir les formations', 'training'], ['training.manage', 'Gérer les formations', 'training'], ['training.assign', 'Assigner des formations', 'training']] as $p) {
            $pdo->prepare("INSERT INTO permissions (tenant_id, name, slug, module, created_at) VALUES (?, ?, ?, ?, NOW())")->execute([$tenantId, $p[1], $p[0], $p[2]]);
        }
        $adminRole = $pdo->prepare('SELECT id FROM roles WHERE tenant_id = ? AND ' . \App\Support\SqlText::equalsLiteral($pdo, 'slug', 'tenant_admin') . ' LIMIT 1');
        $adminRole->execute([$tenantId]);
        $adminRoleId = $adminRole->fetch(PDO::FETCH_ASSOC)['id'] ?? null;
        if ($adminRoleId) {
            $trainPerms = $pdo->prepare('SELECT id FROM permissions WHERE tenant_id = ? AND ' . \App\Support\SqlText::inLiterals($pdo, 'slug', ['training.view', 'training.manage', 'training.assign']));
            $trainPerms->execute([$tenantId]);
            while ($row = $trainPerms->fetch(PDO::FETCH_ASSOC)) {
                $pdo->prepare("INSERT IGNORE INTO role_permissions (role_id, permission_id) VALUES (?, ?)")->execute([$adminRoleId, $row['id']]);
            }
        }
        echo "Permissions training.* ajoutées.\n";
    }

    $run_documents_seed($pdo, $tenantId);

    // Permissions Bureau Courrier
    $stmt = $pdo->prepare('SELECT id FROM permissions WHERE tenant_id = ? AND ' . \App\Support\SqlText::equalsLiteral($pdo, 'slug', 'courrier.view') . ' LIMIT 1');
    $stmt->execute([$tenantId]);
    if (!$stmt->fetch()) {
        foreach ([['courrier.view', 'Voir le Bureau Courrier', 'courrier'], ['courrier.create', 'Créer des documents courrier', 'courrier'], ['courrier.validate', 'Valider des documents', 'courrier'], ['courrier.archive', 'Archiver des documents', 'courrier']] as $p) {
            $pdo->prepare("INSERT INTO permissions (tenant_id, name, slug, module, created_at) VALUES (?, ?, ?, ?, NOW())")->execute([$tenantId, $p[1], $p[0], $p[2]]);
        }
        $adminRole = $pdo->prepare('SELECT id FROM roles WHERE tenant_id = ? AND ' . \App\Support\SqlText::equalsLiteral($pdo, 'slug', 'tenant_admin') . ' LIMIT 1');
        $adminRole->execute([$tenantId]);
        $adminRoleId = $adminRole->fetch(PDO::FETCH_ASSOC)['id'] ?? null;
        if ($adminRoleId) {
            $courrierPerms = $pdo->prepare('SELECT id FROM permissions WHERE tenant_id = ? AND ' . \App\Support\SqlText::inLiterals($pdo, 'slug', ['courrier.view', 'courrier.create', 'courrier.validate', 'courrier.archive']));
            $courrierPerms->execute([$tenantId]);
            while ($row = $courrierPerms->fetch(PDO::FETCH_ASSOC)) {
                $pdo->prepare("INSERT IGNORE INTO role_permissions (role_id, permission_id) VALUES (?, ?)")->execute([$adminRoleId, $row['id']]);
            }
        }
        $memberRole = $pdo->prepare('SELECT id FROM roles WHERE tenant_id = ? AND ' . \App\Support\SqlText::equalsLiteral($pdo, 'slug', 'member') . ' LIMIT 1');
        $memberRole->execute([$tenantId]);
        $memberRoleId = $memberRole->fetch(PDO::FETCH_ASSOC)['id'] ?? null;
        if ($memberRoleId) {
            $pid = $pdo->prepare('SELECT id FROM permissions WHERE tenant_id = ? AND ' . \App\Support\SqlText::equalsLiteral($pdo, 'slug', 'courrier.view') . ' LIMIT 1');
            $pid->execute([$tenantId]);
            $pidRow = $pid->fetch(PDO::FETCH_ASSOC);
            if ($pidRow) {
                $pdo->prepare("INSERT IGNORE INTO role_permissions (role_id, permission_id) VALUES (?, ?)")->execute([$memberRoleId, $pidRow['id']]);
            }
        }
        echo "Permissions courrier.* ajoutées.\n";
    }

    try {
        $allTenants = $pdo->query('SELECT id FROM tenants');
        if ($allTenants) {
            while ($trow = $allTenants->fetch(PDO::FETCH_ASSOC)) {
                \App\Services\Community\TenantSeedHelper::ensureTenantPermissionCatalog($pdo, (int) $trow['id']);
            }
        }
        echo "Catalogue permissions tenant synchronisé.\n";
    } catch (Throwable $e) {
        echo '  [ATTENTION] Catalogue permissions : ' . $e->getMessage() . "\n";
        $migrationRunner->warn('Catalogue permissions', $e);
    }

    // LMS : type de leçon canvas (slides / modales)
    try {
        $tc = $pdo->query("SHOW TABLES LIKE 'training_lessons'");
        if ($tc && $tc->fetch()) {
            $col = $pdo->query("SHOW COLUMNS FROM training_lessons LIKE 'lesson_type'");
            $row = $col ? $col->fetch(PDO::FETCH_ASSOC) : null;
            if ($row && is_string($row['Type'] ?? null) && stripos($row['Type'], 'canvas') === false) {
                $pdo->exec(
                    "ALTER TABLE training_lessons MODIFY COLUMN lesson_type ENUM("
                    . "'richtext','video','pdf','audio','scorm_like','checklist','external_link','canvas'"
                    . ") NOT NULL DEFAULT 'richtext'"
                );
                echo "training_lessons.lesson_type : valeur « canvas » ajoutée.\n";
            }
            $col2 = $pdo->query("SHOW COLUMNS FROM training_lessons LIKE 'lesson_type'");
            $row2 = $col2 ? $col2->fetch(PDO::FETCH_ASSOC) : null;
            if ($row2 && is_string($row2['Type'] ?? null) && stripos($row2['Type'], 'quiz') === false) {
                $pdo->exec(
                    "ALTER TABLE training_lessons MODIFY COLUMN lesson_type ENUM("
                    . "'richtext','video','pdf','audio','scorm_like','checklist','external_link','canvas',"
                    . "'quiz','modals','video_embed','video_integrated','slideshow'"
                    . ") NOT NULL DEFAULT 'richtext'"
                );
                echo "training_lessons.lesson_type : quiz, modals, video_embed, video_integrated, slideshow ajoutés.\n";
            }
        }
    } catch (Throwable $e) {
        echo '  [ATTENTION] training_lessons canvas : ' . $e->getMessage() . "\n";
        $migrationRunner->warn('training_lessons canvas', $e);
    }

    echo "Seed déjà présent — poursuite des migrations annexes (Discord, ATAK, etc.).\n";
    $migrationFlush();
} else {
    echo "Insertion du tenant et admin par défaut...\n";
    $pdo->exec("INSERT INTO tenants (name, slug, created_at, updated_at) VALUES ('Pas d''organisation', 'default', NOW(), NOW())");
    $tenantId = (int) $pdo->lastInsertId();

    $pdo->exec("INSERT INTO roles (tenant_id, name, slug, description, is_system, is_locked, role_layer, created_at) VALUES ($tenantId, 'Propriétaire communauté', 'community_owner', 'Gouvernance complète de la communauté', 1, 1, 'community', NOW())");
    $communityOwnerRoleId = (int) $pdo->lastInsertId();
    $pdo->exec("INSERT INTO roles (tenant_id, name, slug, description, is_system, is_locked, role_layer, created_at) VALUES ($tenantId, 'Administrator', 'tenant_admin', 'Accès administration organisation', 1, 0, 'community', NOW())");
    $tenantAdminRoleId = (int) $pdo->lastInsertId();
    $roleId = $communityOwnerRoleId;

    // La table `grades` peut être l'ancien modèle tenant (tenant_id/name/short_name)
    // ou le référentiel multi-doctrine après bascule (grade_system_id/label_short).
    // Sur une installation neuve, la bascule référentielle a déjà eu lieu : on rattache
    // alors l'admin à un grade officier existant du référentiel (ou NULL) plutôt que
    // d'insérer des colonnes disparues.
    $gradesHasTenantCol = $pdo->query(
        "SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() "
        . "AND TABLE_NAME = 'grades' AND COLUMN_NAME = 'tenant_id' LIMIT 1"
    );
    if ($gradesHasTenantCol && $gradesHasTenantCol->fetchColumn()) {
        $pdo->exec("INSERT INTO grades (tenant_id, name, short_name, rank_order, created_at) VALUES ($tenantId, 'Officer', 'OFR', 10, NOW())");
        $gradeId = (int) $pdo->lastInsertId();
    } else {
        $refGrade = $pdo->query(
            "SELECT id FROM grades WHERE is_active = 1 "
            . "ORDER BY (is_commissioned = 1) DESC, sort_order ASC, id ASC LIMIT 1"
        );
        $refGradeId = $refGrade ? $refGrade->fetchColumn() : false;
        $gradeId = $refGradeId !== false && $refGradeId !== null ? (int) $refGradeId : null;
    }

    // Premier administrateur : plus de mot de passe « admin » écrit en dur.
    // E-mail et mot de passe viennent de ATHENA_FIRST_ADMIN_EMAIL / ATHENA_FIRST_ADMIN_PASSWORD ;
    // sans mot de passe fourni, un mot de passe aléatoire est généré et affiché une seule fois.
    $firstAdminEmail = trim((string) ($_ENV['ATHENA_FIRST_ADMIN_EMAIL'] ?? getenv('ATHENA_FIRST_ADMIN_EMAIL') ?: ''));
    if ($firstAdminEmail === '' || filter_var($firstAdminEmail, FILTER_VALIDATE_EMAIL) === false) {
        $firstAdminEmail = 'admin@athena.local';
    }
    $firstAdminPassword = (string) ($_ENV['ATHENA_FIRST_ADMIN_PASSWORD'] ?? getenv('ATHENA_FIRST_ADMIN_PASSWORD') ?: '');
    $firstAdminGenerated = false;
    if (strlen($firstAdminPassword) < 12) {
        $firstAdminPassword = rtrim(strtr(base64_encode(random_bytes(15)), '+/', 'kQ'), '=');
        $firstAdminGenerated = true;
    }
    $hash = password_hash($firstAdminPassword, PASSWORD_ARGON2ID);
    $pdo->prepare("INSERT INTO users (tenant_id, email, password_hash, display_name, callsign, role_id, grade_id, status, is_platform_admin, created_at, updated_at) VALUES (?, ?, ?, 'Admin', 'ADMIN', ?, ?, 'active', 1, NOW(), NOW())")
        ->execute([$tenantId, $firstAdminEmail, $hash, $roleId, $gradeId]);

    $panels = [
        ['État civil', 'etat-civil', 'Identité et état civil', 10],
        ['Affectation', 'affectation', 'Unité, poste, affectation', 20],
        ['Formation', 'formation', 'Parcours et qualifications', 30],
        ['Sécurité / Clearance', 'securite', 'Niveaux de sécurité et habilitations', 40],
        ['Santé / Aptitude', 'sante', 'Aptitude médicale et restrictions', 50],
        ['Références / Notes', 'references-notes', 'Références et notes administratives', 60],
    ];
    foreach ($panels as $p) {
        $pdo->prepare("INSERT INTO personnel_admin_panels (tenant_id, name, slug, description, display_order) VALUES (?, ?, ?, ?, ?)")
            ->execute([$tenantId, $p[0], $p[1], $p[2], $p[3]]);
    }
    $pdo->exec("INSERT INTO tenant_matricule_config (tenant_id, prefix, format_pattern, next_number, updated_at) VALUES ($tenantId, 'ATH', '{prefix}-{seq:5}', 1, NOW())");

    $run_documents_seed($pdo, $tenantId);

    try {
        $allTenants = $pdo->query('SELECT id FROM tenants');
        if ($allTenants) {
            while ($trow = $allTenants->fetch(PDO::FETCH_ASSOC)) {
                \App\Services\Community\TenantSeedHelper::ensureTenantPermissionCatalog($pdo, (int) $trow['id']);
            }
        }
        echo "Catalogue permissions tenant synchronisé.\n";
    } catch (Throwable $e) {
        echo '  [ATTENTION] Catalogue permissions : ' . $e->getMessage() . "\n";
        $migrationRunner->warn('Catalogue permissions', $e);
    }

    echo "Communauté système créée. Premier administrateur : {$firstAdminEmail}\n";
    if ($firstAdminGenerated) {
        echo "  [ATTENTION] Mot de passe généré (affiché une seule fois, changez-le à la première connexion) : {$firstAdminPassword}\n";
    } else {
        echo "  [OK] Mot de passe : celui de ATHENA_FIRST_ADMIN_PASSWORD\n";
    }
    unset($firstAdminPassword);
}
