<?php

declare(strict_types=1);

namespace App\Support;

use App\Core\Database;
use PDO;

/**
 * Connexion PDO à la première requête SQL — pas au constructeur.
 * Évite qu’un poll ATAK fasse échouer tout le boot Container sur une micro-coupure MySQL.
 */
trait LazyDatabaseConnection
{
    private ?PDO $pdo = null;

    protected function pdo(): PDO
    {
        if ($this->pdo instanceof PDO) {
            return $this->pdo;
        }
        $this->pdo = Database::getPdo();
        $this->onDatabaseConnected($this->pdo);

        return $this->pdo;
    }

    protected function onDatabaseConnected(PDO $pdo): void
    {
    }
}
