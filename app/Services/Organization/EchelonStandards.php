<?php

declare(strict_types=1);

namespace App\Services\Organization;

/**
 * Référentiel des échelons de commandement : ce qu’un échelon attend de son chef et de son adjoint.
 *
 * Pour chaque échelon (clé de UnitMilitarySymbol::ECHELONS) et chaque doctrine (américaine, française, britannique) :
 * fonction du chef, adjoint attendu ou non, plage de grades du chef et de l’adjoint (niveaux GradeLevel, codes OTAN
 * STANAG 2116), effectif de référence et sous-unités attendues.
 *
 * Ce sont des **valeurs par défaut** tirées de la doctrine publique ; chaque communauté les ajuste dans
 * Effectifs › Chaîne › Réglages de l’adéquation (OrgAdequacySettings). Une unité milsim arme rarement un échelon au
 * complet : les écarts d’effectif sont signalés à titre indicatif.
 *
 * Sources publiques :
 * - US : ATP 3-21.8 « Infantry Platoon and Squad » (2016, remplace FM 3-21.8), chap. 1 (fire team, squad, platoon) ;
 *   ATP 3-21.10 « Infantry Rifle Company » (2018) ; ATP 3-21.20 « Infantry Battalion » (2017) ;
 *   DA PAM 600-25 « U.S. Army NCO Professional Development Guide » (grades des chefs et cursus NCOES).
 * - FR : documentation publique de l’armée de Terre (defense.gouv.fr, organisation du groupe de combat et de la section
 *   d’infanterie) ; Code de la défense, art. L4131-1 (hiérarchie des grades).
 * - GB : Infantry Battle School (army.mod.uk) : Section Commanders' Battle Course pour les chefs de section (Corporal),
 *   Platoon Sergeants' Battle Course, Platoon Commanders' Battle Course ; Armed Forces Act 2006, Explanatory Notes, Annex B
 *   (équivalences de grades) ; organisation publique de l’infanterie britannique (fire team de 4, section de 8, platoon
 *   d’une trentaine, company commandée par un Major avec un Company Sergeant Major, battalion par un Lieutenant Colonel
 *   avec un Regimental Sergeant Major).
 * - OTAN : STANAG 2116 (codes OR / OF qui rendent les grades des deux systèmes comparables).
 */
final class EchelonStandards
{
    public const PROFILE_US = 'us';

    public const PROFILE_FR = 'fr';

    public const PROFILE_GB = 'gb';

    /** Échelons voisins évalués avec le référentiel d’un échelon équivalent (même marque sur le symbole). */
    public const ALIASES = [
        'peloton' => 'section',
        'escadron' => 'compagnie',
        'batterie' => 'compagnie',
    ];

    /** @return list<string> */
    public static function profiles(): array
    {
        return [self::PROFILE_FR, self::PROFILE_US, self::PROFILE_GB];
    }

    public static function sanitizeProfile(mixed $raw): string
    {
        $v = strtolower(trim((string) ($raw ?? '')));

        return in_array($v, self::profiles(), true) ? $v : self::PROFILE_FR;
    }

    /** Clé du référentiel pour un échelon d’unité (alias compris), null si l’échelon n’est pas couvert. */
    public static function standardKey(string $echelon): ?string
    {
        $key = strtolower(trim($echelon));
        $key = self::ALIASES[$key] ?? $key;

        return isset(self::all(self::PROFILE_FR)[$key]) ? $key : null;
    }

    /** @return list<string> */
    public static function keys(): array
    {
        return array_keys(self::all(self::PROFILE_FR));
    }

    /**
     * @return array<string, array{
     *   key: string, level: int, label: string, leader_label: string, leader_grade: array{0:int,1:int},
     *   deputy_label: string, deputy_required: bool, deputy_from_subunit: bool, deputy_grade: array{0:int,1:int}|null,
     *   strength: array{0:int,1:int}, subunit_echelons: list<string>, subunits: array{0:int,1:int},
     *   composition: string, qualification_hint: string, sources: list<string>
     * }>
     */
    public static function all(string $profile): array
    {
        return match (self::sanitizeProfile($profile)) {
            self::PROFILE_US => self::american(),
            self::PROFILE_GB => self::british(),
            default => self::french(),
        };
    }

    /** @return array<string, mixed>|null */
    public static function find(string $profile, string $echelon): ?array
    {
        $key = self::standardKey($echelon);

        return $key !== null ? (self::all($profile)[$key] ?? null) : null;
    }

