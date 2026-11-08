<?php

declare(strict_types=1);

namespace App\Services\Watch;

/**
 * État courant de la surveillance, dans un petit fichier JSON (storage/cache/watch/state.json) relu à chaque requête :
 * pas de base, pas de verrou. Écrit uniquement par WatchFlusher (écriture atomique : fichier temporaire puis rename).
 *
 * Règle de sûreté : un état absent, illisible ou plus vieux que LoadLevel::MAX_AGE_SECONDS vaut « palier 0, aucun
 * ralentissement ». Le capteur en panne ne coupe jamais un joueur.
 */
final class WatchState
{
    /** @var array<string, mixed>|null */
    private static ?array $memo = null;

    private static bool $memoLoaded = false;

    public static function path(): string
    {
        return WatchSpool::dir() . '/state.json';
    }

    /** Oublie la copie en mémoire du processus (tests, après écriture). */
    public static function forget(): void
    {
        self::$memo = null;
        self::$memoLoaded = false;
    }

    /**
     * Contenu brut du fichier, ou null s'il manque ou ne se lit pas.
     *
     * @return array<string, mixed>|null
     */
    public static function raw(): ?array
    {
        if (self::$memoLoaded) {
            return self::$memo;
        }
        self::$memoLoaded = true;
        self::$memo = self::decode(@file_get_contents(self::path()));

        return self::$memo;
    }

    /** @return array<string, mixed>|null */
    public static function decode(string|false $json): ?array
    {
        if (!is_string($json) || $json === '') {
            return null;
        }
        $data = json_decode($json, true);

        return is_array($data) && isset($data['at']) ? $data : null;
    }

    /**
     * Palier de charge à appliquer maintenant : 0 si le ralentissement est désactivé, si l'état manque, est trop
     * ancien ou si le capteur n'est pas fiable.
     *
     * @param array<string, mixed>|null $state
     */
    public static function effectiveLevel(?array $state, int $now): int
    {
        if ($state === null || empty($state['shed_enabled'])) {
            return 0;
        }

        return self::measuredLevel($state, $now);
    }

    /**
     * Palier mesuré, indépendamment du réglage d'activation (affiché dans l'administration).
     *
     * @param array<string, mixed>|null $state
     */
    public static function measuredLevel(?array $state, int $now): int
    {
        if ($state === null) {
            return 0;
        }
        $age = $now - (int) ($state['at'] ?? 0);
        if ($age > LoadLevel::MAX_AGE_SECONDS || $age < -30 || empty($state['sensor']['reliable'])) {
            return 0;
        }

        return max(0, min(3, (int) ($state['level'] ?? 0)));
    }

    /** Âge de l'état en secondes (null si absent). */
    public static function ageSeconds(?array $state, int $now): ?int
    {
        return $state === null ? null : max(0, $now - (int) ($state['at'] ?? 0));
    }

    /** La détection d'activité anormale du mod est-elle active ? Vrai tant qu'aucun état ne dit le contraire. */
    public static function detectEnabled(): bool
    {
        $state = self::raw();

        return $state === null || !array_key_exists('detect_enabled', $state) || !empty($state['detect_enabled']);
    }

    /** @param array<string, mixed> $state */
    public static function write(array $state): bool
    {
        $dir = WatchSpool::dir();
        if (!is_dir($dir) && !@mkdir($dir, 0775, true) && !is_dir($dir)) {
            return false;
        }
        $file = self::path();
        $tmp = $file . '.' . getmypid() . '.tmp';
        $json = json_encode($state, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        if ($json === false || @file_put_contents($tmp, $json) === false) {
            return false;
        }
        if (!@rename($tmp, $file)) {
            @unlink($tmp);

            return false;
        }
        self::forget();

        return true;
    }
}
