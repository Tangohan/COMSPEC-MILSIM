<?php

declare(strict_types=1);

namespace App\Services\Cron\Jobs;

use App\Services\Cron\CronJobInterface;
use App\Support\StoredPhotoCompressor;

/**
 * Allège les photos terrain déjà stockées (reconnaissance, SEEK, renseignement) : 1920 px, JPEG 80.
 * Même nom de fichier, donc même identifiant et même URL. Passage borné en temps pour ne pas
 * retenir les autres tâches : le stock se résorbe au fil des passages, puis seules les nouvelles
 * photos restent à traiter (déjà allégées à l’envoi, donc ignorées sans réencodage).
 */
final class PhotoCompressCronJob implements CronJobInterface
{
    public const TIME_BUDGET_SECONDS = 90;

    public function __construct(
        private ?string $root = null,
        private int $timeBudgetSeconds = self::TIME_BUDGET_SECONDS,
        private bool $dryRun = false,
    ) {
    }

    public function key(): string
    {
        return 'photo_compress';
    }

    public function label(): string
    {
        return 'Compression des photos terrain';
    }

    public function description(): string
    {
        return 'Ramène les photos reçues du jeu à 1920 px en JPEG qualité 80, sans changer leur adresse, et indique la place gagnée.';
    }

    public function run(): array
    {
        $limit = (string) ini_get('memory_limit');
        if ($limit !== '-1' && self::iniBytes($limit) < 512 * 1024 * 1024) {
            @ini_set('memory_limit', '512M');
        }
        $started = microtime(true);
        $files = StoredPhotoCompressor::listPhotoFiles($this->root);
        $this->purgeStaleTemps($files);

        $count = ['compressed' => 0, 'skipped' => 0, 'error' => 0];
        $before = 0;
        $after = 0;
        $errors = [];
        $seen = 0;
        foreach ($files as $abs) {
            if ((microtime(true) - $started) >= $this->timeBudgetSeconds) {
                break;
            }
            $seen++;
            $r = StoredPhotoCompressor::compressFile(
                $abs,
                StoredPhotoCompressor::MAX_EDGE,
                StoredPhotoCompressor::QUALITY,
                StoredPhotoCompressor::MIN_AGE_SECONDS,
                $this->dryRun
            );
            $count[$r['status']]++;
            if ($r['status'] === 'compressed') {
                $before += $r['before'];
                $after += $r['after'];
            } elseif ($r['status'] === 'error' && count($errors) < 20) {
                $errors[] = basename($abs) . ' : ' . $r['reason'];
            }
        }
        $remaining = count($files) - $seen;
        $saved = max(0, $before - $after);
        $summary = sprintf(
            '%d photo(s) allégée(s), %s gagnés (%s → %s) · %d déjà légère(s) · %d erreur(s)%s',
            $count['compressed'],
            self::human($saved),
            self::human($before),
            self::human($after),
            $count['skipped'],
            $count['error'],
            $remaining > 0 ? ' · ' . $remaining . ' restante(s) au prochain passage' : ''
        );
        if ($count['compressed'] > 0 || $count['error'] > 0) {
            error_log('[cron/photo-compress] ' . $summary);
        }

        return [
            'ok' => true,
            'summary' => $summary,
            'details' => [
                'files' => count($files),
                'compressed' => $count['compressed'],
                'skipped' => $count['skipped'],
                'errors' => $count['error'],
                'bytes_before' => $before,
                'bytes_after' => $after,
                'bytes_saved' => $saved,
                'remaining' => $remaining,
                'error_samples' => $errors,
            ],
        ];
    }

    /**
     * Fichiers temporaires laissés par un passage interrompu (plus d’une heure).
     *
     * @param list<string> $files
     */
    private function purgeStaleTemps(array $files): void
    {
        $dirs = [];
        foreach ($files as $abs) {
            $dirs[dirname($abs)] = true;
        }
        foreach (array_keys($dirs) as $dir) {
            foreach (glob($dir . DIRECTORY_SEPARATOR . '.*.tmp') ?: [] as $tmp) {
                if (preg_match('/\.(jpe?g|png|webp)\.[0-9a-f]{8}\.tmp$/i', $tmp) === 1 && (time() - (int) @filemtime($tmp)) > 3600) {
                    @unlink($tmp);
                }
            }
        }
    }

    public static function human(int $bytes): string
    {
        if ($bytes >= 1024 * 1024 * 1024) {
            return round($bytes / (1024 ** 3), 2) . ' Go';
        }
        if ($bytes >= 1024 * 1024) {
            return round($bytes / (1024 ** 2), 1) . ' Mo';
        }

        return round($bytes / 1024) . ' Ko';
    }

    private static function iniBytes(string $v): int
    {
        $v = trim($v);
        $n = (int) $v;

        return match (strtolower(substr($v, -1))) {
            'g' => $n * 1024 ** 3,
            'm' => $n * 1024 ** 2,
            'k' => $n * 1024,
            default => $n,
        };
    }
}
