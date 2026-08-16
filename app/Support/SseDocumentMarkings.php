<?php

declare(strict_types=1);

namespace App\Support;

/**
 * Marques officielles d’un document SSE : numéro de contrôle, exemplaire,
 * empreintes d’intégrité, empreinte digitale d’archivage (pièces / personnes),
 * sceau machine QR (chemise de dossier), bordereau d’acheminement et mentions
 * de déclassification.
 *
 * Tout est dérivé de façon déterministe de la référence du document :
 * un même document affiche toujours les mêmes marques.
 */
final class SseDocumentMarkings
{
    /**
     * @param array<string,mixed> $document
     * @return array<string,mixed>
     */
    public static function forDocument(array $document, string $unitLabel = ''): array
    {
        $ref = trim((string) ($document['reference_code'] ?? ''));
        $id = (int) ($document['id'] ?? 0);
        $body = (string) ($document['body'] ?? '');
        $title = (string) ($document['title'] ?? '');

        $seedSource = $ref . '|' . $id . '|' . $unitLabel;
        $bytes = self::bytes($seedSource);

        $copyTotal = 3 + self::pick($bytes, 0, 6);        // 3 à 8 exemplaires
        $copyIndex = 1 + self::pick($bytes, 1, $copyTotal);
        $year = self::yearOf($document);

        $integrity = strtoupper(substr(hash('sha256', $ref . "\0" . $title . "\0" . $body), 0, 32));
        $envelope = strtoupper(substr(hash('sha256', 'ENV' . $seedSource), 0, 16));
        $checksum = strtoupper(str_pad(dechex(crc32($ref . $title . $body)), 8, '0', STR_PAD_LEFT));

        return [
            'control_number' => sprintf(
                'SC %d/%02d',
                1000 + self::pick($bytes, 2, 8999),
                (int) substr((string) $year, -2)
            ),
            'copy_index' => $copyIndex,
            'copy_total' => $copyTotal,
            'registry_number' => sprintf(
                '%s-%03d-%s',
                strtoupper(substr(hash('crc32b', $seedSource), 0, 4)),
                1 + self::pick($bytes, 3, 998),
                $year
            ),
            'channel' => self::channel($document, $bytes),
            'caveats' => self::caveats($document, $bytes),
            'integrity_hash' => $integrity,
            'integrity_groups' => self::group($integrity),
            'envelope_hash' => $envelope,
            'checksum' => $checksum,
            'algorithm' => 'SHA-256 / tronqué 128 bits',
            'fingerprint_id' => sprintf(
                'EMPR-%s-%02d',
                strtoupper(substr(hash('crc32b', 'FP' . $seedSource), 0, 6)),
                1 + self::pick($bytes, 4, 98)
            ),
            'fingerprint_svg' => self::fingerprintSvg($seedSource),
            'workstation' => self::workstationSeal($seedSource, $ref, $document),
            'routing' => self::routing($document, $bytes),
            'declassify_on' => self::declassifyOn($document),
            'destruction_delay' => [10, 15, 20, 25, 30][self::pick($bytes, 5, 5)] . ' ans',
            'pages' => 1 + (int) floor(max(0, mb_strlen($body, 'UTF-8') - 1) / 3200),
            'seal_initials' => self::sealInitials($unitLabel),
        ];
    }

    /** @return list<int> */
    private static function bytes(string $seed): array
    {
        $raw = hash('sha256', $seed, true);
        $unpacked = unpack('C*', $raw);

        return $unpacked === false ? array_fill(0, 32, 7) : array_values($unpacked);
    }

    /** @param list<int> $bytes */
    private static function pick(array $bytes, int $slot, int $modulo): int
    {
        if ($modulo < 1) {
            return 0;
        }
        $a = $bytes[($slot * 3) % count($bytes)];
        $b = $bytes[($slot * 3 + 1) % count($bytes)];

        return (($a << 8 | $b) % $modulo);
    }

