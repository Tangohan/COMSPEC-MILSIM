<?php

declare(strict_types=1);

namespace App\Support;

/**
 * Nom affiché d’un membre, au même format partout sur le site.
 *
 * Règle : hors C2, le prénom et le nom passent d’abord (« Prénom NOM » sur une fiche, « Prénom N. » dans une
 * liste), l’indicatif et le pseudo viennent en second (sous-titre, infobulle). Dans le C2 (carte tactique,
 * ATAK web, Overwatch, ordres, ce qui remonte du jeu), l’indicatif reste prioritaire : voir {@see self::c2()}.
 *
 * Ligne attendue (toutes les clés sont facultatives) : first_name, last_name (identité du personnage, fiche
 * user_profiles), display_name (pseudo), callsign, public_name, trigram, grade_short, anonymized, label.
 * Un membre masqué (anonymized) n’affiche jamais que le libellé de remplacement : ni nom, ni trigramme.
 */
final class MemberName
{
    public const FULL = 'full';
    public const SHORT = 'short';
    public const TRIGRAM = 'trigram';
    public const CALLSIGN = 'callsign';

    /** @var list<string> */
    public const FORMATS = [self::FULL, self::SHORT, self::TRIGRAM, self::CALLSIGN];

    /** Particules de nom de famille (minuscules, sans accents), y compris les particules composées. */
    private const PARTICLES = [
        'de', 'du', 'des', 'd', 'la', 'le', 'les', 'l', 'van', 'von', 'der', 'den', 'ter', 'ten', 'zu', 'zum', 'zur',
        'di', 'da', 'dos', 'das', 'do', 'del', 'della', 'dello', 'degli', 'dei', 'y', 'e', 'af', 'av', 'st', 'ben',
        'bin', 'ibn', 'el', 'al', 'lo',
    ];

    private const ACCENTS = [
        'À' => 'A', 'Á' => 'A', 'Â' => 'A', 'Ã' => 'A', 'Ä' => 'A', 'Å' => 'A', 'Æ' => 'AE', 'Ç' => 'C', 'È' => 'E',
        'É' => 'E', 'Ê' => 'E', 'Ë' => 'E', 'Ì' => 'I', 'Í' => 'I', 'Î' => 'I', 'Ï' => 'I', 'Ð' => 'D', 'Ñ' => 'N',
        'Ò' => 'O', 'Ó' => 'O', 'Ô' => 'O', 'Õ' => 'O', 'Ö' => 'O', 'Ø' => 'O', 'Ù' => 'U', 'Ú' => 'U', 'Û' => 'U',
        'Ü' => 'U', 'Ý' => 'Y', 'Þ' => 'TH', 'ß' => 'SS', 'à' => 'a', 'á' => 'a', 'â' => 'a', 'ã' => 'a', 'ä' => 'a',
        'å' => 'a', 'æ' => 'ae', 'ç' => 'c', 'è' => 'e', 'é' => 'e', 'ê' => 'e', 'ë' => 'e', 'ì' => 'i', 'í' => 'i',
        'î' => 'i', 'ï' => 'i', 'ð' => 'd', 'ñ' => 'n', 'ò' => 'o', 'ó' => 'o', 'ô' => 'o', 'õ' => 'o', 'ö' => 'o',
        'ø' => 'o', 'ù' => 'u', 'ú' => 'u', 'û' => 'u', 'ü' => 'u', 'ý' => 'y', 'þ' => 'th', 'ÿ' => 'y', 'Œ' => 'OE',
        'œ' => 'oe', 'Š' => 'S', 'š' => 's', 'Ž' => 'Z', 'ž' => 'z', 'Ÿ' => 'Y', 'Ł' => 'L', 'ł' => 'l', 'Ś' => 'S',
        'ś' => 's', 'Ź' => 'Z', 'ź' => 'z', 'Ż' => 'Z', 'ż' => 'z', 'Ć' => 'C', 'ć' => 'c', 'Ń' => 'N', 'ń' => 'n',
        'Ą' => 'A', 'ą' => 'a', 'Ę' => 'E', 'ę' => 'e', 'Č' => 'C', 'č' => 'c', 'Ř' => 'R', 'ř' => 'r', 'Ď' => 'D',
        'ď' => 'd', 'Ť' => 'T', 'ť' => 't', 'Ň' => 'N', 'ň' => 'n', 'Ů' => 'U', 'ů' => 'u', 'Ě' => 'E', 'ě' => 'e',
        'Ğ' => 'G', 'ğ' => 'g', 'İ' => 'I', 'ı' => 'i', 'Ş' => 'S', 'ş' => 's', 'Ő' => 'O', 'ő' => 'o', 'Ű' => 'U',
        'ű' => 'u',
    ];

