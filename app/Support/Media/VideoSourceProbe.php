<?php

declare(strict_types=1);

namespace App\Support\Media;

/**
 * Sonde légère de conteneur vidéo : lit la marque `ftyp` et le codec de piste
 * pour produire un type MIME exact, et surtout pour savoir si un navigateur
 * courant saura décoder la piste.
 *
 * Motif : un fichier nommé `.mp4` mais encodé en HEVC (H.265) est annoncé
 * `video/mp4` ; le navigateur accepte la source, décode l'audio AAC et n'affiche
 * rien. On obtient un lecteur noir au lieu d'un repli sur l'affiche. En déclarant
 * le codec réel — ou en écartant la source — le navigateur retombe proprement sur
 * `poster`.
 *
 * Aucune dépendance externe : quelques centaines de kio lus, pas de ffprobe.
 */
final class VideoSourceProbe
{
    /** Octets lus en tête et en queue de fichier (le `moov` peut être à la fin). */
    private const SNIFF_BYTES = 262144;

    /** Codecs vidéo décodés par Chrome, Edge et Firefox sans extension. */
    private const PLAYABLE = ['avc1', 'avc3', 'vp09', 'vp8', 'av01'];

    /** Codecs présents dans les exports Apple, non décodés par ces navigateurs. */
    private const UNPLAYABLE = ['hvc1', 'hev1', 'ap4h', 'apch', 'apcn', 'apcs', 'apco', 'dvh1'];

    /**
     * @return array{mime: string, codec: ?string, brand: ?string, playable: bool}
     */
    public static function inspect(string $path): array
    {
        $ext = strtolower(pathinfo($path, PATHINFO_EXTENSION));
        $fallbackMime = match ($ext) {
            'webm' => 'video/webm',
            'ogv' => 'video/ogg',
            'mov' => 'video/quicktime',
            default => 'video/mp4',
        };

        if (!is_file($path) || !is_readable($path)) {
            return ['mime' => $fallbackMime, 'codec' => null, 'brand' => null, 'playable' => true];
        }

        // WebM / Matroska : conteneur distinct, non sondé ici — VP8/VP9 sont sûrs.
        if ($ext === 'webm') {
            return ['mime' => 'video/webm', 'codec' => null, 'brand' => null, 'playable' => true];
        }

        $blob = self::readEdges($path);
        if ($blob === '') {
            return ['mime' => $fallbackMime, 'codec' => null, 'brand' => null, 'playable' => true];
        }

        $brand = null;
        if (preg_match('/ftyp(.{4})/s', substr($blob, 0, 64), $m) === 1) {
            $brand = rtrim($m[1]);
        }

        $codec = null;
        foreach (array_merge(self::PLAYABLE, self::UNPLAYABLE) as $candidate) {
            if (str_contains($blob, $candidate)) {
                $codec = $candidate;
                break;
            }
        }

        // Inconnu : on ne bloque pas une source qu'on n'a pas su identifier.
        if ($codec === null) {
            return ['mime' => $fallbackMime, 'codec' => null, 'brand' => $brand, 'playable' => true];
        }

        $playable = in_array($codec, self::PLAYABLE, true);
        $mime = $fallbackMime;
        // Conteneur QuickTime déclaré en .mp4 : annoncer le vrai conteneur.
        if ($brand === 'qt') {
            $mime = 'video/quicktime';
        }
        $mime .= '; codecs="' . $codec . '"';

        return ['mime' => $mime, 'codec' => $codec, 'brand' => $brand, 'playable' => $playable];
    }

    /**
     * Vrai si un navigateur courant saura afficher l'image de ce fichier.
     */
    public static function isPlayable(string $path): bool
    {
        return self::inspect($path)['playable'];
    }

    private static function readEdges(string $path): string
    {
        $size = (int) @filesize($path);
        if ($size <= 0) {
            return '';
        }
        $fh = @fopen($path, 'rb');
        if ($fh === false) {
            return '';
        }
        try {
            $head = (string) fread($fh, min(self::SNIFF_BYTES, $size));
            $tail = '';
            if ($size > self::SNIFF_BYTES) {
                fseek($fh, -min(self::SNIFF_BYTES, $size), SEEK_END);
                $tail = (string) fread($fh, self::SNIFF_BYTES);
            }

            return $head . $tail;
        } finally {
            fclose($fh);
        }
    }
}
