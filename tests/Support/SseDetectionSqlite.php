<?php

declare(strict_types=1);

namespace Tests\Support;

use App\Repositories\SseDetectionRepository;
use PDO;

/**
 * Tables de Cerbère-Lecture (étape sse_detection_rules) dans une base sqlite de test : le VRAI fichier de l'étape est joué sur
 * une base de passage qui traduit le SQL MySQL (MysqlDdlSqlitePdo), puis les tables obtenues sont recopiées dans la base visée.
 * Ajoute aussi le journal SSE (sse_audit_log) s'il manque.
 */
final class SseDetectionSqlite
{
    public const TABLES = ['sse_detection_rule_settings', 'sse_detection_decisions'];

    /** Joue l'étape sur la base de passage ; retourne sa sortie. */
    public static function install(PDO $target): string
    {
        $scratch = new MysqlDdlSqlitePdo();
        $output = self::runStep($scratch);
        foreach ($scratch->query("SELECT sql FROM sqlite_master WHERE sql IS NOT NULL AND tbl_name IN ('" . implode("','", self::TABLES) . "') ORDER BY type = 'index', name")->fetchAll(PDO::FETCH_COLUMN) as $sql) {
            $target->exec((string) $sql);
        }
        $target->exec('CREATE TABLE IF NOT EXISTS sse_audit_log (id INTEGER PRIMARY KEY AUTOINCREMENT, tenant_id INTEGER NOT NULL, actor_user_id INTEGER, actor_label TEXT, action TEXT, object_type TEXT, object_id INTEGER, object_uuid TEXT, reason TEXT, before_json TEXT, after_json TEXT, created_at TEXT DEFAULT CURRENT_TIMESTAMP)');
        SseDetectionRepository::forgetReady();

        return $output;
    }

    public static function runStep(PDO $pdo): string
    {
        $root = dirname(__DIR__, 2);
        ob_start();
        try {
            require $root . '/database/steps/4890_sse_detection_rules.php';
        } finally {
            $out = (string) ob_get_clean();
        }

        return $out;
    }
}