    /** Libellé de repli quand rien n’identifie le membre. */
    public const FALLBACK = 'Membre';

    /**
     * Nom au format demandé (full, short, trigram, callsign), avec ou sans grade devant.
     *
     * @param array<string, mixed> $row
     */
    public static function format(array $row, string $format = self::SHORT, bool $withGrade = false): string
    {
        if (self::isMasked($row)) {
            return self::maskedLabel($row);
        }
        $format = self::normalizeFormat($format, self::SHORT);
        $name = match ($format) {
            self::FULL => self::full($row),
            self::TRIGRAM => self::trigramOrShort($row),
            self::CALLSIGN => self::callsignOrShort($row),
            default => self::short($row),
        };

        return $withGrade ? self::withGrade($row, $name) : $name;
    }

    /**
     * « Prénom NOM » (particules en minuscules : « Charles de GAULLE »), sinon le pseudo.
     *
     * @param array<string, mixed> $row
     */
    public static function full(array $row): string
    {
        if (self::isMasked($row)) {
            return self::maskedLabel($row);
        }
        $first = self::clean($row['first_name'] ?? '');
        $last = self::clean($row['last_name'] ?? '');
        if ($first === '' && $last === '') {
            return self::pseudo($row);
        }
        $out = [];
        if ($first !== '') {
            $out[] = $first;
        }
        if ($last !== '') {
            $out[] = self::upperSurname($last);
        }

        return implode(' ', $out);
    }

    /**
     * « Prénom N. » (« Jean M.-D. » pour un nom composé), sinon le pseudo.
     *
     * @param array<string, mixed> $row
     */
    public static function short(array $row): string
    {
        if (self::isMasked($row)) {
            return self::maskedLabel($row);
        }
        $first = self::clean($row['first_name'] ?? '');
        $last = self::clean($row['last_name'] ?? '');
        if ($first === '' && $last === '') {
            return self::pseudo($row);
        }
        if ($first === '') {
            return self::upperSurname($last);
        }
        $initials = self::surnameInitials($last);

        return $initials !== '' ? $first . ' ' . $initials : $first;
    }

    /**
     * Trigramme enregistré (jamais pour un membre masqué), ou chaîne vide.
     *
     * @param array<string, mixed> $row
     */
    public static function trigram(array $row): string
    {
        if (self::isMasked($row)) {
            return '';
        }

        return self::normalizeTrigram((string) ($row['trigram'] ?? '')) ?? '';
    }

    /**
     * Indicatif radio, ou chaîne vide.
     *
     * @param array<string, mixed> $row
     */
    public static function callsign(array $row): string
    {
        if (self::isMasked($row)) {
            return '';
        }

        return self::clean($row['callsign'] ?? '');
    }

    /**
     * C2 (carte tactique, ATAK web, Overwatch, ordres, données du jeu) : l’indicatif d’abord, sinon le nom abrégé.
     *
     * @param array<string, mixed> $row
     */
    public static function c2(array $row): string
    {
        return self::callsignOrShort($row);
    }

