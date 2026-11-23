<?php

declare(strict_types=1);

namespace App\Services\Media;

use App\Services\Sse\SseIdImageService;

/**
 * Signature dessinée au pad (public/assets/js/signature-pad.js) reçue en data URL PNG.
 *
 * Une signature dessinée n'est jamais crue sur parole : le préfixe doit être exactement « data:image/png;base64, »,
 * la taille décodée est plafonnée, le contenu doit être un vrai PNG (signature, blocs lus un à un avec leur CRC,
 * rien après IEND), 8 bits sans entrelacement (ce que produit un canevas), de dimensions raisonnables, et l'image doit
 * porter de l'encre : une image vide (transparente ou entièrement blanche) est refusée. Le PNG est reconstruit : seuls
 * les blocs IHDR, IDAT et IEND sont gardés, donc aucune métadonnée ni donnée cachée ne survit. Le nom du fichier est
 * toujours choisi par le serveur (jamais par le client).
 *
 * Le rangement des signatures d'identités fictives est celui de l'envoi de fichier (SseIdImageService::storeBytes).
 */
final class SignatureImageService
{
    public const PREFIX = 'data:image/png;base64,';

    /** Taille maximale du PNG décodé, en octets. */
    public const MAX_BYTES = 300 * 1024;

    /** Plus grand côté et nombre maximal de pixels acceptés. */
    public const MAX_SIDE = 1600;
    public const MAX_PIXELS = 1200000;

    /** Plus petit côté accepté : en dessous, ce n'est pas une signature. */
    public const MIN_SIDE = 8;

    /** Nombre minimal de pixels d'encre pour qu'une image ne soit pas « vide ». */
    public const MIN_INK_PIXELS = 12;

    /** Usage => type d'image de SseIdImageService. */
    private const PURPOSES = ['sse_identity' => 'signature', 'sse_document' => 'doc_signature'];

    /** Octets par pixel selon le type de couleur PNG (gris, RVB, gris + alpha, RVBA) à 8 bits. */
    private const BYTES_PER_PIXEL = [0 => 1, 2 => 3, 4 => 2, 6 => 4];

    private const PNG_SIGNATURE = "\x89PNG\r\n\x1a\n";

    public function __construct(private ?SseIdImageService $images = null)
    {
        $this->images ??= new SseIdImageService();
    }

    /** Vrai si la valeur envoyée par le formulaire est un dessin (data URL) et non un fichier ou du vide. */
    public static function isDataUrl(string $value): bool
    {
        return str_starts_with(ltrim($value), 'data:');
    }

    /**
     * Valide le dessin et le range comme une signature d'identité fictive de la communauté.
     *
     * @return array{ok: bool, path?: string, error?: string}
     */
    public function fromDataUrl(string $dataUrl, int $tenantId, string $purpose): array
    {
        $kind = self::PURPOSES[$purpose] ?? null;
        if ($kind === null || $tenantId <= 0) {
            return ['ok' => false, 'error' => 'Signature refusée.'];
        }
        $clean = self::normalize($dataUrl);
        if (!$clean['ok'] || !isset($clean['png'])) {
            return ['ok' => false, 'error' => $clean['error'] ?? 'Signature refusée.'];
        }

        return ($this->images ?? new SseIdImageService())->storeBytes($clean['png'], $kind, $tenantId);
    }

    /**
     * Contrôle le data URL et rend un PNG propre (IHDR, IDAT recompressé, IEND).
     *
     * @return array{ok: bool, png?: string, error?: string}
     */
    public static function normalize(string $dataUrl): array
    {
        $dataUrl = trim($dataUrl);
        if (!str_starts_with($dataUrl, self::PREFIX)) {
            return self::refuse('Format de signature invalide : seul un dessin PNG est accepté.');
        }
        $payload = substr($dataUrl, strlen(self::PREFIX));
        if ($payload === '' || strlen($payload) > (int) ceil(self::MAX_BYTES * 4 / 3) + 8) {
            return self::refuse('La signature est vide ou trop lourde. Dessinez-la plus simplement.');
        }
        $raw = base64_decode($payload, true);
        if ($raw === false || $raw === '' || strlen($raw) > self::MAX_BYTES) {
            return self::refuse('La signature est vide ou trop lourde. Dessinez-la plus simplement.');
        }
        $parsed = self::parsePng($raw);
        if ($parsed === null) {
            return self::refuse('La signature n’est pas une image PNG valide.');
        }
        [$width, $height, $colorType, $idat] = $parsed;
        if ($width < self::MIN_SIDE || $height < self::MIN_SIDE || $width > self::MAX_SIDE || $height > self::MAX_SIDE
            || $width * $height > self::MAX_PIXELS) {
            return self::refuse('Les dimensions de la signature sont hors limites.');
        }
        $bpp = self::BYTES_PER_PIXEL[$colorType];
        $rowBytes = $width * $bpp;
        $expected = $height * ($rowBytes + 1);
        $inflated = @gzuncompress($idat, $expected + 1);
        if (!is_string($inflated) || strlen($inflated) !== $expected) {
            return self::refuse('La signature n’est pas une image PNG valide.');
        }
        $ink = self::countInk($inflated, $width, $height, $colorType);
        if ($ink === null) {
            return self::refuse('La signature n’est pas une image PNG valide.');
        }
        if ($ink < self::MIN_INK_PIXELS) {
            return self::refuse('La signature est vide : dessinez-la avant d’enregistrer.');
        }
        $png = self::PNG_SIGNATURE
            . self::chunk('IHDR', pack('NNCCCCC', $width, $height, 8, $colorType, 0, 0, 0))
            . self::chunk('IDAT', (string) gzcompress($inflated, 9))
            . self::chunk('IEND', '');

        return ['ok' => true, 'png' => $png];
    }

