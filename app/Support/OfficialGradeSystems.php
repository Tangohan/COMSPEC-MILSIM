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
 * Grades britanniques (British Army et Royal Marines, Royal Navy, Royal Air Force) : étape 3620, intitulés officiels anglais,
 * codes OTAN de l’Armed Forces Act 2006 (Explanatory Notes, Annex B, legislation.gov.uk) et du STANAG 2116 ; les appellations
 * d’arme du soldat (Trooper, Gunner, Sapper…) ont le code OTAN de Private : chaque communauté masque celles qu’elle n’emploie pas.
 * Ajouter un système = une ligne dans SYSTEMS (+ ses grades et une étape de migration idempotente).
 */
final class OfficialGradeSystems
{
    /** Codes dans l’ordre des onglets. */
    public const CODES = ['FR_CLASSIC', 'US_CLASSIC', 'GB_ARMY', 'GB_RN', 'GB_RAF', 'MY_CLASSIC', 'MY_TLDM', 'MY_TUDM'];

    /**
     * code => onglet, pays, libellé du système en base, titre de l’onglet, nom au pluriel (« les grades … »),
     * grade proposé au fondateur, titre et texte de la carte de l’assistant de création.
     */
    private const SYSTEMS = [
        'FR_CLASSIC' => ['tab' => 'fr', 'country' => 'FR', 'label' => 'Grades français (classique)', 'tab_label' => 'Grades français', 'name' => 'grades français', 'founder' => 'CNE', 'card' => 'Français', 'card_text' => 'Grades classiques de l’armée de terre française.'],
        'US_CLASSIC' => ['tab' => 'us', 'country' => 'US', 'label' => 'Grades américains (classique)', 'tab_label' => 'Grades américains', 'name' => 'grades américains', 'founder' => 'CPT', 'card' => 'United States', 'card_text' => 'Grades classiques des forces armées américaines.'],
        'GB_ARMY' => ['tab' => 'gb', 'country' => 'GB', 'label' => 'Grades britanniques (British Army et Royal Marines)', 'tab_label' => 'Royaume-Uni : British Army et Royal Marines', 'name' => 'grades britanniques (British Army et Royal Marines)', 'founder' => 'CAPT', 'card' => 'United Kingdom · British Army', 'card_text' => 'Modèle britannique : grades de la British Army et des Royal Marines (Private, Trooper, Lance Corporal, Sergeant, Captain…).'],
        'GB_RN' => ['tab' => 'gb_rn', 'country' => 'GB', 'label' => 'Grades britanniques (Royal Navy)', 'tab_label' => 'Royaume-Uni : Royal Navy', 'name' => 'grades de la Royal Navy', 'founder' => 'LT', 'card' => 'United Kingdom · Royal Navy', 'card_text' => 'Modèle britannique : grades de la Royal Navy (Able Rating, Petty Officer, Lieutenant Commander…).'],
        'GB_RAF' => ['tab' => 'gb_raf', 'country' => 'GB', 'label' => 'Grades britanniques (Royal Air Force)', 'tab_label' => 'Royaume-Uni : Royal Air Force', 'name' => 'grades de la Royal Air Force', 'founder' => 'FLTLT', 'card' => 'United Kingdom · Royal Air Force', 'card_text' => 'Modèle britannique : grades de la Royal Air Force (Aircraftman, Corporal, Flight Lieutenant…).'],
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
        'GB_ARMY' => [
            ['OCDT', 'OCdt', 'Officer Cadet', 'Officer Cadet', 'OF(D)', 'OFFICIER', 10, 1],
            ['2LT', '2Lt', 'Second Lieutenant', 'Second Lieutenant', 'OF-1', 'OFFICIER', 11, 1],
            ['LT', 'Lt', 'Lieutenant', 'Lieutenant', 'OF-1', 'OFFICIER', 12, 1],
            ['CAPT', 'Capt', 'Captain', 'Captain', 'OF-2', 'OFFICIER', 13, 1],
            ['MAJ', 'Maj', 'Major', 'Major', 'OF-3', 'OFFICIER', 14, 1],
            ['LTCOL', 'Lt Col', 'Lieutenant Colonel', 'Lieutenant Colonel', 'OF-4', 'OFFICIER', 15, 1],
            ['COL', 'Col', 'Colonel', 'Colonel', 'OF-5', 'OFFICIER', 16, 1],
            ['BRIG', 'Brig', 'Brigadier', 'Brigadier', 'OF-6', 'OFFICIER', 17, 1],
            ['MAJGEN', 'Maj Gen', 'Major General', 'Major General', 'OF-7', 'OFFICIER', 18, 1],
            ['LTGEN', 'Lt Gen', 'Lieutenant General', 'Lieutenant General', 'OF-8', 'OFFICIER', 19, 1],
            ['GEN', 'Gen', 'General', 'General', 'OF-9', 'OFFICIER', 20, 1],
            ['FM', 'FM', 'Field Marshal', 'Field Marshal', 'OF-10', 'OFFICIER', 21, 1],
            ['WO1', 'WO1', 'Warrant Officer Class 1', 'Warrant Officer Class 1', 'OR-9', 'SOUS_OFFICIER', 21, 0],
            ['WO2', 'WO2', 'Warrant Officer Class 2', 'Warrant Officer Class 2', 'OR-8', 'SOUS_OFFICIER', 22, 0],
            ['SSGT', 'SSgt', 'Staff Sergeant', 'Staff Sergeant', 'OR-7', 'SOUS_OFFICIER', 23, 0],
            ['CSGT', 'CSgt', 'Colour Sergeant', 'Colour Sergeant', 'OR-7', 'SOUS_OFFICIER', 24, 0],
            ['SGT', 'Sgt', 'Sergeant', 'Sergeant', 'OR-6', 'SOUS_OFFICIER', 25, 0],
            ['CPL', 'Cpl', 'Corporal', 'Corporal', 'OR-4', 'SOUS_OFFICIER', 26, 0],
            ['BDR', 'Bdr', 'Bombardier', 'Bombardier', 'OR-4', 'SOUS_OFFICIER', 27, 0],
            ['LCPL', 'LCpl', 'Lance Corporal', 'Lance Corporal', 'OR-3', 'SOUS_OFFICIER', 28, 0],
            ['LBDR', 'LBdr', 'Lance Bombardier', 'Lance Bombardier', 'OR-3', 'SOUS_OFFICIER', 29, 0],
            ['PTE', 'Pte', 'Private', 'Private', 'OR-2', 'MDR', 31, 0],
            ['MNE', 'Mne', 'Marine', 'Marine', 'OR-2', 'MDR', 32, 0],
            ['TPR', 'Tpr', 'Trooper', 'Trooper', 'OR-2', 'MDR', 33, 0],
            ['GNR', 'Gnr', 'Gunner', 'Gunner', 'OR-2', 'MDR', 34, 0],
            ['SPR', 'Spr', 'Sapper', 'Sapper', 'OR-2', 'MDR', 35, 0],
            ['SIG', 'Sig', 'Signaller', 'Signaller', 'OR-2', 'MDR', 36, 0],
            ['GDSM', 'Gdsm', 'Guardsman', 'Guardsman', 'OR-2', 'MDR', 37, 0],
            ['RFN', 'Rfn', 'Rifleman', 'Rifleman', 'OR-2', 'MDR', 38, 0],
            ['CFN', 'Cfn', 'Craftsman', 'Craftsman', 'OR-2', 'MDR', 39, 0],
            ['FUS', 'Fus', 'Fusilier', 'Fusilier', 'OR-2', 'MDR', 40, 0],
            ['KGN', 'Kgn', 'Kingsman', 'Kingsman', 'OR-2', 'MDR', 41, 0],
            ['RGR', 'Rgr', 'Ranger', 'Ranger', 'OR-2', 'MDR', 42, 0],
        ],
        'GB_RN' => [
            ['MID', 'Mid', 'Midshipman', 'Midshipman', 'OF(D)', 'OFFICIER', 10, 1],
            ['SLT', 'SLt', 'Sub-Lieutenant', 'Sub-Lieutenant', 'OF-1', 'OFFICIER', 11, 1],
            ['LT', 'Lt', 'Lieutenant', 'Lieutenant', 'OF-2', 'OFFICIER', 12, 1],
            ['LTCDR', 'Lt Cdr', 'Lieutenant Commander', 'Lieutenant Commander', 'OF-3', 'OFFICIER', 13, 1],
            ['CDR', 'Cdr', 'Commander', 'Commander', 'OF-4', 'OFFICIER', 14, 1],
            ['CAPT', 'Capt', 'Captain', 'Captain', 'OF-5', 'OFFICIER', 15, 1],
            ['CDRE', 'Cdre', 'Commodore', 'Commodore', 'OF-6', 'OFFICIER', 16, 1],
            ['RADM', 'RAdm', 'Rear Admiral', 'Rear Admiral', 'OF-7', 'OFFICIER', 17, 1],
            ['VADM', 'VAdm', 'Vice Admiral', 'Vice Admiral', 'OF-8', 'OFFICIER', 18, 1],
            ['ADM', 'Adm', 'Admiral', 'Admiral', 'OF-9', 'OFFICIER', 19, 1],
            ['AF', 'AF', 'Admiral of the Fleet', 'Admiral of the Fleet', 'OF-10', 'OFFICIER', 20, 1],
            ['WO1', 'WO1', 'Warrant Officer 1', 'Warrant Officer 1', 'OR-9', 'SOUS_OFFICIER', 21, 0],
            ['WO2', 'WO2', 'Warrant Officer 2', 'Warrant Officer 2', 'OR-8', 'SOUS_OFFICIER', 22, 0],
            ['CPO', 'CPO', 'Chief Petty Officer', 'Chief Petty Officer', 'OR-7', 'SOUS_OFFICIER', 23, 0],
            ['PO', 'PO', 'Petty Officer', 'Petty Officer', 'OR-6', 'SOUS_OFFICIER', 24, 0],
            ['LH', 'LH', 'Leading Rating', 'Leading Rating', 'OR-4', 'SOUS_OFFICIER', 25, 0],
            ['AB', 'AB', 'Able Rating', 'Able Rating', 'OR-2', 'MDR', 31, 0],
        ],
        'GB_RAF' => [
            ['OCDT', 'OCdt', 'Officer Cadet', 'Officer Cadet', 'OF(D)', 'OFFICIER', 10, 1],
            ['PLTOFF', 'Plt Off', 'Pilot Officer', 'Pilot Officer', 'OF-1', 'OFFICIER', 11, 1],
            ['FGOFF', 'Fg Off', 'Flying Officer', 'Flying Officer', 'OF-1', 'OFFICIER', 12, 1],
            ['FLTLT', 'Flt Lt', 'Flight Lieutenant', 'Flight Lieutenant', 'OF-2', 'OFFICIER', 13, 1],
            ['SQNLDR', 'Sqn Ldr', 'Squadron Leader', 'Squadron Leader', 'OF-3', 'OFFICIER', 14, 1],
            ['WGCDR', 'Wg Cdr', 'Wing Commander', 'Wing Commander', 'OF-4', 'OFFICIER', 15, 1],
            ['GPCAPT', 'Gp Capt', 'Group Captain', 'Group Captain', 'OF-5', 'OFFICIER', 16, 1],
            ['AIRCDRE', 'Air Cdre', 'Air Commodore', 'Air Commodore', 'OF-6', 'OFFICIER', 17, 1],
            ['AVM', 'AVM', 'Air Vice-Marshal', 'Air Vice-Marshal', 'OF-7', 'OFFICIER', 18, 1],
            ['AIRMSHL', 'Air Mshl', 'Air Marshal', 'Air Marshal', 'OF-8', 'OFFICIER', 19, 1],
            ['ACM', 'ACM', 'Air Chief Marshal', 'Air Chief Marshal', 'OF-9', 'OFFICIER', 20, 1],
            ['MRAF', 'MRAF', 'Marshal of the Royal Air Force', 'Marshal of the Royal Air Force', 'OF-10', 'OFFICIER', 21, 1],
            ['WO', 'WO', 'Warrant Officer', 'Warrant Officer', 'OR-9', 'SOUS_OFFICIER', 21, 0],
            ['MACR', 'MAcr', 'Master Aircrew', 'Master Aircrew', 'OR-9', 'SOUS_OFFICIER', 22, 0],
            ['FS', 'FS', 'Flight Sergeant', 'Flight Sergeant', 'OR-7', 'SOUS_OFFICIER', 23, 0],
            ['CHFTECH', 'Chf Tech', 'Chief Technician', 'Chief Technician', 'OR-7', 'SOUS_OFFICIER', 24, 0],
            ['SGT', 'Sgt', 'Sergeant', 'Sergeant', 'OR-6', 'SOUS_OFFICIER', 25, 0],
            ['CPL', 'Cpl', 'Corporal', 'Corporal', 'OR-4', 'SOUS_OFFICIER', 26, 0],
            ['LCPL', 'LCpl', 'Lance Corporal (RAF Regiment)', 'Lance Corporal (RAF Regiment)', 'OR-3', 'SOUS_OFFICIER', 27, 0],
            ['SAC', 'SAC', 'Senior Aircraftman', 'Senior Aircraftman', 'OR-2', 'MDR', 31, 0],
            ['LAC', 'LAC', 'Leading Aircraftman', 'Leading Aircraftman', 'OR-2', 'MDR', 32, 0],
            ['AC', 'AC', 'Aircraftman', 'Aircraftman', 'OR-1', 'MDR', 33, 0],
        ],
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
                return $g[3] !== $g[2] ? $g[3] : '';
            }
        }

        return '';
    }
}
