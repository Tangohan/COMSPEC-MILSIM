<?php

declare(strict_types=1);

/**
 * Tables moderation_artifacts, moderation_decisions.
 */
return function (PDO $pdo): void {
    $sqlPath = dirname(__DIR__) . '/migrations/moderation_content.sql';
    if (!is_file($sqlPath)) {
        echo "[ATTENTION] moderation_content.sql introuvable.\n";

        return;
    }

    echo "Modération contenus : exécution de migrations/moderation_content.sql...\n";
    @flush();
    @ob_flush();

    $sql = file_get_contents($sqlPath);
    if ($sql === false || $sql === '') {
        return;
    }
    $sql = preg_replace('/--[^\r\n]*/s', '', $sql);
    $sql = preg_replace('/SET NAMES utf8mb4;/i', '', $sql);
    $chunks = preg_split('/;\s*[\r\n]+/', trim($sql));
    foreach ($chunks as $stmtSql) {
        $stmtSql = trim($stmtSql);
        if ($stmtSql === '') {
            continue;
        }
        $full = $stmtSql . (str_ends_with($stmtSql, ';') ? '' : ';');
        try {
            $pdo->exec($full);
        } catch (PDOException $e) {
            $driverCode = (int) ($e->errorInfo[1] ?? 0);
            $msg = $e->getMessage();
            $ignorable = in_array($driverCode, [1005, 1007, 1022, 1050, 1060, 1061, 1091, 1826], true)
                || preg_match('/Duplicate (column|key|foreign key|entry)/i', $msg)
                || (str_contains($msg, 'already exists') && !str_contains($msg, 'Failed'));
            if (!$ignorable) {
                echo '  [ATTENTION] Modération SQL : ' . $msg . "\n";
            }
        }
    }

    $stmt = $pdo->query("SELECT COLUMN_NAME FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'courrier_documents' AND COLUMN_NAME = 'moderation_state'");
    if ($stmt && !$stmt->fetch()) {
        echo "  Ajout courrier_documents.moderation_state...\n";
        try {
            $pdo->exec('ALTER TABLE courrier_documents ADD COLUMN moderation_state VARCHAR(32) DEFAULT NULL AFTER classification_level');
        } catch (PDOException $e) {
            echo '  [ATTENTION] ' . $e->getMessage() . "\n";
        }
    }
};
