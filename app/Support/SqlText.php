<?php

declare(strict_types=1);

namespace App\Support;

use InvalidArgumentException;
use PDO;

/**
 * Comparaisons texte MariaDB / MySQL sans mélange de collations (erreur 1267).
 *
 * Un LOWER()/TRIM() sur une colonne utf8mb4_bin a une collation non coercible ;
 * le paramètre PDO suit collation_connection (souvent utf8mb4_general_ci) → '=' refuse.
 */
final class SqlText
{
    public const COLLATION = 'utf8mb4_unicode_ci';

    public static function isMysqlFamily(PDO $pdo): bool
    {
        $driver = strtolower((string) $pdo->getAttribute(PDO::ATTR_DRIVER_NAME));

        return $driver === 'mysql' || $driver === 'mariadb';
    }

    /**
     * LOWER(TRIM(colonne)) = ? — un placeholder.
     */
    public static function normalizedEquals(PDO $pdo, string $columnExpr): string
    {
        self::assertColumnExpr($columnExpr);
        if (!self::isMysqlFamily($pdo)) {
            return 'LOWER(TRIM(' . $columnExpr . ')) = ?';
        }

        return 'LOWER(TRIM(' . $columnExpr . ')) COLLATE ' . self::COLLATION
            . ' = CONVERT(? USING utf8mb4) COLLATE ' . self::COLLATION;
    }

    /**
     * LOWER(TRIM(colonne)) <> ? — un placeholder.
     */
    public static function normalizedNotEquals(PDO $pdo, string $columnExpr): string
    {
        self::assertColumnExpr($columnExpr);
        if (!self::isMysqlFamily($pdo)) {
            return 'LOWER(TRIM(' . $columnExpr . ')) <> ?';
        }

        return 'LOWER(TRIM(' . $columnExpr . ')) COLLATE ' . self::COLLATION
            . ' <> CONVERT(? USING utf8mb4) COLLATE ' . self::COLLATION;
    }

    /**
     * LOWER(TRIM(colonne)) NOT LIKE ? — un placeholder.
     */
    public static function normalizedNotLike(PDO $pdo, string $columnExpr): string
    {
        self::assertColumnExpr($columnExpr);
        if (!self::isMysqlFamily($pdo)) {
            return 'LOWER(TRIM(' . $columnExpr . ')) NOT LIKE ?';
        }

        return 'LOWER(TRIM(' . $columnExpr . ')) COLLATE ' . self::COLLATION
            . ' NOT LIKE CONVERT(? USING utf8mb4) COLLATE ' . self::COLLATION;
    }

    /**
     * LOWER(TRIM(colonne)) <> 'valeur' (littéral figé).
     */
    public static function normalizedNotEqualsLiteral(PDO $pdo, string $columnExpr, string $value): string
    {
        self::assertColumnExpr($columnExpr);
        self::assertEmailLiteral($value);
        $quoted = "'" . str_replace("'", "''", $value) . "'";
        if (!self::isMysqlFamily($pdo)) {
            return 'LOWER(TRIM(' . $columnExpr . ')) <> ' . $quoted;
        }

        return 'LOWER(TRIM(' . $columnExpr . ')) COLLATE ' . self::COLLATION
            . ' <> ' . $quoted . ' COLLATE ' . self::COLLATION;
    }

    /**
     * colonne = 'valeur' (littéral figé — slug, statut, etc.).
     */
    public static function equalsLiteral(PDO $pdo, string $columnExpr, string $value): string
    {
        self::assertColumnExpr($columnExpr);
        self::assertTokenLiteral($value);
        $quoted = "'" . str_replace("'", "''", $value) . "'";
        if (!self::isMysqlFamily($pdo)) {
            return $columnExpr . ' = ' . $quoted;
        }

        return '(' . $columnExpr . ' COLLATE ' . self::COLLATION . ') = (' . $quoted . ' COLLATE ' . self::COLLATION . ')';
    }

    /**
     * colonne <> 'valeur' (littéral figé).
     */
    public static function notEqualsLiteral(PDO $pdo, string $columnExpr, string $value): string
    {
        self::assertColumnExpr($columnExpr);
        self::assertTokenLiteral($value);
        $quoted = "'" . str_replace("'", "''", $value) . "'";
        if (!self::isMysqlFamily($pdo)) {
            return $columnExpr . ' <> ' . $quoted;
        }

        return '(' . $columnExpr . ' COLLATE ' . self::COLLATION . ') <> (' . $quoted . ' COLLATE ' . self::COLLATION . ')';
    }

    /**
     * COALESCE(a, b) = 'valeur' (littéral figé).
     */
    public static function coalesceEqualsLiteral(PDO $pdo, string $first, string $second, string $value): string
    {
        self::assertColumnExpr($first);
        self::assertColumnExpr($second);
        self::assertTokenLiteral($value);
        $expr = 'COALESCE(' . $first . ', ' . $second . ')';
        $quoted = "'" . str_replace("'", "''", $value) . "'";
        if (!self::isMysqlFamily($pdo)) {
            return $expr . ' = ' . $quoted;
        }

        return '(' . $expr . ' COLLATE ' . self::COLLATION . ') = (' . $quoted . ' COLLATE ' . self::COLLATION . ')';
    }

    /**
     * colonne = ? — un placeholder.
     */
    public static function equals(PDO $pdo, string $columnExpr): string
    {
        self::assertColumnExpr($columnExpr);
        if (!self::isMysqlFamily($pdo)) {
            return $columnExpr . ' = ?';
        }

        return '(' . $columnExpr . ' COLLATE ' . self::COLLATION
            . ') = (CONVERT(? USING utf8mb4) COLLATE ' . self::COLLATION . ')';
    }