    /** @return array<string, array<string, mixed>> */
    private static function american(): array
    {
        $atp = 'ATP 3-21.8, Infantry Platoon and Squad (2016), chap. 1';
        $pam = 'DA PAM 600-25, NCO Professional Development Guide';

        return [
            'equipe' => self::def('equipe', 1, 'Fire team', 'Team leader', [5, 5], 'Aucun adjoint : le team leader commande seul sa cellule', false, false, null, [4, 4], [], [0, 0],
                'Plus petite cellule de combat : team leader, fusilier automatique, grenadier, fusilier. Deux par squad.',
                'Basic Leader Course (BLC)', [$atp, $pam]),
            'groupe' => self::def('groupe', 2, 'Squad', 'Squad leader', [6, 6], 'Team leader le plus ancien (adjoint de fait)', true, true, [5, 5], [8, 9], ['equipe'], [2, 2],
                'Un squad leader et deux fire teams.',
                'Advanced Leader Course (ALC)', [$atp, $pam]),
            'section' => self::def('section', 3, 'Platoon', 'Platoon leader', [10, 10], 'Platoon sergeant', true, false, [7, 7], [30, 42], ['groupe'], [3, 4],
                'Un élément de commandement (platoon leader, platoon sergeant, radio, auxiliaire sanitaire), trois squads de combat et un squad d’armes.',
                'Basic Officer Leader Course (BOLC) ; Senior Leader Course (SLC) pour le platoon sergeant', [$atp, $pam]),
            'compagnie' => self::def('compagnie', 4, 'Company', 'Company commander', [11, 11], 'First sergeant ou executive officer', true, false, [8, 10], [100, 150], ['section'], [3, 4],
                'Un commandement de compagnie (commandant, executive officer, first sergeant), trois platoons de combat et un platoon d’armes.',
                'Captains Career Course (CCC) ; Master Leader Course (MLC) pour le first sergeant', ['ATP 3-21.10, Infantry Rifle Company (2018), chap. 1', $pam]),
            'bataillon' => self::def('bataillon', 5, 'Battalion', 'Battalion commander', [13, 13], 'Command sergeant major ou executive officer', true, false, [9, 12], [500, 800], ['compagnie'], [3, 5],
                'Un état-major, une compagnie de commandement et de soutien, plusieurs companies de combat.',
                'Command and General Staff College (CGSC) ; Sergeants Major Course (SMC) pour le command sergeant major', ['ATP 3-21.20, Infantry Battalion (2017), chap. 1', $pam]),
            'regiment' => self::def('regiment', 6, 'Regiment', 'Regimental commander', [14, 14], 'Command sergeant major ou executive officer', true, false, [9, 13], [1500, 3500], ['bataillon'], [2, 4],
                'Un état-major et plusieurs bataillons (ordre de grandeur : le régiment n’est plus un échelon type de l’infanterie américaine).',
                '', ['Ordre de grandeur, à ajuster par la communauté']),
        ];
    }

    /** @return array<string, array<string, mixed>> */
    private static function british(): array
    {
        $ibs = 'Infantry Battle School, Brecon (army.mod.uk) : SCBC, PSBC, PCBC';
        $afa = 'Armed Forces Act 2006, Explanatory Notes, Annex B (legislation.gov.uk) ; STANAG 2116';

        return [
            'equipe' => self::def('equipe', 1, 'Fire team', 'Fire team commander (Lance Corporal)', [3, 3], 'Aucun adjoint : le Lance Corporal commande seul son équipe', false, false, null, [4, 4], [], [0, 0],
                'Plus petite cellule de combat : quatre soldats sous un Lance Corporal. Deux par section.',
                'Junior NCO cadre (Lance Corporal)', [$ibs, $afa]),
            'groupe' => self::def('groupe', 2, 'Section', 'Section commander (Corporal)', [4, 4], 'Section second-in-command (Lance Corporal)', true, true, [3, 3], [8, 8], ['equipe'], [2, 2],
                'Un Corporal chef de section, un Lance Corporal adjoint et deux fire teams de quatre (Charlie et Delta).',
                'Section Commanders’ Battle Course (Junior Brecon)', [$ibs, $afa]),
            'section' => self::def('section', 3, 'Platoon', 'Platoon commander (Second Lieutenant ou Lieutenant)', [10, 10], 'Platoon Sergeant (Sergeant)', true, false, [6, 6], [27, 35], ['groupe'], [3, 3],
                'Un quartier général de peloton (platoon commander, platoon sergeant, radio, parfois mortier léger ou tireur d’élite) et trois sections.',
                'Platoon Commanders’ Battle Course ; Platoon Sergeants’ Battle Course (Senior Brecon) pour le platoon sergeant', [$ibs, $afa]),
            'compagnie' => self::def('compagnie', 4, 'Company', 'Officer Commanding (Major)', [12, 12], 'Company second-in-command (Captain) et Company Sergeant Major (WO2)', true, false, [8, 11], [90, 130], ['section'], [3, 4],
                'Un quartier général de compagnie (OC, 2IC, CSM, CQMS) et trois platoons.',
                'Company Sergeant Major : Warrant Officer Class 2', [$afa]),
            'bataillon' => self::def('bataillon', 5, 'Battalion', 'Commanding Officer (Lieutenant Colonel)', [13, 13], 'Battalion second-in-command (Major) et Regimental Sergeant Major (WO1)', true, false, [9, 12], [500, 700], ['compagnie'], [4, 6],
                'Un quartier général, une headquarters company, une support company (mortiers, antichars, reconnaissance, tireurs d’élite) et trois compagnies de fusiliers.',
                'Regimental Sergeant Major : Warrant Officer Class 1', [$afa]),
            'regiment' => self::def('regiment', 6, 'Regiment', 'Commanding Officer (Lieutenant Colonel)', [13, 14], 'Second-in-command (Major) et Regimental Sergeant Major (WO1)', true, false, [9, 12], [400, 800], ['compagnie', 'bataillon'], [3, 6],
                'Dans l’armée britannique, un régiment d’armes (cavalerie, artillerie, génie, transmissions) a la taille d’un bataillon : squadrons ou batteries ; un régiment d’infanterie regroupe plusieurs bataillons.',
                '', ['Ordre de grandeur, à ajuster par la communauté', $afa]),
        ];
    }

