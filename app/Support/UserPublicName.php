<?php

declare(strict_types=1);

namespace App\Support;

use App\Services\Moderation\NameModerationService;
use PDO;

/**
 * Pseudo public (users.public_name) : le nom qu'un membre choisit pour les pages publiques du site
 * (liste des contributeurs, propositions de traduction) et que l'administration voit en premier.
 * Sans pseudo, l'indicatif est repris ; le prénom et le nom (display_name) ne sont jamais affichés en public.
 * Colonne posée par migrations/20261025000003_user_public_name.sql, ou ici si la migration n'a pas tourné.
 */
final class UserPublicName
{
    public const MAX = 40;

    private static ?bool $ready = null;

    /** Ajoute la colonne si elle manque ; false si elle reste absente (droits SQL insuffisants). */
    public static function ensure(PDO $pdo): bool
    {
        if (self::$ready !== null) {
            return self::$ready;
        }
        try {
            $st = $pdo->query("SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'users' AND COLUMN_NAME = 'public_name' LIMIT 1");
            if ($st === false || !$st->fetchColumn()) {
                $pdo->exec("ALTER TABLE users ADD COLUMN public_name VARCHAR(40) NULL DEFAULT NULL COMMENT 'Pseudo public (pages publiques)' AFTER callsign");
            }
            self::$ready = true;
        } catch (\Throwable $e) {
            error_log('[users.public_name] ' . $e->getMessage());
            self::$ready = false;
        }

        return self::$ready;
    }

    /**
     * Expression SQL du nom public : pseudo, sinon indicatif, sinon $fallback.
     * Appeler ensure() avant : sans la colonne, seul l'indicatif est repris.
     */
    public static function sql(string $alias = 'u', string $fallback = '?'): string
    {
        $fb = "'" . str_replace("'", "''", $fallback) . "'";
        if (self::$ready === true) {
            return "COALESCE(NULLIF(TRIM($alias.public_name), ''), NULLIF(TRIM($alias.callsign), ''), $fb)";
        }

        return "COALESCE(NULLIF(TRIM($alias.callsign), ''), $fb)";
    }

    /** Colonne à ajouter dans un SELECT (NULL si absente). */
    public static function column(string $alias = 'u'): string
    {
        return self::$ready === true ? "$alias.public_name" : 'NULL AS public_name';
    }

    public static function get(PDO $pdo, int $userId): string
    {
        if ($userId < 1 || !self::ensure($pdo)) {
            return '';
        }
        $st = $pdo->prepare('SELECT public_name FROM users WHERE id = ? LIMIT 1');
        $st->execute([$userId]);

        return trim((string) $st->fetchColumn());
    }

    /**
     * Nettoie un pseudo saisi : espaces resserrés, caractères de contrôle et chevrons retirés, 40 caractères.
     */
    public static function normalize(string $raw): string
    {
        $v = (string) preg_replace('/[\x00-\x1F\x7F<>]+/u', '', $raw);
        $v = trim((string) preg_replace('/\s+/u', ' ', $v));

        return mb_substr($v, 0, self::MAX);
    }

    /** Message d'erreur (clé de traduction) ou null si le pseudo est acceptable. */
    public static function validationError(string $name): ?string
    {
        if ($name === '') {
            return null;
        }
        if (mb_strlen($name) < 2) {
            return 'site.public_name_too_short';
        }
        try {
            if (NameModerationService::filter()->isFlagged($name)) {
                return 'site.public_name_refused';
            }
        } catch (\Throwable) {
        }

        return null;
    }

    /** Enregistre le pseudo (vide = aucun). */
    public static function save(PDO $pdo, int $userId, string $name): bool
    {
        if ($userId < 1 || !self::ensure($pdo)) {
            return false;
        }
        $st = $pdo->prepare('UPDATE users SET public_name = ? WHERE id = ?');

        return $st->execute([$name !== '' ? $name : null, $userId]);
    }
}
