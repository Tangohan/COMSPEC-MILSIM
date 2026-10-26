<?php

declare(strict_types=1);

namespace App\Support;

/**
 * Ménage opportuniste des petits fichiers témoins (storage/cache) : un appel sur $oneIn
 * efface les fichiers plus vieux que $maxAgeSec. Évite qu'un dossier par joueur grossisse sans fin.
 */
final class StaleFileSweeper
{
    public static function maybe(string $dir, string $pattern, int $maxAgeSec, int $oneIn = 500): void
    {
        if ($oneIn > 1 && random_int(1, $oneIn) !== 1) {
            return;
        }
        $limit = time() - max(60, $maxAgeSec);
        foreach (glob(rtrim($dir, '/') . '/' . $pattern) ?: [] as $file) {
            $mtime = @filemtime((string) $file);
            if ($mtime !== false && $mtime < $limit) {
                @unlink((string) $file);
            }
        }
    }
}
