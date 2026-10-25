<?php

declare(strict_types=1);

/**
 * Nettoyage des comptes techniques « Modération automatique » (system.moderation@internal.local)
 * que les anciennes migrations inséraient dans chaque communauté sans qu’ils servent.
 *
 * Un compte n’est supprimé que s’il n’est référencé par aucune clé étrangère vers users.id :
 * ceux qui portent déjà une action automatique (quarantaine, etc.) restent en place.
 * Il est recréé à la demande si une action automatique en a besoin.
 *
 * Idempotent — appelée depuis run-migrations.php.
 */
return static function (PDO $pdo): void {
    $st = $pdo->query("SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'users' AND COLUMN_NAME = 'is_service_account' LIMIT 1");
    if (!$st || !$st->fetchColumn()) {
        echo "  [OK] Rien à nettoyer (colonne users.is_service_account absente)\n";

        return;
    }

    $ids = $pdo->query(
        "SELECT id FROM users
         WHERE LOWER(TRIM(email)) = 'system.moderation@internal.local' AND COALESCE(is_service_account, 0) = 1"
    )?->fetchAll(PDO::FETCH_COLUMN) ?: [];
    if ($ids === []) {
        echo "  [OK] Aucun compte « Modération automatique » à nettoyer\n";

        return;
    }

    $refs = $pdo->query(
        "SELECT TABLE_NAME, COLUMN_NAME FROM information_schema.KEY_COLUMN_USAGE
         WHERE TABLE_SCHEMA = DATABASE() AND REFERENCED_TABLE_NAME = 'users' AND REFERENCED_COLUMN_NAME = 'id'"
    )?->fetchAll(PDO::FETCH_ASSOC) ?: [];
    $safe = static fn (string $name): bool => preg_match('/^[A-Za-z0-9_]+$/', $name) === 1;
    // Lignes d’identité créées avec le compte lui-même : elles partent avec lui, ce n’est pas un usage.
    $identityTables = ['user_community_memberships', 'user_community_profiles', 'account_tenant_memberships'];

    $delete = $pdo->prepare('DELETE FROM users WHERE id = ? AND COALESCE(is_service_account, 0) = 1');
    $removed = 0;
    $kept = 0;
    foreach ($ids as $rawId) {
        $id = (int) $rawId;
        $used = false;
        foreach ($refs as $ref) {
            $table = (string) $ref['TABLE_NAME'];
            $column = (string) $ref['COLUMN_NAME'];
            if (!$safe($table) || !$safe($column) || in_array($table, $identityTables, true)) {
                continue;
            }
            $check = $pdo->prepare('SELECT 1 FROM `' . $table . '` WHERE `' . $column . '` = ? LIMIT 1');
            $check->execute([$id]);
            if ($check->fetchColumn()) {
                $used = true;
                break;
            }
        }
        if ($used) {
            $kept++;
            continue;
        }
        try {
            $delete->execute([$id]);
            $removed += $delete->rowCount();
        } catch (Throwable) {
            $kept++;
        }
    }
    echo "  [OK] Comptes « Modération automatique » inutilisés supprimés : {$removed} (conservés car utilisés : {$kept})\n";
};
