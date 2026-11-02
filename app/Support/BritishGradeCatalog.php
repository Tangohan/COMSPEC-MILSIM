<?php

declare(strict_types=1);

namespace App\Support;

/**
 * Grades britanniques du catalogue partagé, posés par l’étape database/steps/3610_grades_royaume_uni.php :
 * British Army et Royal Marines (GB_ARMY), Royal Navy (GB_RN), Royal Air Force (GB_RAF).
 *
 * Chaque grade : [système, catégorie, code, abréviation officielle, nom officiel, code OTAN (STANAG 2116), ordre, officier].
 * Ordre comme FR_CLASSIC / US_CLASSIC : officiers du plus bas au plus haut (10 → 21), sous-officiers du plus haut au plus
 * bas (21 → 29), militaires du rang du plus haut au plus bas (31 → …), civil 80, hors grade 90.
 *
 * Les appellations d’arme du soldat (Trooper, Gunner, Sapper, Signaller, Guardsman, Rifleman, Craftsman, Fusilier,
 * Kingsman, Ranger) et des Royal Marines (Marine, Colour Sergeant) ont le même code OTAN que leur équivalent : chaque
 * communauté masque celles qui ne la concernent pas. Lance Corporal et Corporal sont des sous-officiers (junior NCOs).
 * Officer Cadet, Midshipman et Pilot Officer en formation : « OF(D) », hors comparaison.
 *
 * Sources : Armed Forces Act 2006, Explanatory Notes, Annex B « Comparative ranks » (legislation.gov.uk) ;
 * STANAG 2116 (codes OTAN) ; pages « Ranks » de l’armée (army.mod.uk), de la Royal Navy (royalnavy.mod.uk) et de la RAF
 * (raf.mod.uk). Warrant Officer 2 de la Royal Navy : supprimé en 2014 puis rétabli (2021), à vérifier sur source officielle.
 */
final class BritishGradeCatalog
{
    public const SYSTEMS = [
        'GB_ARMY' => 'Grades britanniques (British Army et Royal Marines)',
        'GB_RN' => 'Grades britanniques (Royal Navy)',
        'GB_RAF' => 'Grades britanniques (Royal Air Force)',
    ];

