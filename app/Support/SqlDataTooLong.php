<?php

declare(strict_types=1);

namespace App\Support;

use Throwable;

/**
 * Erreur « valeur trop longue pour la colonne » (SQLSTATE 22001, MySQL 1406 en mode strict) : colonne fautive lue dans le
 * message, table retrouvée dans la requête appelante (INSERT / UPDATE au-dessus de la ligne qui a exécuté la requête).
 * Ne renvoie que des noms de schéma, jamais la valeur refusée.
 */
final class SqlDataTooLong
{
    public static function matches(Throwable $e): bool
    {
        return self::find($e) !== null;
    }

    public static function column(Throwable $e): ?string
    {
        $hit = self::find($e);
        if ($hit === null) {
            return null;
        }

        return preg_match("/for column [`'\"]?([A-Za-z0-9_]+)[`'\"]?/i", $hit->getMessage(), $m) === 1 ? $m[1] : null;
    }

    public static function table(Throwable $e): ?string
    {
        $hit = self::find($e);
        if ($hit === null) {
            return null;
        }
        $origin = self::origin($hit);
        if ($origin === null || !is_file($origin['file'])) {
            return null;
        }
        $lines = @file($origin['file']);
        if (!is_array($lines)) {
            return null;
        }
        $from = max(0, $origin['line'] - 30);
        $chunk = implode("\n", array_slice($lines, $from, $origin['line'] - $from));
        if (preg_match_all('/\b(?:INSERT(?:\s+IGNORE)?\s+INTO|REPLACE\s+INTO|UPDATE)\s+`?([A-Za-z0-9_]+)`?/i', $chunk, $m) < 1) {
            return null;
        }

        return (string) end($m[1]);
    }

    /** « table.colonne » (ou la colonne seule), pour le support et le journal. */
    public static function reference(Throwable $e): string
    {
        $column = self::column($e) ?? '?';
        $table = self::table($e);

        return $table !== null ? $table . '.' . $column : $column;
    }

    /** Ligne de journal sans donnée personnelle : SQLSTATE, table.colonne et ligne du code qui a exécuté la requête. */
    public static function logLine(Throwable $e): string
    {
        $hit = self::find($e);
        if ($hit === null) {
            return '';
        }
        $origin = self::origin($hit);

        return 'SQLSTATE 22001 valeur trop longue pour ' . self::reference($e)
            . ($origin !== null ? ' (' . basename($origin['file']) . ':' . $origin['line'] . ')' : '');
    }

    private static function find(Throwable $e): ?Throwable
    {
        for ($cur = $e, $depth = 0; $cur !== null && $depth < 8; $cur = $cur->getPrevious(), $depth++) {
            $msg = $cur->getMessage();
            if ((string) $cur->getCode() === '22001' || preg_match('/SQLSTATE\[22001\]|\b1406\b.*Data too long|Data too long for column/i', $msg) === 1) {
                return $cur;
            }
        }

        return null;
    }

    /** @return array{file: string, line: int}|null Appel PDO le plus proche du code de l'application. */
    private static function origin(Throwable $e): ?array
    {
        $pdoSeen = false;
        foreach ($e->getTrace() as $frame) {
            if (!isset($frame['file'], $frame['line'])) {
                continue;
            }
            $class = (string) ($frame['class'] ?? '');
            $fn = (string) ($frame['function'] ?? '');
            if (!$pdoSeen) {
                $pdoSeen = ($class === 'PDOStatement' || $class === 'PDO') && in_array($fn, ['execute', 'exec', 'query'], true);
                if (!$pdoSeen) {
                    continue;
                }
            }
            // Requête passée par l'enveloppe App\Core\Database : la table est écrite chez l'appelant.
            if (str_ends_with(str_replace('\\', '/', (string) $frame['file']), 'app/Core/Database.php')) {
                continue;
            }

            return ['file' => (string) $frame['file'], 'line' => (int) $frame['line']];
        }

        return null;
    }
}
