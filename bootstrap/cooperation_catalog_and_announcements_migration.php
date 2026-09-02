<?php

declare(strict_types=1);

/**
 * Catalogue des types de coopération (plateforme + communauté) et gabarits d’annonces (courriel, portail, forum).
 * Idempotent — invoqué depuis run-migrations.php.
 */

require_once dirname(__DIR__) . '/app/Support/SqlText.php';

return function (PDO $pdo): void {
    $col = static function (PDO $pdo, string $table, string $column): bool {
        $st = $pdo->prepare(
            'SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ? LIMIT 1'
        );
        $st->execute([$table, $column]);

        return (bool) $st->fetchColumn();
    };

    $hasCatalog = $pdo->query("SELECT 1 FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'cooperation_catalog_entries' LIMIT 1");
    if (!$hasCatalog || !$hasCatalog->fetch()) {
        $pdo->exec(
            'CREATE TABLE cooperation_catalog_entries (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            tenant_id INT UNSIGNED NOT NULL DEFAULT 0 COMMENT "0 = référence plateforme",
            slug VARCHAR(64) NOT NULL,
            label VARCHAR(255) NOT NULL,
            description TEXT DEFAULT NULL,
            default_priority VARCHAR(24) DEFAULT NULL,
            checklist_json JSON DEFAULT NULL,
            sort_order INT NOT NULL DEFAULT 0,
            is_active TINYINT UNSIGNED NOT NULL DEFAULT 1,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY uq_coop_catalog_tenant_slug (tenant_id, slug),
            KEY idx_coop_catalog_tenant (tenant_id, is_active, sort_order)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
        );
        echo "Table cooperation_catalog_entries créée.\n";
    }

    $hasTpl = $pdo->query("SELECT 1 FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'cooperation_announcement_templates' LIMIT 1");
    if (!$hasTpl || !$hasTpl->fetch()) {
        $pdo->exec(
            'CREATE TABLE cooperation_announcement_templates (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            tenant_id INT UNSIGNED NOT NULL DEFAULT 0 COMMENT "0 = défaut plateforme",
            event_key VARCHAR(64) NOT NULL,
            channel ENUM(\'email\',\'in_app\',\'forum\') NOT NULL,
            subject VARCHAR(255) DEFAULT NULL,
            body TEXT NOT NULL,
            forum_settings_json JSON DEFAULT NULL,
            min_interval_hours INT UNSIGNED NOT NULL DEFAULT 24,
            is_active TINYINT UNSIGNED NOT NULL DEFAULT 0,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY uq_coop_ann_tpl (tenant_id, event_key, channel),
            KEY idx_coop_ann_evt (event_key, is_active)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
        );
        echo "Table cooperation_announcement_templates créée.\n";
    }

    $hasLog = $pdo->query("SELECT 1 FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'cooperation_forum_announcement_log' LIMIT 1");
    if (!$hasLog || !$hasLog->fetch()) {
        $pdo->exec(
            'CREATE TABLE cooperation_forum_announcement_log (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            mission_id BIGINT UNSIGNED NOT NULL,
            event_key VARCHAR(64) NOT NULL,
            posted_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY uq_coop_forum_ann (mission_id, event_key),
            KEY idx_coop_forum_ann_time (posted_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
        );
        echo "Table cooperation_forum_announcement_log créée.\n";
    }

    // Semis typologies plateforme (équivalent CooperationDictionary) si table vide pour tenant_id=0
    $cntSt = $pdo->query('SELECT COUNT(*) FROM cooperation_catalog_entries WHERE tenant_id = 0');
    $cnt = $cntSt ? (int) $cntSt->fetchColumn() : 0;
    if ($cnt === 0) {
        $defaults = [
            ['formation', 'Formation', 'Mise en commun pour un entraînement ou un module pédagogique.', 'routine', 10],
            ['exercice', 'Exercice', 'Scénario structuré entre unités (validation mutuelle, calendrier).', 'planifiee', 20],
            ['appui_operationnel', 'Appui opérationnel', 'Soutien ponctuel ou spécialisé d’une unité à une autre.', 'prioritaire', 30],
            ['coordination_renseignement', 'Coordination renseignement', 'Partage d’information encadré entre communautés.', 'planifiee', 40],
            ['liaison_interservices', 'Liaison interservices', 'Alignement entre fonctions ou pôles distincts.', 'routine', 50],
            ['soutien_logistique', 'Soutien logistique', 'Coordination matériel, transport ou ressources.', 'routine', 60],
            ['preparation_mission', 'Préparation de mission', 'Montée en puissance avant une opération conjointe.', 'prioritaire', 70],
            ['retour_experience', 'Retour d’expérience', 'Capitalisation après action ou clôture de dossier.', 'routine', 80],
        ];
        $ins = $pdo->prepare(
            'INSERT INTO cooperation_catalog_entries (tenant_id, slug, label, description, default_priority, sort_order, is_active, created_at)
             VALUES (0, ?, ?, ?, ?, ?, 1, NOW())'
        );
        foreach ($defaults as $d) {
            $ins->execute([$d[0], $d[1], $d[2], $d[3], $d[4]]);
        }
        echo "Semis cooperation_catalog_entries (plateforme) : " . count($defaults) . " entrées.\n";
    }

    // Migrer d’anciens modèles cooperation_mission_templates vers le catalogue communautaire
    $oldTpl = $pdo->query("SELECT 1 FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'cooperation_mission_templates' LIMIT 1");
    if ($oldTpl && $oldTpl->fetch()) {
        $rows = $pdo->query('SELECT id, tenant_id, title, default_typology, default_priority, checklist_json FROM cooperation_mission_templates')->fetchAll(PDO::FETCH_ASSOC) ?: [];
        foreach ($rows as $r) {
            $tid = (int) ($r['tenant_id'] ?? 0);
            if ($tid < 1) {
                continue;
            }
            $slug = 'modele_' . (int) ($r['id'] ?? 0);
            $chk = $pdo->prepare('SELECT 1 FROM cooperation_catalog_entries WHERE tenant_id = ? AND ' . \App\Support\SqlText::equals($pdo, 'slug') . ' LIMIT 1');
            $chk->execute([$tid, $slug]);
            if ($chk->fetchColumn()) {
                continue;
            }
            $title = trim((string) ($r['title'] ?? 'Modèle'));
            $typ = trim((string) ($r['default_typology'] ?? ''));
            $prio = trim((string) ($r['default_priority'] ?? ''));
            $cj = $r['checklist_json'] ?? null;
            $pdo->prepare(
                'INSERT INTO cooperation_catalog_entries (tenant_id, slug, label, description, default_priority, checklist_json, sort_order, is_active, created_at)
                 VALUES (?, ?, ?, NULL, ?, ?, 100, 1, NOW())'
            )->execute([
                $tid,
                $slug,
                $title,
                $prio !== '' ? $prio : null,
                is_string($cj) && $cj !== '' ? $cj : (is_array($cj) ? json_encode($cj, JSON_UNESCAPED_UNICODE) : null),
            ]);
        }
        if ($rows !== []) {
            echo "Import cooperation_mission_templates vers cooperation_catalog_entries effectué (" . count($rows) . " source(s)).\n";
        }
    }

    // Gabarits plateforme par défaut (inactifs sauf in_app basiques — activables par l’admin)
    $tplCnt = $pdo->query('SELECT COUNT(*) FROM cooperation_announcement_templates WHERE tenant_id = 0');
    $tplN = $tplCnt ? (int) $tplCnt->fetchColumn() : 0;
    if ($tplN === 0) {
        $events = [
            ['coop_mission_created', 'Nouveau dossier de coopération : {titre_cooperation}', "Une nouvelle coopération a été créée par {unite_support}.\n\nVoir la synthèse : {lien_synthese}"],
            ['coop_proposal_updated', 'Proposition mise à jour : {titre_cooperation}', "La proposition de coopération « {titre_cooperation} » a été modifiée.\n\n{lien_proposition}"],
            ['coop_invitation_sent', 'Invitation à une coopération inter-unités', "{unite_support} vous invite à rejoindre la coopération « {titre_cooperation} ».\n\nRépondre depuis le portail : {lien_synthese}"],
            ['coop_partner_accepted', 'Partenaire accepté : {titre_cooperation}', "La communauté {unite_destinataire} a accepté de participer à « {titre_cooperation} »."],
            ['coop_partner_declined', 'Partenaire a décliné : {titre_cooperation}', "La communauté {unite_destinataire} a décliné l’invitation pour « {titre_cooperation} »."],
            ['coop_mission_activated', 'Coopération ouverte : {titre_cooperation}', "La coopération « {titre_cooperation} » est désormais active. Espace commun : {lien_espace_commun}"],
            ['coop_mission_closed', 'Coopération clôturée : {titre_cooperation}', "La coopération « {titre_cooperation} » a été clôturée.\n\nSynthèse : {lien_synthese}"],
        ];
        $insT = $pdo->prepare(
            'INSERT INTO cooperation_announcement_templates (tenant_id, event_key, channel, subject, body, min_interval_hours, is_active, created_at)
             VALUES (0, ?, \'email\', ?, ?, 24, 0, NOW())'
        );
        foreach ($events as $e) {
            $insT->execute([$e[0], $e[1], $e[2]]);
        }
        $insI = $pdo->prepare(
            'INSERT INTO cooperation_announcement_templates (tenant_id, event_key, channel, subject, body, min_interval_hours, is_active, created_at)
             VALUES (0, ?, \'in_app\', NULL, ?, 0, 1, NOW())'
        );
        foreach ($events as $e) {
            $insI->execute([$e[0], $e[1] . ' — ' . mb_substr(preg_replace('/\s+/', ' ', $e[2]), 0, 200)]);
        }
        $insF = $pdo->prepare(
            'INSERT INTO cooperation_announcement_templates (tenant_id, event_key, channel, subject, body, forum_settings_json, min_interval_hours, is_active, created_at)
             VALUES (0, ?, \'forum\', NULL, ?, ?, 24, 0, NOW())'
        );
        $forumBody = [];
        foreach ($events as $ev) {
            $forumBody[$ev[0]] = $ev[2];
        }
        foreach (['coop_invitation_sent', 'coop_mission_activated', 'coop_mission_closed'] as $ek) {
            $settings = json_encode(['as_draft' => true, 'category_id' => null, 'topic_id' => null], JSON_UNESCAPED_UNICODE);
            $insF->execute([$ek, (string) ($forumBody[$ek] ?? ''), $settings]);
        }
        echo "Semis cooperation_announcement_templates (plateforme, e-mails et forum désactivés par défaut).\n";
    }
};
