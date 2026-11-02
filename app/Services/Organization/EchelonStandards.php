<?php

declare(strict_types=1);

namespace App\Services\Organization;

/**
 * Référentiel des échelons de commandement : ce qu’un échelon attend de son chef et de son adjoint.
 *
 * Pour chaque échelon (clé de UnitMilitarySymbol::ECHELONS) et chaque doctrine (américaine, française, malaisienne) :
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
 * - MY : Angkatan Tentera Malaysia, organisation d’origine britannique (seksyen de huit à dix commandé par un Koperal
 *   secondé par un Lans Koperal, platun d’un Leftenan et d’un Sarjan, kompeni d’un Mejar, batalion d’un Leftenan Kolonel) :
 *   articles publics sur l’infanterie malaisienne (Infantry Corps (Malaysia), 9 RAMD (Para)) et sur la section d’infanterie
 *   de modèle britannique (Wikipédia « Section (military unit) »).
 * - DE : Bundeswehr, organisation publique de l’infanterie (Jägergruppe, Zug, Kompanie, Bataillon ; Wikipédia allemand
 *   « Gruppe (Militär) », « Zug (Militär) », « Kompanie (Militär) ») et ZDv A-1420/24 (Dienstgrade, codes OTAN).
 * - RU : US Army ATP 7-100.1 « Russian Tactics » (2024) et FM 100-2-3 « The Soviet Army: Troops, Organization and
 *   Equipment » (отделение, взвод, рота, батальон, полк) ; loi fédérale 53-FZ art. 46 (grades).
 * - CN : US Army ATP 7-100.3 « Chinese Tactics » (2021) (班, 排, 连, 营, brigades interarmes) ; règlements des grades de
 *   l’Armée populaire de libération (officiers, réforme des sous-officiers de 2022).
 * - OTAN : STANAG 2116 (codes OR / OF qui rendent les grades des deux systèmes comparables) ; AAP-6 et APP-6 (échelons).
 */
final class EchelonStandards
{
    public const PROFILE_US = 'us';

    public const PROFILE_FR = 'fr';

    /** Armée malaisienne (système d’origine britannique : seksyen commandé par un Koperal). */
    public const PROFILE_MY = 'my';

    /** Bundeswehr (Heer) : Gruppe commandée par un Unteroffizier ou un Feldwebel. */
    public const PROFILE_DE = 'de';

    /** Forces armées russes : отделение commandé par un младший сержант ou un сержант. */
    public const PROFILE_RU = 'ru';

    /** Armée populaire de libération : 班 commandé par un 下士 ou un 中士, binôme commandant / commissaire politique. */
    public const PROFILE_CN = 'cn';

    /** Échelle OTAN générique (communautés multinationales) : attentes exprimées en codes OR / OF. */
    public const PROFILE_NATO = 'nato';

    /** Échelons voisins évalués avec le référentiel d’un échelon équivalent (même marque sur le symbole). */
    public const ALIASES = [
        'peloton' => 'section',
        'escadron' => 'compagnie',
        'batterie' => 'compagnie',
    ];

