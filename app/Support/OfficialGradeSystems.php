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
 * Grades allemands (Bundeswehr : Heer, Marine, Luftwaffe), russes (armée de terre, marine, ВКС), chinois (Armée populaire de
 * libération : 陆军, 海军, 空军) et échelle OTAN générique : étape 3660, intitulés dans la langue d’origine (allemand, russe en
 * cyrillique, chinois en sinogrammes) ; le référentiel affiche à côté la translittération (russe, pinyin) et l’équivalent anglais.
 * Ajouter un système = une ligne dans SYSTEMS (+ ses grades et une étape de migration idempotente).
 */
final class OfficialGradeSystems
{
    /** Codes dans l’ordre des onglets. */
    public const CODES = [
        'FR_CLASSIC', 'US_CLASSIC', 'GB_ARMY', 'GB_RN', 'GB_RAF', 'MY_CLASSIC', 'MY_TLDM', 'MY_TUDM',
        'DE_HEER', 'DE_MARINE', 'DE_LW',
        'RU_SV', 'RU_VMF', 'RU_VKS',
        'CN_PLA', 'CN_PLAN', 'CN_PLAAF',
        'NATO_GENERIC',
    ];

    /**
     * code => onglet, pays, libellé du système en base, titre de l’onglet, nom au pluriel (« les grades … »),
     * grade proposé au fondateur, titre et texte de la carte de l’assistant de création, langue des intitulés
     * (attribut lang), référentiel d’adéquation par échelon (EchelonStandards, vide = selon la doctrine), famille
     * (« Grades allemands ») et armée (army, navy, air, joint : catalogue des systèmes).
     */
    private const SYSTEMS = [
        'FR_CLASSIC' => ['tab' => 'fr', 'country' => 'FR', 'label' => 'Grades français (classique)', 'tab_label' => 'Grades français', 'name' => 'grades français', 'founder' => 'CNE', 'card' => 'Français', 'card_text' => 'Grades classiques de l’armée de terre française.', 'lang' => '', 'profile' => '', 'family' => 'Grades français', 'branch' => 'army'],
        'US_CLASSIC' => ['tab' => 'us', 'country' => 'US', 'label' => 'Grades américains (classique)', 'tab_label' => 'Grades américains', 'name' => 'grades américains', 'founder' => 'CPT', 'card' => 'United States', 'card_text' => 'Grades classiques des forces armées américaines.', 'lang' => '', 'profile' => '', 'family' => 'Grades américains', 'branch' => 'joint'],
        'GB_ARMY' => ['tab' => 'gb', 'country' => 'GB', 'label' => 'Grades britanniques (British Army et Royal Marines)', 'tab_label' => 'Royaume-Uni : British Army et Royal Marines', 'name' => 'grades britanniques (British Army et Royal Marines)', 'founder' => 'CAPT', 'card' => 'United Kingdom · British Army', 'card_text' => 'Modèle britannique : grades de la British Army et des Royal Marines (Private, Trooper, Lance Corporal, Sergeant, Captain…).', 'lang' => 'en', 'profile' => 'gb', 'family' => 'Grades britanniques', 'branch' => 'army'],
        'GB_RN' => ['tab' => 'gb_rn', 'country' => 'GB', 'label' => 'Grades britanniques (Royal Navy)', 'tab_label' => 'Royaume-Uni : Royal Navy', 'name' => 'grades de la Royal Navy', 'founder' => 'LT', 'card' => 'United Kingdom · Royal Navy', 'card_text' => 'Modèle britannique : grades de la Royal Navy (Able Rating, Petty Officer, Lieutenant Commander…).', 'lang' => 'en', 'profile' => 'gb', 'family' => 'Grades britanniques', 'branch' => 'navy'],
        'GB_RAF' => ['tab' => 'gb_raf', 'country' => 'GB', 'label' => 'Grades britanniques (Royal Air Force)', 'tab_label' => 'Royaume-Uni : Royal Air Force', 'name' => 'grades de la Royal Air Force', 'founder' => 'FLTLT', 'card' => 'United Kingdom · Royal Air Force', 'card_text' => 'Modèle britannique : grades de la Royal Air Force (Aircraftman, Corporal, Flight Lieutenant…).', 'lang' => 'en', 'profile' => 'gb', 'family' => 'Grades britanniques', 'branch' => 'air'],
        'MY_CLASSIC' => ['tab' => 'my', 'country' => 'MY', 'label' => 'Pangkat Tentera Darat Malaysia', 'tab_label' => 'Malaisie : armée de terre', 'name' => 'grades malaisiens de l’armée de terre (Tentera Darat)', 'founder' => 'KAPT', 'card' => 'Malaysia · Tentera Darat', 'card_text' => 'Grades de l’armée de terre malaisienne (système d’origine britannique), en malais.', 'lang' => 'ms', 'profile' => 'my', 'family' => 'Grades malaisiens', 'branch' => 'army'],
        'MY_TLDM' => ['tab' => 'my_tldm', 'country' => 'MY', 'label' => 'Pangkat Tentera Laut Diraja Malaysia (TLDM)', 'tab_label' => 'Malaisie : marine', 'name' => 'grades malaisiens de la marine (TLDM)', 'founder' => 'LT', 'card' => 'Malaysia · TLDM', 'card_text' => 'Grades de la marine royale malaisienne, en malais.', 'lang' => 'ms', 'profile' => 'my', 'family' => 'Grades malaisiens', 'branch' => 'navy'],
        'MY_TUDM' => ['tab' => 'my_tudm', 'country' => 'MY', 'label' => 'Pangkat Tentera Udara Diraja Malaysia (TUDM)', 'tab_label' => 'Malaisie : armée de l’air', 'name' => 'grades malaisiens de l’armée de l’air (TUDM)', 'founder' => 'KAPT', 'card' => 'Malaysia · TUDM', 'card_text' => 'Grades de l’armée de l’air royale malaisienne, en malais.', 'lang' => 'ms', 'profile' => 'my', 'family' => 'Grades malaisiens', 'branch' => 'air'],
        'DE_HEER' => ['tab' => 'de', 'country' => 'DE', 'label' => 'Dienstgrade des Heeres (Bundeswehr)', 'tab_label' => 'Allemagne : Heer', 'name' => 'grades allemands de l’armée de terre (Heer)', 'founder' => 'HPTM', 'card' => 'Deutschland · Heer', 'card_text' => 'Grades de l’armée de terre allemande (Bundeswehr), en allemand.', 'lang' => 'de', 'profile' => 'de', 'family' => 'Grades allemands', 'branch' => 'army'],
        'DE_MARINE' => ['tab' => 'de_marine', 'country' => 'DE', 'label' => 'Dienstgrade der Marine (Bundeswehr)', 'tab_label' => 'Allemagne : Marine', 'name' => 'grades allemands de la marine (Deutsche Marine)', 'founder' => 'KPTLT', 'card' => 'Deutschland · Marine', 'card_text' => 'Grades de la marine allemande (Bundeswehr), en allemand.', 'lang' => 'de', 'profile' => 'de', 'family' => 'Grades allemands', 'branch' => 'navy'],
        'DE_LW' => ['tab' => 'de_lw', 'country' => 'DE', 'label' => 'Dienstgrade der Luftwaffe (Bundeswehr)', 'tab_label' => 'Allemagne : Luftwaffe', 'name' => 'grades allemands de l’armée de l’air (Luftwaffe)', 'founder' => 'HPTM', 'card' => 'Deutschland · Luftwaffe', 'card_text' => 'Grades de l’armée de l’air allemande (Bundeswehr), en allemand.', 'lang' => 'de', 'profile' => 'de', 'family' => 'Grades allemands', 'branch' => 'air'],
        'RU_SV' => ['tab' => 'ru', 'country' => 'RU', 'label' => 'Воинские звания: Сухопутные войска', 'tab_label' => 'Russie : armée de terre', 'name' => 'grades russes de l’armée de terre (Сухопутные войска)', 'founder' => 'KPT', 'card' => 'Россия · Сухопутные войска', 'card_text' => 'Grades de l’armée de terre russe, en russe (cyrillique et translittération).', 'lang' => 'ru', 'profile' => 'ru', 'family' => 'Grades russes', 'branch' => 'army'],
        'RU_VMF' => ['tab' => 'ru_vmf', 'country' => 'RU', 'label' => 'Корабельные воинские звания: ВМФ', 'tab_label' => 'Russie : marine', 'name' => 'grades russes de la marine (ВМФ)', 'founder' => 'KPTLT', 'card' => 'Россия · ВМФ', 'card_text' => 'Grades de la marine russe (grades « de bord »), en russe.', 'lang' => 'ru', 'profile' => 'ru', 'family' => 'Grades russes', 'branch' => 'navy'],
        'RU_VKS' => ['tab' => 'ru_vks', 'country' => 'RU', 'label' => 'Воинские звания: ВКС', 'tab_label' => 'Russie : forces aérospatiales', 'name' => 'grades russes des forces aérospatiales (ВКС)', 'founder' => 'KPT', 'card' => 'Россия · ВКС', 'card_text' => 'Grades des forces aérospatiales russes, en russe.', 'lang' => 'ru', 'profile' => 'ru', 'family' => 'Grades russes', 'branch' => 'air'],
        'CN_PLA' => ['tab' => 'cn', 'country' => 'CN', 'label' => '中国人民解放军陆军军衔', 'tab_label' => 'Chine : armée de terre', 'name' => 'grades chinois de l’armée de terre (陆军)', 'founder' => 'SHANGWEI', 'card' => '中国 · 陆军', 'card_text' => 'Grades de l’armée de terre de l’Armée populaire de libération, en chinois (sinogrammes et pinyin).', 'lang' => 'zh', 'profile' => 'cn', 'family' => 'Grades chinois', 'branch' => 'army'],
        'CN_PLAN' => ['tab' => 'cn_plan', 'country' => 'CN', 'label' => '中国人民解放军海军军衔', 'tab_label' => 'Chine : marine', 'name' => 'grades chinois de la marine (海军)', 'founder' => 'SHANGWEI', 'card' => '中国 · 海军', 'card_text' => 'Grades de la marine de l’Armée populaire de libération, en chinois.', 'lang' => 'zh', 'profile' => 'cn', 'family' => 'Grades chinois', 'branch' => 'navy'],
        'CN_PLAAF' => ['tab' => 'cn_plaaf', 'country' => 'CN', 'label' => '中国人民解放军空军军衔', 'tab_label' => 'Chine : armée de l’air', 'name' => 'grades chinois de l’armée de l’air (空军)', 'founder' => 'SHANGWEI', 'card' => '中国 · 空军', 'card_text' => 'Grades de l’armée de l’air de l’Armée populaire de libération, en chinois.', 'lang' => 'zh', 'profile' => 'cn', 'family' => 'Grades chinois', 'branch' => 'air'],
        'NATO_GENERIC' => ['tab' => 'otan', 'country' => 'NATO', 'label' => 'Grades OTAN génériques (STANAG 2116)', 'tab_label' => 'Grades OTAN génériques', 'name' => 'grades OTAN génériques', 'founder' => 'OF2', 'card' => 'NATO · OTAN', 'card_text' => 'Échelle générique OR-1 à OR-9, WO et OF-1 à OF-10 pour les communautés multinationales.', 'lang' => '', 'profile' => 'nato', 'family' => 'Grades OTAN génériques', 'branch' => 'joint'],
    ];