    /** @param array<string,mixed> $document */
    private static function yearOf(array $document): string
    {
        $src = (string) ($document['validated_at'] ?? $document['created_at'] ?? $document['updated_at'] ?? '');
        $ts = $src !== '' ? strtotime($src) : false;

        return date('Y', $ts !== false ? $ts : time());
    }

    /**
     * @param array<string,mixed> $document
     * @param list<int> $bytes
     */
    private static function channel(array $document, array $bytes): string
    {
        $class = strtolower((string) ($document['classification'] ?? ''));
        if (str_contains($class, 'secret') || str_contains($class, 'tres_restreint')) {
            return 'CANAL RÉSERVÉ — TRANSMISSION CHIFFRÉE UNIQUEMENT';
        }
        if (str_contains($class, 'confid') || str_contains($class, 'restreint')) {
            return 'CANAL PROTÉGÉ — REMISE EN MAIN PROPRE';
        }

        return ['CANAL INTERNE SSE', 'CANAL DE SERVICE', 'CANAL INTERNE SSE'][self::pick($bytes, 6, 3)];
    }

    /**
     * @param array<string,mixed> $document
     * @param list<int> $bytes
     * @return list<string>
     */
    private static function caveats(array $document, array $bytes): array
    {
        $pool = [
            'NE PAS REPRODUIRE',
            'DIFFUSION RESTREINTE',
            'ORIGINE PROTÉGÉE',
            'NON EXPORTABLE',
            'USAGE INTERNE',
            'PORTER AU REGISTRE',
        ];
        $start = self::pick($bytes, 7, count($pool));
        $step = 1 + self::pick($bytes, 8, count($pool) - 1);
        $out = [];
        for ($i = 0; $i < count($pool) && count($out) < 3; $i++) {
            $candidate = $pool[($start + ($i * $step)) % count($pool)];
            if (!in_array($candidate, $out, true)) {
                $out[] = $candidate;
            }
        }

        return $out;
    }

    /**
     * @param array<string,mixed> $document
     * @param list<int> $bytes
     * @return list<array{slot:int,holder:string,date:string,initials:string}>
     */
    private static function routing(array $document, array $bytes): array
    {
        $holders = [
            'Chef de bureau SSE',
            'Officier de renseignement',
            'Cellule exploitation',
            'Officier de sécurité',
            'Archives — registre central',
        ];
        $base = (string) ($document['created_at'] ?? $document['updated_at'] ?? '');
        $ts = $base !== '' ? (strtotime($base) ?: time()) : time();

        $rows = [];
        $served = 2 + self::pick($bytes, 11, 3); // 2 à 4 lignes remplies
        foreach ($holders as $i => $holder) {
            $filled = $i < $served;
            $rows[] = [
                'slot' => $i + 1,
                'holder' => $filled ? $holder : '',
                'date' => $filled ? date('d/m/y', $ts + ($i * 86400)) : '',
                'initials' => $filled ? self::initialsFor($holder . $i) : '',
            ];
        }

        return $rows;
    }

    private static function initialsFor(string $seed): string
    {
        $letters = str_split('ABCDEFGHJKLMNPRSTVW');
        $bytes = self::bytes($seed);

        return $letters[$bytes[0] % count($letters)] . '.' . $letters[$bytes[1] % count($letters)] . '.';
    }

    /** @param array<string,mixed> $document */
    private static function declassifyOn(array $document): string
    {
        $src = (string) ($document['created_at'] ?? $document['updated_at'] ?? '');
        $ts = $src !== '' ? (strtotime($src) ?: time()) : time();

        return date('d/m/Y', strtotime('+10 years', $ts) ?: $ts);
    }

    private static function sealInitials(string $unitLabel): string
    {
        $clean = preg_replace('/[^\p{L}\s]+/u', ' ', $unitLabel) ?? $unitLabel;
        $words = preg_split('/\s+/u', trim($clean)) ?: [];
        $out = '';
        foreach ($words as $word) {
            if ($word === '') {
                continue;
            }
            $out .= mb_strtoupper(mb_substr($word, 0, 1, 'UTF-8'), 'UTF-8');
            if (mb_strlen($out, 'UTF-8') >= 3) {
                break;
            }
        }

        return $out !== '' ? $out : 'SSE';
    }

