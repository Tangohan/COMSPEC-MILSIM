<?php

declare(strict_types=1);

/**
 * Crée forum_moderation_rules + forum_moderation_logs si absentes (déploiement partiel ou sans forum_v2).
 */
return function (PDO $pdo): void {
    $chk = $pdo->query("SELECT TABLE_NAME FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'forum_moderation_logs' LIMIT 1");
    if ($chk && $chk->fetchColumn()) {
        return;
    }

    $path = dirname(__DIR__) . '/migrations/forum_moderation_bot.sql';
    if (!is_file($path)) {
        echo "[ATTENTION] forum_moderation_bot.sql introuvable.\n";

        return;
    }

    echo "Forum modération bot : création des tables rules/logs...\n";
    @flush();
    @ob_flush();

    $sql = file_get_contents($path);
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
                echo '  [ATTENTION] forum_moderation_bot SQL : ' . $msg . "\n";
            }
        }
    }
};
