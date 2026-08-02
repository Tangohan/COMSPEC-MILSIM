<?php

declare(strict_types=1);

/**
 * État civil (identité légale) séparé du profil public — table user_legal_identities.
 *
 * La migration SQL 20260417103000_login_security_otp_legal_identity.sql n’était appelée par aucun
 * point d’entrée : sur les bases de production la table n’a jamais été créée, et l’annuaire
 * personnel tombait en SQLSTATE 42S02. Cette version PHP est branchée sur run-migrations.php.
 *
 * Idempotent — appelée depuis run-migrations.php.
 */
return function (PDO $pdo): void {
    $tableExists = static function (PDO $pdo, string $table): bool {
        $stmt = $pdo->prepare(
            'SELECT 1 FROM information_schema.TABLES
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? LIMIT 1'
        );
        $stmt->execute([$table]);

        return (bool) $stmt->fetchColumn();
    };

    $columnExists = static function (PDO $pdo, string $table, string $column): bool {
        $stmt = $pdo->prepare(
            'SELECT 1 FROM information_schema.COLUMNS
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ? LIMIT 1'
        );
        $stmt->execute([$table, $column]);

        return (bool) $stmt->fetchColumn();
    };

    $created = false;

    if (!$tableExists($pdo, 'user_legal_identities')) {
        // Types alignés sur users.id / tenants.id (INT UNSIGNED) : un BIGINT ferait échouer les
        // clés étrangères (errno 150).
        $pdo->exec(
            'CREATE TABLE user_legal_identities (
                id INT UNSIGNED NOT NULL AUTO_INCREMENT,
                tenant_id INT UNSIGNED NOT NULL,
                user_id INT UNSIGNED NOT NULL,
                first_name VARCHAR(100) DEFAULT NULL,
                last_name VARCHAR(100) DEFAULT NULL,
                phone VARCHAR(50) DEFAULT NULL,
                birth_date DATE DEFAULT NULL,
                nationality VARCHAR(100) DEFAULT NULL,
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                PRIMARY KEY (id),
                UNIQUE KEY uniq_user_legal_identities_user (user_id),
                KEY idx_user_legal_identities_tenant (tenant_id),
                CONSTRAINT fk_user_legal_identities_tenant FOREIGN KEY (tenant_id) REFERENCES tenants (id) ON DELETE CASCADE ON UPDATE CASCADE,
                CONSTRAINT fk_user_legal_identities_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE ON UPDATE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
        );
        $created = true;
        echo "Table user_legal_identities créée.\n";
    }

    // Reprise de l’état civil déjà saisi dans user_profiles (une seule fois, à la création).
    if (!$created || !$tableExists($pdo, 'user_profiles')) {
        return;
    }

    $sourceColumns = [];
    foreach (['first_name', 'last_name', 'phone', 'birth_date', 'nationality'] as $column) {
        if ($columnExists($pdo, 'user_profiles', $column)) {
            $sourceColumns[] = $column;
        }
    }
    if ($sourceColumns === []) {
        return;
    }

    $select = [];
    $filled = [];
    foreach ($sourceColumns as $column) {
        if ($column === 'birth_date') {
            $select[] = 'up.birth_date';
            $filled[] = 'up.birth_date IS NOT NULL';
            continue;
        }
        $select[] = "NULLIF(TRIM(up.{$column}), '')";
        $filled[] = "NULLIF(TRIM(up.{$column}), '') IS NOT NULL";
    }

    $inserted = $pdo->exec(
        'INSERT INTO user_legal_identities (tenant_id, user_id, ' . implode(', ', $sourceColumns) . ', created_at, updated_at)
         SELECT u.tenant_id, up.user_id, ' . implode(', ', $select) . ', NOW(), NOW()
         FROM user_profiles up
         INNER JOIN users u ON u.id = up.user_id
         LEFT JOIN user_legal_identities uli ON uli.user_id = up.user_id
         WHERE uli.user_id IS NULL AND (' . implode(' OR ', $filled) . ')'
    );

    echo '  État civil repris depuis user_profiles : ' . (int) $inserted . " ligne(s).\n";
};
