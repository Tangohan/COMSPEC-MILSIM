<?php

declare(strict_types=1);

namespace App\Support;

use App\Core\Response;

/**
 * Fichiers audio de la bibliothèque musicale : dépôt hors du dossier public (storage/uploads/music),
 * lecture par le site (session) ou par le jeu (URL signée, sans en-tête Athena : la DLL du lecteur n'en envoie pas).
 */
final class MusicTrackStorage
{
    public const MAX_BYTES = 25 * 1024 * 1024;

    /** Quota par membre (tous ses envois). */
    public const USER_QUOTA_BYTES = 300 * 1024 * 1024;

    public const PREFIX = 'music/';

    /** Durée de validité d'une URL signée remise au téléphone (la file d'attente peut durer une soirée). */
    public const SIGNED_TTL = 12 * 3600;

    /** @var array<string, string> mime => extension */
    private const MIME_EXT = [
        'audio/mpeg' => 'mp3',
        'audio/mp3' => 'mp3',
        'audio/x-mpeg' => 'mp3',
        'audio/mp4' => 'm4a',
        'audio/x-m4a' => 'm4a',
        'audio/m4a' => 'm4a',
        'audio/aac' => 'aac',
        'audio/x-aac' => 'aac',
        'audio/aacp' => 'aac',
        'audio/wav' => 'wav',
        'audio/x-wav' => 'wav',
        'audio/wave' => 'wav',
        'audio/vnd.wave' => 'wav',
    ];

    /** @var array<string, string> extension => mime servi */
    public const EXT_MIME = [
        'mp3' => 'audio/mpeg',
        'm4a' => 'audio/mp4',
        'aac' => 'audio/aac',
        'wav' => 'audio/wav',
    ];

    /**
     * @param array<string, mixed>|mixed $file entrée de $_FILES
     * @return array{path:?string, ext:?string, mime:?string, size:int, sha1:?string, original_name:?string, error:?string,
     *   game?:array{status:string, message:string, removed:int}}
     */
    public static function storeFromUpload(int $tenantId, int $userId, mixed $file): array
    {
        $fail = static fn (string $e): array => ['path' => null, 'ext' => null, 'mime' => null, 'size' => 0, 'sha1' => null, 'original_name' => null, 'error' => $e];
        if ($tenantId < 1 || $userId < 1) {
            return $fail('Compte introuvable.');
        }
        if (!is_array($file) || !isset($file['tmp_name'], $file['error'])) {
            return $fail('Aucun fichier reçu.');
        }
        $err = (int) $file['error'];
        if ($err === UPLOAD_ERR_INI_SIZE || $err === UPLOAD_ERR_FORM_SIZE) {
            return $fail('Fichier trop lourd (25 Mo maximum).');
        }
        if ($err !== UPLOAD_ERR_OK) {
            return $fail('Envoi interrompu, réessayez.');
        }
        $tmp = (string) $file['tmp_name'];
        if ($tmp === '' || !is_uploaded_file($tmp)) {
            return $fail('Fichier invalide.');
        }
        $size = (int) ($file['size'] ?? 0);
        if ($size < 1) {
            $size = (int) @filesize($tmp);
        }
        if ($size < 1024) {
            return $fail('Fichier vide ou illisible.');
        }
        if ($size > self::MAX_BYTES) {
            return $fail('Fichier trop lourd (25 Mo maximum).');
        }
        $original = (string) ($file['name'] ?? 'morceau');
        $ext = self::detectExtension($tmp, $original);
        if ($ext === null) {
            return $fail('Format non pris en charge : MP3, M4A, AAC ou WAV.');
        }
        $relDir = self::PREFIX . $tenantId . '/' . $userId;
        $absDir = base_path('storage/uploads/' . $relDir);
        if (!is_dir($absDir) && !@mkdir($absDir, 0775, true) && !is_dir($absDir)) {
            return $fail('Stockage indisponible.');
        }
        $name = bin2hex(random_bytes(16)) . '.' . $ext;
        $abs = $absDir . '/' . $name;
        if (!@move_uploaded_file($tmp, $abs)) {
            return $fail('Enregistrement impossible.');
        }
        @chmod($abs, 0640);
        // Lisible en jeu ? Un MP3 dont le son est caché derrière une grosse pochette est réparé, un faux MP3 refusé.
        $game = MusicGameCheck::check($abs, $ext);
        if ($game['status'] === MusicGameCheck::BAD) {
            @unlink($abs);

            return $fail('Illisible en jeu : ' . $game['message']);
        }
        clearstatcache(true, $abs);

        return [
            'path' => $relDir . '/' . $name,
            'ext' => $ext,
            'mime' => self::EXT_MIME[$ext],
            'size' => (int) (@filesize($abs) ?: $size),
            'sha1' => (string) sha1_file($abs),
            'original_name' => $original,
            'error' => null,
            'game' => $game,
        ];
    }

