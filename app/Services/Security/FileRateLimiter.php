<?php

declare(strict_types=1);

namespace App\Services\Security;

/**
 * Fenêtre fixe par clé (fichier JSON + verrou), pour limiter login / recrutement / etc.
 */
final class FileRateLimiter
{
    /**
     * Incrémente le compteur et indique si le plafond est dépassé.
     */
    public function tooManyAttempts(string $key, int $maxAttempts, int $decaySeconds): bool
    {
        return $this->hit($key, $decaySeconds) > $maxAttempts;
    }

    /**
     * Enregistre une tentative et retourne le compteur courant dans la fenêtre.
     */
    public function hit(string $key, int $decaySeconds): int
    {
        return $this->mutate($key, $decaySeconds, true);
    }

    /**
     * Compteur courant sans incrémenter (fenêtre expirée → 0).
     */
    public function attempts(string $key, int $decaySeconds): int
    {
        return $this->mutate($key, $decaySeconds, false);
    }

    /**
     * Secondes avant la remise à zéro de la fenêtre en cours pour cette clé, au moins 1 et au plus $decaySeconds
     * (NET-027). La fenêtre est fixe : un refus prend fin à window_start + $decaySeconds, et non une fenêtre entière
     * après lui. Sans compteur lisible, ou fenêtre déjà finie : 1. Ne modifie pas le compteur.
     */
    public function secondsUntilReset(string $key, int $decaySeconds): int
    {
        $path = base_path('storage/cache/ratelimit') . '/' . hash('sha256', $key) . '.json';
        $fp = is_file($path) ? @fopen($path, 'r') : false;
        if ($fp === false) {
            return 1;
        }
        try {
            if (!flock($fp, LOCK_SH)) {
                return 1;
            }
            $raw = stream_get_contents($fp);
            flock($fp, LOCK_UN);
            $data = is_string($raw) && $raw !== '' ? json_decode($raw, true) : null;
            if (!is_array($data) || !isset($data['window_start'])) {
                return 1;
            }

            return max(1, min($decaySeconds, (int) $data['window_start'] + $decaySeconds - time()));
        } catch (\Throwable) {
            return 1;
        } finally {
            fclose($fp);
        }
    }

    /**
     * Supprime le fichier de compteur pour une clé (déblocage ops / honeypot).
     */
    public function clear(string $key): bool
    {
        $path = base_path('storage/cache/ratelimit') . '/' . hash('sha256', $key) . '.json';
        if (!is_file($path)) {
            return true;
        }

        return @unlink($path);
    }

    private function mutate(string $key, int $decaySeconds, bool $increment): int
    {
        $dir = base_path('storage/cache/ratelimit');
        if (!is_dir($dir) && !@mkdir($dir, 0755, true)) {
            return 0;
        }
        $path = $dir . '/' . hash('sha256', $key) . '.json';
        $now = time();
        $fp = @fopen($path, 'c+');
        if ($fp === false) {
            return 0;
        }
        try {
            if (!flock($fp, LOCK_EX)) {
                return 0;
            }
            rewind($fp);
            $raw = stream_get_contents($fp);
            $data = is_string($raw) && $raw !== '' ? json_decode($raw, true) : null;
            if (!is_array($data)) {
                $data = ['window_start' => $now, 'count' => 0];
            }
            $start = (int) ($data['window_start'] ?? $now);
            $count = (int) ($data['count'] ?? 0);
            if ($now - $start >= $decaySeconds) {
                $start = $now;
                $count = 0;
            }
            if ($increment) {
                $count++;
                $payload = json_encode(['window_start' => $start, 'count' => $count], JSON_THROW_ON_ERROR);
                ftruncate($fp, 0);
                rewind($fp);
                fwrite($fp, $payload);
                fflush($fp);
            }

            return $count;
        } catch (\Throwable) {
            return 0;
        } finally {
            flock($fp, LOCK_UN);
            fclose($fp);
        }
    }
}