    /**
     * Ligne secondaire (sous-titre, infobulle) : indicatif puis pseudo, sans répéter le nom principal.
     *
     * @param array<string, mixed> $row
     */
    public static function secondary(array $row, string $primary = ''): string
    {
        if (self::isMasked($row)) {
            return '';
        }
        $parts = [];
        $seen = [self::fold($primary) => true];
        $full = self::fold(self::full($row));
        foreach (['callsign', 'display_name', 'public_name'] as $key) {
            $v = self::clean($row[$key] ?? '');
            $k = self::fold($v);
            if ($v === '' || isset($seen[$k]) || $k === $full) {
                continue;
            }
            $seen[$k] = true;
            $parts[] = $v;
        }

        return implode(' · ', $parts);
    }

    /**
     * « Sgt Prénom N. » : grade abrégé devant le nom, s’il est connu et pas déjà écrit.
     *
     * @param array<string, mixed> $row
     */
    public static function withGrade(array $row, string $name): string
    {
        $grade = self::clean($row['grade_short'] ?? '');
        if ($grade === '' || self::isMasked($row)) {
            return $name;
        }
        if (self::fold(mb_substr($name, 0, mb_strlen($grade) + 1)) === self::fold($grade . ' ')) {
            return $name;
        }

        return $grade . ' ' . $name;
    }

    /**
     * Complète une ligne d’affichage avec l’identité du personnage (prénom, nom, trigramme) chargée à part.
     * Une ligne masquée reste telle quelle : l’identité réelle n’y est jamais réinjectée.
     *
     * @param array<string, mixed> $row
     * @param array<string, mixed>|null $identity
     * @return array<string, mixed>
     */
    public static function withIdentity(array $row, ?array $identity): array
    {
        if ($identity === null || self::isMasked($row)) {
            if (self::isMasked($row)) {
                unset($row['first_name'], $row['last_name'], $row['trigram']);
            }

            return $row;
        }
        foreach (['first_name', 'last_name', 'trigram'] as $key) {
            if (array_key_exists($key, $identity)) {
                $row[$key] = $identity[$key];
            }
        }
        foreach (['display_name', 'callsign'] as $key) {
            if (self::clean($row[$key] ?? '') === '' && self::clean($identity[$key] ?? '') !== '') {
                $row[$key] = $identity[$key];
            }
        }

        return $row;
    }

    /** @param array<string, mixed> $row */
    public static function isMasked(array $row): bool
    {
        return !empty($row['anonymized']);
    }

    public static function normalizeFormat(string $format, string $default = self::SHORT): string
    {
        $f = strtolower(trim($format));

        return in_array($f, self::FORMATS, true) ? $f : $default;
    }

    /**
     * Trigramme saisi → trois lettres majuscules sans accents, ou null s’il n’est pas valable.
     */
    public static function normalizeTrigram(string $raw): ?string
    {
        $t = self::letters($raw);

        return strlen($t) === 3 ? $t : null;
    }