    /**
     * LOWER(TRIM(colonne)) NOT LIKE 'motif' (motif figé, pas un placeholder).
     */
    public static function normalizedNotLikeLiteral(PDO $pdo, string $columnExpr, string $pattern): string
    {
        self::assertColumnExpr($columnExpr);
        self::assertLikePattern($pattern);
        $quoted = "'" . str_replace("'", "''", $pattern) . "'";
        if (!self::isMysqlFamily($pdo)) {
            return 'LOWER(TRIM(' . $columnExpr . ')) NOT LIKE ' . $quoted;
        }

        return 'LOWER(TRIM(' . $columnExpr . ')) COLLATE ' . self::COLLATION
            . ' NOT LIKE ' . $quoted . ' COLLATE ' . self::COLLATION;
    }

    /**
     * colonne IN ('a', 'b') — littéraux métier figés.
     *
     * @param list<string> $literals
     */
    public static function inLiterals(PDO $pdo, string $columnExpr, array $literals): string
    {
        self::assertColumnExpr($columnExpr);
        if ($literals === []) {
            throw new InvalidArgumentException('Liste IN vide.');
        }
        $quoted = [];
        foreach ($literals as $literal) {
            if (!is_string($literal) || !preg_match('/^[a-z][a-z0-9_]*$/', $literal)) {
                throw new InvalidArgumentException('Littéral IN invalide.');
            }
            $quoted[] = "'" . $literal . "'";
        }
        if (!self::isMysqlFamily($pdo)) {
            return $columnExpr . ' IN (' . implode(', ', $quoted) . ')';
        }
        $coerced = [];
        foreach ($quoted as $item) {
            $coerced[] = $item . ' COLLATE ' . self::COLLATION;
        }

        return '(' . $columnExpr . ' COLLATE ' . self::COLLATION . ') IN (' . implode(', ', $coerced) . ')';
    }

    /**
     * COALESCE(a, b) = ? — un placeholder.
     */
    public static function coalesceEquals(PDO $pdo, string $first, string $second): string
    {
        self::assertColumnExpr($first);
        self::assertColumnExpr($second);
        $expr = 'COALESCE(' . $first . ', ' . $second . ')';
        if (!self::isMysqlFamily($pdo)) {
            return $expr . ' = ?';
        }

        return '(' . $expr . ' COLLATE ' . self::COLLATION
            . ') = (CONVERT(? USING utf8mb4) COLLATE ' . self::COLLATION . ')';
    }

    /**
     * COALESCE(a, b) IN ('a', 'b') — littéraux métier figés.
     *
     * @param list<string> $literals
     */
    public static function coalesceInLiterals(PDO $pdo, string $first, string $second, array $literals): string
    {
        self::assertColumnExpr($first);
        self::assertColumnExpr($second);
        $expr = 'COALESCE(' . $first . ', ' . $second . ')';
        if ($literals === []) {
            throw new InvalidArgumentException('Liste IN vide.');
        }
        $quoted = [];
        foreach ($literals as $literal) {
            if (!is_string($literal) || !preg_match('/^[a-z][a-z0-9_]*$/', $literal)) {
                throw new InvalidArgumentException('Littéral IN invalide.');
            }
            $quoted[] = "'" . $literal . "'";
        }
        if (!self::isMysqlFamily($pdo)) {
            return $expr . ' IN (' . implode(', ', $quoted) . ')';
        }
        $coerced = [];
        foreach ($quoted as $item) {
            $coerced[] = $item . ' COLLATE ' . self::COLLATION;
        }

        return '(' . $expr . ' COLLATE ' . self::COLLATION . ') IN (' . implode(', ', $coerced) . ')';
    }

    /**
     * LOWER(TRIM(colonne)) IN (?,?,…) — autant de placeholders que $count.
     */
    public static function normalizedInPlaceholders(PDO $pdo, string $columnExpr, int $count): string
    {
        self::assertColumnExpr($columnExpr);
        if ($count < 1) {
            throw new InvalidArgumentException('Liste IN vide.');
        }
        if (!self::isMysqlFamily($pdo)) {
            return 'LOWER(TRIM(' . $columnExpr . ')) IN (' . implode(', ', array_fill(0, $count, '?')) . ')';
        }
        $coerced = implode(', ', array_fill(0, $count, 'CONVERT(? USING utf8mb4) COLLATE ' . self::COLLATION));

        return 'LOWER(TRIM(' . $columnExpr . ')) COLLATE ' . self::COLLATION . ' IN (' . $coerced . ')';
    }

    private static function assertColumnExpr(string $columnExpr): void
    {
        if (!preg_match('/^[A-Za-z0-9_`.]+$/', $columnExpr)) {
            throw new InvalidArgumentException('Expression de colonne invalide.');
        }
    }

    private static function assertLikePattern(string $pattern): void
    {
        if ($pattern === '' || !preg_match('/^[A-Za-z0-9%@._-]+$/', $pattern)) {
            throw new InvalidArgumentException('Motif LIKE invalide.');
        }
    }

    private static function assertEmailLiteral(string $value): void
    {
        if ($value === '' || !preg_match('/^[A-Za-z0-9.@_+-]+$/', $value)) {
            throw new InvalidArgumentException('Littéral e-mail invalide.');
        }
    }

    private static function assertTokenLiteral(string $value): void
    {
        if ($value === '' || !preg_match('/^[a-z][a-z0-9_]*$/', $value)) {
            throw new InvalidArgumentException('Littéral texte invalide.');
        }
    }
}
