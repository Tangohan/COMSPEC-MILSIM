<?php

declare(strict_types=1);

namespace App\Services\Sse;

/**
 * Bande MRZ (zone de lecture automatique) au format ICAO 9303 — TD1 (carte, 3 × 30) et TD3 (passeport, 2 × 44).
 *
 * Même algorithme que le mod SSE (fn_idMrzCheck) : chiffre de contrôle à poids 7, 3, 1 répétés, chiffres = leur
 * valeur, A..Z = 10..35, « < » = 0. Vérifié sur les spécimens de l'ICAO 9303 (L898902C3 → 6, 740812 → 2,
 * 120415 → 9, ZE184226B → 1, composite → 0).
 *
 * Le permis de conduire (hors ICAO) reçoit une bande au gabarit TD1, code « D< », pour que le lecteur du jeu le
 * traite comme une carte.
 */
final class SseMrzService
{
    public static function checkDigit(string $data): int
    {
        $weights = [7, 3, 1];
        $sum = 0;
        $data = strtoupper($data);
        $len = strlen($data);
        for ($i = 0; $i < $len; $i++) {
            $c = $data[$i];
            if ($c >= '0' && $c <= '9') {
                $v = ord($c) - 48;
            } elseif ($c >= 'A' && $c <= 'Z') {
                $v = ord($c) - 55;
            } else {
                $v = 0;
            }
            $sum += $v * $weights[$i % 3];
        }

        return $sum % 10;
    }

    /** Lettres accentuées → lettre de base (même table que le mod) ; ß → SS, Æ → AE, Œ → OE. */
    private const ACCENTS = [
        'À' => 'A', 'Á' => 'A', 'Â' => 'A', 'Ã' => 'A', 'Ä' => 'A', 'Å' => 'A', 'Ą' => 'A', 'Æ' => 'AE',
        'Ç' => 'C', 'Ć' => 'C', 'Č' => 'C', 'Ď' => 'D', 'Đ' => 'D',
        'È' => 'E', 'É' => 'E', 'Ê' => 'E', 'Ë' => 'E', 'Ę' => 'E', 'Ě' => 'E',
        'Ì' => 'I', 'Í' => 'I', 'Î' => 'I', 'Ï' => 'I', 'Ł' => 'L', 'Ñ' => 'N', 'Ń' => 'N', 'Ň' => 'N',
        'Ò' => 'O', 'Ó' => 'O', 'Ô' => 'O', 'Õ' => 'O', 'Ö' => 'O', 'Ø' => 'O', 'Œ' => 'OE',
        'Ř' => 'R', 'Ś' => 'S', 'Š' => 'S', 'ß' => 'SS', 'Ť' => 'T',
        'Ù' => 'U', 'Ú' => 'U', 'Û' => 'U', 'Ü' => 'U', 'Ů' => 'U', 'Ý' => 'Y', 'Ÿ' => 'Y', 'Ź' => 'Z', 'Ż' => 'Z', 'Ž' => 'Z',
    ];

    /** Texte ramené à l'alphabet MRZ : majuscules sans accents, tout le reste devient « < ». */
    public static function sanitize(string $text): string
    {
        $t = strtr(mb_strtoupper(trim($text), 'UTF-8'), self::ACCENTS);

        return (string) preg_replace('/[^A-Z0-9<]/u', '<', $t);
    }

    public static function pad(string $s, int $len): string
    {
        return substr(str_pad($s, $len, '<'), 0, $len);
    }

    /** Champ nom ICAO : NOM<<PRÉNOM<AUTRES, tronqué à la largeur. */
    public static function nameField(string $last, string $first, int $len): string
    {
        $l = trim((string) preg_replace('/<+/', '<', self::sanitize($last)), '<');
        $f = trim((string) preg_replace('/<+/', '<', self::sanitize($first)), '<');

        return self::pad($f !== '' ? $l . '<<' . $f : $l, $len);
    }

