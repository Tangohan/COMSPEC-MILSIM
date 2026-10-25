<?php

declare(strict_types=1);

namespace App\Support\Api;

use Throwable;

/**
 * Tri des exceptions attrapées par les API : seules les erreurs métier (levées par nos services
 * avec un message destiné au membre) sont renvoyées telles quelles en 4xx. Le reste (PDOException,
 * TypeError, ErrorException…) doit remonter à ExceptionHandler : réponse générique, 503 si la base
 * est coupée, journal et e-mail d’erreur — jamais le message SQL ou le chemin du fichier au client.
 */
final class ApiBusinessError
{
    public static function isBusiness(Throwable $e): bool
    {
        if ($e instanceof \PDOException) {
            return false;
        }

        return $e instanceof \InvalidArgumentException
            || $e instanceof \DomainException
            || $e instanceof \RuntimeException;
    }
}
