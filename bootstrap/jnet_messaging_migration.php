<?php

declare(strict_types=1);

/**
 * Messagerie d’unité (JNET) : métadonnées de diffusion sur les fils existants.
 *
 * Les colonnes sont ajoutées avec des valeurs par défaut sûres : les fils déjà
 * présents restent lisibles (précédence « routine », diffusion inconnue = NULL).
 * Idempotent — appelée depuis run-migrations.php.
 */
return function (PDO $pdo): void {
    $tableExists = static function (PDO $pdo, string $table): bool {
        $st = $pdo->prepare('SELECT 1 FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? LIMIT 1');
        $st->execute([$table]);

        return (bool) $st->fetchColumn();
    };
    $columnExists = static function (PDO $pdo, string $table, string $column): bool {
        $st = $pdo->prepare('SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ? LIMIT 1');
        $st->execute([$table, $column]);

        return (bool) $st->fetchColumn();
    };

    if (!$tableExists($pdo, 'tenant_message_threads') || !$tableExists($pdo, 'tenant_message_thread_users')) {
        return;
    }

    $threadColumns = [
        'origin' => "ALTER TABLE tenant_message_threads ADD COLUMN origin varchar(24) NOT NULL DEFAULT 'athena'",
        'precedence' => "ALTER TABLE tenant_message_threads ADD COLUMN precedence varchar(16) NOT NULL DEFAULT 'routine'",
        'recipients_summary' => 'ALTER TABLE tenant_message_threads ADD COLUMN recipients_summary varchar(255) DEFAULT NULL',
    ];
    foreach ($threadColumns as $column => $sql) {
        if (!$columnExists($pdo, 'tenant_message_threads', $column)) {
            echo "JNET messagerie : colonne tenant_message_threads.{$column}...\n";
            $pdo->exec($sql);
        }
    }

    $participantColumns = [
        'recipient_kind' => "ALTER TABLE tenant_message_thread_users ADD COLUMN recipient_kind varchar(12) NOT NULL DEFAULT 'to'",
        'via_label' => 'ALTER TABLE tenant_message_thread_users ADD COLUMN via_label varchar(160) DEFAULT NULL',
    ];
    foreach ($participantColumns as $column => $sql) {
        if (!$columnExists($pdo, 'tenant_message_thread_users', $column)) {
            echo "JNET messagerie : colonne tenant_message_thread_users.{$column}...\n";
            $pdo->exec($sql);
        }
    }

    // Les fils historiques gardent leur auteur comme expéditeur : information certaine, aucune valeur inventée.
    try {
        $pdo->exec(
            "UPDATE tenant_message_thread_users tu
             INNER JOIN tenant_message_threads t ON t.id = tu.thread_id
             SET tu.recipient_kind = 'sender'
             WHERE tu.user_id = t.created_by_user_id AND tu.recipient_kind = 'to'"
        );
    } catch (PDOException) {
    }
};