    /**
     * Grades posés par migration (systèmes sans étape historique) : [code, abrégé, intitulé officiel, équivalent anglais,
     * code OTAN, catégorie (grade_categories.code), ordre, officier commissionné, translittération (russe, pinyin ;
     * facultative)]. Ordre : officiers du plus bas au plus
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
        // Bundeswehr (Heer, Luftwaffe, Marine). Sources : ZDv A-1420/24 « Dienstgradbezeichnungen » et tableaux de
        // Wikipédia (de « Dienstgrade der Bundeswehr », en « Ranks of the German Bundeswehr ») : intitulés, abrégés
        // officiels et codes OTAN STANAG 2116 ; Korporal et Stabskorporal créés en 2021. Offizieranwärter (Fahnenjunker,
        // Fähnrich, Oberfähnrich, Seekadett) : groupe des sous-officiers, comme dans la loi (Soldatenlaufbahnverordnung).
        // Équivalents anglais : traductions usuelles (pas d’intitulé anglais officiel) ; grades sanitaires non repris.
        'DE_HEER' => [
            ['LT', 'Lt', 'Leutnant', 'Second Lieutenant', 'OF-1', 'OFFICIER', 10, 1],
            ['OLT', 'OLt', 'Oberleutnant', 'First Lieutenant', 'OF-1', 'OFFICIER', 11, 1],
            ['HPTM', 'Hptm', 'Hauptmann', 'Captain', 'OF-2', 'OFFICIER', 12, 1],
            ['STHPTM', 'StHptm', 'Stabshauptmann', 'Staff Captain', 'OF-2', 'OFFICIER', 13, 1],
            ['MAJ', 'Maj', 'Major', 'Major', 'OF-3', 'OFFICIER', 14, 1],
            ['OTL', 'OTL', 'Oberstleutnant', 'Lieutenant Colonel', 'OF-4', 'OFFICIER', 15, 1],
            ['O', 'O', 'Oberst', 'Colonel', 'OF-5', 'OFFICIER', 16, 1],
            ['BRIGGEN', 'BrigGen', 'Brigadegeneral', 'Brigadier General', 'OF-6', 'OFFICIER', 17, 1],
            ['GENMAJ', 'GenMaj', 'Generalmajor', 'Major General', 'OF-7', 'OFFICIER', 18, 1],
            ['GENLT', 'GenLt', 'Generalleutnant', 'Lieutenant General', 'OF-8', 'OFFICIER', 19, 1],
            ['GEN', 'Gen', 'General', 'General', 'OF-9', 'OFFICIER', 20, 1],
            ['OSTFW', 'OStFw', 'Oberstabsfeldwebel', 'Sergeant Major', 'OR-9', 'SOUS_OFFICIER', 21, 0],
            ['STFW', 'StFw', 'Stabsfeldwebel', 'Master Sergeant', 'OR-8', 'SOUS_OFFICIER', 22, 0],
            ['HPTFW', 'HptFw', 'Hauptfeldwebel', 'Sergeant First Class', 'OR-7', 'SOUS_OFFICIER', 23, 0],
            ['OFAEHNR', 'OFähnr', 'Oberfähnrich', 'Senior Officer Cadet', 'OR-7', 'SOUS_OFFICIER', 24, 0],
            ['OFW', 'OFw', 'Oberfeldwebel', 'Technical Sergeant', 'OR-6', 'SOUS_OFFICIER', 25, 0],
            ['FW', 'Fw', 'Feldwebel', 'Staff Sergeant', 'OR-6', 'SOUS_OFFICIER', 26, 0],
            ['FAEHNR', 'Fähnr', 'Fähnrich', 'Officer Cadet (Ensign)', 'OR-6', 'SOUS_OFFICIER', 27, 0],
            ['STUFFZ', 'StUffz', 'Stabsunteroffizier', 'Senior Sergeant', 'OR-5', 'SOUS_OFFICIER', 28, 0],
            ['UFFZ', 'Uffz', 'Unteroffizier', 'Sergeant', 'OR-5', 'SOUS_OFFICIER', 29, 0],
            ['FHJ', 'Fhj', 'Fahnenjunker', 'Officer Candidate', 'OR-5', 'SOUS_OFFICIER', 30, 0],
            ['STKORP', 'StKorp', 'Stabskorporal', 'Staff Corporal', 'OR-4', 'MDR', 31, 0],
            ['KORP', 'Korp', 'Korporal', 'Corporal', 'OR-4', 'MDR', 32, 0],
            ['OSTGEFR', 'OStGefr', 'Oberstabsgefreiter', 'Senior Specialist', 'OR-4', 'MDR', 33, 0],
            ['STGEFR', 'StGefr', 'Stabsgefreiter', 'Specialist', 'OR-4', 'MDR', 34, 0],
            ['HPTGEFR', 'HptGefr', 'Hauptgefreiter', 'Senior Private First Class', 'OR-3', 'MDR', 35, 0],
            ['OGEFR', 'OGefr', 'Obergefreiter', 'Private First Class', 'OR-3', 'MDR', 36, 0],
            ['GEFR', 'Gefr', 'Gefreiter', 'Private (E-2)', 'OR-2', 'MDR', 37, 0],
            ['S', 'S', 'Soldat', 'Private', 'OR-1', 'MDR', 38, 0],
        ],
        'DE_MARINE' => [
            ['LZS', 'LzS', 'Leutnant zur See', 'Ensign', 'OF-1', 'OFFICIER', 10, 1],
            ['OLZS', 'OLzS', 'Oberleutnant zur See', 'Lieutenant (junior grade)', 'OF-1', 'OFFICIER', 11, 1],
            ['KPTLT', 'KptLt', 'Kapitänleutnant', 'Lieutenant', 'OF-2', 'OFFICIER', 12, 1],
            ['STKPTLT', 'StKptLt', 'Stabskapitänleutnant', 'Staff Lieutenant', 'OF-2', 'OFFICIER', 13, 1],
            ['KKPT', 'KKpt', 'Korvettenkapitän', 'Lieutenant Commander', 'OF-3', 'OFFICIER', 14, 1],
            ['FKPT', 'FKpt', 'Fregattenkapitän', 'Commander', 'OF-4', 'OFFICIER', 15, 1],
            ['KPTZS', 'KptzS', 'Kapitän zur See', 'Captain', 'OF-5', 'OFFICIER', 16, 1],
            ['FLTLADM', 'FltlAdm', 'Flottillenadmiral', 'Commodore (Flotilla Admiral)', 'OF-6', 'OFFICIER', 17, 1],
            ['KADM', 'KAdm', 'Konteradmiral', 'Rear Admiral', 'OF-7', 'OFFICIER', 18, 1],
            ['VADM', 'VAdm', 'Vizeadmiral', 'Vice Admiral', 'OF-8', 'OFFICIER', 19, 1],
            ['ADM', 'Adm', 'Admiral', 'Admiral', 'OF-9', 'OFFICIER', 20, 1],
            ['OSTBTSM', 'OStBtsm', 'Oberstabsbootsmann', 'Master Chief Petty Officer', 'OR-9', 'SOUS_OFFICIER', 21, 0],
            ['STBTSM', 'StBtsm', 'Stabsbootsmann', 'Senior Chief Petty Officer', 'OR-8', 'SOUS_OFFICIER', 22, 0],
            ['HPTBTSM', 'HptBtsm', 'Hauptbootsmann', 'Chief Petty Officer', 'OR-7', 'SOUS_OFFICIER', 23, 0],
            ['OFRZS', 'OFähnrzS', 'Oberfähnrich zur See', 'Senior Midshipman', 'OR-7', 'SOUS_OFFICIER', 24, 0],
            ['OBTSM', 'OBtsm', 'Oberbootsmann', 'Petty Officer First Class', 'OR-6', 'SOUS_OFFICIER', 25, 0],
            ['BTSM', 'Btsm', 'Bootsmann', 'Petty Officer Second Class', 'OR-6', 'SOUS_OFFICIER', 26, 0],
            ['FRZS', 'FähnrzS', 'Fähnrich zur See', 'Midshipman', 'OR-6', 'SOUS_OFFICIER', 27, 0],
            ['OMT', 'OMt', 'Obermaat', 'Petty Officer Third Class', 'OR-5', 'SOUS_OFFICIER', 28, 0],
            ['MT', 'Mt', 'Maat', 'Leading Petty Officer', 'OR-5', 'SOUS_OFFICIER', 29, 0],
            ['SKAD', 'SKad', 'Seekadett', 'Naval Officer Candidate', 'OR-5', 'SOUS_OFFICIER', 30, 0],
            ['STKORP', 'StKorp', 'Stabskorporal', 'Staff Corporal', 'OR-4', 'MDR', 31, 0],
            ['KORP', 'Korp', 'Korporal', 'Corporal', 'OR-4', 'MDR', 32, 0],
            ['OSTGEFR', 'OStGefr', 'Oberstabsgefreiter', 'Senior Leading Seaman', 'OR-4', 'MDR', 33, 0],
            ['STGEFR', 'StGefr', 'Stabsgefreiter', 'Leading Seaman', 'OR-4', 'MDR', 34, 0],
            ['HPTGEFR', 'HptGefr', 'Hauptgefreiter', 'Senior Able Seaman', 'OR-3', 'MDR', 35, 0],
            ['OGEFR', 'OGefr', 'Obergefreiter', 'Able Seaman', 'OR-3', 'MDR', 36, 0],
            ['GEFR', 'Gefr', 'Gefreiter', 'Seaman Apprentice', 'OR-2', 'MDR', 37, 0],
            ['MATR', 'Matr', 'Matrose', 'Seaman Recruit', 'OR-1', 'MDR', 38, 0],
        ],
        'DE_LW' => [
            ['LT', 'Lt', 'Leutnant', 'Second Lieutenant', 'OF-1', 'OFFICIER', 10, 1],
            ['OLT', 'OLt', 'Oberleutnant', 'First Lieutenant', 'OF-1', 'OFFICIER', 11, 1],
            ['HPTM', 'Hptm', 'Hauptmann', 'Captain', 'OF-2', 'OFFICIER', 12, 1],
            ['STHPTM', 'StHptm', 'Stabshauptmann', 'Staff Captain', 'OF-2', 'OFFICIER', 13, 1],
            ['MAJ', 'Maj', 'Major', 'Major', 'OF-3', 'OFFICIER', 14, 1],
            ['OTL', 'OTL', 'Oberstleutnant', 'Lieutenant Colonel', 'OF-4', 'OFFICIER', 15, 1],
            ['O', 'O', 'Oberst', 'Colonel', 'OF-5', 'OFFICIER', 16, 1],
            ['BRIGGEN', 'BrigGen', 'Brigadegeneral', 'Brigadier General', 'OF-6', 'OFFICIER', 17, 1],
            ['GENMAJ', 'GenMaj', 'Generalmajor', 'Major General', 'OF-7', 'OFFICIER', 18, 1],
            ['GENLT', 'GenLt', 'Generalleutnant', 'Lieutenant General', 'OF-8', 'OFFICIER', 19, 1],
            ['GEN', 'Gen', 'General', 'General', 'OF-9', 'OFFICIER', 20, 1],
            ['OSTFW', 'OStFw', 'Oberstabsfeldwebel', 'Chief Master Sergeant', 'OR-9', 'SOUS_OFFICIER', 21, 0],
            ['STFW', 'StFw', 'Stabsfeldwebel', 'Senior Master Sergeant', 'OR-8', 'SOUS_OFFICIER', 22, 0],
            ['HPTFW', 'HptFw', 'Hauptfeldwebel', 'Master Sergeant', 'OR-7', 'SOUS_OFFICIER', 23, 0],
            ['OFAEHNR', 'OFähnr', 'Oberfähnrich', 'Senior Officer Cadet', 'OR-7', 'SOUS_OFFICIER', 24, 0],
            ['OFW', 'OFw', 'Oberfeldwebel', 'Technical Sergeant', 'OR-6', 'SOUS_OFFICIER', 25, 0],
            ['FW', 'Fw', 'Feldwebel', 'Staff Sergeant', 'OR-6', 'SOUS_OFFICIER', 26, 0],
            ['FAEHNR', 'Fähnr', 'Fähnrich', 'Officer Cadet (Ensign)', 'OR-6', 'SOUS_OFFICIER', 27, 0],
            ['STUFFZ', 'StUffz', 'Stabsunteroffizier', 'Senior Sergeant', 'OR-5', 'SOUS_OFFICIER', 28, 0],
            ['UFFZ', 'Uffz', 'Unteroffizier', 'Sergeant', 'OR-5', 'SOUS_OFFICIER', 29, 0],
            ['FHJ', 'Fhj', 'Fahnenjunker', 'Officer Candidate', 'OR-5', 'SOUS_OFFICIER', 30, 0],
            ['STKORP', 'StKorp', 'Stabskorporal', 'Staff Corporal', 'OR-4', 'MDR', 31, 0],
            ['KORP', 'Korp', 'Korporal', 'Corporal', 'OR-4', 'MDR', 32, 0],
            ['OSTGEFR', 'OStGefr', 'Oberstabsgefreiter', 'Senior Airman (Specialist)', 'OR-4', 'MDR', 33, 0],
            ['STGEFR', 'StGefr', 'Stabsgefreiter', 'Senior Airman', 'OR-4', 'MDR', 34, 0],
            ['HPTGEFR', 'HptGefr', 'Hauptgefreiter', 'Leading Airman', 'OR-3', 'MDR', 35, 0],
            ['OGEFR', 'OGefr', 'Obergefreiter', 'Airman First Class', 'OR-3', 'MDR', 36, 0],
            ['GEFR', 'Gefr', 'Gefreiter', 'Airman', 'OR-2', 'MDR', 37, 0],
            ['FLG', 'Flg', 'Flieger', 'Airman Basic', 'OR-1', 'MDR', 38, 0],
        ],
        // Forces armées de la Fédération de Russie. Source des intitulés : loi fédérale n° 53-FZ « О воинской обязанности
        // и военной службе », art. 46 (grades « войсковые » des forces terrestres et aérospatiales, grades « корабельные »
        // de la marine) ; translittération usuelle (système BGN/PCGN simplifié). La Russie n’est pas membre de l’OTAN : codes
        // OR / OF par correspondance de fonction (tableaux comparatifs de Wikipédia « Army ranks and insignia of the
        // Russian Federation »). Abrégés : usage courant de la presse et des documents militaires russes (non codifié).
        // Маршал Российской Федерации (OF-10) non repris : grade honorifique, sans titulaire.
        'RU_SV' => [
            ['KURS', 'курс.', 'Курсант', 'Officer Cadet', 'OF(D)', 'OFFICIER', 9, 0, 'Kursant'],
            ['MLLT', 'мл. л-т', 'Младший лейтенант', 'Junior Lieutenant', 'OF-1', 'OFFICIER', 10, 1, 'Mladshiy leytenant'],
            ['LT', 'л-т', 'Лейтенант', 'Lieutenant', 'OF-1', 'OFFICIER', 11, 1, 'Leytenant'],
            ['STLT', 'ст. л-т', 'Старший лейтенант', 'Senior Lieutenant', 'OF-1', 'OFFICIER', 12, 1, 'Starshiy leytenant'],
            ['KPT', 'к-н', 'Капитан', 'Captain', 'OF-2', 'OFFICIER', 13, 1, 'Kapitan'],
            ['MAJ', 'м-р', 'Майор', 'Major', 'OF-3', 'OFFICIER', 14, 1, 'Mayor'],
            ['PPLK', 'п/п-к', 'Подполковник', 'Lieutenant Colonel', 'OF-4', 'OFFICIER', 15, 1, 'Podpolkovnik'],
            ['PLK', 'п-к', 'Полковник', 'Colonel', 'OF-5', 'OFFICIER', 16, 1, 'Polkovnik'],
            ['GENMAJ', 'ген.-м.', 'Генерал-майор', 'Major General', 'OF-6', 'OFFICIER', 17, 1, 'General-mayor'],
            ['GENLT', 'ген.-л.', 'Генерал-лейтенант', 'Lieutenant General', 'OF-7', 'OFFICIER', 18, 1, 'General-leytenant'],
            ['GENPLK', 'ген.-п.', 'Генерал-полковник', 'Colonel General', 'OF-8', 'OFFICIER', 19, 1, 'General-polkovnik'],
            ['GENARM', 'ген. армии', 'Генерал армии', 'General of the Army', 'OF-9', 'OFFICIER', 20, 1, 'General armii'],
            ['STPRAP', 'ст. пр-к', 'Старший прапорщик', 'Senior Warrant Officer', 'OR-9', 'SOUS_OFFICIER', 21, 0, 'Starshiy praporshchik'],
            ['PRAP', 'пр-к', 'Прапорщик', 'Warrant Officer', 'OR-8', 'SOUS_OFFICIER', 22, 0, 'Praporshchik'],
            ['STARSHINA', 'ст-на', 'Старшина', 'Sergeant Major', 'OR-7', 'SOUS_OFFICIER', 23, 0, 'Starshina'],
            ['STSERZH', 'ст. с-т', 'Старший сержант', 'Senior Sergeant', 'OR-6', 'SOUS_OFFICIER', 24, 0, 'Starshiy serzhant'],
            ['SERZH', 'с-т', 'Сержант', 'Sergeant', 'OR-5', 'SOUS_OFFICIER', 25, 0, 'Serzhant'],
            ['MLSERZH', 'мл. с-т', 'Младший сержант', 'Junior Sergeant', 'OR-4', 'SOUS_OFFICIER', 26, 0, 'Mladshiy serzhant'],
            ['EFR', 'ефр.', 'Ефрейтор', 'Lance Corporal', 'OR-2', 'MDR', 31, 0, 'Yefreytor'],
            ['RYAD', 'ряд.', 'Рядовой', 'Private', 'OR-1', 'MDR', 32, 0, 'Ryadovoy'],
        ],
        'RU_VMF' => [
            ['KURS', 'курс.', 'Курсант', 'Officer Cadet', 'OF(D)', 'OFFICIER', 9, 0, 'Kursant'],
            ['MLLT', 'мл. л-т', 'Младший лейтенант', 'Junior Lieutenant', 'OF-1', 'OFFICIER', 10, 1, 'Mladshiy leytenant'],
            ['LT', 'л-т', 'Лейтенант', 'Lieutenant', 'OF-1', 'OFFICIER', 11, 1, 'Leytenant'],
            ['STLT', 'ст. л-т', 'Старший лейтенант', 'Senior Lieutenant', 'OF-1', 'OFFICIER', 12, 1, 'Starshiy leytenant'],
            ['KPTLT', 'к-л', 'Капитан-лейтенант', 'Captain Lieutenant', 'OF-2', 'OFFICIER', 13, 1, 'Kapitan-leytenant'],
            ['KPT3', 'к3р', 'Капитан 3-го ранга', 'Captain 3rd Rank', 'OF-3', 'OFFICIER', 14, 1, 'Kapitan 3-go ranga'],
            ['KPT2', 'к2р', 'Капитан 2-го ранга', 'Captain 2nd Rank', 'OF-4', 'OFFICIER', 15, 1, 'Kapitan 2-go ranga'],
            ['KPT1', 'к1р', 'Капитан 1-го ранга', 'Captain 1st Rank', 'OF-5', 'OFFICIER', 16, 1, 'Kapitan 1-go ranga'],
            ['KADM', 'к.-адм.', 'Контр-адмирал', 'Rear Admiral', 'OF-6', 'OFFICIER', 17, 1, 'Kontr-admiral'],
            ['VADM', 'в.-адм.', 'Вице-адмирал', 'Vice Admiral', 'OF-7', 'OFFICIER', 18, 1, 'Vitse-admiral'],
            ['ADM', 'адм.', 'Адмирал', 'Admiral', 'OF-8', 'OFFICIER', 19, 1, 'Admiral'],
            ['ADMFL', 'адм. флота', 'Адмирал флота', 'Admiral of the Fleet', 'OF-9', 'OFFICIER', 20, 1, 'Admiral flota'],
            ['STMICH', 'ст. мичм.', 'Старший мичман', 'Senior Michman (Senior Warrant Officer)', 'OR-9', 'SOUS_OFFICIER', 21, 0, 'Starshiy michman'],
            ['MICH', 'мичм.', 'Мичман', 'Michman (Warrant Officer)', 'OR-8', 'SOUS_OFFICIER', 22, 0, 'Michman'],
            ['GLKST', 'гл. кор. ст-на', 'Главный корабельный старшина', 'Chief Ship Petty Officer', 'OR-7', 'SOUS_OFFICIER', 23, 0, 'Glavnyy korabelnyy starshina'],
            ['GLST', 'гл. ст-на', 'Главный старшина', 'Chief Petty Officer', 'OR-6', 'SOUS_OFFICIER', 24, 0, 'Glavnyy starshina'],
            ['ST1', 'ст-на 1 ст.', 'Старшина 1-й статьи', 'Petty Officer 1st Class', 'OR-5', 'SOUS_OFFICIER', 25, 0, 'Starshina 1-y stati'],
            ['ST2', 'ст-на 2 ст.', 'Старшина 2-й статьи', 'Petty Officer 2nd Class', 'OR-4', 'SOUS_OFFICIER', 26, 0, 'Starshina 2-y stati'],
            ['STMATR', 'ст. мат.', 'Старший матрос', 'Senior Seaman', 'OR-2', 'MDR', 31, 0, 'Starshiy matros'],
            ['MATR', 'мат.', 'Матрос', 'Seaman', 'OR-1', 'MDR', 32, 0, 'Matros'],
        ],
        'RU_VKS' => [
            ['KURS', 'курс.', 'Курсант', 'Officer Cadet', 'OF(D)', 'OFFICIER', 9, 0, 'Kursant'],
            ['MLLT', 'мл. л-т', 'Младший лейтенант', 'Junior Lieutenant', 'OF-1', 'OFFICIER', 10, 1, 'Mladshiy leytenant'],
            ['LT', 'л-т', 'Лейтенант', 'Lieutenant', 'OF-1', 'OFFICIER', 11, 1, 'Leytenant'],
            ['STLT', 'ст. л-т', 'Старший лейтенант', 'Senior Lieutenant', 'OF-1', 'OFFICIER', 12, 1, 'Starshiy leytenant'],
            ['KPT', 'к-н', 'Капитан', 'Captain', 'OF-2', 'OFFICIER', 13, 1, 'Kapitan'],
            ['MAJ', 'м-р', 'Майор', 'Major', 'OF-3', 'OFFICIER', 14, 1, 'Mayor'],
            ['PPLK', 'п/п-к', 'Подполковник', 'Lieutenant Colonel', 'OF-4', 'OFFICIER', 15, 1, 'Podpolkovnik'],
            ['PLK', 'п-к', 'Полковник', 'Colonel', 'OF-5', 'OFFICIER', 16, 1, 'Polkovnik'],
            ['GENMAJ', 'ген.-м.', 'Генерал-майор', 'Major General', 'OF-6', 'OFFICIER', 17, 1, 'General-mayor'],
            ['GENLT', 'ген.-л.', 'Генерал-лейтенант', 'Lieutenant General', 'OF-7', 'OFFICIER', 18, 1, 'General-leytenant'],
            ['GENPLK', 'ген.-п.', 'Генерал-полковник', 'Colonel General', 'OF-8', 'OFFICIER', 19, 1, 'General-polkovnik'],
            ['GENARM', 'ген. армии', 'Генерал армии', 'General of the Army', 'OF-9', 'OFFICIER', 20, 1, 'General armii'],
            ['STPRAP', 'ст. пр-к', 'Старший прапорщик', 'Senior Warrant Officer', 'OR-9', 'SOUS_OFFICIER', 21, 0, 'Starshiy praporshchik'],
            ['PRAP', 'пр-к', 'Прапорщик', 'Warrant Officer', 'OR-8', 'SOUS_OFFICIER', 22, 0, 'Praporshchik'],
            ['STARSHINA', 'ст-на', 'Старшина', 'Sergeant Major', 'OR-7', 'SOUS_OFFICIER', 23, 0, 'Starshina'],
            ['STSERZH', 'ст. с-т', 'Старший сержант', 'Senior Sergeant', 'OR-6', 'SOUS_OFFICIER', 24, 0, 'Starshiy serzhant'],
            ['SERZH', 'с-т', 'Сержант', 'Sergeant', 'OR-5', 'SOUS_OFFICIER', 25, 0, 'Serzhant'],
            ['MLSERZH', 'мл. с-т', 'Младший сержант', 'Junior Sergeant', 'OR-4', 'SOUS_OFFICIER', 26, 0, 'Mladshiy serzhant'],
            ['EFR', 'ефр.', 'Ефрейтор', 'Lance Corporal', 'OR-2', 'MDR', 31, 0, 'Yefreytor'],
            ['RYAD', 'ряд.', 'Рядовой', 'Private', 'OR-1', 'MDR', 32, 0, 'Ryadovoy'],
        ],
        // Armée populaire de libération. Sources : « 中国人民解放军军官军衔条例 » (règlement des grades d’officiers, révisé en
        // 1994 : 大尉 supprimé ; les officiers de la marine et de l’air portent le préfixe 海军 / 空军) et réforme des grades
        // de sous-officiers du 27 février 2022 (四级军士长 remplacé par 一级上士, 上士 devenu 二级上士) ; tableaux de Wikipédia
        // « Ranks of the People’s Liberation Army Ground Force ». La Chine n’est pas membre de l’OTAN : codes OR / OF par
        // correspondance de fonction. Pas d’abrégé officiel : l’abrégé est l’intitulé sans préfixe d’armée.
        'CN_PLA' => [
            ['XUEYUAN', '学员', '学员', 'Cadet', 'OF(D)', 'OFFICIER', 9, 0, 'Xuéyuán'],
            ['SHAOWEI', '少尉', '少尉', 'Second Lieutenant', 'OF-1', 'OFFICIER', 10, 1, 'Shàowèi'],
            ['ZHONGWEI', '中尉', '中尉', 'First Lieutenant', 'OF-1', 'OFFICIER', 11, 1, 'Zhōngwèi'],
            ['SHANGWEI', '上尉', '上尉', 'Captain', 'OF-2', 'OFFICIER', 12, 1, 'Shàngwèi'],
            ['SHAOXIAO', '少校', '少校', 'Major', 'OF-3', 'OFFICIER', 13, 1, 'Shàoxiào'],
            ['ZHONGXIAO', '中校', '中校', 'Lieutenant Colonel', 'OF-4', 'OFFICIER', 14, 1, 'Zhōngxiào'],
            ['SHANGXIAO', '上校', '上校', 'Colonel', 'OF-5', 'OFFICIER', 15, 1, 'Shàngxiào'],
            ['DAXIAO', '大校', '大校', 'Senior Colonel', 'OF-6', 'OFFICIER', 16, 1, 'Dàxiào'],
            ['SHAOJIANG', '少将', '少将', 'Major General', 'OF-7', 'OFFICIER', 17, 1, 'Shàojiàng'],
            ['ZHONGJIANG', '中将', '中将', 'Lieutenant General', 'OF-8', 'OFFICIER', 18, 1, 'Zhōngjiàng'],
            ['SHANGJIANG', '上将', '上将', 'General', 'OF-9', 'OFFICIER', 19, 1, 'Shàngjiàng'],
            ['JSZ1', '一级军士长', '一级军士长', 'Master Sergeant First Class', 'OR-9', 'SOUS_OFFICIER', 21, 0, 'Yījí jūnshìzhǎng'],
            ['JSZ2', '二级军士长', '二级军士长', 'Master Sergeant Second Class', 'OR-9', 'SOUS_OFFICIER', 22, 0, 'Èrjí jūnshìzhǎng'],
            ['JSZ3', '三级军士长', '三级军士长', 'Master Sergeant Third Class', 'OR-8', 'SOUS_OFFICIER', 23, 0, 'Sānjí jūnshìzhǎng'],
            ['SS1', '一级上士', '一级上士', 'Staff Sergeant First Class', 'OR-7', 'SOUS_OFFICIER', 24, 0, 'Yījí shàngshì'],
            ['SS2', '二级上士', '二级上士', 'Staff Sergeant Second Class', 'OR-6', 'SOUS_OFFICIER', 25, 0, 'Èrjí shàngshì'],
            ['ZHONGSHI', '中士', '中士', 'Sergeant', 'OR-5', 'SOUS_OFFICIER', 26, 0, 'Zhōngshì'],
            ['XIASHI', '下士', '下士', 'Corporal', 'OR-4', 'SOUS_OFFICIER', 27, 0, 'Xiàshì'],
            ['SDB', '上等兵', '上等兵', 'Private First Class', 'OR-2', 'MDR', 31, 0, 'Shàngděngbīng'],
            ['LIEBING', '列兵', '列兵', 'Private', 'OR-1', 'MDR', 32, 0, 'Lièbīng'],
        ],
        'CN_PLAN' => [
            ['XUEYUAN', '学员', '海军学员', 'Cadet', 'OF(D)', 'OFFICIER', 9, 0, 'Hǎijūn xuéyuán'],
            ['SHAOWEI', '少尉', '海军少尉', 'Ensign', 'OF-1', 'OFFICIER', 10, 1, 'Hǎijūn shàowèi'],
            ['ZHONGWEI', '中尉', '海军中尉', 'Lieutenant (junior grade)', 'OF-1', 'OFFICIER', 11, 1, 'Hǎijūn zhōngwèi'],
            ['SHANGWEI', '上尉', '海军上尉', 'Lieutenant', 'OF-2', 'OFFICIER', 12, 1, 'Hǎijūn shàngwèi'],
            ['SHAOXIAO', '少校', '海军少校', 'Lieutenant Commander', 'OF-3', 'OFFICIER', 13, 1, 'Hǎijūn shàoxiào'],
            ['ZHONGXIAO', '中校', '海军中校', 'Commander', 'OF-4', 'OFFICIER', 14, 1, 'Hǎijūn zhōngxiào'],
            ['SHANGXIAO', '上校', '海军上校', 'Captain', 'OF-5', 'OFFICIER', 15, 1, 'Hǎijūn shàngxiào'],
            ['DAXIAO', '大校', '海军大校', 'Senior Captain', 'OF-6', 'OFFICIER', 16, 1, 'Hǎijūn dàxiào'],
            ['SHAOJIANG', '少将', '海军少将', 'Rear Admiral', 'OF-7', 'OFFICIER', 17, 1, 'Hǎijūn shàojiàng'],
            ['ZHONGJIANG', '中将', '海军中将', 'Vice Admiral', 'OF-8', 'OFFICIER', 18, 1, 'Hǎijūn zhōngjiàng'],
            ['SHANGJIANG', '上将', '海军上将', 'Admiral', 'OF-9', 'OFFICIER', 19, 1, 'Hǎijūn shàngjiàng'],
            ['JSZ1', '一级军士长', '一级军士长', 'Master Chief Petty Officer First Class', 'OR-9', 'SOUS_OFFICIER', 21, 0, 'Yījí jūnshìzhǎng'],
            ['JSZ2', '二级军士长', '二级军士长', 'Master Chief Petty Officer Second Class', 'OR-9', 'SOUS_OFFICIER', 22, 0, 'Èrjí jūnshìzhǎng'],
            ['JSZ3', '三级军士长', '三级军士长', 'Master Chief Petty Officer Third Class', 'OR-8', 'SOUS_OFFICIER', 23, 0, 'Sānjí jūnshìzhǎng'],
            ['SS1', '一级上士', '一级上士', 'Chief Petty Officer First Class', 'OR-7', 'SOUS_OFFICIER', 24, 0, 'Yījí shàngshì'],
            ['SS2', '二级上士', '二级上士', 'Chief Petty Officer Second Class', 'OR-6', 'SOUS_OFFICIER', 25, 0, 'Èrjí shàngshì'],
            ['ZHONGSHI', '中士', '中士', 'Petty Officer First Class', 'OR-5', 'SOUS_OFFICIER', 26, 0, 'Zhōngshì'],
            ['XIASHI', '下士', '下士', 'Petty Officer Second Class', 'OR-4', 'SOUS_OFFICIER', 27, 0, 'Xiàshì'],
            ['SDB', '上等兵', '上等兵', 'Seaman First Class', 'OR-2', 'MDR', 31, 0, 'Shàngděngbīng'],
            ['LIEBING', '列兵', '列兵', 'Seaman', 'OR-1', 'MDR', 32, 0, 'Lièbīng'],
        ],
        'CN_PLAAF' => [
            ['XUEYUAN', '学员', '空军学员', 'Cadet', 'OF(D)', 'OFFICIER', 9, 0, 'Kōngjūn xuéyuán'],
            ['SHAOWEI', '少尉', '空军少尉', 'Second Lieutenant', 'OF-1', 'OFFICIER', 10, 1, 'Kōngjūn shàowèi'],
            ['ZHONGWEI', '中尉', '空军中尉', 'First Lieutenant', 'OF-1', 'OFFICIER', 11, 1, 'Kōngjūn zhōngwèi'],
            ['SHANGWEI', '上尉', '空军上尉', 'Captain', 'OF-2', 'OFFICIER', 12, 1, 'Kōngjūn shàngwèi'],
            ['SHAOXIAO', '少校', '空军少校', 'Major', 'OF-3', 'OFFICIER', 13, 1, 'Kōngjūn shàoxiào'],
            ['ZHONGXIAO', '中校', '空军中校', 'Lieutenant Colonel', 'OF-4', 'OFFICIER', 14, 1, 'Kōngjūn zhōngxiào'],
            ['SHANGXIAO', '上校', '空军上校', 'Colonel', 'OF-5', 'OFFICIER', 15, 1, 'Kōngjūn shàngxiào'],
            ['DAXIAO', '大校', '空军大校', 'Senior Colonel', 'OF-6', 'OFFICIER', 16, 1, 'Kōngjūn dàxiào'],
            ['SHAOJIANG', '少将', '空军少将', 'Major General', 'OF-7', 'OFFICIER', 17, 1, 'Kōngjūn shàojiàng'],
            ['ZHONGJIANG', '中将', '空军中将', 'Lieutenant General', 'OF-8', 'OFFICIER', 18, 1, 'Kōngjūn zhōngjiàng'],
            ['SHANGJIANG', '上将', '空军上将', 'General', 'OF-9', 'OFFICIER', 19, 1, 'Kōngjūn shàngjiàng'],
            ['JSZ1', '一级军士长', '一级军士长', 'Chief Master Sergeant First Class', 'OR-9', 'SOUS_OFFICIER', 21, 0, 'Yījí jūnshìzhǎng'],
            ['JSZ2', '二级军士长', '二级军士长', 'Chief Master Sergeant Second Class', 'OR-9', 'SOUS_OFFICIER', 22, 0, 'Èrjí jūnshìzhǎng'],
            ['JSZ3', '三级军士长', '三级军士长', 'Senior Master Sergeant', 'OR-8', 'SOUS_OFFICIER', 23, 0, 'Sānjí jūnshìzhǎng'],
            ['SS1', '一级上士', '一级上士', 'Master Sergeant', 'OR-7', 'SOUS_OFFICIER', 24, 0, 'Yījí shàngshì'],
            ['SS2', '二级上士', '二级上士', 'Technical Sergeant', 'OR-6', 'SOUS_OFFICIER', 25, 0, 'Èrjí shàngshì'],
            ['ZHONGSHI', '中士', '中士', 'Staff Sergeant', 'OR-5', 'SOUS_OFFICIER', 26, 0, 'Zhōngshì'],
            ['XIASHI', '下士', '下士', 'Sergeant', 'OR-4', 'SOUS_OFFICIER', 27, 0, 'Xiàshì'],
            ['SDB', '上等兵', '上等兵', 'Airman First Class', 'OR-2', 'MDR', 31, 0, 'Shàngděngbīng'],
            ['LIEBING', '列兵', '列兵', 'Airman', 'OR-1', 'MDR', 32, 0, 'Lièbīng'],
        ],
        // Échelle OTAN générique (STANAG 2116, « NATO codes for grades of military personnel ») pour les communautés
        // multinationales : l’intitulé est le code lui-même, l’équivalent anglais donne les grades types (armée de terre,
        // marine). WO : warrant officers hors échelle OR (forces américaines, W-1 à W-5), rangés au-dessus de OR-9.
        // OR-1 à OR-3 : militaires du rang ; OR-4 et au-delà : sous-officiers (le caporal OR-4 commande une équipe).
        'NATO_GENERIC' => [
            ['OF1', 'OF-1', 'OF-1', 'Second Lieutenant, Lieutenant, Ensign', 'OF-1', 'OFFICIER', 10, 1],
            ['OF2', 'OF-2', 'OF-2', 'Captain, Navy Lieutenant', 'OF-2', 'OFFICIER', 11, 1],
            ['OF3', 'OF-3', 'OF-3', 'Major, Lieutenant Commander', 'OF-3', 'OFFICIER', 12, 1],
            ['OF4', 'OF-4', 'OF-4', 'Lieutenant Colonel, Commander', 'OF-4', 'OFFICIER', 13, 1],
            ['OF5', 'OF-5', 'OF-5', 'Colonel, Navy Captain', 'OF-5', 'OFFICIER', 14, 1],
            ['OF6', 'OF-6', 'OF-6', 'Brigadier General, Commodore', 'OF-6', 'OFFICIER', 15, 1],
            ['OF7', 'OF-7', 'OF-7', 'Major General, Rear Admiral', 'OF-7', 'OFFICIER', 16, 1],
            ['OF8', 'OF-8', 'OF-8', 'Lieutenant General, Vice Admiral', 'OF-8', 'OFFICIER', 17, 1],
            ['OF9', 'OF-9', 'OF-9', 'General, Admiral', 'OF-9', 'OFFICIER', 18, 1],
            ['OF10', 'OF-10', 'OF-10', 'Field Marshal, Admiral of the Fleet', 'OF-10', 'OFFICIER', 19, 1],
            ['WO', 'WO', 'WO', 'Warrant Officer (W-1 to W-5)', 'WO-1', 'SOUS_OFFICIER', 20, 0],
            ['OR9', 'OR-9', 'OR-9', 'Sergeant Major, Warrant Officer Class 1, Master Chief Petty Officer', 'OR-9', 'SOUS_OFFICIER', 21, 0],
            ['OR8', 'OR-8', 'OR-8', 'Master Sergeant, Warrant Officer Class 2, Senior Chief Petty Officer', 'OR-8', 'SOUS_OFFICIER', 22, 0],
            ['OR7', 'OR-7', 'OR-7', 'Sergeant First Class, Staff Sergeant, Chief Petty Officer', 'OR-7', 'SOUS_OFFICIER', 23, 0],
            ['OR6', 'OR-6', 'OR-6', 'Staff Sergeant, Sergeant, Petty Officer First Class', 'OR-6', 'SOUS_OFFICIER', 24, 0],
            ['OR5', 'OR-5', 'OR-5', 'Sergeant, Petty Officer', 'OR-5', 'SOUS_OFFICIER', 25, 0],
            ['OR4', 'OR-4', 'OR-4', 'Corporal, Leading Seaman', 'OR-4', 'SOUS_OFFICIER', 26, 0],
            ['OR3', 'OR-3', 'OR-3', 'Lance Corporal, Private First Class, Able Seaman', 'OR-3', 'MDR', 31, 0],
            ['OR2', 'OR-2', 'OR-2', 'Private (trained), Seaman', 'OR-2', 'MDR', 32, 0],
            ['OR1', 'OR-1', 'OR-1', 'Private, Seaman Recruit, Airman', 'OR-1', 'MDR', 33, 0],
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

    /** Langue des intitulés officiels (attribut lang : « ms », « de », « ru », « zh ») ; vide pour FR, US et OTAN. */
    public static function lang(string $code): string
    {
        return self::SYSTEMS[strtoupper(trim($code))]['lang'] ?? '';
    }

    /** Référentiel d’adéquation par échelon propre au système (EchelonStandards), vide = selon la doctrine. */
    public static function adequacyProfile(string $code): string
    {
        return self::SYSTEMS[strtoupper(trim($code))]['profile'] ?? '';
    }

    /** « Grades allemands », « Grades russes »… (modèles d’unités, résumés). */
    public static function family(string $code): string
    {
        return self::SYSTEMS[strtoupper(trim($code))]['family'] ?? '';
    }

    /** Translittération d’un grade posé par migration (« Подполковник » → « Podpolkovnik », « 上尉 » → « Shàngwèi »). */
    public static function romanized(string $systemCode, string $gradeCode): string
    {
        foreach (self::GRADES[strtoupper(trim($systemCode))] ?? [] as $g) {
            if ($g[0] === strtoupper(trim($gradeCode))) {
                return (string) ($g[8] ?? '');
            }
        }

        return '';
    }

    /** Armée du système (army, navy, air, joint) : regroupement du catalogue des systèmes par pays. */
    public static function branch(string $code): string
    {
        return self::SYSTEMS[strtoupper(trim($code))]['branch'] ?? 'army';
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
