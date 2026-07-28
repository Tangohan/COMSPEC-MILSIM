<?php

declare(strict_types=1);

namespace App\Support;

use InvalidArgumentException;
use RuntimeException;
use Throwable;

/**
 * Traduit les erreurs techniques en messages actionnables pour l’interface.
 */
final class UserFacingExceptionMapper
{
    public static function registrationMessage(Throwable $e): string
    {
        if (self::isDuplicateEmail($e)) {
            return 'Cette adresse e-mail est déjà utilisée. Connectez-vous ou choisissez une autre adresse.';
        }

        return 'Inscription impossible pour le moment. Réessayez dans quelques instants.';
    }

    public static function communityCreationMessage(Throwable $e): string
    {
        $msg = trim($e->getMessage());
        if ($e instanceof InvalidArgumentException && $msg !== '') {
            return $msg;
        }
        if ($e instanceof RuntimeException && $msg !== '' && !self::looksTechnical($msg)) {
            return $msg;
        }
        if (self::isDuplicateSlug($e)) {
            return 'Une communauté utilise déjà cette adresse courte. Choisissez-en une autre.';
        }

        return 'Impossible de créer la communauté pour le moment. Réessayez plus tard ou contactez le support.';
    }

    private static function looksTechnical(string $msg): bool
    {
        return (bool) preg_match('/SQLSTATE|Unknown column|PDOException|\bPDO\b|duplicate key/i', $msg);
    }

    private static function isDuplicateEmail(Throwable $e): bool
    {
        return (bool) preg_match('/Duplicate.*(email|users\.email|uniq.*email)/i', $e->getMessage());
    }

    private static function isDuplicateSlug(Throwable $e): bool
    {
        return (bool) preg_match('/Duplicate.*(slug|tenants\.slug)/i', $e->getMessage());
    }
}