    /**
     * Lit les blocs du PNG : signature, IHDR en premier (8 bits, sans entrelacement, gris/RVB/RVBA), CRC de chaque
     * bloc, IEND en dernier et plus aucun octet après.
     *
     * @return array{0: int, 1: int, 2: int, 3: string}|null [largeur, hauteur, type de couleur, données IDAT]
     */
    private static function parsePng(string $raw): ?array
    {
        $len = strlen($raw);
        if ($len < 45 || !str_starts_with($raw, self::PNG_SIGNATURE)) {
            return null;
        }
        $pos = 8;
        $ihdr = null;
        $idat = '';
        $ended = false;
        while ($pos + 12 <= $len) {
            $n = unpack('N', substr($raw, $pos, 4));
            $size = is_array($n) ? (int) $n[1] : -1;
            if ($size < 0 || $pos + 12 + $size > $len) {
                return null;
            }
            $type = substr($raw, $pos + 4, 4);
            $data = substr($raw, $pos + 8, $size);
            $crc = unpack('N', substr($raw, $pos + 8 + $size, 4));
            if (!is_array($crc) || (int) $crc[1] !== crc32($type . $data)) {
                return null;
            }
            if ($pos === 8 && $type !== 'IHDR') {
                return null;
            }
            if ($type === 'IHDR') {
                if ($ihdr !== null || $size !== 13) {
                    return null;
                }
                $ihdr = unpack('Nw/Nh/Cdepth/Ccolor/Ccompression/Cfilter/Cinterlace', $data);
            } elseif ($type === 'IDAT') {
                if ($ihdr === null || $ended) {
                    return null;
                }
                $idat .= $data;
            } elseif ($type === 'IEND') {
                $ended = true;
                $pos += 12 + $size;
                break;
            }
            $pos += 12 + $size;
        }
        if (!$ended || $pos !== $len || !is_array($ihdr) || $idat === '') {
            return null;
        }
        if ($ihdr['depth'] !== 8 || !isset(self::BYTES_PER_PIXEL[$ihdr['color']]) || $ihdr['compression'] !== 0
            || $ihdr['filter'] !== 0 || $ihdr['interlace'] !== 0) {
            return null;
        }

        return [(int) $ihdr['w'], (int) $ihdr['h'], (int) $ihdr['color'], $idat];
    }

    /**
     * Défait les filtres PNG ligne à ligne et compte les pixels d'encre : visibles (alpha d'au moins 32 sur 255) et
     * qui ne sont pas du blanc opaque (fond blanc). S'arrête dès que le minimum est atteint ; null si un filtre est
     * inconnu.
     */
    private static function countInk(string $data, int $width, int $height, int $colorType): ?int
    {
        $bpp = self::BYTES_PER_PIXEL[$colorType];
        $rowBytes = $width * $bpp;
        $stride = $rowBytes + 1;
        for ($y = 0; $y < $height; $y++) {
            if (ord($data[$y * $stride]) > 4) {
                return null;
            }
        }
        $prev = array_fill(0, $rowBytes, 0);
        $ink = 0;
        for ($y = 0; $y < $height; $y++) {
            $filter = ord($data[$y * $stride]);
            $cur = array_values((array) unpack('C*', substr($data, $y * $stride + 1, $rowBytes)));
            for ($i = 0; $i < $rowBytes; $i++) {
                $a = $i >= $bpp ? $cur[$i - $bpp] : 0;
                $b = $prev[$i];
                switch ($filter) {
                    case 1:
                        $cur[$i] = ($cur[$i] + $a) & 255;
                        break;
                    case 2:
                        $cur[$i] = ($cur[$i] + $b) & 255;
                        break;
                    case 3:
                        $cur[$i] = ($cur[$i] + (($a + $b) >> 1)) & 255;
                        break;
                    case 4:
                        $c = $i >= $bpp ? $prev[$i - $bpp] : 0;
                        $p = $a + $b - $c;
                        $pa = abs($p - $a);
                        $pb = abs($p - $b);
                        $pc = abs($p - $c);
                        $pred = ($pa <= $pb && $pa <= $pc) ? $a : ($pb <= $pc ? $b : $c);
                        $cur[$i] = ($cur[$i] + $pred) & 255;
                        break;
                    default:
                        break;
                }
            }
            for ($x = 0; $x < $width; $x++) {
                $o = $x * $bpp;
                if (match ($colorType) {
                    6 => $cur[$o + 3] >= 32 && !($cur[$o + 3] >= 250 && $cur[$o] >= 245 && $cur[$o + 1] >= 245 && $cur[$o + 2] >= 245),
                    2 => $cur[$o] < 245 || $cur[$o + 1] < 245 || $cur[$o + 2] < 245,
                    4 => $cur[$o + 1] >= 32 && !($cur[$o + 1] >= 250 && $cur[$o] >= 245),
                    default => $cur[$o] < 245,
                }) {
                    if (++$ink >= self::MIN_INK_PIXELS) {
                        return $ink;
                    }
                }
            }
            $prev = $cur;
        }

        return $ink;
    }

    private static function chunk(string $type, string $data): string
    {
        return pack('N', strlen($data)) . $type . $data . pack('N', crc32($type . $data));
    }

    /** @return array{ok: bool, error: string} */
    private static function refuse(string $message): array
    {
        return ['ok' => false, 'error' => $message];
    }
}