    /** Extension retenue d'après le contenu (finfo, signature) puis le nom ; null si ce n'est pas un format lisible partout. */
    public static function detectExtension(string $absPath, string $originalName): ?string
    {
        $byName = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));
        $mime = '';
        if (function_exists('finfo_open')) {
            $fi = @finfo_open(FILEINFO_MIME_TYPE);
            if ($fi) {
                $mime = strtolower((string) @finfo_file($fi, $absPath));
                finfo_close($fi);
            }
        }
        if (isset(self::MIME_EXT[$mime])) {
            $ext = self::MIME_EXT[$mime];
            // finfo lit souvent un M4A comme audio/mp4 ou video/mp4 : le nom tranche entre m4a et aac.
            return $ext === 'm4a' && $byName === 'aac' ? 'aac' : $ext;
        }
        $head = (string) @file_get_contents($absPath, false, null, 0, 12);
        $sig = self::extensionFromSignature($head);
        if ($sig !== null) {
            return $sig;
        }
        // video/mp4 ou application/octet-stream : on accepte l'extension si la signature ne contredit pas.
        if (in_array($mime, ['video/mp4', 'application/octet-stream', ''], true) && isset(self::EXT_MIME[$byName]) && $byName !== 'wav') {
            return $byName;
        }

        return null;
    }

    public static function extensionFromSignature(string $head): ?string
    {
        if (str_starts_with($head, 'ID3')) {
            return 'mp3';
        }
        if (strlen($head) >= 2 && ord($head[0]) === 0xFF && (ord($head[1]) & 0xE0) === 0xE0) {
            // Trame MPEG (mp3) ou ADTS (aac : couche 00).
            return (ord($head[1]) & 0x06) === 0 ? 'aac' : 'mp3';
        }
        if (str_starts_with($head, 'RIFF') && substr($head, 8, 4) === 'WAVE') {
            return 'wav';
        }
        if (substr($head, 4, 4) === 'ftyp') {
            return 'm4a';
        }

        return null;
    }

    public static function absolutePath(string $relPath): ?string
    {
        $relPath = str_replace('\\', '/', trim($relPath));
        if ($relPath === '' || str_contains($relPath, '..') || !str_starts_with($relPath, self::PREFIX)) {
            return null;
        }
        $base = realpath(base_path('storage/uploads'));
        $abs = realpath(base_path('storage/uploads/' . $relPath));
        if ($base === false || $abs === false || !str_starts_with($abs, $base . DIRECTORY_SEPARATOR) || !is_file($abs)) {
            return null;
        }

        return $abs;
    }

    public static function delete(string $relPath): void
    {
        $abs = self::absolutePath($relPath);
        if ($abs !== null) {
            @unlink($abs);
        }
    }

    // ----- URL signées (jeu) -----

    private static function key(): string
    {
        $k = (string) (function_exists('env') ? env('APP_KEY', '') : '');

        return 'music-stream|' . ($k !== '' ? $k : 'dev-insecure-key');
    }

    public static function signature(int $trackId, int $exp): string
    {
        return substr(hash_hmac('sha256', $trackId . '|' . $exp, self::key()), 0, 32);
    }

    /** Chemin relatif signé : /api/music/stream/{id}.{ext}?exp=…&sig=… (exp arrondi à l'heure : même URL pour les voisins). */
    public static function signedPath(int $trackId, string $ext, ?int $now = null): string
    {
        $now ??= time();
        $exp = (intdiv($now, 3600) + 1) * 3600 + self::SIGNED_TTL;

        return '/api/music/stream/' . $trackId . '.' . $ext . '?exp=' . $exp . '&sig=' . self::signature($trackId, $exp);
    }

    public static function verify(int $trackId, int $exp, string $sig, ?int $now = null): bool
    {
        $now ??= time();
        if ($trackId < 1 || $exp < $now || $exp > $now + self::SIGNED_TTL + 7200) {
            return false;
        }

        return hash_equals(self::signature($trackId, $exp), $sig);
    }

    /**
     * Diffuse le fichier, avec prise en charge des plages (Range) pour la barre de lecture du navigateur.
     *
     * @param array<string, mixed> $track
     */
    public static function streamResponse(array $track, ?string $rangeHeader = null): Response
    {
        $abs = self::absolutePath((string) ($track['file_path'] ?? ''));
        if ($abs === null) {
            return (new Response())->setStatusCode(404)->setBody('Fichier introuvable');
        }
        $size = (int) filesize($abs);
        $ext = (string) ($track['file_ext'] ?? 'mp3');
        $mime = self::EXT_MIME[$ext] ?? 'application/octet-stream';
        $start = 0;
        $end = $size - 1;
        $response = new Response();
        if ($rangeHeader !== null && preg_match('/^bytes=(\d*)-(\d*)$/', trim($rangeHeader), $m)) {
            if ($m[1] === '' && $m[2] !== '') {
                $start = max(0, $size - (int) $m[2]);
            } else {
                $start = (int) $m[1];
                if ($m[2] !== '') {
                    $end = min($end, (int) $m[2]);
                }
            }
            if ($start > $end || $start >= $size) {
                return $response->setStatusCode(416)->header('Content-Range', 'bytes */' . $size);
            }
            $response->setStatusCode(206)->header('Content-Range', 'bytes ' . $start . '-' . $end . '/' . $size);
        }
        $length = $end - $start + 1;
        $safe = preg_replace('/[^A-Za-z0-9 _.-]+/', '', (string) ($track['title'] ?? 'morceau')) ?: 'morceau';
        $response->header('Content-Type', $mime);
        $response->header('Content-Length', (string) $length);
        $response->header('Accept-Ranges', 'bytes');
        $response->header('Content-Disposition', 'inline; filename="' . $safe . '.' . $ext . '"');
        $response->header('X-Content-Type-Options', 'nosniff');
        $response->header('Cache-Control', 'private, max-age=3600');
        $response->setBodyStream(static function () use ($abs, $start, $length): void {
            $h = fopen($abs, 'rb');
            if (!$h) {
                return;
            }
            fseek($h, $start);
            $left = $length;
            while ($left > 0 && !feof($h)) {
                $chunk = fread($h, (int) min(65536, $left));
                if ($chunk === false || $chunk === '') {
                    break;
                }
                echo $chunk;
                $left -= strlen($chunk);
                if (connection_aborted()) {
                    break;
                }
            }
            fclose($h);
        });

        return $response;
    }
}
