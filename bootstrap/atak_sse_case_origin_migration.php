<?php

declare(strict_types=1);

/**
 * Origine d'un dossier : le dossier d'intérêt dont il est issu.
 *
 * Sans ce lien, un dossier d'intérêt instruit ne devenait jamais un dossier :
 * les deux moitiés du portail vivaient côte à côte sans passerelle.
 */
return static function (PDO $pdo): void {
    $tableExists = static function (PDO $pdo, string $table): bool {
        $st = $pdo->prepare(
            'SELECT 1 FROM information_schema.TABLES
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? LIMIT 1'
        );
        $st->execute([$table]);

        return (bool) $st->fetchColumn();
    };

    $columnExists = static function (PDO $pdo, string $table, string $column): bool {
        $st = $pdo->prepare(
            'SELECT 1 FROM information_schema.COLUMNS
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ? LIMIT 1'
        );
        $st->execute([$table, $column]);

        return (bool) $st->fetchColumn();
    };

    if (!$tableExists($pdo, 'sse_cases') || $columnExists($pdo, 'sse_cases', 'interest_case_id')) {
        return;
    }

    // NULL assumé : les dossiers existants n'ont pas d'origine connue, et en
    // inventer une serait un faux.
    $pdo->exec('ALTER TABLE sse_cases ADD COLUMN interest_case_id INT UNSIGNED DEFAULT NULL AFTER parent_id');
    $pdo->exec('ALTER TABLE sse_cases ADD KEY idx_sse_cases_interest (tenant_id, interest_case_id)');
};
