<?php

declare(strict_types=1);

namespace App\Support;

use App\Core\Database;
use PDO;

/**
 * Les trois tables de l'étape 4690 (sse_triage_state, sse_triage_rules, sse_triage_events) existent-elles ? Sans elles « À trier »
 * reste lisible et regroupé, sans acquittement persistant (mode dégradé). Résultat gardé par connexion : durablement quand il est
 * positif, 20 s quand il est négatif (l'étape peut passer pendant qu'une page est servie).
 */
final class SseTriageSchema
{
    public const TABLES = ['sse_triage_state', 'sse_triage_rules', 'sse_triage_events'];

    /** @var \WeakMap<PDO, array{ok: bool, at: int}>|null */
    private static ?\WeakMap $known = null;

    public static function ready(): bool
    {
        try {
            $pdo = Database::getPdo();
        } catch (\Throwable) {
            return false;
        }
        self::$known ??= new \WeakMap();
        $hit = self::$known[$pdo] ?? null;
        if (is_array($hit) && ($hit['ok'] || time() - $hit['at'] < 20)) {
            return $hit['ok'];
        }
        $ok = true;
        foreach (self::TABLES as $table) {
            try {
                $pdo->query('SELECT 1 FROM ' . $table . ' WHERE 1 = 0')->closeCursor();
            } catch (\Throwable) {
                $ok = false;
                break;
            }
        }
        self::$known[$pdo] = ['ok' => $ok, 'at' => time()];

        return $ok;
    }

    public static function forget(): void
    {
        self::$known = null;
    }
}
