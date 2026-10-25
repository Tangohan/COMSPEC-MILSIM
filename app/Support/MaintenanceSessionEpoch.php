<?php

declare(strict_types=1);

namespace App\Support;

/**
 * Une maintenance globale ferme toutes les sessions du site, sauf celles des administrateurs plateforme.
 *
 * Pendant la maintenance, MaintenanceGuard déconnecte toute session qui se présente. Celles qui ne reviennent
 * qu'après la réouverture sont fermées par AuthMiddleware : une session ouverte avant le début de la dernière
 * maintenance globale (auth_issued_at plus ancien que l'horodatage gardé ici) doit se reconnecter.
 *
 * L'horodatage est posé la première fois qu'une règle globale s'applique (fichier storage/, aucune migration),
 * et reposé quand la règle est réactivée ou modifiée (clé : id, starts_at, updated_at).
 */
final class MaintenanceSessionEpoch
{
    private static ?string $pathOverride = null;

    /** Tests : fichier temporaire à la place de storage/maintenance_session_epoch.json. */
    public static function usePath(?string $path): void
    {
        self::$pathOverride = $path;
    }

    /**
     * Règle globale active vue par MaintenanceGuard : pose l'horodatage si cette activation n'est pas encore connue.
     *
     * @param array<string, mixed> $rule
     */
    public static function mark(array $rule, ?int $now = null): int
    {
        $key = implode('|', [
            (string) ($rule['id'] ?? ''),
            (string) ($rule['starts_at'] ?? ''),
            (string) ($rule['updated_at'] ?? ''),
        ]);
        $current = self::read();
        if (($current['key'] ?? null) === $key && (int) ($current['epoch'] ?? 0) > 0) {
            return (int) $current['epoch'];
        }
        $epoch = $now ?? time();
        $path = self::path();
        $dir = dirname($path);
        if (!is_dir($dir)) {
            @mkdir($dir, 0775, true);
        }
        @file_put_contents($path, json_encode(['key' => $key, 'epoch' => $epoch], JSON_UNESCAPED_SLASHES), LOCK_EX);

        return $epoch;
    }

    /** Début de la dernière maintenance globale (horodatage Unix), 0 si aucune. */
    public static function epoch(): int
    {
        return (int) (self::read()['epoch'] ?? 0);
    }

    /** @return array<string, mixed> */
    private static function read(): array
    {
        $path = self::path();
        if (!is_file($path)) {
            return [];
        }
        $data = json_decode((string) @file_get_contents($path), true);

        return is_array($data) ? $data : [];
    }

    private static function path(): string
    {
        if (self::$pathOverride !== null) {
            return self::$pathOverride;
        }

        return function_exists('base_path')
            ? base_path('storage/maintenance_session_epoch.json')
            : dirname(__DIR__, 2) . '/storage/maintenance_session_epoch.json';
    }
}