    /**
     * Trigrammes candidats dans l’ordre d’état-major, du plus attendu au moins attendu.
     *
     * 1. 1re lettre du prénom + 2 premières du nom (Jean DUPONT → JDU) ;
     * 2. 1re du prénom + 1re et 3e du nom (JDP) ; 3. 1re du prénom + 1re et dernière du nom (JDT) ;
     * 4. 2 premières du prénom + 1re du nom (JED) ; puis initiales des prénoms / noms composés
     *    (Jean-Pierre → JPD, MARTIN-DUPONT → JMD) ; puis 1re du prénom + 1re du nom + chaque autre lettre du nom,
     *    puis + chaque lettre de l’alphabet. Particules (de, van der…) ignorées.
     *
     * @return list<string>
     */
    public static function trigramCandidates(string $firstName, string $lastName, string $fallbackName = ''): array
    {
        $firstParts = self::nameParts($firstName, false);
        $lastParts = self::nameParts($lastName, true);
        if ($firstParts === [] && $lastParts === []) {
            $words = self::nameParts($fallbackName, false);
            if (count($words) >= 2) {
                $firstParts = [$words[0]];
                $lastParts = array_slice($words, 1);
            } else {
                $lastParts = $words;
            }
        }
        $f = implode('', $firstParts);
        $l = implode('', $lastParts);
        $out = [];
        $add = static function (string $c) use (&$out): void {
            if (strlen($c) === 3 && preg_match('/^[A-Z]{3}$/', $c) === 1 && !in_array($c, $out, true)) {
                $out[] = $c;
            }
        };

        if ($f !== '' && $l !== '') {
            $f1 = $f[0];
            $add($f1 . substr($l, 0, 2));
            if (strlen($l) >= 3) {
                $add($f1 . $l[0] . $l[2]);
            }
            $add($f1 . $l[0] . substr($l, -1));
            $add(substr($f, 0, 2) . $l[0]);
            if (count($firstParts) >= 2) {
                $add($f1 . $firstParts[1][0] . $l[0]);
            }
            if (count($lastParts) >= 2) {
                $add($f1 . $lastParts[0][0] . $lastParts[1][0]);
            }
            for ($i = 1, $n = strlen($l); $i < $n; $i++) {
                $add($f1 . $l[0] . $l[$i]);
            }
            for ($i = 1, $n = strlen($f); $i < $n; $i++) {
                $add($f1 . $f[$i] . $l[0]);
            }
            foreach (range('A', 'Z') as $c) {
                $add($f1 . $l[0] . $c);
            }
            foreach (range('A', 'Z') as $c) {
                $add($f1 . $c . $l[0]);
            }
        } else {
            $w = $f !== '' ? $f : $l;
            if ($w === '') {
                return [];
            }
            $add(str_pad(substr($w, 0, 3), 3, 'X'));
            for ($i = 2, $n = strlen($w); $i < $n; $i++) {
                $add(substr($w, 0, 2) . $w[$i]);
            }
            for ($i = 1, $n = strlen($w); $i < $n; $i++) {
                for ($j = $i + 1; $j < $n; $j++) {
                    $add($w[0] . $w[$i] . $w[$j]);
                }
            }
            foreach (range('A', 'Z') as $c) {
                $add(substr(str_pad($w, 2, 'X'), 0, 2) . $c);
            }
        }

        return $out;
    }

    /**
     * Premier trigramme candidat libre dans la communauté, ou null (aucun nom exploitable ou tout est pris).
     *
     * @param array<string, mixed> $row first_name, last_name, display_name, callsign
     * @param array<string, mixed> $taken trigrammes déjà attribués (clés)
     */
    public static function pickTrigram(array $row, array $taken): ?string
    {
        $fallback = self::clean($row['display_name'] ?? '');
        if ($fallback === '') {
            $fallback = self::clean($row['callsign'] ?? '');
        }
        foreach (self::trigramCandidates((string) ($row['first_name'] ?? ''), (string) ($row['last_name'] ?? ''), $fallback) as $c) {
            if (!isset($taken[$c])) {
                return $c;
            }
        }

        return null;
    }

    /** Lettres A-Z sans accents, en majuscules. */
    public static function letters(string $raw): string
    {
        $t = strtr($raw, self::ACCENTS);
        if (function_exists('iconv')) {
            $ascii = @iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $t);
            if (is_string($ascii) && $ascii !== '') {
                $t = $ascii;
            }
        }

