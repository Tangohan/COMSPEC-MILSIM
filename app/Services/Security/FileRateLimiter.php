<?php

declare(strict_types=1);

namespace App\Services\Security;

/**
 * Fenêtre fixe par clé (fichier JSON + verrou), pour limiter login / recrutement / etc.
 */
final class FileRateLimiter
{
    public function tooManyAttempts(string $key, int $maxAttempts, int $decaySeconds): bool
    {
        $dir = base_path('storage/cache/ratelimit');
        if (!is_dir($dir) && !@mkdir($dir, 0755, true)) {
            return false;
        }
        $path = $dir . '/' . hash('sha256', $key) . '.json';
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
                $data = ['window_start' => $now, 'count' => 0];
            }
            $start = (int) ($data['window_start'] ?? $now);
            $count = (int) ($data['count'] ?? 0);
            if ($now - $start >= $decaySeconds) {
                $start = $now;
                $count = 0;
            }
            $count++;
            $payload = json_encode(['window_start' => $start, 'count' => $count], JSON_THROW_ON_ERROR);
            ftruncate($fp, 0);
            rewind($fp);
            fwrite($fp, $payload);
            fflush($fp);

            return $count > $maxAttempts;
        } catch (\Throwable) {
            return false;
        } finally {
            flock($fp, LOCK_UN);
            fclose($fp);
        }
    }
}
