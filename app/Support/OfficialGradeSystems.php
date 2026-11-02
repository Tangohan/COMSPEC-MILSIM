<?php

declare(strict_types=1);

namespace App\Support;

/**
 * Systèmes de grades officiels du catalogue commun (table grade_systems, partagée par toutes les communautés) :
 * onglet du référentiel des grades, pays, noms affichés et grade proposé par défaut au fondateur.
 *
 * Les grades français et américains sont posés par les étapes 1010 et 1050 ; les grades malaisiens (Angkatan
 * Tentera Malaysia, système d’origine britannique) par l’étape 3610 à partir de GRADES ci-dessous. Les intitulés restent
 * en malais (langue d’origine) ; l’équivalent anglais officiel est affiché à côté dans le référentiel.
 * Ajouter un système = une ligne dans SYSTEMS (+ ses grades et une étape de migration idempotente).
 */
final class OfficialGradeSystems
{
    /** Codes dans l’ordre des onglets. */
    public const CODES = ['FR_CLASSIC', 'US_CLASSIC', 'MY_CLASSIC', 'MY_TLDM', 'MY_TUDM'];

    /**
     * code => onglet, pays, libellé du système en base, titre de l’onglet, nom au pluriel (« les grades … »),
     * grade proposé au fondateur, titre et texte de la carte de l’assistant de création.
     */
    private const SYSTEMS = [
        'FR_CLASSIC' => ['tab' => 'fr', 'country' => 'FR', 'label' => 'Grades français (classique)', 'tab_label' => 'Grades français', 'name' => 'grades français', 'founder' => 'CNE', 'card' => 'Français', 'card_text' => 'Grades classiques de l’armée de terre française.'],
        'US_CLASSIC' => ['tab' => 'us', 'country' => 'US', 'label' => 'Grades américains (classique)', 'tab_label' => 'Grades américains', 'name' => 'grades américains', 'founder' => 'CPT', 'card' => 'United States', 'card_text' => 'Grades classiques des forces armées américaines.'],
        'MY_CLASSIC' => ['tab' => 'my', 'country' => 'MY', 'label' => 'Pangkat Tentera Darat Malaysia', 'tab_label' => 'Malaisie : armée de terre', 'name' => 'grades malaisiens de l’armée de terre (Tentera Darat)', 'founder' => 'KAPT', 'card' => 'Malaysia · Tentera Darat', 'card_text' => 'Grades de l’armée de terre malaisienne (système d’origine britannique), en malais.'],
        'MY_TLDM' => ['tab' => 'my_tldm', 'country' => 'MY', 'label' => 'Pangkat Tentera Laut Diraja Malaysia (TLDM)', 'tab_label' => 'Malaisie : marine', 'name' => 'grades malaisiens de la marine (TLDM)', 'founder' => 'LT', 'card' => 'Malaysia · TLDM', 'card_text' => 'Grades de la marine royale malaisienne, en malais.'],
        'MY_TUDM' => ['tab' => 'my_tudm', 'country' => 'MY', 'label' => 'Pangkat Tentera Udara Diraja Malaysia (TUDM)', 'tab_label' => 'Malaisie : armée de l’air', 'name' => 'grades malaisiens de l’armée de l’air (TUDM)', 'founder' => 'KAPT', 'card' => 'Malaysia · TUDM', 'card_text' => 'Grades de l’armée de l’air royale malaisienne, en malais.'],
    ];