        return (string) preg_replace('/[^A-Z]/', '', strtoupper($t));
    }

    /** @param array<string, mixed> $row */
    private static function trigramOrShort(array $row): string
    {
        $t = self::trigram($row);

        return $t !== '' ? $t : self::short($row);
    }

    /** @param array<string, mixed> $row */
    private static function callsignOrShort(array $row): string
    {
        if (self::isMasked($row)) {
            return self::maskedLabel($row);
        }
        $cs = self::clean($row['callsign'] ?? '');

        return $cs !== '' ? $cs : self::short($row);
    }

    /** @param array<string, mixed> $row */
    private static function pseudo(array $row): string
    {
        foreach (['display_name', 'callsign', 'public_name'] as $key) {
            $v = self::clean($row[$key] ?? '');
            if ($v !== '') {
                return $v;
            }
        }

        return self::FALLBACK;
    }

    /** @param array<string, mixed> $row */
    private static function maskedLabel(array $row): string
    {
        foreach (['label', 'display_name', 'anonymized_label'] as $key) {
            $v = self::clean($row[$key] ?? '');
            if ($v !== '') {
                return $v;
            }
        }

        return ConfidentialDisplay::personLabel();
    }

    /** NOM en capitales, particules en minuscules (« de GAULLE », « van der BERG », « MARTIN-DUPONT »). */
    private static function upperSurname(string $last): string
    {
        $words = preg_split('/\s+/u', $last) ?: [];
        $out = [];
        $seenMain = false;
        $count = count($words);
        foreach ($words as $i => $w) {
            $isParticle = !$seenMain && $i < $count - 1 && self::isParticle($w);
            if ($isParticle) {
                $out[] = mb_strtolower($w, 'UTF-8');
                continue;
            }
            $seenMain = true;
            if (preg_match('/^([dDlL][’\'])(.+)$/u', $w, $m) === 1) {
                $out[] = mb_strtolower($m[1], 'UTF-8') . mb_strtoupper($m[2], 'UTF-8');
                continue;
            }
            $out[] = mb_strtoupper($w, 'UTF-8');
        }

        return implode(' ', $out);
    }

    /** Initiales du nom sans les particules : « D. », « M.-D. » (trait d’union), « G. L. » (deux noms). */
    private static function surnameInitials(string $last): string
    {
        if ($last === '') {
            return '';
        }
        $words = preg_split('/\s+/u', $last) ?: [];
        $count = count($words);
        $out = [];
        $seenMain = false;
        foreach ($words as $i => $w) {
            if (!$seenMain && $i < $count - 1 && self::isParticle($w)) {
                continue;
            }
            $seenMain = true;
            $pieces = [];
            foreach (preg_split('/-+/u', $w) ?: [] as $piece) {
                $piece = (string) preg_replace('/^[^\p{L}]+/u', '', $piece);
                // « d’Artagnan » : l’initiale est celle du nom, pas de la particule élidée.
                $piece = (string) preg_replace('/^[dDlL][’\']/u', '', $piece);
                if ($piece !== '') {
                    $pieces[] = mb_strtoupper(mb_substr($piece, 0, 1, 'UTF-8'), 'UTF-8') . '.';
                }
            }
            if ($pieces !== []) {
                $out[] = implode('-', $pieces);
            }
        }

        return implode(' ', $out);
    }

    /**
     * Morceaux de nom en lettres A-Z (particules retirées pour un nom de famille).
     *
     * @return list<string>
     */
    private static function nameParts(string $raw, bool $surname): array
    {
        $raw = self::clean($raw);
        if ($raw === '') {
            return [];
        }
        // « d’Artagnan », « O’Neil » : l’apostrophe sépare la particule élidée du nom.
        $raw = (string) preg_replace('/^([dDlL])[’\']/u', '$1 ', $raw);
        $words = preg_split('/[\s\-]+/u', $raw) ?: [];
        $out = [];
        $count = count($words);
        $seenMain = false;
        foreach ($words as $i => $w) {
            if ($surname && !$seenMain && $i < $count - 1 && self::isParticle($w)) {
                continue;
            }
            $seenMain = true;
            $letters = self::letters($w);
            if ($letters !== '') {
                $out[] = $letters;
            }
        }

        return $out;
    }

    private static function isParticle(string $word): bool
    {
        $w = strtolower(self::letters(rtrim($word, "’'")));

        return $w !== '' && in_array($w, self::PARTICLES, true);
    }

    private static function clean(mixed $raw): string
    {
        $v = trim((string) preg_replace('/\s+/u', ' ', (string) $raw));

        return ($v === '—' || $v === '-') ? '' : $v;
    }

    private static function fold(string $v): string
    {
        return mb_strtolower(trim($v), 'UTF-8');
    }
}
