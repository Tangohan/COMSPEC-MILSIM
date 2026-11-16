<?php

declare(strict_types=1);

namespace App\Support;

use App\Core\Database;
use PDO;
use Throwable;

/**
 * Transaction robuste au commit implicite de MySQL / MariaDB.
 *
 * Un DDL (CREATE / ALTER TABLE, même « IF NOT EXISTS ») exécuté par un dépôt entre beginTransaction() et commit()
 * valide la transaction sans prévenir PDO : commit() et rollBack() lèvent alors « There is no active transaction »,
 * ce qui masquait l'erreur d'origine et renvoyait une page 500 (inscription du 16/11). Ici :
 *  - l'erreur d'origine est journalisée avant toute annulation ;
 *  - commit() et rollBack() ne sont appelés que si la transaction est encore ouverte ;
 *  - une transaction déjà close à la validation est signalée dans le journal (les écritures sont enregistrées).
 */
final class SafeTransaction
{
    /**
     * @template T
     * @param callable(): T $work
     * @return array{ok: bool, value: T|null, error: ?Throwable, closed_early: bool}
     */
    public static function run(PDO $pdo, callable $work, string $logTag): array
    {
        $pdo->beginTransaction();
        try {
            $value = $work();
            $committed = Database::safeCommit($pdo);
            if (!$committed) {
                error_log('[' . $logTag . '] transaction déjà close avant la validation (commit implicite d’un DDL) : les écritures sont enregistrées.');
            }

            return ['ok' => true, 'value' => $value, 'error' => null, 'closed_early' => !$committed];
        } catch (Throwable $e) {
            error_log('[' . $logTag . '] ' . get_class($e) . ' : ' . $e->getMessage() . ' (' . basename($e->getFile()) . ':' . $e->getLine() . ')');
            $rolledBack = Database::safeRollback($pdo);
            if (!$rolledBack) {
                error_log('[' . $logTag . '] annulation impossible : transaction déjà close, des écritures partielles peuvent rester.');
            }

            return ['ok' => false, 'value' => null, 'error' => $e, 'closed_early' => !$rolledBack];
        }
    }
}