    /** @return array<string, array<string, mixed>> */
    private static function french(): array
    {
        $at = 'Armée de Terre, documentation publique sur l’organisation de l’infanterie (defense.gouv.fr)';
        $code = 'Code de la défense, art. L4131-1 (hiérarchie des grades)';

        return [
            'equipe' => self::def('equipe', 1, 'Équipe', 'Chef d’équipe', [4, 5], 'Aucun adjoint : le chef d’équipe commande seul sa cellule', false, false, null, [3, 4], [], [0, 0],
                'Plus petite cellule : chef d’équipe, tireur d’appui, grenadier voltigeur, voltigeur. Deux par groupe de combat.',
                '', [$at, $code]),
            'groupe' => self::def('groupe', 2, 'Groupe de combat', 'Chef de groupe', [5, 6], 'Chef de la seconde équipe (adjoint de fait)', true, true, [4, 5], [8, 10], ['equipe'], [2, 2],
                'Un chef de groupe et deux équipes, l’une de choc, l’autre d’appui.',
                '', [$at, $code]),
            'section' => self::def('section', 3, 'Section', 'Chef de section', [7, 10], 'Sous-officier adjoint', true, false, [6, 7], [30, 40], ['groupe'], [3, 4],
                'Un commandement de section (chef de section, sous-officier adjoint, radio, auxiliaire sanitaire) et trois à quatre groupes de combat.',
                '', [$at, $code]),
            'compagnie' => self::def('compagnie', 4, 'Compagnie', 'Commandant d’unité', [11, 11], 'Officier adjoint', true, false, [10, 11], [100, 150], ['section'], [3, 5],
                'Une section de commandement et trois à quatre sections de combat ; l’adjudant d’unité encadre le soutien.',
                '', [$at, $code]),
            'bataillon' => self::def('bataillon', 5, 'Bataillon', 'Chef de bataillon', [12, 13], 'Officier adjoint', true, false, [11, 12], [500, 800], ['compagnie'], [3, 5],
                'Un état-major et plusieurs compagnies de combat et de soutien.',
                '', [$at, $code]),
            'regiment' => self::def('regiment', 6, 'Régiment', 'Chef de corps', [14, 14], 'Chef de corps adjoint', true, false, [13, 13], [600, 1200], ['compagnie', 'bataillon'], [3, 8],
                'Un état-major, une compagnie de commandement et de logistique, plusieurs compagnies de combat.',
                '', [$at, $code]),
        ];
    }

    /**
     * @param array{0:int,1:int} $leaderGrade
     * @param array{0:int,1:int}|null $deputyGrade
     * @param array{0:int,1:int} $strength
     * @param list<string> $subunitEchelons
     * @param array{0:int,1:int} $subunits
     * @param list<string> $sources
     * @return array<string, mixed>
     */
    private static function def(
        string $key,
        int $level,
        string $label,
        string $leaderLabel,
        array $leaderGrade,
        string $deputyLabel,
        bool $deputyRequired,
        bool $deputyFromSubunit,
        ?array $deputyGrade,
        array $strength,
        array $subunitEchelons,
        array $subunits,
        string $composition,
        string $qualificationHint,
        array $sources
    ): array {
        return [
            'key' => $key,
            'level' => $level,
            'label' => $label,
            'leader_label' => $leaderLabel,
            'leader_grade' => $leaderGrade,
            'deputy_label' => $deputyLabel,
            'deputy_required' => $deputyRequired,
            'deputy_from_subunit' => $deputyFromSubunit,
            'deputy_grade' => $deputyGrade,
            'strength' => $strength,
            'subunit_echelons' => $subunitEchelons,
            'subunits' => $subunits,
            'composition' => $composition,
            'qualification_hint' => $qualificationHint,
            'sources' => $sources,
        ];
    }
}
