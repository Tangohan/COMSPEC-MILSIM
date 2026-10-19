<?php

declare(strict_types=1);

namespace App\Support;

/**
 * Contrôle « lisible en jeu » d'un morceau de la bibliothèque musique.
 *
 * L'app Musique du téléphone ATAK lit les fichiers par Windows MCI (DirectShow), qui ne cherche le début du son que
 * dans les premiers Ko : derrière une grosse balise ID3v2 (pochette de plusieurs centaines de Ko, courante sur les MP3
 * tirés d'une vidéo YouTube) ou des octets parasites, il ne trouve aucune trame et répond « format non lu (ERR277) »
 * alors que le fichier est un MP3 valide. Le site retire donc tout ce qui précède la première trame MPEG (le titre et
 * l'artiste restent ceux saisis sur le site) et refuse un fichier où il n'en trouve aucune.
 */
final class MusicGameCheck
{
    /** Lisible tel quel. */
    public const OK = 'ok';
    /** Rendu lisible (balise ou octets parasites retirés). */
    public const FIXED = 'fixed';
    /** Format accepté par le site mais lecture en jeu non garantie. */
    public const WARN = 'warn';
    /** Illisible en jeu. */
    public const BAD = 'bad';

    private const BITRATES_V1 = [0, 32, 40, 48, 56, 64, 80, 96, 112, 128, 160, 192, 224, 256, 320];
    private const BITRATES_V2 = [0, 8, 16, 24, 32, 40, 48, 56, 64, 80, 96, 112, 128, 144, 160];
    private const SAMPLE_RATES = [44100, 48000, 32000];

    /**
     * @param bool $repair true : réécrit le fichier sans ce qui précède la première trame (MP3)
     * @return array{status:string, message:string, removed:int}
     */
    public static function check(string $absPath, string $ext, bool $repair = true): array
    {
        $data = @file_get_contents($absPath);
        if (!is_string($data) || strlen($data) < 64) {
            return self::result(self::BAD, 'fichier vide ou introuvable sur le serveur.');
        }

        return match ($ext) {
            'mp3' => self::checkMp3($absPath, $data, $repair),
            'wav' => self::checkWav($data),
            default => self::result(self::WARN, 'format ' . strtoupper($ext) . ' : lecture en jeu non garantie, le MP3 est conseillé.'),
        };
    }

    /**
     * Position de la première trame MPEG audio Layer III suivie d'une deuxième trame valide, après les balises ID3v2.
     * -1 si aucune.
     */
    public static function firstFrameOffset(string $data): int
    {
        $len = strlen($data);
        $start = 0;
        while ($len - $start > 10 && substr($data, $start, 3) === 'ID3') {
            $size = (ord($data[$start + 6]) & 0x7F) << 21 | (ord($data[$start + 7]) & 0x7F) << 14
                | (ord($data[$start + 8]) & 0x7F) << 7 | (ord($data[$start + 9]) & 0x7F);
            $start += 10 + $size + ((ord($data[$start + 5]) & 0x10) !== 0 ? 10 : 0);
        }
        $limit = min($len - 4, $start + 4 * 1024 * 1024);
        for ($i = max(0, $start); $i < $limit; $i++) {
            if ($data[$i] !== "\xFF") {
                continue;
            }
            $frame = self::frameLength($data, $i);
            if ($frame > 0 && ($i + $frame + 4 > $len || self::frameLength($data, $i + $frame) > 0)) {
                return $i;
            }
        }

        return -1;
    }