    /**
     * Grades posés par migration (systèmes sans étape historique) : [code, abrégé, intitulé officiel, équivalent anglais,
     * code OTAN, catégorie (grade_categories.code), ordre, officier commissionné]. Ordre : officiers du plus bas au plus
     * haut, sous-officiers et militaires du rang du plus haut au plus bas (même convention que les grades français).
     * Sources : tableau des grades de l’Angkatan Tentera Malaysia (Wikipédia « Malaysian military ranks », tableaux
     * OTAN STANAG 2116) ; abrégés : usage courant de la presse et des forces malaisiennes (Bernama, sites ATM).
     * Grades honorifiques OF-10 (Fil Marsyal, Laksamana Armada, Marsyal Tentera Udara) non repris : réservés au chef
     * de l’État. Leftenan Muda n’a pas de code OTAN propre : rangé en OF-1, sous Leftenan.
     */
    public const GRADES = [
        'MY_CLASSIC' => [
            ['PK', 'PK', 'Pegawai Kadet', 'Officer Cadet', 'OF(D)', 'OFFICIER', 10, 0],
            ['LTM', 'Lt M', 'Leftenan Muda', 'Second Lieutenant', 'OF-1', 'OFFICIER', 11, 1],
            ['LT', 'Lt', 'Leftenan', 'Lieutenant', 'OF-1', 'OFFICIER', 12, 1],
            ['KAPT', 'Kapt', 'Kapten', 'Captain', 'OF-2', 'OFFICIER', 13, 1],
            ['MEJ', 'Mej', 'Mejar', 'Major', 'OF-3', 'OFFICIER', 14, 1],
            ['LTKOL', 'Lt Kol', 'Leftenan Kolonel', 'Lieutenant Colonel', 'OF-4', 'OFFICIER', 15, 1],
            ['KOL', 'Kol', 'Kolonel', 'Colonel', 'OF-5', 'OFFICIER', 16, 1],
            ['BRIGJEN', 'Brig Jen', 'Brigedier Jeneral', 'Brigadier General', 'OF-6', 'OFFICIER', 17, 1],
            ['MEJJEN', 'Mej Jen', 'Mejar Jeneral', 'Major General', 'OF-7', 'OFFICIER', 18, 1],
            ['LTJEN', 'Lt Jen', 'Leftenan Jeneral', 'Lieutenant General', 'OF-8', 'OFFICIER', 19, 1],
            ['JEN', 'Jen', 'Jeneral', 'General', 'OF-9', 'OFFICIER', 20, 1],
            ['PW1', 'PW I', 'Pegawai Waran I', 'Warrant Officer I', 'OR-9', 'SOUS_OFFICIER', 21, 0],
            ['PW2', 'PW II', 'Pegawai Waran II', 'Warrant Officer II', 'OR-8', 'SOUS_OFFICIER', 22, 0],
            ['SSJN', 'SSjn', 'Staf Sarjan', 'Staff Sergeant', 'OR-7', 'SOUS_OFFICIER', 23, 0],
            ['SJN', 'Sjn', 'Sarjan', 'Sergeant', 'OR-6', 'SOUS_OFFICIER', 24, 0],
            ['KPL', 'Kpl', 'Koperal', 'Corporal', 'OR-4', 'SOUS_OFFICIER', 25, 0],
            ['LKPL', 'L/Kpl', 'Lans Koperal', 'Lance Corporal', 'OR-3', 'SOUS_OFFICIER', 26, 0],
            ['PBT', 'Pbt', 'Prebet', 'Private', 'OR-1', 'MDR', 31, 0],
        ],
        'MY_TLDM' => [
            ['PK', 'PK', 'Pegawai Kadet', 'Officer Cadet', 'OF(D)', 'OFFICIER', 9, 0],
            ['PKK', 'PKK', 'Pegawai Kadet Kanan', 'Midshipman', 'OF(D)', 'OFFICIER', 10, 0],
            ['LTM', 'Lt M', 'Leftenan Muda', 'Acting Sub-Lieutenant', 'OF-1', 'OFFICIER', 11, 1],
            ['LTMDY', 'Lt Mdy', 'Leftenan Madya', 'Sub-Lieutenant', 'OF-1', 'OFFICIER', 12, 1],
            ['LT', 'Lt', 'Leftenan', 'Lieutenant', 'OF-2', 'OFFICIER', 13, 1],
            ['LTKDR', 'Lt Kdr', 'Leftenan Komander', 'Lieutenant Commander', 'OF-3', 'OFFICIER', 14, 1],
            ['KDR', 'Kdr', 'Komander', 'Commander', 'OF-4', 'OFFICIER', 15, 1],
            ['KEPT', 'Kept', 'Kepten', 'Captain', 'OF-5', 'OFFICIER', 16, 1],
            ['LKSPTM', 'Laks Pertama', 'Laksamana Pertama', 'Commodore', 'OF-6', 'OFFICIER', 17, 1],
            ['LKSMUDA', 'Laks Muda', 'Laksamana Muda', 'Rear Admiral', 'OF-7', 'OFFICIER', 18, 1],
            ['LKSMADYA', 'Laks Madya', 'Laksamana Madya', 'Vice Admiral', 'OF-8', 'OFFICIER', 19, 1],
            ['LKS', 'Laks', 'Laksamana', 'Admiral', 'OF-9', 'OFFICIER', 20, 1],
            ['PW1', 'PW I', 'Pegawai Waran I', 'Warrant Officer I', 'OR-9', 'SOUS_OFFICIER', 21, 0],
            ['PW2', 'PW II', 'Pegawai Waran II', 'Warrant Officer II', 'OR-8', 'SOUS_OFFICIER', 22, 0],
            ['BK', 'BK', 'Bintara Kanan', 'Chief Petty Officer', 'OR-7', 'SOUS_OFFICIER', 23, 0],
            ['BM', 'BM', 'Bintara Muda', 'Petty Officer', 'OR-6', 'SOUS_OFFICIER', 24, 0],
            ['LKN', 'LK', 'Laskar Kanan', 'Leading Seaman', 'OR-4', 'MDR', 31, 0],
            ['LK1', 'LK I', 'Laskar Kelas I', 'Able Seaman', 'OR-3', 'MDR', 32, 0],
            ['PM', 'PM', 'Perajurit Muda', 'Ordinary Seaman', 'OR-1', 'MDR', 33, 0],
        ],
        'MY_TUDM' => [
            ['PK', 'PK', 'Pegawai Kadet', 'Officer Cadet', 'OF(D)', 'OFFICIER', 10, 0],
            ['LTM', 'Lt M', 'Leftenan Muda', 'Second Lieutenant', 'OF-1', 'OFFICIER', 11, 1],
            ['LT', 'Lt', 'Leftenan', 'Lieutenant', 'OF-1', 'OFFICIER', 12, 1],
            ['KAPT', 'Kapt', 'Kapten', 'Captain', 'OF-2', 'OFFICIER', 13, 1],
            ['MEJ', 'Mej', 'Mejar', 'Major', 'OF-3', 'OFFICIER', 14, 1],
            ['LTKOL', 'Lt Kol', 'Leftenan Kolonel', 'Lieutenant Colonel', 'OF-4', 'OFFICIER', 15, 1],
            ['KOL', 'Kol', 'Kolonel', 'Colonel', 'OF-5', 'OFFICIER', 16, 1],
            ['BRIGJEN', 'Brig Jen', 'Brigedier Jeneral', 'Brigadier General', 'OF-6', 'OFFICIER', 17, 1],
            ['MEJJEN', 'Mej Jen', 'Mejar Jeneral', 'Major General', 'OF-7', 'OFFICIER', 18, 1],
            ['LTJEN', 'Lt Jen', 'Leftenan Jeneral', 'Lieutenant General', 'OF-8', 'OFFICIER', 19, 1],
            ['JEN', 'Jen', 'Jeneral', 'General', 'OF-9', 'OFFICIER', 20, 1],
            ['PWU1', 'PWU I', 'Pegawai Waran Udara I', 'Warrant Officer I', 'OR-9', 'SOUS_OFFICIER', 21, 0],
            ['PWU2', 'PWU II', 'Pegawai Waran Udara II', 'Warrant Officer II', 'OR-8', 'SOUS_OFFICIER', 22, 0],
            ['FSJN', 'F/Sjn', 'Flait Sarjan', 'Flight Sergeant', 'OR-7', 'SOUS_OFFICIER', 23, 0],
            ['SJNU', 'Sjn U', 'Sarjan Udara', 'Sergeant', 'OR-6', 'SOUS_OFFICIER', 24, 0],
            ['KPLU', 'Kpl U', 'Koperal Udara', 'Corporal', 'OR-4', 'SOUS_OFFICIER', 25, 0],
            ['LUK', 'LUK', 'Laskar Udara Kanan', 'Leading Aircraftman', 'OR-3', 'MDR', 31, 0],
            ['LU', 'LU', 'Laskar Udara', 'Aircraftman', 'OR-1', 'MDR', 32, 0],
        ],
    ];