    /** Date AAAA-MM-JJ → AAMMJJ ; inconnue → <<<<<<. */
    public static function date(?string $ymd): string
    {
        $ymd = trim((string) $ymd);
        if (preg_match('/^(\d{2})(\d{2})-(\d{2})-(\d{2})$/', $ymd, $m) === 1) {
            return $m[2] . $m[3] . $m[4];
        }

        return '<<<<<<';
    }

    private static function sex(?string $sex): string
    {
        $s = strtoupper(trim((string) $sex));

        return $s === 'M' || $s === 'F' ? $s : '<';
    }

    /**
     * TD3 (passeport) : 2 lignes de 44.
     *
     * @param array{doc_code?: string, issuer: string, last_name: string, first_name: string, number: string, nationality: string, birth_date: ?string, sex: ?string, expires_on: ?string, personal_number?: string} $d
     * @return array{0: string, 1: string}
     */
    public static function td3(array $d): array
    {
        $code = self::pad(self::sanitize($d['doc_code'] ?? 'P'), 2);
        $line1 = self::pad($code . self::pad(self::sanitize($d['issuer']), 3) . self::nameField($d['last_name'], $d['first_name'], 39), 44);

        $number = self::pad(self::sanitize($d['number']), 9);
        $dob = self::date($d['birth_date'] ?? null);
        $exp = self::date($d['expires_on'] ?? null);
        $personal = self::pad(self::sanitize($d['personal_number'] ?? ''), 14);
        $numCheck = (string) self::checkDigit($number);
        $dobCheck = (string) self::checkDigit($dob);
        $expCheck = (string) self::checkDigit($exp);
        $persCheck = trim($personal, '<') === '' ? '<' : (string) self::checkDigit($personal);
        $composite = (string) self::checkDigit(
            $number . $numCheck . $dob . $dobCheck . $exp . $expCheck . $personal . $persCheck
        );
        $line2 = $number . $numCheck . self::pad(self::sanitize($d['nationality']), 3) . $dob . $dobCheck
            . self::sex($d['sex'] ?? null) . $exp . $expCheck . $personal . $persCheck . $composite;

        return [$line1, $line2];
    }

    /**
     * TD1 (carte d'identité, permis) : 3 lignes de 30.
     *
     * @param array{doc_code?: string, issuer: string, last_name: string, first_name: string, number: string, nationality: string, birth_date: ?string, sex: ?string, expires_on: ?string, optional1?: string, optional2?: string} $d
     * @return array{0: string, 1: string, 2: string}
     */
    public static function td1(array $d): array
    {
        $code = self::pad(self::sanitize($d['doc_code'] ?? 'I'), 2);
        $number = self::pad(self::sanitize($d['number']), 9);
        $opt1 = self::pad(self::sanitize($d['optional1'] ?? ''), 15);
        $line1 = $code . self::pad(self::sanitize($d['issuer']), 3) . $number . self::checkDigit($number) . $opt1;

        $dob = self::date($d['birth_date'] ?? null);
        $exp = self::date($d['expires_on'] ?? null);
        $opt2 = self::pad(self::sanitize($d['optional2'] ?? ''), 11);
        $upper = substr($line1, 5, 25);
        $middle = $dob . self::checkDigit($dob) . self::sex($d['sex'] ?? null) . $exp . self::checkDigit($exp)
            . self::pad(self::sanitize($d['nationality']), 3) . $opt2;
        $composite = (string) self::checkDigit($upper . substr($middle, 0, 7) . substr($middle, 8, 7) . substr($middle, 18, 11));
        $line2 = $middle . $composite;

        return [$line1, $line2, self::nameField($d['last_name'], $d['first_name'], 30)];
    }