    private static function group(string $hash): string
    {
        return trim(implode(' ', str_split($hash, 4)));
    }

    /**
     * Sceau machine de la chemise : empreinte poste, adresse réseau et métadonnées
     * encodées dans un QR. Distinct de l’empreinte digitale biométrique (personnes).
     *
     * @param array<string,mixed> $document
     * @return array{
     *   id:string,
     *   host:string,
     *   fingerprint:string,
     *   ip:string,
     *   session:string,
     *   captured_at:string,
     *   payload:string,
     *   qr_html:string
     * }
     */
    public static function workstationSeal(string $seedSource, string $ref, array $document = []): array
    {
        $bytes = self::bytes('WS' . $seedSource);
        $host = sprintf(
            'SSE-WS-%04d',
            1000 + self::pick($bytes, 0, 8999)
        );
        $fingerprint = strtoupper(substr(hash('sha256', 'HOST|' . $seedSource . '|' . $host), 0, 32));
        $ip = sprintf(
            '10.%d.%d.%d',
            16 + self::pick($bytes, 1, 47),
            1 + self::pick($bytes, 2, 254),
            2 + self::pick($bytes, 3, 252)
        );
        $session = sprintf(
            'SID-%s-%02d',
            strtoupper(substr(hash('crc32b', 'SID' . $seedSource), 0, 6)),
            1 + self::pick($bytes, 4, 98)
        );
        $src = (string) ($document['updated_at'] ?? $document['created_at'] ?? '');
        $ts = $src !== '' ? strtotime($src) : false;
        $capturedAt = date('Y-m-d\TH:i:s\Z', $ts !== false ? $ts : time());

        $payload = implode("\n", [
            'ATHENA-SSE-SEAL/1',
            'REF:' . ($ref !== '' ? $ref : 'AFF-UNKNOWN'),
            'HOST:' . $host,
            'FP:' . $fingerprint,
            'IP:' . $ip,
            'SID:' . $session,
            'TS:' . $capturedAt,
        ]);

        return [
            'id' => sprintf(
                'QR-%s-%02d',
                strtoupper(substr(hash('crc32b', 'QR' . $seedSource), 0, 6)),
                1 + self::pick($bytes, 5, 98)
            ),
            'host' => $host,
            'fingerprint' => self::group($fingerprint),
            'ip' => $ip,
            'session' => $session,
            'captured_at' => $capturedAt,
            'payload' => $payload,
            'qr_html' => self::workstationQrHtml($payload, $seedSource),
        ];
    }

    private static function workstationQrHtml(string $payload, string $seed): string
    {
        try {
            $png = (new \App\Services\Qr\QrPngGenerator())->png($payload, 180, 6);
            if ($png !== null) {
                $uri = 'data:' . $png['mime'] . ';base64,' . base64_encode($png['body']);

                return '<img class="sse-doc-paper__qr-img" src="' . htmlspecialchars($uri, ENT_QUOTES, 'UTF-8')
                    . '" alt="Sceau machine du poste" width="140" height="140" decoding="async">';
            }
        } catch (\Throwable) {
            // Repli SVG minimal ci-dessous.
        }

        // Grille déterministe de secours si le générateur QR est indisponible.
        $bytes = self::bytes('QRFALLBACK' . $seed);
        $cells = '';
        $isFinder = static function (int $x, int $y): bool {
            $in = static function (int $ox, int $oy) use ($x, $y): bool {
                $lx = $x - $ox;
                $ly = $y - $oy;
                if ($lx < 0 || $lx > 6 || $ly < 0 || $ly > 6) {
                    return false;
                }
                if ($lx === 0 || $lx === 6 || $ly === 0 || $ly === 6) {
                    return true;
                }
                return $lx >= 2 && $lx <= 4 && $ly >= 2 && $ly <= 4;
            };

            return $in(0, 0) || $in(14, 0) || $in(0, 14);
        };
        for ($y = 0; $y < 21; $y++) {
            for ($x = 0; $x < 21; $x++) {
                $on = $isFinder($x, $y)
                    || ((($bytes[($x + $y * 3) % count($bytes)] ^ ($x * 17 + $y * 31)) & 1) === 1);
                if ($on) {
                    $cells .= sprintf('<rect x="%d" y="%d" width="1" height="1"/>', $x, $y);
                }
            }
        }

        return '<svg class="sse-doc-paper__qr-img" viewBox="0 0 21 21" role="img" aria-label="Sceau machine" focusable="false">'
            . '<rect width="21" height="21" fill="#fff"/>'
            . '<g fill="#0b1220">' . $cells . '</g></svg>';
    }

