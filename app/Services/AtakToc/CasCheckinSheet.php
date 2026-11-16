<?php

declare(strict_types=1);

namespace App\Services\AtakToc;

/**
 * Fiche de check-in CAS (« 9 LINE CLOSE AIR SUPPORT BRIEF », doctrine publique JP 3-09.3) jointe à un compte rendu par
 * l'app JTAC du téléphone (onglet CKIN, fn_jtacCkin) : le compte rendu libre (OTHER) porte dans ses données structurées
 * form = cas_ckin et une clé ckin_<champ> par champ rempli. Le poste TOC en tire la fiche imprimable (CasCheckinSheetPdf).
 *
 * Les clés suivent l'ordre de la fiche blanche : en-tête, check-in (7 champs), situation et game plan, 9 lignes,
 * remarques. Une clé inconnue est ignorée ; une valeur est ramenée à une ligne de 300 caractères au plus.
 */
final class CasCheckinSheet
{
    public const FORM = 'cas_ckin';

    /** Champs de la fiche : clé => [libellé de la fiche (anglais), aide française, aide anglaise]. */
    public const FIELDS = [
        'ac' => ['Aircraft callsign', 'Indicatif de l’appareil', 'Aircraft callsign'],
        'fac' => ['FAC callsign', 'Indicatif du FAC', 'FAC callsign'],
        'pilot' => ['Pilot Callsign / Mission #', '', ''],
        'acft' => ['Number and type of aircraft', '', ''],
        'pos' => ['Position and altitude', '', ''],
        'ord' => ['Ordnance', '', ''],
        'tos' => ['Time on station', '', ''],
        'abort' => ['Abort Code', '', ''],
        'add' => ['Additional Remarks', '', ''],
        'sit' => ['Friendly, enemy situation and game plan', '', ''],
        'l1' => ['IP / BP', 'Point initial (voilure fixe) ou position de combat (hélicoptère)', 'Initial point (FW) or battle position (RW)'],
        'l2' => ['Heading', 'Degrés magnétiques, IP / BP vers la cible', 'Degrees magnetic, IP / BP to Tgt'],
        'l2o' => ['Offset', '', ''],
        'l3' => ['Distance', 'IP vers la cible en milles nautiques (FW), centre du BP vers la cible en mètres (RW)', 'IP / BP to Tgt in nautical miles for FW, center of BP to Tgt in meters for RW'],
        'l4' => ['Target Elevation', 'Pieds MSL (mètres × 3,3 = pieds)', 'Feet MSL (meters x 3.3 = feet)'],
        'l5' => ['Target Description', 'Combien, quoi, degré de protection', 'How many, what it is, degree of protection'],
        'l6' => ['Target Location', 'Avec l’identifiant du carré de 100 000 m', 'Include 100,000 meter grid identification'],
        'l7' => ['Type Mark', 'WP, éclairant, laser, pointeur IR', 'WP, Illum, Laser, IR pointer'],
        'l7c' => ['Laser Code', '', ''],
        'l8' => ['Friendlies', 'Depuis la cible, direction cardinale et distance en mètres', 'From Target, cardinal direction and distance in meters'],
        'l9' => ['Egress', 'Direction cardinale ou intermédiaire et point de sortie (CP / IP) au besoin', 'Cardinal / subcardinal direction and location (CP / IP) as required'],
        'rem' => ['Remarks', 'Rester au-dessus / au-dessous, zones interdites', 'Stay above / below, No Fly Areas'],
        'fah' => ['Final Attack Heading', 'Degrés magnétiques', 'Degrees magnetic'],
        'ltl' => ['Laser Target Line', 'Degrés magnétiques', 'Degrees magnetic'],
        'thr' => ['Threats', 'Description, direction cardinale depuis la cible, distance en mètres, type de neutralisation', 'Description, cardinal direction from target, distance in meters, type of suppression'],
        'gtl' => ['Active Gun Target Line', '', ''],
        'tot' => ['TOT / TTT', '', ''],
        'push' => ['PUSH ASAP', '', ''],
        'amp' => ['Amplifying information', 'Guidage du pilote vers la cible, du général au détail', 'Give pilot talk-on to TGT, big to small'],
    ];

    /** Les sept champs du bloc de check-in, dans l'ordre de la fiche. */
    public const CHECK_IN = ['pilot', 'acft', 'pos', 'ord', 'tos', 'abort', 'add'];

    /** Les neuf lignes (1 à 9) : clé de la valeur. */
    public const NINE = ['l1', 'l2', 'l3', 'l4', 'l5', 'l6', 'l7', 'l8', 'l9'];

    /**
     * Fiche jointe à des données structurées, ou null si le compte rendu n'en porte pas.
     *
     * @param array<string, mixed> $sd structured_data décodé
     * @return array<string, string>|null
     */
    public static function fromStructured(array $sd): ?array
    {
        if ((string) ($sd['form'] ?? '') !== self::FORM) {
            return null;
        }
        $out = [];
        foreach (array_keys(self::FIELDS) as $key) {
            $v = $sd['ckin_' . $key] ?? null;
            if (!is_scalar($v)) {
                continue;
            }
            $v = trim((string) preg_replace('/\s+/u', ' ', (string) $v));
            if ($v !== '') {
                $out[$key] = mb_substr($v, 0, 300, 'UTF-8');
            }
        }

        return $out;
    }

    /** Clé de données structurées propre à la fiche (masquée dans la liste « autres informations » du rapport). */
    public static function isSheetKey(string $key): bool
    {
        return $key === 'form' || str_starts_with($key, 'ckin_');
    }

    /** Décalage de la ligne 2 : « LEFT », « RIGHT » ou "" (la fiche du téléphone l'écrit en français). */
    public static function offset(string $v): string
    {
        $v = mb_strtoupper(trim($v), 'UTF-8');

        return match (true) {
            in_array($v, ['GAUCHE', 'LEFT', 'L'], true) => 'LEFT',
            in_array($v, ['DROITE', 'RIGHT', 'R'], true) => 'RIGHT',
            default => '',
        };
    }
}