    /** Longueur de la trame Layer III qui commence à $i, 0 si ce n'est pas un en-tête valide. */
    public static function frameLength(string $d, int $i): int
    {
        if ($i + 4 > strlen($d) || ord($d[$i]) !== 0xFF || (ord($d[$i + 1]) & 0xE0) !== 0xE0) {
            return 0;
        }
        $b1 = ord($d[$i + 1]);
        $b2 = ord($d[$i + 2]);
        $version = ($b1 >> 3) & 3;   // 3 = MPEG-1, 2 = MPEG-2, 0 = MPEG-2.5, 1 = réservé
        $layer = ($b1 >> 1) & 3;     // 1 = Layer III
        $rateIdx = ($b2 >> 4) & 15;
        $srIdx = ($b2 >> 2) & 3;
        if ($version === 1 || $layer !== 1 || $rateIdx === 0 || $rateIdx === 15 || $srIdx === 3) {
            return 0;
        }
        $sr = self::SAMPLE_RATES[$srIdx] >> ($version === 3 ? 0 : ($version === 2 ? 1 : 2));
        $kbps = ($version === 3 ? self::BITRATES_V1 : self::BITRATES_V2)[$rateIdx];

        return intdiv(($version === 3 ? 144000 : 72000) * $kbps, $sr) + (($b2 >> 1) & 1);
    }

    /** @return array{status:string, message:string, removed:int} */
    private static function checkMp3(string $absPath, string $data, bool $repair): array
    {
        $real = self::sniff($data);
        if ($real !== '') {
            return self::result(self::BAD, 'ce n\'est pas un vrai MP3 (' . $real . ' renommé) : reconvertissez-le en MP3.');
        }
        $offset = self::firstFrameOffset($data);
        if ($offset < 0) {
            return self::result(self::BAD, 'aucun son MP3 trouvé dans le fichier (corrompu ou mal converti).');
        }
        if ($offset === 0) {
            return self::result(self::OK, 'lisible en jeu.');
        }
        $what = substr($data, 0, 3) === 'ID3' ? 'balise ID3 (pochette, infos)' : 'octets parasites';
        $kb = (int) ceil($offset / 1024);
        if (!$repair) {
            return self::result(self::WARN, $what . ' de ' . $kb . ' Ko avant le son : le jeu peut refuser de le lire.', $offset);
        }
        $tmp = $absPath . '.part';
        if (@file_put_contents($tmp, substr($data, $offset)) === false || !@rename($tmp, $absPath)) {
            @unlink($tmp);

            return self::result(self::WARN, $what . ' de ' . $kb . ' Ko avant le son, impossible de la retirer : le jeu peut refuser de le lire.', $offset);
        }
        @chmod($absPath, 0640);

        return self::result(self::FIXED, $what . ' de ' . $kb . ' Ko retirée pour que le jeu le lise.', $offset);
    }

    /** @return array{status:string, message:string, removed:int} */
    private static function checkWav(string $data): array
    {
        if (substr($data, 0, 4) !== 'RIFF' || substr($data, 8, 4) !== 'WAVE') {
            return self::result(self::BAD, 'en-tête WAV absent : fichier corrompu ou mal renommé.');
        }
        $pos = strpos($data, 'fmt ', 12);
        $format = $pos !== false && strlen($data) >= $pos + 10 ? ord($data[$pos + 8]) | (ord($data[$pos + 9]) << 8) : 0;
        if ($format !== 1 && $format !== 0xFFFE) {
            return self::result(self::WARN, 'WAV compressé : lecture en jeu non garantie, le MP3 est conseillé.');
        }

        return self::result(self::OK, 'lisible en jeu.');
    }

    /** Vrai format d'un fichier d'après ses premiers octets, quand ce n'est pas du MPEG audio ('' sinon). */
    public static function sniff(string $data): string
    {
        return match (true) {
            substr($data, 4, 4) === 'ftyp' => 'M4A/MP4',
            str_starts_with($data, "\x1A\x45\xDF\xA3") => 'WebM',
            str_starts_with($data, 'OggS') => 'Ogg/Opus',
            str_starts_with($data, 'fLaC') => 'FLAC',
            str_starts_with($data, 'RIFF') => 'WAV',
            str_starts_with(ltrim(substr($data, 0, 64)), '<'), str_starts_with(ltrim(substr($data, 0, 64)), '{') => 'page web',
            default => '',
        };
    }

    /** @return array{status:string, message:string, removed:int} */
    private static function result(string $status, string $message, int $removed = 0): array
    {
        return ['status' => $status, 'message' => $message, 'removed' => $removed];
    }
}
