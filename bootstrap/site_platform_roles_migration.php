<?php

declare(strict_types=1);

/**
 * Rôles site : assistance, modérateur, modérateur senior (+ permissions globales associées).
 * Idempotent — exécuter après RBAC trois couches et schéma roles organique.
 */
function run_site_platform_roles_migration(PDO $pdo): void
{
    $hasRbacScope = (bool) $pdo->query(
        "SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'permissions' AND COLUMN_NAME = 'rbac_scope' LIMIT 1"
    )->fetchColumn();

    $ensureGlobalPermission = function (string $slug, string $name, string $module) use ($pdo, $hasRbacScope): int {
        $st = $pdo->prepare('SELECT id FROM permissions WHERE tenant_id IS NULL AND slug = ? LIMIT 1');
        $st->execute([$slug]);
        $row = $st->fetch(PDO::FETCH_ASSOC);
        if ($row) {
            return (int) $row['id'];
        }
        if ($hasRbacScope) {
            $pdo->prepare(
                'INSERT INTO permissions (tenant_id, name, slug, module, scope, rbac_scope, created_at)
                 VALUES (NULL, ?, ?, ?, \'site\', \'global\', NOW())'
            )->execute([$name, $slug, $module]);
        } else {
            $pdo->prepare(
                'INSERT INTO permissions (tenant_id, name, slug, module, scope, created_at)
                 VALUES (NULL, ?, ?, ?, \'site\', NOW())'
            )->execute([$name, $slug, $module]);
        }

        return (int) $pdo->lastInsertId();
    };

    $pidModerate = $ensureGlobalPermission(
        'forum.moderate',
        'Modération forum (toutes communautés)',
        'forum'
    );
    $pidCategories = $ensureGlobalPermission(
        'forum.categories.manage',
        'Canaux forum (toutes communautés)',
        'forum'
    );
    $pidSupport = $ensureGlobalPermission(
        'site.support',
        'Assistance membres (accès guidé)',
        'admin'
    );
    $pidReportsManage = $ensureGlobalPermission(
        'forum.reports.manage',
        'Pilotage des dossiers de signalement',
        'forum'
    );

    $definitions = [
        [
            'slug' => 'site_moderator',
            'name' => 'Modérateur plateforme',
            'description' => 'Modération des contenus du brief sur l’ensemble des communautés, sans administration système ni gestion des organisations.',
            'permission_ids' => [$pidModerate],
        ],
        [
            'slug' => 'site_senior_moderator',
            'name' => 'Modérateur senior plateforme',
            'description' => 'Même périmètre que le modérateur plateforme, avec la gestion de l’arborescence des canaux forum sur toutes les communautés.',
            'permission_ids' => [$pidModerate, $pidCategories],
        ],
        [
            'slug' => 'site_support',
            'name' => 'Équipe assistance',
            'description' => 'Accompagnement des membres : consultation des éléments utiles au support dans le back-office, sans modération globale des canaux ni réglages système.',
            'permission_ids' => [$pidSupport],
        ],
        [
            'slug' => 'site_report_operator',
            'name' => 'Opérateur signalements',
            'description' => 'Prise en charge des dossiers, qualification des mesures et ajout de commentaires de traitement dans la timeline.',
            'permission_ids' => [$pidReportsManage],
        ],
        [
            'slug' => 'site_report_supervisor',
            'name' => 'Superviseur signalements',
            'description' => 'Supervision de la file signalements, coordination des assignations et appui aux décisions de clôture.',
            'permission_ids' => [$pidReportsManage, $pidModerate],
        ],
    ];

    foreach ($definitions as $def) {
        $slug = $def['slug'];
        $st = $pdo->prepare('SELECT id FROM roles WHERE tenant_id IS NULL AND slug = ? LIMIT 1');
        $st->execute([$slug]);
        $row = $st->fetch(PDO::FETCH_ASSOC);
        if ($row) {
            $rid = (int) $row['id'];
        } else {
            $pdo->prepare(
                'INSERT INTO roles (tenant_id, name, slug, description, is_system, is_locked, role_layer, created_at)
                 VALUES (NULL, ?, ?, ?, 1, 0, \'site\', NOW())'
            )->execute([$def['name'], $slug, $def['description']]);
            $rid = (int) $pdo->lastInsertId();
            echo "Rôles site : « {$def['name']} » créé (slug={$slug}, id={$rid}).\n";
        }
        $link = $pdo->prepare('INSERT IGNORE INTO role_permissions (role_id, permission_id) VALUES (?, ?)');
        foreach ($def['permission_ids'] as $pid) {
            if ($pid > 0) {
                $link->execute([$rid, $pid]);
            }
        }
    }

    try {
        $pdo->exec(
            "UPDATE roles SET semantic_tier = 'function', display_group = 1, is_visual_only = 0
             WHERE tenant_id IS NULL AND slug = 'site_moderator'"
        );
        $pdo->exec(
            "UPDATE roles SET semantic_tier = 'authority', display_group = 1, is_visual_only = 0
             WHERE tenant_id IS NULL AND slug IN ('site_senior_moderator','site_support')"
        );
        $pdo->exec(
            "UPDATE roles SET semantic_tier = 'function', display_group = 1, is_visual_only = 0
             WHERE tenant_id IS NULL AND slug IN ('site_report_operator','site_report_supervisor')"
        );
    } catch (\Throwable) {
    }
}
