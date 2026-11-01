<?php

declare(strict_types=1);

namespace App\Support;

/**
 * Symbole militaire d’une unité de l’ORBAT (MIL-STD-2525C / APP-6) : code symbole (SIDC) et échelon.
 *
 * `units.sidc` et `units.echelon` restent NULL tant que personne ne les choisit : le symbole est alors
 * déduit du nom et du type de structure (infanterie amie par défaut). Le SIDC stocké est la base 2525C
 * (15 caractères, ou 2525D numérique à 20/30 chiffres) ; l’échelon est appliqué à l’affichage.
 */
final class UnitMilitarySymbol
{
    /**
     * Échelons proposés : clé => [lettre 2525C (12e caractère), code 2525D (positions 9-10), libellé FR, libellé EN].
     * Plusieurs clés partagent la même marque (section et peloton, compagnie et escadron) : le libellé suit la doctrine
     * de la communauté, le symbole reste le même.
     */
    public const ECHELONS = [
        'equipe' => ['A', '11', 'Équipe / équipage', 'Team / crew'],
        'groupe' => ['B', '12', 'Groupe', 'Squad'],
        'section_otan' => ['C', '13', 'Demi-section (section OTAN)', 'Section'],
        'section' => ['D', '14', 'Section', 'Platoon'],
        'peloton' => ['D', '14', 'Peloton', 'Platoon (cavalry)'],
        'compagnie' => ['E', '15', 'Compagnie', 'Company'],
        'escadron' => ['E', '15', 'Escadron', 'Troop / squadron'],
        'batterie' => ['E', '15', 'Batterie', 'Battery'],
        'bataillon' => ['F', '16', 'Bataillon', 'Battalion'],
        'regiment' => ['G', '17', 'Régiment', 'Regiment / group'],
        'brigade' => ['H', '18', 'Brigade', 'Brigade'],
        'division' => ['I', '21', 'Division', 'Division'],
        'corps' => ['J', '22', 'Corps d’armée', 'Corps'],
        'armee' => ['K', '23', 'Armée', 'Army'],
    ];

    /**
     * Fonctions usuelles : clé => [dimension 2525C, identifiant de fonction (6 car.), libellé FR, libellé EN].
     * Dimension G = unité terrestre, F = forces spéciales.
     */
    public const FUNCTIONS = [
        'infantry' => ['G', 'UCI---', 'Infanterie', 'Infantry'],
        'infantry_light' => ['G', 'UCIL--', 'Infanterie légère', 'Light infantry'],
        'infantry_motorized' => ['G', 'UCIM--', 'Infanterie motorisée', 'Motorized infantry'],
        'infantry_mechanized' => ['G', 'UCIZ--', 'Infanterie mécanisée', 'Mechanized infantry'],
        'infantry_airborne' => ['G', 'UCIA--', 'Infanterie aéroportée', 'Airborne infantry'],
        'infantry_air_assault' => ['G', 'UCIS--', 'Infanterie d’assaut aérien', 'Air assault infantry'],
        'infantry_mountain' => ['G', 'UCIO--', 'Infanterie de montagne', 'Mountain infantry'],
        'infantry_naval' => ['G', 'UCIN--', 'Infanterie de marine', 'Naval infantry'],
        'armor' => ['G', 'UCA---', 'Blindé', 'Armor'],
        'recon' => ['G', 'UCR---', 'Reconnaissance / cavalerie', 'Reconnaissance / cavalry'],
        'artillery' => ['G', 'UCF---', 'Artillerie', 'Field artillery'],
        'mortar' => ['G', 'UCFM--', 'Mortiers', 'Mortar'],
        'observer' => ['G', 'UCFO--', 'Observation / appui feu (JTAC)', 'Fire support observer (JTAC)'],
        'anti_armor' => ['G', 'UCAA--', 'Antichar', 'Anti-armor'],
        'air_defense' => ['G', 'UCD---', 'Défense sol-air', 'Air defense'],
        'engineer' => ['G', 'UCE---', 'Génie', 'Engineer'],
        'aviation' => ['G', 'UCV---', 'Aviation', 'Aviation'],
        'aviation_rotary' => ['G', 'UCVR--', 'Aviation (hélicoptères)', 'Rotary wing aviation'],
        'aviation_fixed' => ['G', 'UCVF--', 'Aviation (avions)', 'Fixed wing aviation'],
        'uav' => ['G', 'UCVU--', 'Drones', 'Unmanned aerial systems'],
        'signal' => ['G', 'UUS---', 'Transmissions', 'Signal'],
        'intelligence' => ['G', 'UUM---', 'Renseignement', 'Military intelligence'],
        'military_police' => ['G', 'UUL---', 'Prévôté / police militaire', 'Military police'],
        'cbrn' => ['G', 'UUA---', 'NRBC', 'CBRN defense'],
        'medical' => ['G', 'USM---', 'Santé / sauvetage', 'Medical'],
        'supply' => ['G', 'USS---', 'Ravitaillement / logistique', 'Supply'],
        'maintenance' => ['G', 'USX---', 'Maintenance', 'Maintenance'],
        'transport' => ['G', 'UST---', 'Transport', 'Transportation'],
        'sof' => ['F', 'G-----', 'Forces spéciales (terre)', 'Special operations forces'],
        'special_forces' => ['F', 'GS----', 'Commandos / forces spéciales', 'Special forces'],
        'ranger' => ['F', 'GR----', 'Rangers', 'Ranger'],
        'sof_naval' => ['F', 'NS----', 'Commandos marine (SEAL)', 'SEAL'],
        'sof_aviation' => ['F', 'A-----', 'Aviation des forces spéciales', 'SOF aviation'],
    ];

