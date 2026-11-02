<?php

declare(strict_types=1);

/**
 * Étape « Liens d'invitation partageables » (Schéma).
 *
 * Exécutée par run-migrations.php (file active) avec $pdo et $root. Idempotente : ajoute à recruitment_invite_codes les
 * colonnes des liens /i/<code> (public_code unique, link_action, requires_approval, revoked_at) et crée
 * recruitment_invite_link_events si elles manquent (bootstrap/recruitment_invite_links_migration.php). Aucune ligne
 * existante n'est modifiée : les codes prioritaires restent utilisables tels quels.
 */

require_once $root . '/bootstrap/recruitment_invite_links_migration.php';
run_recruitment_invite_links_migration($pdo);
