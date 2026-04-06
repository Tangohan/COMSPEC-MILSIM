<?php

declare(strict_types=1);

namespace App\Services\Monitoring;

/**
 * Limite la fréquence des alertes e-mail (même erreur / même IP) pour éviter les tempêtes.
 */
final class ErrorAlertThrottle
{
    public function __construct(
        private int $cooldownSeconds = 120,
        private int $maxPerHour = 30
    ) {
    }

    /**
     * @return true si l’envoi doit être ignoré (trop tôt ou quota horaire dépassé)
     */
    public function isThrottled(string $dedupeKey): bool
    {
        $dir = base_path('storage/cache/error-alerts');
        if (!is_dir($dir) && !@mkdir($dir, 0755, true)) {
            return false;
        }
        $path = $dir . '/' . hash('sha256', $dedupeKey) . '.json';
        $now = time();
        $fp = @fopen($path, 'c+');
        if ($fp === false) {
            return false;
        }
        try {
            if (!flock($fp, LOCK_EX)) {
                return false;
            }
            rewind($fp);
            $raw = stream_get_contents($fp);
            $data = is_string($raw) && $raw !== '' ? json_decode($raw, true) : null;
            if (!is_array($data)) {
                $data = [
                    'last_sent' => 0,
                    'hour_start' => $now,
                    'hour_count' => 0,
                ];
            }
            $lastSent = (int) ($data['last_sent'] ?? 0);
            $hourStart = (int) ($data['hour_start'] ?? $now);
            $hourCount = (int) ($data['hour_count'] ?? 0);

            if ($now - $hourStart >= 3600) {
                $hourStart = $now;
                $hourCount = 0;
            }

            if ($now - $lastSent < $this->cooldownSeconds) {
                return true;
            }
            if ($hourCount >= $this->maxPerHour) {
                return true;
            }

            $hourCount++;
            $payload = json_encode([
                'last_sent' => $now,
                'hour_start' => $hourStart,
                'hour_count' => $hourCount,
            ], JSON_THROW_ON_ERROR);
            ftruncate($fp, 0);
            rewind($fp);
            fwrite($fp, $payload);
            fflush($fp);

            return false;
        } catch (\Throwable) {
            return false;
        } finally {
            flock($fp, LOCK_UN);
            fclose($fp);
        }
    }

    public static function fromEnv(): self
    {
        $cooldown = (int) (env('ERROR_ALERT_COOLDOWN_SECONDS', 120) ?: 120);
        $maxHour = (int) (env('ERROR_ALERT_MAX_PER_HOUR', 30) ?: 30);

        return new self(max(10, $cooldown), max(1, $maxHour));
    }
}