    /** @return list<array{0: string, 1: string, 2: string, 3: string, 4: string, 5: string|null, 6: int, 7: int}> */
    public static function rows(): array
    {
        $o = 'OFFICIER';
        $s = 'SOUS_OFFICIER';
        $m = 'MDR';

        return [
            // --- British Army et Royal Marines ---
            ['GB_ARMY', $o, 'OCDT', 'OCdt', 'Officer Cadet', 'OF(D)', 10, 1],
            ['GB_ARMY', $o, '2LT', '2Lt', 'Second Lieutenant', 'OF-1', 11, 1],
            ['GB_ARMY', $o, 'LT', 'Lt', 'Lieutenant', 'OF-1', 12, 1],
            ['GB_ARMY', $o, 'CAPT', 'Capt', 'Captain', 'OF-2', 13, 1],
            ['GB_ARMY', $o, 'MAJ', 'Maj', 'Major', 'OF-3', 14, 1],
            ['GB_ARMY', $o, 'LTCOL', 'Lt Col', 'Lieutenant Colonel', 'OF-4', 15, 1],
            ['GB_ARMY', $o, 'COL', 'Col', 'Colonel', 'OF-5', 16, 1],
            ['GB_ARMY', $o, 'BRIG', 'Brig', 'Brigadier', 'OF-6', 17, 1],
            ['GB_ARMY', $o, 'MAJGEN', 'Maj Gen', 'Major General', 'OF-7', 18, 1],
            ['GB_ARMY', $o, 'LTGEN', 'Lt Gen', 'Lieutenant General', 'OF-8', 19, 1],
            ['GB_ARMY', $o, 'GEN', 'Gen', 'General', 'OF-9', 20, 1],
            ['GB_ARMY', $o, 'FM', 'FM', 'Field Marshal', 'OF-10', 21, 1],
            ['GB_ARMY', $s, 'WO1', 'WO1', 'Warrant Officer Class 1', 'OR-9', 21, 0],
            ['GB_ARMY', $s, 'WO2', 'WO2', 'Warrant Officer Class 2', 'OR-8', 22, 0],
            ['GB_ARMY', $s, 'SSGT', 'SSgt', 'Staff Sergeant', 'OR-7', 23, 0],
            ['GB_ARMY', $s, 'CSGT', 'CSgt', 'Colour Sergeant', 'OR-7', 24, 0],
            ['GB_ARMY', $s, 'SGT', 'Sgt', 'Sergeant', 'OR-6', 25, 0],
            ['GB_ARMY', $s, 'CPL', 'Cpl', 'Corporal', 'OR-4', 26, 0],
            ['GB_ARMY', $s, 'BDR', 'Bdr', 'Bombardier', 'OR-4', 27, 0],
            ['GB_ARMY', $s, 'LCPL', 'LCpl', 'Lance Corporal', 'OR-3', 28, 0],
            ['GB_ARMY', $s, 'LBDR', 'LBdr', 'Lance Bombardier', 'OR-3', 29, 0],
            ['GB_ARMY', $m, 'PTE', 'Pte', 'Private', 'OR-2', 31, 0],
            ['GB_ARMY', $m, 'MNE', 'Mne', 'Marine', 'OR-2', 32, 0],
            ['GB_ARMY', $m, 'TPR', 'Tpr', 'Trooper', 'OR-2', 33, 0],
            ['GB_ARMY', $m, 'GNR', 'Gnr', 'Gunner', 'OR-2', 34, 0],
            ['GB_ARMY', $m, 'SPR', 'Spr', 'Sapper', 'OR-2', 35, 0],
            ['GB_ARMY', $m, 'SIG', 'Sig', 'Signaller', 'OR-2', 36, 0],
            ['GB_ARMY', $m, 'GDSM', 'Gdsm', 'Guardsman', 'OR-2', 37, 0],
            ['GB_ARMY', $m, 'RFN', 'Rfn', 'Rifleman', 'OR-2', 38, 0],
            ['GB_ARMY', $m, 'CFN', 'Cfn', 'Craftsman', 'OR-2', 39, 0],
            ['GB_ARMY', $m, 'FUS', 'Fus', 'Fusilier', 'OR-2', 40, 0],
            ['GB_ARMY', $m, 'KGN', 'Kgn', 'Kingsman', 'OR-2', 41, 0],
            ['GB_ARMY', $m, 'RGR', 'Rgr', 'Ranger', 'OR-2', 42, 0],
            ['GB_ARMY', 'CIVIL', 'CIV', 'Civilian', 'Civilian (non-military)', null, 80, 0],
            ['GB_ARMY', 'HORS_GRADE', 'HG', 'No rank', 'No military rank', null, 90, 0],
            // --- Royal Navy ---
            ['GB_RN', $o, 'MID', 'Mid', 'Midshipman', 'OF(D)', 10, 1],
            ['GB_RN', $o, 'SLT', 'SLt', 'Sub-Lieutenant', 'OF-1', 11, 1],
            ['GB_RN', $o, 'LT', 'Lt', 'Lieutenant', 'OF-2', 12, 1],
            ['GB_RN', $o, 'LTCDR', 'Lt Cdr', 'Lieutenant Commander', 'OF-3', 13, 1],
            ['GB_RN', $o, 'CDR', 'Cdr', 'Commander', 'OF-4', 14, 1],
            ['GB_RN', $o, 'CAPT', 'Capt', 'Captain', 'OF-5', 15, 1],
            ['GB_RN', $o, 'CDRE', 'Cdre', 'Commodore', 'OF-6', 16, 1],
            ['GB_RN', $o, 'RADM', 'RAdm', 'Rear Admiral', 'OF-7', 17, 1],
            ['GB_RN', $o, 'VADM', 'VAdm', 'Vice Admiral', 'OF-8', 18, 1],
            ['GB_RN', $o, 'ADM', 'Adm', 'Admiral', 'OF-9', 19, 1],
            ['GB_RN', $o, 'AF', 'AF', 'Admiral of the Fleet', 'OF-10', 20, 1],
            ['GB_RN', $s, 'WO1', 'WO1', 'Warrant Officer 1', 'OR-9', 21, 0],
            ['GB_RN', $s, 'WO2', 'WO2', 'Warrant Officer 2', 'OR-8', 22, 0],
            ['GB_RN', $s, 'CPO', 'CPO', 'Chief Petty Officer', 'OR-7', 23, 0],
            ['GB_RN', $s, 'PO', 'PO', 'Petty Officer', 'OR-6', 24, 0],
            ['GB_RN', $s, 'LH', 'LH', 'Leading Rating', 'OR-4', 25, 0],
            ['GB_RN', $m, 'AB', 'AB', 'Able Rating', 'OR-2', 31, 0],
            ['GB_RN', 'CIVIL', 'CIV', 'Civilian', 'Civilian (non-military)', null, 80, 0],
            ['GB_RN', 'HORS_GRADE', 'HG', 'No rank', 'No military rank', null, 90, 0],
            // --- Royal Air Force ---
            ['GB_RAF', $o, 'OCDT', 'OCdt', 'Officer Cadet', 'OF(D)', 10, 1],
            ['GB_RAF', $o, 'PLTOFF', 'Plt Off', 'Pilot Officer', 'OF-1', 11, 1],
            ['GB_RAF', $o, 'FGOFF', 'Fg Off', 'Flying Officer', 'OF-1', 12, 1],
            ['GB_RAF', $o, 'FLTLT', 'Flt Lt', 'Flight Lieutenant', 'OF-2', 13, 1],
            ['GB_RAF', $o, 'SQNLDR', 'Sqn Ldr', 'Squadron Leader', 'OF-3', 14, 1],
            ['GB_RAF', $o, 'WGCDR', 'Wg Cdr', 'Wing Commander', 'OF-4', 15, 1],
            ['GB_RAF', $o, 'GPCAPT', 'Gp Capt', 'Group Captain', 'OF-5', 16, 1],
            ['GB_RAF', $o, 'AIRCDRE', 'Air Cdre', 'Air Commodore', 'OF-6', 17, 1],
            ['GB_RAF', $o, 'AVM', 'AVM', 'Air Vice-Marshal', 'OF-7', 18, 1],
            ['GB_RAF', $o, 'AIRMSHL', 'Air Mshl', 'Air Marshal', 'OF-8', 19, 1],
            ['GB_RAF', $o, 'ACM', 'ACM', 'Air Chief Marshal', 'OF-9', 20, 1],
            ['GB_RAF', $o, 'MRAF', 'MRAF', 'Marshal of the Royal Air Force', 'OF-10', 21, 1],
            ['GB_RAF', $s, 'WO', 'WO', 'Warrant Officer', 'OR-9', 21, 0],
            ['GB_RAF', $s, 'MACR', 'MAcr', 'Master Aircrew', 'OR-9', 22, 0],
            ['GB_RAF', $s, 'FS', 'FS', 'Flight Sergeant', 'OR-7', 23, 0],
            ['GB_RAF', $s, 'CHFTECH', 'Chf Tech', 'Chief Technician', 'OR-7', 24, 0],
            ['GB_RAF', $s, 'SGT', 'Sgt', 'Sergeant', 'OR-6', 25, 0],
            ['GB_RAF', $s, 'CPL', 'Cpl', 'Corporal', 'OR-4', 26, 0],
            ['GB_RAF', $s, 'LCPL', 'LCpl', 'Lance Corporal (RAF Regiment)', 'OR-3', 27, 0],
            ['GB_RAF', $m, 'SAC', 'SAC', 'Senior Aircraftman', 'OR-2', 31, 0],
            ['GB_RAF', $m, 'LAC', 'LAC', 'Leading Aircraftman', 'OR-2', 32, 0],
            ['GB_RAF', $m, 'AC', 'AC', 'Aircraftman', 'OR-1', 33, 0],
            ['GB_RAF', 'CIVIL', 'CIV', 'Civilian', 'Civilian (non-military)', null, 80, 0],
            ['GB_RAF', 'HORS_GRADE', 'HG', 'No rank', 'No military rank', null, 90, 0],
        ];
    }
}