    /** Mots du nom → fonction ; l’ordre compte (le plus spécifique d’abord). */
    private const FUNCTION_KEYWORDS = [
        'uav' => '/\b(drone|uav|uas)s?\b/u',
        'sof_aviation' => '/\b(soar|night\s*stalkers?)\b/u',
        'aviation_rotary' => '/(h[ée]lico|rotary|\bheli|aviation l[ée]g[èe]re|\balat\b)/u',
        'aviation_fixed' => '/(\bavion|fixed\s*wing|escadrille)/u',
        'aviation' => '/\b(aviation|air\s*wing|a[ée]ro)/u',
        'sof_naval' => '/\b(seal|commandos? marine|nageurs?)\b/u',
        'ranger' => '/\brangers?\b/u',
        'special_forces' => '/(forces? sp[ée]ciales?|special forces|\bsfod\b|\bdelta\b|\bsas\b|\bcpa\b|\bcommandos?\b|\bgcp\b|\bdevgru\b|green berets?)/u',
        'sof' => '/(\bsof\b|\bsoc\b|\bjsoc\b|special operations|op[ée]rations sp[ée]ciales|\bcos\b|\bafsoc\b|\bsocom\b)/u',
        'medical' => '/(m[ée]dic|sant[ée]|pararescue|\bpj\b|\bcasevac|\bmedevac|secour|infirmer)/u',
        'observer' => '/(\bjtac\b|\btacp\b|observat|\bfac\b|appui feu|fire support|\bfso\b)/u',
        'mortar' => '/(mortier|mortar)/u',
        'artillery' => '/(artiller|\barty\b|\bobus|canon)/u',
        'anti_armor' => '/(antichar|anti-?char|anti-?armor)/u',
        'air_defense' => '/(sol-?air|air defen[cs]e|\bdca\b|\bsam\b)/u',
        'armor' => '/(\bchars?\b|blind[ée]|\barmor|\btank|\bcuirass)/u',
        'recon' => '/(recon|reco\b|[ée]claireur|cavaler|\bcav\b|scout|pathfinder)/u',
        'engineer' => '/(g[ée]nie|sapeur|engineer|\beod\b|d[ée]minage)/u',
        'signal' => '/(transmission|\bsignal|\bradio|\bcomms?\b|\bsic\b)/u',
        'intelligence' => '/(renseignement|\bintel|\bs2\b|\bisr\b)/u',
        'military_police' => '/(pr[ée]v[ôo]t|police militaire|military police|\bmp\b)/u',
        'cbrn' => '/(\bnrbc\b|\bcbrn\b|chimique)/u',
        'maintenance' => '/(maintenance|r[ée]paration|\bmat[ée]riel\b)/u',
        'transport' => '/(transport|convoi)/u',
        'supply' => '/(logisti|ravitaill|soutien|\bsupply|\bintendance)/u',
        'infantry_mechanized' => '/(m[ée]canis|mechani[sz]ed|\bvbci\b|\bifv\b)/u',
        'infantry_motorized' => '/(motoris|motori[sz]ed)/u',
        'infantry_airborne' => '/(parachut|airborne|a[ée]roport|\bpara\b)/u',
        'infantry_air_assault' => '/(air assault|aeromobile|a[ée]romobile)/u',
        'infantry_mountain' => '/(montagne|mountain|alpin)/u',
        'infantry_naval' => '/(marine|marines|\bmarsoin)/u',
    ];