    /**
     * Empreinte digitale dessinée : boucles concentriques + minuties,
     * générées à partir de la graine du document.
     */
    private static function fingerprintSvg(string $seed): string
    {
        $bytes = self::bytes('FINGER' . $seed);
        $count = count($bytes);
        $at = static fn (int $i): int => $bytes[$i % $count];

        $cx = 60.0;
        $cy = 74.0;
        $tilt = -14 + ($at(0) % 28);

        $ridges = [];
        for ($i = 0; $i < 19; $i++) {
            $rx = 3.5 + ($i * 3.6) + (($at($i * 2) % 9) / 10);
            $ry = 5.0 + ($i * 5.2) + (($at($i * 2 + 1) % 11) / 10);
            $ox = (($at($i + 5) % 7) - 3) / 2.2;
            $oy = (($at($i + 9) % 7) - 3) / 2.2;
            $rot = $tilt + (($at($i + 13) % 9) - 4);
            $opacity = number_format(0.55 + (($at($i + 17) % 35) / 100), 2, '.', '');

            $ridges[] = sprintf(
                '<ellipse cx="%.1f" cy="%.1f" rx="%.1f" ry="%.1f" transform="rotate(%d %.1f %.1f)" opacity="%s"/>',
                $cx + $ox,
                $cy + $oy,
                $rx,
                $ry,
                $rot,
                $cx + $ox,
                $cy + $oy,
                $opacity
            );
        }

        // Minuties : petites interruptions et points d’encre.
        $marks = [];
        for ($i = 0; $i < 7; $i++) {
            $angle = ($at($i * 5 + 3) / 255) * 2 * M_PI;
            $radius = 12 + ($at($i * 5 + 4) % 34);
            $mx = $cx + cos($angle) * $radius * 0.72;
            $my = $cy + sin($angle) * $radius;
            $marks[] = sprintf('<circle cx="%.1f" cy="%.1f" r="%.1f" stroke="none" fill="currentColor" opacity="0.7"/>', $mx, $my, 0.7 + ($at($i) % 3) / 4);
        }

        // Plis de peau : traits clairs qui coupent les crêtes.
        $creases = [];
        for ($i = 0; $i < 3; $i++) {
            $y = 34 + ($i * 26) + ($at($i + 21) % 7);
            $creases[] = sprintf(
                '<path d="M8 %d Q60 %d 112 %d" stroke="#ffffff" stroke-width="2.1" opacity="0.85"/>',
                $y,
                $y - 6 + ($at($i + 25) % 12),
                $y + 3
            );
        }

        $clipId = 'fp-' . substr(hash('crc32b', $seed), 0, 8);
        $pad = 'M60 5 C88 5 105 27 105 60 C105 101 87 141 60 141 C33 141 15 101 15 60 C15 27 32 5 60 5 Z';

        return '<svg class="sse-doc-paper__fp-ink" viewBox="0 0 120 150" role="img" aria-label="Empreinte d’archivage" focusable="false">'
            . '<defs><clipPath id="' . $clipId . '"><path d="' . $pad . '"/></clipPath></defs>'
            . '<path d="' . $pad . '" fill="currentColor" opacity="0.05"/>'
            . '<g clip-path="url(#' . $clipId . ')" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round">'
            . implode('', $ridges)
            . implode('', $creases)
            . implode('', $marks)
            . '</g></svg>';
    }
}
