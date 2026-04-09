<?php

declare(strict_types=1);

/**
 * Réactions forum + badge de publication sur les messages.
 */
return function (PDO $pdo): void {
    $sqlPath = dirname(__DIR__) . '/migrations/forum_premium.sql';
    if (!is_file($sqlPath)) {
        echo "[ATTENTION] forum_premium.sql introuvable — migration ignorée.\n";

        return;
    }

    echo "Forum premium : exécution de migrations/forum_premium.sql...\n";
    @flush();
    @ob_flush();

    $sql = file_get_contents($sqlPath);
    if ($sql === false || $sql === '') {
        return;
    }
    $sql = preg_replace('/--[^\r\n]*/', '', $sql);
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
                echo '  [ATTENTION] forum_premium SQL : ' . $msg . "\n";
            }
        }
    }
};