    /** Mots du nom → échelon (le plus spécifique d’abord). */
    private const ECHELON_KEYWORDS = [
        'equipe' => '/(\b[ée]quipe\b|\bteam\b|\bfire\s*team\b|\bbin[ôo]me\b|\b[ée]quipage\b|\bcrew\b|\bcellule\b)/u',
        'groupe' => '/(\bgroupe de combat\b|\bsquad\b|\bescouade\b|\bgroupe\b|\bgrp\b)/u',
        'peloton' => '/\bpeloton\b/u',
        'section' => '/(\bsection\b|\bplatoon\b|\bplt\b)/u',
        'escadron' => '/(\bescadron\b|\btroop\b)/u',
        'batterie' => '/\bbatterie\b|\bbattery\b/u',
        'compagnie' => '/(\bcompagnie\b|\bcompany\b|\bcie\b|\bcoy\b)/u',
        'bataillon' => '/(\bbataillon\b|\bbattalion\b|\bbn\b|\bsquadron\b)/u',
        'regiment' => '/(\br[ée]giment\b|\bregiment\b|\brgt\b|\bgroup\b)/u',
        'brigade' => '/\bbrigade\b|\bbde\b/u',
        'division' => '/\bdivision\b|\bdiv\b/u',
        'corps' => '/\bcorps\b/u',
    ];

    /** Type de structure (config units.types) → échelon par défaut. */
    private const ECHELON_BY_STRUCT_TYPE = [
        'team' => 'equipe',
        'squad' => 'groupe',
        'section' => 'section',
        'squadron' => 'escadron',
    ];

    private const HQ_PATTERN = '/(\bqg\b|\bhq\b|[ée]tat-major|\bpc\b|\bcommandement\b|\bcommand\b|\bheadquarters?\b|\bstaff\b)/u';

    /** Clé d’échelon valide, null pour « automatique » ; lève une exception si la valeur est inconnue. */
    public static function normalizeEchelon(?string $raw): ?string
    {
        $value = strtolower(trim((string) $raw));
        if ($value === '') {
            return null;
        }
        if (!isset(self::ECHELONS[$value])) {
            throw new \InvalidArgumentException('Échelon non reconnu.');
        }

        return $value;
    }

    /** SIDC valide en majuscules, null pour « automatique » ; lève une exception si le code est invalide. */
    public static function normalizeSidc(?string $raw): ?string
    {
        $value = strtoupper(preg_replace('/\s+/', '', (string) $raw) ?? '');
        if ($value === '') {
            return null;
        }
        if (!self::isValidSidc($value)) {
            throw new \InvalidArgumentException('Code symbole MIL-STD-2525 invalide (15 caractères 2525C ou 20/30 chiffres 2525D).');
        }

        return $value;
    }

    public static function isValidSidc(string $sidc): bool
    {
        // 2525C : codage, affiliation, dimension, statut, fonction (6), modificateurs (2), pays (2), ordre de bataille.
        if (preg_match('/^[SIOEWG][PUAFNSHGWMDLJK\-*][A-Z\-*][APCDXF\-*][A-Z0-9\-*]{6}[A-Z\-*]{2}[A-Z\-*]{2}[A-Z\-*]$/', $sidc) === 1) {
            return true;
        }

        return preg_match('/^\d{20}(\d{10})?$/', $sidc) === 1;
    }

    /** Base 2525C pour une fonction usuelle (unité amie, présente) ; $hq ajoute la hampe de poste de commandement. */
    public static function sidcForFunction(string $function, bool $hq = false): string
    {
        [$dim, $fid] = self::FUNCTIONS[$function] ?? self::FUNCTIONS['infantry'];

        return 'SF' . $dim . 'P' . $fid . ($hq ? 'A' : '-') . '----';
    }

