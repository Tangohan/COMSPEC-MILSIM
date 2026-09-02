<?php

declare(strict_types=1);

/**
 * Autorise l’absence de pièce jointe sur une version. Idempotent.
 */
function run_document_versions_file_path_nullable_migration(PDO $pdo): void
{
    $st = $pdo->prepare(
        'SELECT IS_NULLABLE FROM information_schema.COLUMNS
         WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ? LIMIT 1'
    );
    $st->execute(['document_versions', 'file_path']);
    $nullable = $st->fetchColumn();
    if ($nullable === false) {
        echo "  [ATTENTION] document_versions.file_path absente\n";

        return;
    }
    if (is_string($nullable) && strtoupper($nullable) === 'YES') {
        echo "  [OK] document_versions.file_path déjà nullable\n";

        return;
    }
    try {
        $pdo->exec('ALTER TABLE document_versions MODIFY file_path VARCHAR(500) NULL');
        echo "  [OK] document_versions.file_path nullable\n";
    } catch (PDOException $e) {
        echo '  [ATTENTION] document_versions.file_path nullable : ' . $e->getMessage() . "\n";
    }
}
