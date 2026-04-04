<?php

declare(strict_types=1);

/**
 * Compléments idempotents pour BDD importées (dump prod, hébergeur partiel, anciennes versions).
 * Appelé depuis run-migrations.php après les migrations plateforme.
 */
function run_production_import_gap_migrations(PDO $pdo, string $projectRoot): void
{
    echo "Migrations complémentaires (écarts import prod / ancien schéma)...\n";
    @flush();
    @ob_flush();

    // Refonte courrier (snippets, etc.) — fichier court, idempotent
    $refonte = $projectRoot . '/migrations/courrier_refonte.sql';
    if (is_file($refonte)) {
        $sql = file_get_contents($refonte);
        if ($sql !== false && $sql !== '') {
            $sql = preg_replace('/--[^\r\n]*/s', '', $sql);
            $sql = preg_replace('/SET NAMES utf8mb4;|SET FOREIGN_KEY_CHECKS = \d+;/i', '', $sql);
            $chunks = preg_split('/;\s*[\r\n]+/', trim($sql));
            foreach ($chunks as $stmtSql) {
                $stmtSql = trim($stmtSql);
                if ($stmtSql === '') {
                    continue;
                }
                try {
                    $pdo->exec($stmtSql . (str_ends_with($stmtSql, ';') ? '' : ';'));
                } catch (PDOException $e) {
                    echo '  [ATTENTION] courrier_refonte : ' . $e->getMessage() . "\n";
                }
            }
        }
    }

    // Index manquant possible sur documents (anciennes migrations partielles)
    $idx = $pdo->query("SELECT 1 FROM information_schema.STATISTICS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'documents' AND INDEX_NAME = 'idx_documents_visibility'");
    if ($idx && !$idx->fetch()) {
        $col = $pdo->query("SELECT COLUMN_NAME FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'documents' AND COLUMN_NAME = 'visibility_scope'");
        if ($col && $col->fetch()) {
            try {
                $pdo->exec('ALTER TABLE documents ADD INDEX idx_documents_visibility (visibility_scope)');
                echo "Index idx_documents_visibility ajouté.\n";
            } catch (PDOException $e) {
                echo '  [ATTENTION] idx_documents_visibility : ' . $e->getMessage() . "\n";
            }
        }
    }

    echo "Compléments import prod OK.\n";
    @flush();
    @ob_flush();
}
