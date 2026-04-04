<?php

declare(strict_types=1);

namespace App\Services\Community;

use PDO;
use PDOException;

/**
 * Reprise des seeds run-migrations.php pour un nouveau tenant (forum, documents, permissions admin de base).
 */
final class TenantSeedHelper
{
    public static function seedForumAndRoles(PDO $pdo, int $tenantId): void
    {
        $stmt = $pdo->prepare('SELECT id FROM permissions WHERE tenant_id = ? AND slug = ? LIMIT 1');
        $stmt->execute([$tenantId, 'forum.view']);
        if ($stmt->fetch()) {
            return;
        }

        $permissions = [
            ['admin.access', 'Accès administration', 'admin'],
            ['forum.view', 'Voir le forum', 'forum'],
            ['forum.create_topic', 'Créer un sujet', 'forum'],
            ['forum.reply', 'Répondre', 'forum'],
            ['forum.edit_own', 'Modifier son message', 'forum'],
            ['forum.delete_own', 'Supprimer son message', 'forum'],
            ['forum.moderate', 'Modérer le forum', 'forum'],
            ['forum.manage_categories', 'Gérer les catégories', 'forum'],
        ];

        $permIds = [];
        $insertPerm = $pdo->prepare('INSERT INTO permissions (tenant_id, name, slug, module, created_at) VALUES (?, ?, ?, ?, NOW())');
        foreach ($permissions as $p) {
            $insertPerm->execute([$tenantId, $p[1], $p[0], $p[2]]);
            $permIds[$p[0]] = (int) $pdo->lastInsertId();
        }

        $adminRole = $pdo->prepare('SELECT id FROM roles WHERE tenant_id = ? AND slug = ? LIMIT 1');
        $adminRole->execute([$tenantId, 'tenant_admin']);
        $adminRoleId = (int) ($adminRole->fetch(PDO::FETCH_ASSOC)['id'] ?? 0);
        if ($adminRoleId) {
            $link = $pdo->prepare('INSERT IGNORE INTO role_permissions (role_id, permission_id) VALUES (?, ?)');
            foreach ($permIds as $pid) {
                $link->execute([$adminRoleId, $pid]);
            }
        }

        foreach (['forum_moderator' => 'Modérateur forum', 'member' => 'Membre', 'officer' => 'Officier'] as $slug => $roleName) {
            $st = $pdo->prepare('SELECT id FROM roles WHERE tenant_id = ? AND slug = ? LIMIT 1');
            $st->execute([$tenantId, $slug]);
            if (!$st->fetch()) {
                $pdo->prepare('INSERT INTO roles (tenant_id, name, slug, description, is_system, created_at) VALUES (?, ?, ?, ?, 1, NOW())')
                    ->execute([$tenantId, $roleName, $slug, '']);
            }
        }

        $modRole = $pdo->prepare('SELECT id FROM roles WHERE tenant_id = ? AND slug = ? LIMIT 1');
        $modRole->execute([$tenantId, 'forum_moderator']);
        $modRoleId = (int) ($modRole->fetch(PDO::FETCH_ASSOC)['id'] ?? 0);
        if ($modRoleId) {
            $link = $pdo->prepare('INSERT IGNORE INTO role_permissions (role_id, permission_id) VALUES (?, ?)');
            foreach (['forum.view', 'forum.create_topic', 'forum.reply', 'forum.edit_own', 'forum.moderate'] as $slug) {
                if (isset($permIds[$slug])) {
                    $link->execute([$modRoleId, $permIds[$slug]]);
                }
            }
        }

        $memberRole = $pdo->prepare('SELECT id FROM roles WHERE tenant_id = ? AND slug = ? LIMIT 1');
        $memberRole->execute([$tenantId, 'member']);
        $memberRoleId = (int) ($memberRole->fetch(PDO::FETCH_ASSOC)['id'] ?? 0);
        if ($memberRoleId) {
            $link = $pdo->prepare('INSERT IGNORE INTO role_permissions (role_id, permission_id) VALUES (?, ?)');
            foreach (['forum.view', 'forum.create_topic', 'forum.reply', 'forum.edit_own'] as $slug) {
                if (isset($permIds[$slug])) {
                    $link->execute([$memberRoleId, $permIds[$slug]]);
                }
            }
        }

        $stmt = $pdo->prepare('SELECT 1 FROM forum_categories WHERE tenant_id = ? LIMIT 1');
        $stmt->execute([$tenantId]);
        if ($stmt->fetch()) {
            return;
        }

        $categories = [
            ['Communiqués officiels', 'annonces', 'Annonces et communiqués de l\'équipe.', 'orange', 10],
            ['Général', 'general', 'Discussions générales et présentation.', 'indigo', 20],
            ['Missions & Opérations', 'missions', 'Briefs et retours d\'opérations.', 'violet', 30],
            ['Support & Technique', 'support', 'Aide, ATAK, équipement, technique.', 'rose', 40],
            ['Hors sujet', 'hors-sujet', 'Échanges informels.', 'emerald', 50],
        ];
        $insCat = $pdo->prepare('INSERT INTO forum_categories (tenant_id, parent_id, name, slug, description, color_theme, display_order, is_locked, created_at, updated_at) VALUES (?, NULL, ?, ?, ?, ?, ?, 0, NOW(), NOW())');
        foreach ($categories as $c) {
            $insCat->execute([$tenantId, $c[0], $c[1], $c[2], $c[3], $c[4]]);
        }
    }

