<?php

declare(strict_types=1);

/**
 * Table enlistment_canned_messages (messages préfaits décision recrutement).
 */
return function (PDO $pdo): void {
    $sqlPath = dirname(__DIR__) . '/migrations/enlistment_canned_messages.sql';
    if (!is_file($sqlPath)) {
        echo "[ATTENTION] enlistment_canned_messages.sql introuvable — ignoré.\n";

        return;
    }

    echo "Recrutement : messages préfaits (enlistment_canned_messages)...\n";
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
                echo '  [ATTENTION] enlistment_canned_messages : ' . $msg . "\n";
            }
        }
    }
};