    /** Fonction 2525C retrouvée depuis un SIDC (sans tenir compte de l’affiliation ni de l’échelon). */
    public static function functionForSidc(string $sidc): ?string
    {
        if (strlen($sidc) !== 15) {
            return null;
        }
        $dim = $sidc[2];
        $fid = substr($sidc, 4, 6);
        foreach (self::FUNCTIONS as $key => [$d, $f]) {
            if ($d === $dim && $f === $fid) {
                return $key;
            }
        }

        return null;
    }

    public static function guessFunction(string $name): string
    {
        $haystack = mb_strtolower($name);
        foreach (self::FUNCTION_KEYWORDS as $key => $pattern) {
            if (preg_match($pattern, $haystack) === 1) {
                return $key;
            }
        }

        return 'infantry';
    }

    public static function guessEchelon(string $structType, string $name): ?string
    {
        $haystack = mb_strtolower($name);
        foreach (self::ECHELON_KEYWORDS as $key => $pattern) {
            if (preg_match($pattern, $haystack) === 1) {
                return $key;
            }
        }

        return self::ECHELON_BY_STRUCT_TYPE[strtolower(trim($structType))] ?? null;
    }

    /** Applique la marque d’échelon au SIDC (12e caractère en 2525C, positions 9-10 en 2525D). */
    public static function compose(string $sidc, ?string $echelon): string
    {
        $meta = $echelon !== null ? (self::ECHELONS[$echelon] ?? null) : null;
        if ($meta === null) {
            return $sidc;
        }
        if (strlen($sidc) === 15) {
            return substr($sidc, 0, 11) . $meta[0] . substr($sidc, 12);
        }
        if (ctype_digit($sidc) && strlen($sidc) >= 20) {
            return substr($sidc, 0, 8) . $meta[1] . substr($sidc, 10);
        }

        return $sidc;
    }

    /**
     * Symbole effectif d’une ligne `units` : valeurs saisies, sinon déduites.
     *
     * @param array<string, mixed> $unit
     * @return array{sidc: string, echelon: string, sidcAuto: bool, echelonAuto: bool, symbol: string, functionKey: string}
     */
    public static function resolve(array $unit): array
    {
        $name = (string) ($unit['name'] ?? '');
        $structType = (string) ($unit['type'] ?? '');

        $storedSidc = null;
        try {
            $storedSidc = self::normalizeSidc(isset($unit['sidc']) ? (string) $unit['sidc'] : null);
        } catch (\InvalidArgumentException) {
            $storedSidc = null;
        }
        $storedEchelon = null;
        try {
            $storedEchelon = self::normalizeEchelon(isset($unit['echelon']) ? (string) $unit['echelon'] : null);
        } catch (\InvalidArgumentException) {
            $storedEchelon = null;
        }

        $functionKey = $storedSidc !== null ? (self::functionForSidc($storedSidc) ?? '') : self::guessFunction($name);
        $sidc = $storedSidc ?? self::sidcForFunction($functionKey, preg_match(self::HQ_PATTERN, mb_strtolower($name)) === 1);
        $echelon = $storedEchelon ?? self::guessEchelon($structType, $name);

        return [
            'sidc' => $sidc,
            'echelon' => $echelon ?? '',
            'sidcAuto' => $storedSidc === null,
            'echelonAuto' => $storedEchelon === null,
            'symbol' => self::compose($sidc, $echelon),
            'functionKey' => $functionKey,
        ];
    }

    /**
     * Listes pour les sélecteurs de l’ORBAT.
     *
     * @return array{echelons: list<array{id: string, label: string, letter: string}>, functions: list<array{id: string, label: string, sidc: string}>}
     */
    public static function options(string $locale = 'fr'): array
    {
        $i = $locale === 'en' ? 3 : 2;
        $echelons = [];
        foreach (self::ECHELONS as $key => $meta) {
            $echelons[] = ['id' => $key, 'label' => $meta[$i], 'letter' => $meta[0]];
        }
        $functions = [];
        foreach (self::FUNCTIONS as $key => $meta) {
            $functions[] = ['id' => $key, 'label' => $meta[$i], 'sidc' => self::sidcForFunction($key)];
        }

        return ['echelons' => $echelons, 'functions' => $functions];
    }
}
