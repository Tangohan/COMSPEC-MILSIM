<?php

declare(strict_types=1);

namespace Tests\Support;

use PDOException;
use PDOStatement;

/**
 * Classe de requête préparée (PDO::ATTR_STATEMENT_CLASS) qui note chaque erreur levée par MySQL avant de la relancer.
 *
 * La création d'une communauté avale beaucoup d'exceptions (« non bloquant ») : sans ce relevé, une valeur trop longue
 * refusée par le mode strict passerait inaperçue. Rien n'est simulé : la requête part vraiment au serveur.
 */
final class StrictWriteRecorder extends PDOStatement
{
    /** Erreurs de données du mode strict (valeur trop longue, hors plage, chaîne invalide, colonne sans défaut…). */
    private const DATA_ERROR_CODES = [1048, 1264, 1265, 1292, 1364, 1366, 1406];

    /** @var list<array{sqlstate: string, code: int, message: string, sql: string}> */
    private static array $errors = [];

    protected function __construct()
    {
    }

    public function execute(?array $params = null): bool
    {
        try {
            return parent::execute($params);
        } catch (PDOException $e) {
            self::$errors[] = [
                'sqlstate' => (string) ($e->errorInfo[0] ?? $e->getCode()),
                'code' => (int) ($e->errorInfo[1] ?? 0),
                'message' => $e->getMessage(),
                'sql' => preg_replace('/\s+/', ' ', trim($this->queryString)) ?? '',
            ];
            throw $e;
        }
    }

    public static function reset(): void
    {
        self::$errors = [];
    }

    /** @return list<array{sqlstate: string, code: int, message: string, sql: string}> */
    public static function dataErrors(): array
    {
        return array_values(array_filter(
            self::$errors,
            static fn (array $e): bool => str_starts_with($e['sqlstate'], '22') || in_array($e['code'], self::DATA_ERROR_CODES, true)
        ));
    }

    /** Tables ou colonnes absentes (42S02, 42S22) : schéma de test incomplet ou code qui vise une colonne retirée. */
    public static function schemaErrors(): array
    {
        return array_values(array_filter(self::$errors, static fn (array $e): bool => in_array($e['sqlstate'], ['42S02', '42S22'], true)));
    }

    /** @param list<array{sqlstate: string, code: int, message: string, sql: string}> $errors */
    public static function describe(array $errors): string
    {
        $lines = [];
        foreach ($errors as $e) {
            $lines[] = $e['message'] . ' | ' . mb_substr($e['sql'], 0, 240);
        }

        return implode("\n", array_unique($lines));
    }
}