    public static function seedDocumentsEquipment(PDO $pdo, int $tenantId): void
    {
        $docPermSlugs = ['documents.view', 'documents.upload', 'documents.update', 'documents.archive', 'documents.download_sensitive'];
        $stmt = $pdo->prepare('SELECT id FROM permissions WHERE tenant_id = ? AND slug = ? LIMIT 1');
        $stmt->execute([$tenantId, 'documents.view']);
        $docPermIds = [];
        if ($stmt->fetch()) {
            foreach ($docPermSlugs as $slug) {
                $s = $pdo->prepare('SELECT id FROM permissions WHERE tenant_id = ? AND slug = ? LIMIT 1');
                $s->execute([$tenantId, $slug]);
                $id = $s->fetch(PDO::FETCH_ASSOC);
                if ($id) {
                    $docPermIds[$slug] = (int) $id['id'];
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
            $insPerm = $pdo->prepare('INSERT INTO permissions (tenant_id, name, slug, module, created_at) VALUES (?, ?, ?, ?, NOW())');
            foreach ($docPerms as $p) {
                $insPerm->execute([$tenantId, $p[1], $p[0], $p[2]]);
                $docPermIds[$p[0]] = (int) $pdo->lastInsertId();
            }
        }
        $adminRole = $pdo->prepare('SELECT id FROM roles WHERE tenant_id = ? AND slug = ? LIMIT 1');
        $adminRole->execute([$tenantId, 'tenant_admin']);
        $adminRoleId = (int) ($adminRole->fetch(PDO::FETCH_ASSOC)['id'] ?? 0);
        if ($adminRoleId && $docPermIds !== []) {
            $link = $pdo->prepare('INSERT IGNORE INTO role_permissions (role_id, permission_id) VALUES (?, ?)');
            foreach ($docPermIds as $pid) {
                $link->execute([$adminRoleId, $pid]);
            }
        }
        $memberRole = $pdo->prepare('SELECT id FROM roles WHERE tenant_id = ? AND slug = ? LIMIT 1');
        $memberRole->execute([$tenantId, 'member']);
        $memberRoleId = (int) ($memberRole->fetch(PDO::FETCH_ASSOC)['id'] ?? 0);
        if ($memberRoleId && isset($docPermIds['documents.view'])) {
            $pdo->prepare('INSERT IGNORE INTO role_permissions (role_id, permission_id) VALUES (?, ?)')->execute([$memberRoleId, $docPermIds['documents.view']]);
        }
        $officerRole = $pdo->prepare('SELECT id FROM roles WHERE tenant_id = ? AND slug = ? LIMIT 1');
        $officerRole->execute([$tenantId, 'officer']);
        $officerRoleId = (int) ($officerRole->fetch(PDO::FETCH_ASSOC)['id'] ?? 0);
        if (!$officerRoleId) {
            $pdo->prepare('INSERT INTO roles (tenant_id, name, slug, description, is_system, created_at) VALUES (?, ?, ?, ?, 1, NOW())')->execute([$tenantId, 'Officier', 'officer', '']);
            $officerRoleId = (int) $pdo->lastInsertId();
        }
        if ($officerRoleId) {
            $link = $pdo->prepare('INSERT IGNORE INTO role_permissions (role_id, permission_id) VALUES (?, ?)');
            foreach (['documents.view', 'documents.upload', 'documents.update'] as $slug) {
                if (isset($docPermIds[$slug])) {
                    $link->execute([$officerRoleId, $docPermIds[$slug]]);
                }
            }
        }

        $stmt = $pdo->prepare('SELECT 1 FROM document_categories WHERE tenant_id = ? LIMIT 1');
        $stmt->execute([$tenantId]);
        if (!$stmt->fetch()) {
            foreach ([['Doctrine / SOP', 'doctrine'], ['Manuel opérateur', 'manuel'], ['Fiche équipement', 'fiche-equipement'], ['Rapport mission', 'rapport'], ['Média pédagogique', 'media']] as $i => $c) {
                $pdo->prepare('INSERT INTO document_categories (tenant_id, name, slug, color, created_at) VALUES (?, ?, ?, ?, NOW())')->execute([$tenantId, $c[0], $c[1], ['emerald', 'blue', 'amber', 'slate', 'violet'][$i] ?? null]);
            }
        }
        $stmt = $pdo->prepare('SELECT 1 FROM equipment_classes WHERE tenant_id = ? LIMIT 1');
        $stmt->execute([$tenantId]);
        if (!$stmt->fetch()) {
            foreach ([['Radio', 'radio', 'radio'], ['Optique', 'optic', 'optic'], ['Armement', 'weapon', 'weapon'], ['Véhicule', 'vehicle', 'vehicle'], ['Drone', 'drone', 'drone'], ['Médical', 'medical', 'medical']] as $c) {
                $pdo->prepare('INSERT INTO equipment_classes (tenant_id, name, slug, category, description, created_at) VALUES (?, ?, ?, ?, NULL, NOW())')->execute([$tenantId, $c[0], $c[1], $c[2]]);
            }
        }
    }

    public static function ensureSystemAdminPermissions(PDO $pdo, int $tenantId): void
    {
        $stmt = $pdo->prepare('SELECT id FROM permissions WHERE tenant_id = ? AND slug = ? LIMIT 1');
        $stmt->execute([$tenantId, 'admin.system']);
        if (!$stmt->fetch()) {
            $pdo->prepare('INSERT INTO permissions (tenant_id, name, slug, module, created_at) VALUES (?, ?, ?, ?, NOW())')->execute([$tenantId, 'Administration système', 'admin.system', 'admin']);
        }
        $stmt = $pdo->prepare('SELECT id FROM permissions WHERE tenant_id = ? AND slug = ? LIMIT 1');
        $stmt->execute([$tenantId, 'admin.organization']);
        if (!$stmt->fetch()) {
            $pdo->prepare('INSERT INTO permissions (tenant_id, name, slug, module, created_at) VALUES (?, ?, ?, ?, NOW())')->execute([$tenantId, 'Administration organisationnelle', 'admin.organization', 'admin']);
        }

        $stmt = $pdo->prepare('SELECT id FROM roles WHERE tenant_id = ? AND slug = ? LIMIT 1');
        $stmt->execute([$tenantId, 'super_admin']);
        if (!$stmt->fetch()) {
            $pdo->prepare('INSERT INTO roles (tenant_id, name, slug, description, is_system, is_locked, created_at) VALUES (?, ?, ?, ?, 1, 1, NOW())')->execute([$tenantId, 'Super Administrator', 'super_admin', '']);
            $superAdminRoleId = (int) $pdo->lastInsertId();
            foreach (['admin.system', 'admin.organization', 'admin.access'] as $permSlug) {
                $p = $pdo->prepare('SELECT id FROM permissions WHERE tenant_id = ? AND slug = ? LIMIT 1');
                $p->execute([$tenantId, $permSlug]);
                $permId = $p->fetch(PDO::FETCH_ASSOC)['id'] ?? null;
                if ($permId) {
                    $pdo->prepare('INSERT IGNORE INTO role_permissions (role_id, permission_id) VALUES (?, ?)')->execute([$superAdminRoleId, $permId]);
                }
            }
        }

        $tenantAdminRole = $pdo->prepare('SELECT id FROM roles WHERE tenant_id = ? AND slug = ? LIMIT 1');
        $tenantAdminRole->execute([$tenantId, 'tenant_admin']);
        $tenantAdminRoleId = $tenantAdminRole->fetch(PDO::FETCH_ASSOC)['id'] ?? null;
        $permOrg = $pdo->prepare('SELECT id FROM permissions WHERE tenant_id = ? AND slug = ? LIMIT 1');
        $permOrg->execute([$tenantId, 'admin.organization']);
        $permOrgId = $permOrg->fetch(PDO::FETCH_ASSOC)['id'] ?? null;
        if ($tenantAdminRoleId && $permOrgId) {
            $pdo->prepare('INSERT IGNORE INTO role_permissions (role_id, permission_id) VALUES (?, ?)')->execute([$tenantAdminRoleId, $permOrgId]);
        }

        $stmt = $pdo->prepare('SELECT id FROM permissions WHERE tenant_id = ? AND slug = ? LIMIT 1');
        $stmt->execute([$tenantId, 'training.view']);
        if (!$stmt->fetch()) {
            foreach ([['training.view', 'Voir les formations', 'training'], ['training.manage', 'Gérer les formations', 'training'], ['training.assign', 'Assigner des formations', 'training']] as $p) {
                $pdo->prepare('INSERT INTO permissions (tenant_id, name, slug, module, created_at) VALUES (?, ?, ?, ?, NOW())')->execute([$tenantId, $p[1], $p[0], $p[2]]);
            }
            $adminRole = $pdo->prepare('SELECT id FROM roles WHERE tenant_id = ? AND slug = ? LIMIT 1');
            $adminRole->execute([$tenantId, 'tenant_admin']);
            $adminRoleId = $adminRole->fetch(PDO::FETCH_ASSOC)['id'] ?? null;
            if ($adminRoleId) {
                $trainPerms = $pdo->prepare('SELECT id FROM permissions WHERE tenant_id = ? AND slug IN (\'training.view\',\'training.manage\',\'training.assign\')');
                $trainPerms->execute([$tenantId]);
                while ($row = $trainPerms->fetch(PDO::FETCH_ASSOC)) {
                    $pdo->prepare('INSERT IGNORE INTO role_permissions (role_id, permission_id) VALUES (?, ?)')->execute([$adminRoleId, $row['id']]);
                }
            }
        }
    }

    public static function ensurePersonnelPanelsAndMatricule(PDO $pdo, int $tenantId): void
    {
        $stmt = $pdo->query('SELECT 1 FROM personnel_admin_panels WHERE tenant_id = ' . (int) $tenantId . ' LIMIT 1');
        if ($stmt && $stmt->fetch()) {
            return;
        }
        $panels = [
            ['État civil', 'etat-civil', 'Identité et état civil', 10],
            ['Affectation', 'affectation', 'Unité, poste, affectation', 20],
            ['Formation', 'formation', 'Parcours et qualifications', 30],
            ['Sécurité / Clearance', 'securite', 'Niveaux de sécurité et habilitations', 40],
            ['Santé / Aptitude', 'sante', 'Aptitude médicale et restrictions', 50],
            ['Références / Notes', 'references-notes', 'Références et notes administratives', 60],
        ];
        foreach ($panels as $p) {
            $pdo->prepare('INSERT INTO personnel_admin_panels (tenant_id, name, slug, description, display_order) VALUES (?, ?, ?, ?, ?)')
                ->execute([$tenantId, $p[0], $p[1], $p[2], $p[3]]);
        }
        try {
            $pdo->prepare('INSERT IGNORE INTO tenant_matricule_config (tenant_id, prefix, format_pattern, next_number, updated_at) VALUES (?, ?, ?, ?, NOW())')->execute([$tenantId, 'ATH', '{prefix}-{seq:5}', 1]);
        } catch (PDOException) {
            // table absente ou déjà présent
        }
    }
}
