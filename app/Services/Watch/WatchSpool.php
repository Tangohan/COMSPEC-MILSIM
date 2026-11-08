<?php

declare(strict_types=1);

namespace App\Services\Watch;

/**
 * Tampon fichier des mesures de surveillance : une ligne courte ajoutée par requête, un fichier par minute et par type
 * (« load » = une ligne par requête, « mod » = signaux des envois du mod). Aucune base ni verrou sur le chemin des
 * requêtes : l'ajout en fin de fichier (O_APPEND) d'une ligne de moins de 4 Ko est atomique. WatchFlusher relit les
 * minutes closes, les regroupe et les écrit en base.
 *
 * Un tampon qui ne se vide plus (base ou tâche arrêtée) ne grossit pas sans fin : plafond par fichier, et les fichiers
 * de plus de 30 minutes sont supprimés.
 */
final class WatchSpool
{
    public const KIND_LOAD = 'load';
    public const KIND_MOD = 'mod';

    /** Plafond d'un fichier de minute (octets) : au-delà, les lignes suivantes sont ignorées. */
    public const MAX_FILE_BYTES = 6_000_000;

    /** Âge au-delà duquel un fichier est supprimé même s'il n'a pas pu être traité (secondes). */
    public const MAX_AGE_SECONDS = 1800;

    /** Dossier imposé (tests) ; null = storage/cache/watch. */
    private static ?string $dirOverride = null;

    public static function setDir(?string $dir): void
    {
        self::$dirOverride = $dir;
    }

    public static function dir(): string
    {
        if (self::$dirOverride !== null) {
            return self::$dirOverride;
        }

        return function_exists('base_path')
            ? base_path('storage/cache/watch')
            : dirname(__DIR__, 3) . '/storage/cache/watch';
    }

    /** Ajoute une ligne (sans saut de ligne) au fichier de la minute courante. Ne lève jamais. */
    public static function append(string $kind, string $line, ?int $now = null): void
    {
        try {
            $dir = self::dir();
            if (!is_dir($dir) && !@mkdir($dir, 0775, true) && !is_dir($dir)) {
                return;
            }
            $file = self::fileFor($kind, $now ?? time());
            if (is_file($file) && (int) @filesize($file) > self::MAX_FILE_BYTES) {
                return;
            }
            @file_put_contents($file, substr($line, 0, 400) . "\n", FILE_APPEND);
        } catch (\Throwable) {
        }
    }

    public static function fileFor(string $kind, int $ts, string $ext = 'log'): string
    {
        return self::dir() . '/' . $kind . '-' . gmdate('YmdHi', $ts) . '.' . $ext;
    }

    /**
     * Fichiers d'un type, du plus ancien au plus récent.
     *
     * @return list<array{path: string, minute: int, ext: string}> minute = début de la minute (secondes Unix)
     */
    public static function files(string $kind): array
    {
        $out = [];
        foreach (glob(self::dir() . '/' . $kind . '-*.*') ?: [] as $path) {
            if (preg_match('/-(\d{12})\.(log|done)$/', $path, $m) !== 1) {
                continue;
            }
            $ts = \DateTimeImmutable::createFromFormat('YmdHi', $m[1], new \DateTimeZone('UTC'));
            if ($ts === false) {
                continue;
            }
            $out[] = ['path' => $path, 'minute' => $ts->getTimestamp(), 'ext' => $m[2]];
        }
        usort($out, static fn (array $a, array $b): int => $a['minute'] <=> $b['minute']);

        return $out;
    }

    /** Plafond de lignes relues par fichier : une inondation ne fait pas exploser la mémoire du regroupement. */
    public const MAX_LINES_PER_FILE = 400000;

    /**
     * Lignes d'un fichier, une à une, découpées sur « | » (aucun champ ne contient de barre). Lecture en flux : la
     * mémoire ne dépend pas de la taille du fichier.
     *
     * @return \Generator<int, list<string>>
     */
    public static function lines(string $path): \Generator
    {
        $fh = @fopen($path, 'r');
        if ($fh === false) {
            return;
        }
        try {
            $n = 0;
            while (($line = fgets($fh)) !== false) {
                $line = rtrim($line, "\r\n");
                if ($line === '') {
                    continue;
                }
                if (++$n > self::MAX_LINES_PER_FILE) {
                    return;
                }
                yield explode('|', $line);
            }
        } finally {
            fclose($fh);
        }
    }

    /**
     * Lignes d'un fichier en tableau (petits fichiers et tests).
     *
     * @return list<list<string>>
     */
    public static function read(string $path): array
    {
        return iterator_to_array(self::lines($path), false);
    }

    /** Supprime les fichiers trop vieux ou, pour les « .done », devenus inutiles à la mesure de charge. */
    public static function sweep(string $kind, int $now, int $keepDoneSeconds = 300): void
    {
        foreach (self::files($kind) as $f) {
            $age = $now - $f['minute'];
            if ($age > self::MAX_AGE_SECONDS || ($f['ext'] === 'done' && $age > $keepDoneSeconds)) {
                @unlink($f['path']);
            }
        }
    }

    /** Champ sûr pour une ligne : sans barre, sans saut de ligne, borné. */
    public static function field(string $value, int $max = 80): string
    {
        $value = str_replace(['|', "\r", "\n"], ' ', $value);

        return substr($value, 0, $max);
    }
}