    /** @return list<string> */
    public static function profiles(): array
    {
        return [self::PROFILE_FR, self::PROFILE_US, self::PROFILE_MY, self::PROFILE_DE, self::PROFILE_RU, self::PROFILE_CN, self::PROFILE_NATO];
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
            self::PROFILE_MY => self::malaysian(),
            self::PROFILE_DE => self::german(),
            self::PROFILE_RU => self::russian(),
            self::PROFILE_CN => self::chinese(),
            self::PROFILE_NATO => self::nato(),
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
     * Armée malaisienne (Tentera Darat) : articulation d’infanterie d’origine britannique, intitulés malais.
     *
     * @return array<string, array<string, mixed>>
     */
    private static function malaysian(): array
    {
        $inf = 'Infantry Corps (Malaysia) et 9th Battalion (Parachute), Royal Malay Regiment (Wikipédia)';
        $sec = 'Section (military unit), modèle britannique : Koperal chef de seksyen, Lans Koperal adjoint (Wikipédia)';
        $rank = 'Grades de l’Angkatan Tentera Malaysia et codes OTAN STANAG 2116 (Wikipédia « Malaysian military ranks »)';

        return [
            'equipe' => self::def('equipe', 1, 'Tim', 'Ketua Tim', [3, 4], 'Aucun adjoint : le chef d’équipe commande seul sa cellule', false, false, null, [4, 4], [], [0, 0],
                'Deux équipes de quatre par seksyen (modèle britannique) : l’une conduite par le Koperal, l’autre par le Lans Koperal.',
                '', [$sec, $rank]),
            'groupe' => self::def('groupe', 2, 'Seksyen', 'Ketua Seksyen (Koperal)', [4, 4], 'Penolong Ketua Seksyen (Lans Koperal)', true, true, [3, 3], [8, 10], ['equipe'], [2, 2],
                'Un Koperal chef de seksyen, un Lans Koperal adjoint et six à huit soldats, en deux équipes.',
                '', [$sec, $rank]),
            'section' => self::def('section', 3, 'Platun', 'Komander Platun (Leftenan Muda ou Leftenan)', [10, 10], 'Sarjan Platun', true, false, [6, 6], [25, 35], ['groupe'], [3, 3],
                'Un commandement de platun (officier, Sarjan, radio) et trois seksyen.',
                '', [$sec, $rank]),
            'compagnie' => self::def('compagnie', 4, 'Kompeni', 'Pegawai Memerintah Kompeni (Mejar ou Kapten)', [11, 12], 'Penolong Pegawai Memerintah Kompeni (Kapten)', true, false, [10, 11], [90, 130], ['section'], [3, 4],
                'Un commandement de kompeni (commandant, adjoint, Sarjan Mejar Kompeni) et trois platun.',
                '', [$inf, $rank]),
            'bataillon' => self::def('bataillon', 5, 'Batalion', 'Pegawai Memerintah (Leftenan Kolonel)', [13, 13], 'Penolong Pegawai Memerintah (Mejar)', true, false, [12, 12], [500, 800], ['compagnie'], [4, 6],
                'Un état-major (Markas Batalion), quatre kompeni de fusiliers (A à D) et une kompeni d’appui (Kompeni Bantuan : mortiers, mitrailleuses lourdes, antichar, transmissions).',
                '', [$inf, $rank]),
            'regiment' => self::def('regiment', 6, 'Rejimen', 'Pegawai Memerintah Rejimen (Leftenan Kolonel ou Kolonel)', [13, 14], 'Penolong Pegawai Memerintah (Mejar)', true, false, [12, 12], [300, 900], ['compagnie', 'bataillon'], [3, 6],
                'Unité de la taille d’un bataillon (rejimen Gerak Khas, artillerie, génie) ; les grands régiments d’infanterie (RAMD, RRD) regroupent des bataillons sans être un échelon de commandement.',
                '', [$inf, $rank]),
        ];
    }

    /**
     * Bundeswehr (Heer) : Jägergruppe en deux Trupps, Zug, Kompanie, Bataillon, Regiment ; intitulés allemands.
     *
     * @return array<string, array<string, mixed>>
     */
    private static function german(): array
    {
        $org = 'Organisation de l’infanterie de la Bundeswehr (Wikipédia allemand « Gruppe (Militär) », « Zug (Militär) », « Kompanie (Militär) »)';
        $rank = 'ZDv A-1420/24 « Dienstgradbezeichnungen » et codes OTAN STANAG 2116';

        return [
            'equipe' => self::def('equipe', 1, 'Trupp', 'Truppführer', [4, 5], 'Aucun adjoint : le Truppführer commande seul son Trupp', false, false, null, [3, 5], [], [0, 0],
                'Deux Trupps par Gruppe : l’un conduit par le Gruppenführer, l’autre par son adjoint.',
                '', [$org, $rank]),
            'groupe' => self::def('groupe', 2, 'Gruppe', 'Gruppenführer (Unteroffizier ou Feldwebel)', [5, 6], 'Stellvertretender Gruppenführer', true, true, [4, 5], [8, 10], ['equipe'], [2, 2],
                'Un Gruppenführer, son adjoint et six à huit soldats, en deux Trupps (débarqués d’un véhicule de combat).',
                '', [$org, $rank]),
            'section' => self::def('section', 3, 'Zug', 'Zugführer (Leutnant ou Oberleutnant)', [10, 10], 'Zugführer-Stellvertreter (Feldwebel ou Oberfeldwebel)', true, false, [6, 6], [30, 40], ['groupe'], [3, 4],
                'Un Zugtrupp (chef de section, adjoint, radio) et trois à quatre Gruppen.',
                '', [$org, $rank]),
            'compagnie' => self::def('compagnie', 4, 'Kompanie', 'Kompaniechef (Hauptmann)', [11, 11], 'Stellvertretender Kompaniechef (Oberleutnant ou Hauptmann)', true, false, [10, 11], [100, 160], ['section'], [3, 4],
                'Un Kompanietrupp (chef, adjoint, Kompaniefeldwebel, le « Spieß ») et trois à quatre Züge.',
                '', [$org, $rank]),
            'bataillon' => self::def('bataillon', 5, 'Bataillon', 'Bataillonskommandeur (Oberstleutnant)', [13, 13], 'Stellvertretender Bataillonskommandeur (Major)', true, false, [12, 12], [600, 900], ['compagnie'], [4, 6],
                'Un état-major, une compagnie de commandement et de soutien (Stabs- und Versorgungskompanie) et plusieurs compagnies de combat.',
                '', [$org, $rank]),
            'regiment' => self::def('regiment', 6, 'Regiment', 'Regimentskommandeur (Oberst)', [14, 14], 'Stellvertretender Regimentskommandeur (Oberstleutnant)', true, false, [13, 13], [1000, 2000], ['compagnie', 'bataillon'], [5, 10],
                'Régiment sans bataillons intermédiaires (Fallschirmjägerregiment 26 et 31 : compagnies placées directement sous le régiment).',
                '', [$org, $rank]),
        ];
    }

    /**
     * Forces armées russes : отделение, взвод, рота, батальон, полк ; intitulés russes.
     *
     * @return array<string, array<string, mixed>>
     */
    private static function russian(): array
    {
        $org = 'US Army ATP 7-100.1 « Russian Tactics » (2024) et FM 100-2-3 « The Soviet Army: Troops, Organization and Equipment »';
        $rank = 'Loi fédérale 53-FZ, art. 46 (grades), correspondance OTAN par fonction';

        return [
            'equipe' => self::def('equipe', 1, 'Огневая группа', 'Старший группы', [2, 4], 'Aucun adjoint : le старший группы commande seul sa cellule', false, false, null, [3, 4], [], [0, 0],
                'Cellule de circonstance : l’отделение russe n’a pas d’équipes fixes, le chef forme une ou deux группы à pied.',
                '', [$org, $rank]),
            'groupe' => self::def('groupe', 2, 'Отделение', 'Командир отделения (младший сержант ou сержант)', [4, 5], 'Aucun adjoint désigné : le наводчик-оператор ou le plus ancien remplace le chef', false, false, [2, 4], [7, 10], ['equipe'], [0, 2],
                'Un командир отделения, l’équipage du véhicule (наводчик-оператор, механик-водитель) et le groupe débarqué.',
                '', [$org, $rank]),
            'section' => self::def('section', 3, 'Взвод', 'Командир взвода (лейтенант ou старший лейтенант)', [10, 10], 'Заместитель командира взвода (старший сержант)', true, false, [6, 6], [25, 35], ['groupe'], [3, 3],
                'Un commandement de взвод (officier, adjoint sergent) et trois отделения.',
                '', [$org, $rank]),
            'compagnie' => self::def('compagnie', 4, 'Рота', 'Командир роты (капитан)', [11, 11], 'Заместитель командира роты (старший лейтенант)', true, false, [10, 10], [80, 120], ['section'], [3, 4],
                'Un commandement de рота (commandant, adjoint, старшина роты) et trois взвода.',
                '', [$org, $rank]),
            'bataillon' => self::def('bataillon', 5, 'Батальон', 'Командир батальона (майор ou подполковник)', [12, 13], 'Заместитель командира батальона (майор)', true, false, [12, 12], [350, 550], ['compagnie'], [3, 5],
                'Un état-major, trois роты de fusiliers motorisés et des éléments d’appui (mortiers, antichar, transmissions).',
                '', [$org, $rank]),
            'regiment' => self::def('regiment', 6, 'Полк', 'Командир полка (полковник)', [14, 14], 'Заместитель командира полка (подполковник)', true, false, [13, 13], [1500, 2500], ['compagnie', 'bataillon'], [3, 8],
                'Un état-major, trois bataillons de fusiliers motorisés, un bataillon de chars, l’artillerie et les compagnies d’appui.',
                '', [$org, $rank]),
        ];
    }

    /**
     * Armée populaire de libération (陆军) : 班, 排, 连, 营, 团 ; intitulés chinois. Du 连 au-dessus, le commandant
     * partage le commandement avec un commissaire politique (指导员, 教导员, 政委) du même niveau.
     *
     * @return array<string, array<string, mixed>>
     */
    private static function chinese(): array
    {
        $org = 'US Army ATP 7-100.3 « Chinese Tactics » (2021), organisation des unités de manœuvre';
        $rank = 'Règlements des grades de l’Armée populaire de libération (officiers ; sous-officiers, réforme de 2022), correspondance OTAN par fonction';

        return [
            'equipe' => self::def('equipe', 1, '战斗小组', '组长', [2, 4], 'Aucun adjoint : le 组长 commande seul sa cellule', false, false, null, [3, 4], [], [0, 0],
                'Deux à trois 战斗小组 par 班, conduits par le 班长, le 副班长 et un soldat ancien.',
                '', [$org, $rank]),
            'groupe' => self::def('groupe', 2, '班', '班长 (下士 ou 中士)', [4, 5], '副班长', true, true, [2, 4], [8, 12], ['equipe'], [2, 3],
                'Un 班长, un 副班长 et six à dix soldats, en deux ou trois 战斗小组.',
                '', [$org, $rank]),
            'section' => self::def('section', 3, '排', '排长 (少尉 ou 中尉)', [10, 10], '副排长 (sous-officier)', true, false, [5, 7], [30, 45], ['groupe'], [3, 4],
                'Un 排长, un 副排长 et trois à quatre 班.',
                '', [$org, $rank]),
            'compagnie' => self::def('compagnie', 4, '连', '连长 (上尉)', [11, 11], '指导员 (commissaire politique, 上尉)', true, false, [10, 11], [100, 150], ['section'], [3, 4],
                'Un 连长 et un 指导员 qui partagent le commandement, un 副连长, et trois à quatre 排.',
                '', [$org, $rank]),
            'bataillon' => self::def('bataillon', 5, '营', '营长 (少校 ou 中校)', [12, 13], '教导员 (commissaire politique)', true, false, [12, 13], [400, 700], ['compagnie'], [3, 6],
                'Bataillon interarmes (合成营) des brigades : compagnies d’infanterie, de chars, d’appui feu et de soutien.',
                '', [$org, $rank]),
            'regiment' => self::def('regiment', 6, '团', '团长 (上校)', [14, 14], '政委 (commissaire politique)', true, false, [13, 14], [1500, 3000], ['compagnie', 'bataillon'], [3, 6],
                'Échelon devenu rare dans l’armée de terre depuis 2017 (brigades à bataillons interarmes) ; conservé dans certaines armes et unités.',
                '', [$org, $rank]),
        ];
    }

    /**
     * Échelle OTAN générique : attentes moyennes des armées de l’Alliance, exprimées en codes OR / OF (STANAG 2116),
     * pour les communautés multinationales sans doctrine nationale dominante.
     *
     * @return array<string, array<string, mixed>>
     */
    private static function nato(): array
    {
        $rank = 'STANAG 2116 (codes OTAN des grades) ; AAP-6 et APP-6 (échelons)';
        $avg = 'Ordre de grandeur commun aux armées de l’OTAN, à ajuster par la communauté';

        return [
            'equipe' => self::def('equipe', 1, 'Fire team', 'Team leader (OR-4 à OR-5)', [4, 5], 'Aucun adjoint : le team leader commande seul sa cellule', false, false, null, [3, 5], [], [0, 0],
                'Plus petite cellule de combat, deux par groupe.',
                '', [$rank, $avg]),
            'groupe' => self::def('groupe', 2, 'Section / Squad', 'Squad leader (OR-5 à OR-6)', [5, 6], 'Team leader le plus ancien (adjoint de fait)', true, true, [4, 5], [8, 10], ['equipe'], [2, 2],
                'Un chef de groupe et deux équipes.',
                '', [$rank, $avg]),
            'section' => self::def('section', 3, 'Platoon', 'Platoon commander (OF-1)', [10, 10], 'Platoon sergeant (OR-6 à OR-7)', true, false, [6, 7], [30, 40], ['groupe'], [3, 4],
                'Un commandement de section et trois à quatre groupes.',
                '', [$rank, $avg]),
            'compagnie' => self::def('compagnie', 4, 'Company', 'Company commander (OF-2 à OF-3)', [11, 12], 'Second-in-command (OF-1 à OF-2) ou Company sergeant major (OR-8)', true, false, [8, 11], [100, 150], ['section'], [3, 4],
                'Un commandement de compagnie et trois à quatre sections.',
                '', [$rank, $avg]),
            'bataillon' => self::def('bataillon', 5, 'Battalion', 'Battalion commander (OF-4)', [13, 13], 'Executive officer (OF-3) ou Regimental sergeant major (OR-9)', true, false, [9, 12], [500, 800], ['compagnie'], [3, 5],
                'Un état-major, une compagnie de commandement et de soutien et plusieurs compagnies de combat.',
                '', [$rank, $avg]),
            'regiment' => self::def('regiment', 6, 'Regiment', 'Regimental commander (OF-5)', [14, 14], 'Deputy commander (OF-4)', true, false, [13, 13], [600, 2500], ['compagnie', 'bataillon'], [3, 8],
                'Selon les armées : régiment de la taille d’un bataillon (France, Royaume-Uni) ou regroupant plusieurs bataillons.',
                '', [$rank, $avg]),
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