    public static function has(string $code): bool
    {
        return isset(self::SYSTEMS[strtoupper(trim($code))]);
    }

    /** Onglet du référentiel des grades (« fr », « us », « my »…). */
    public static function tab(string $code): string
    {
        return self::SYSTEMS[strtoupper(trim($code))]['tab'] ?? 'fr';
    }

    /** Système d’un onglet ; null si l’onglet n’est pas celui d’un système officiel. */
    public static function codeForTab(string $tab): ?string
    {
        foreach (self::SYSTEMS as $code => $s) {
            if ($s['tab'] === $tab) {
                return $code;
            }
        }

        return null;
    }

    /** @return list<string> */
    public static function tabs(): array
    {
        return array_values(array_map(static fn (array $s): string => $s['tab'], self::SYSTEMS));
    }

    public static function country(string $code): string
    {
        return self::SYSTEMS[strtoupper(trim($code))]['country'] ?? '';
    }

    /** Libellé du système en base (grade_systems.label). */
    public static function label(string $code): string
    {
        return self::SYSTEMS[strtoupper(trim($code))]['label'] ?? '';
    }

    public static function tabLabel(string $code): string
    {
        return self::SYSTEMS[strtoupper(trim($code))]['tab_label'] ?? '';
    }

    /** « grades français », « grades malaisiens de la marine (TLDM) »… (après « les »). */
    public static function name(string $code): string
    {
        return self::SYSTEMS[strtoupper(trim($code))]['name'] ?? 'grades';
    }

    /** Code du grade proposé par défaut au fondateur d’une communauté. */
    public static function founderGrade(string $code): string
    {
        return self::SYSTEMS[strtoupper(trim($code))]['founder'] ?? 'CNE';
    }

    /** @return array{title: string, text: string} carte de l’assistant de création */
    public static function card(string $code): array
    {
        $s = self::SYSTEMS[strtoupper(trim($code))] ?? null;

        return ['title' => (string) ($s['card'] ?? $code), 'text' => (string) ($s['card_text'] ?? '')];
    }

    /** Système d’un code de grade inconnu ou vide : français par défaut. */
    public static function normalize(string $code): string
    {
        $code = strtoupper(trim($code));

        return self::has($code) ? $code : 'FR_CLASSIC';
    }

    /** Équivalent anglais officiel d’un grade posé par migration (« Leftenan Kolonel » → « Lieutenant Colonel »). */
    public static function english(string $systemCode, string $gradeCode): string
    {
        foreach (self::GRADES[strtoupper(trim($systemCode))] ?? [] as $g) {
            if ($g[0] === strtoupper(trim($gradeCode))) {
                return $g[3];
            }
        }

        return '';
    }
}