    /**
     * Bande d'un papier du registre (document hydraté + identité + pays).
     *
     * @param array<string, mixed> $doc SseIdDocumentRepository::hydrate()
     * @param array<string, mixed> $identity SseIdentityRepository::hydrate()
     * @return list<string>
     */
    public static function forDocument(array $doc, array $identity, string $mrzCountry = ''): array
    {
        $issuer = $mrzCountry !== '' ? $mrzCountry : (string) ($doc['country_code'] ?? '');
        // Nationalité = code MRZ du pays : celui du papier s'il s'agit du même pays, sinon le code OACI du jeu stylisé
        // (Allemagne : DEU → « D », imprimé « D<< »).
        $nat = strtoupper(trim((string) ($identity['nationality'] ?? '')));
        if ($nat === '' || $nat === strtoupper((string) ($doc['country_code'] ?? ''))) {
            $nat = $issuer;
        } else {
            $nat = \App\Support\SseIdRealCountryCatalog::mrzFor($nat) ?? $nat;
        }
        $data = [
            'issuer' => $issuer,
            'last_name' => (string) ($identity['last_name'] ?? ''),
            'first_name' => (string) ($identity['first_name'] ?? ''),
            'number' => (string) ($doc['doc_number'] ?? ''),
            'nationality' => $nat,
            'birth_date' => $identity['birth_date'] ?? null,
            'sex' => $identity['sex'] ?? null,
            'expires_on' => $doc['expires_on'] ?? null,
        ];
        $type = (string) ($doc['doc_type'] ?? 'id_card');
        if ($type === 'passport') {
            return self::td3($data + ['doc_code' => 'P']);
        }

        // Type sans bande de lecture (certificat d'immatriculation) : rien, comme le mod (fn_idMrz.sqf).
        if (!\App\Support\SseDocumentTypes::hasMrz($type)) {
            return [];
        }

        return self::td1($data + ['doc_code' => match ($type) {
            'driving_license' => 'D',
            'military_id' => 'A',
            // Titre de séjour : carte TD1, code « IR ».
            'residence_permit' => 'IR',
            default => 'I',
        }]);
    }

    /**
     * Contrôle d'une bande lue (N1) : chaque chiffre de contrôle recalculé.
     *
     * @param list<string> $lines
     * @return array{format: string, valid: bool, checks: array<string, bool>}
     */
    public static function verify(array $lines): array
    {
        $lines = array_values(array_map(static fn (string $l): string => strtoupper(trim($l)), $lines));
        $checks = [];
        if (count($lines) === 2 && strlen($lines[0]) === 44 && strlen($lines[1]) === 44) {
            $l2 = $lines[1];
            $checks['number'] = self::checkDigit(substr($l2, 0, 9)) === (int) $l2[9];
            $checks['birth_date'] = self::checkDigit(substr($l2, 13, 6)) === (int) $l2[19];
            $checks['expires_on'] = self::checkDigit(substr($l2, 21, 6)) === (int) $l2[27];
            $personal = substr($l2, 28, 14);
            $checks['personal_number'] = $l2[42] === '<' ? trim($personal, '<') === '' : self::checkDigit($personal) === (int) $l2[42];
            $checks['composite'] = self::checkDigit(substr($l2, 0, 10) . substr($l2, 13, 7) . substr($l2, 21, 22)) === (int) $l2[43];

            return ['format' => 'TD3', 'valid' => !in_array(false, $checks, true), 'checks' => $checks];
        }
        if (count($lines) === 3 && strlen($lines[0]) === 30 && strlen($lines[1]) === 30) {
            [$l1, $l2] = $lines;
            $checks['number'] = self::checkDigit(substr($l1, 5, 9)) === (int) $l1[14];
            $checks['birth_date'] = self::checkDigit(substr($l2, 0, 6)) === (int) $l2[6];
            $checks['expires_on'] = self::checkDigit(substr($l2, 8, 6)) === (int) $l2[14];
            $checks['composite'] = self::checkDigit(substr($l1, 5, 25) . substr($l2, 0, 7) . substr($l2, 8, 7) . substr($l2, 18, 11)) === (int) $l2[29];

            return ['format' => 'TD1', 'valid' => !in_array(false, $checks, true), 'checks' => $checks];
        }

        return ['format' => '', 'valid' => false, 'checks' => []];
    }
}
