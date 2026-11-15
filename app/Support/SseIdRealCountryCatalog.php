<?php

declare(strict_types=1);

namespace App\Support;

/**
 * COMSPEC IDENTITY — pays réels STYLISÉS, proposés à l'import (jamais imposés) pour un univers « réaliste — simulation »
 * choisi explicitement par la communauté.
 *
 * Garde-fous (non négociables) :
 * - les gabarits restent génériques, par « zone de style » (europe : bordeaux, americas : bleu, other : vert ou noir) ;
 *   aucune reproduction d'armoiries, d'emblèmes officiels, d'hologrammes, de motifs de sécurité, de polices ou de mises
 *   en page officielles ; le drapeau est un simple jeu de bandes (pas d'armoiries, d'étoiles ni d'inscriptions) ;
 * - l'image exportée porte toujours la mention « DOCUMENT DE JEU – SANS VALEUR » (marquage discret au minimum, jamais
 *   désactivable pour ces pays : voir SseIdMarking) et la répète dans ses métadonnées ;
 * - numéros au format générique du jeu : code pays à deux lettres + une lettre + six chiffres (ex. GBK482913), qui ne
 *   reprend le format d'aucun vrai titre (le code MRZ, lui, est le vrai code OACI : « D » pour l'Allemagne) ;
 * - photos : uniquement des personnages du jeu.
 *
 * Même jeu de pays dans le mod SSE (identity/functions/fn_idRealCountries.sqf) : mêmes codes, codes MRZ, formats,
 * réservoirs de noms (une graine donne la même identité en jeu et sur Athena).
 */
final class SseIdRealCountryCatalog
{
    /** Univers à choisir explicitement pour importer ces pays (thème du pays). */
    public const UNIVERSE = 'realistic_sim';

    /** Mention imprimée sur chaque face d'un papier de jeu. */
    public const FACE_MENTION = 'DOCUMENT DE JEU – SANS VALEUR';

    /** Bandeau incrusté dans l'image exportée (non retirable). */
    public const EXPORT_BAND = 'DOCUMENT DE JEU – SANS VALEUR';

    /** Zones de style : gabarit générique commun (aucun modèle réel copié). */
    public const ZONES = [
        'europe' => ['label' => 'Europe (couverture bordeaux)', 'colors' => ['#6d1a2b', '#c9a227']],
        'americas' => ['label' => 'Amériques (couverture bleue)', 'colors' => ['#1b2a5c', '#c9a227']],
        'other' => ['label' => 'Autres (couverture verte ou noire)', 'colors' => ['#1f4d3a', '#c9a227']],
    ];

    /**
     * code => définition. mrz : code OACI (Allemagne « D », imprimé « D<< ») ; iso2 : préfixe du numéro de jeu ;
     * validity : années par type délivré ; colors : couverture puis rappel du drapeau ; flag : [h|v|cross|canton, couleurs].
     *
     * @var array<string, array{mrz: string, iso2: string, name: string, name_en: string, name_native: string, zone: string, region: string, languages: list<string>, authority: string, validity: array<string, int>, colors: array{0: string, 1: string}, flag: array{0: string, 1: list<string>}}>
     */
    public const COUNTRIES = [
        'GBR' => ['mrz' => 'GBR', 'iso2' => 'GB', 'name' => 'Royaume-Uni', 'name_en' => 'United Kingdom', 'name_native' => 'United Kingdom', 'zone' => 'europe', 'region' => 'UK',
            'languages' => ['en'], 'authority' => 'United Kingdom — Home Office', 'validity' => ['passport' => 10, 'driving_license' => 10],
            'colors' => ['#1c2541', '#c8102e'], 'flag' => ['cross', ['#012169', '#c8102e']]],
        'GRC' => ['mrz' => 'GRC', 'iso2' => 'GR', 'name' => 'Grèce', 'name_en' => 'Greece', 'name_native' => 'Ελλάδα', 'zone' => 'europe', 'region' => 'GREECE',
            'languages' => ['el', 'en'], 'authority' => 'Hellenic Republic — Ministry of Interior', 'validity' => ['id_card' => 10, 'passport' => 5, 'driving_license' => 15],
            'colors' => ['#6d1a2b', '#0d5eaf'], 'flag' => ['h', ['#0d5eaf', '#ffffff', '#0d5eaf', '#ffffff', '#0d5eaf']]],
        'USA' => ['mrz' => 'USA', 'iso2' => 'US', 'name' => 'États-Unis', 'name_en' => 'United States', 'name_native' => 'United States of America', 'zone' => 'americas', 'region' => 'USA',
            'languages' => ['en'], 'authority' => 'United States — Department of State', 'validity' => ['id_card' => 8, 'passport' => 10, 'driving_license' => 8],
            'colors' => ['#1b2a5c', '#b22234'], 'flag' => ['canton', ['#b22234', '#ffffff', '#3c3b6e']]],
        'DEU' => ['mrz' => 'D', 'iso2' => 'DE', 'name' => 'Allemagne', 'name_en' => 'Germany', 'name_native' => 'Deutschland', 'zone' => 'europe', 'region' => 'GERMANY',
            'languages' => ['de', 'en', 'fr'], 'authority' => 'Bundesrepublik Deutschland', 'validity' => ['id_card' => 10, 'passport' => 10, 'driving_license' => 15],
            'colors' => ['#6d1a2b', '#ffce00'], 'flag' => ['h', ['#000000', '#dd0000', '#ffce00']]],
        'AUT' => ['mrz' => 'AUT', 'iso2' => 'AT', 'name' => 'Autriche', 'name_en' => 'Austria', 'name_native' => 'Österreich', 'zone' => 'europe', 'region' => 'AUSTRIA',
            'languages' => ['de', 'en', 'fr'], 'authority' => 'Republik Österreich', 'validity' => ['id_card' => 10, 'passport' => 10, 'driving_license' => 15],
            'colors' => ['#6d1a2b', '#c8102e'], 'flag' => ['h', ['#c8102e', '#ffffff', '#c8102e']]],
        'COL' => ['mrz' => 'COL', 'iso2' => 'CO', 'name' => 'Colombie', 'name_en' => 'Colombia', 'name_native' => 'Colombia', 'zone' => 'americas', 'region' => 'COLOMBIA',
            'languages' => ['es', 'en'], 'authority' => 'República de Colombia — Ministerio del Interior', 'validity' => ['id_card' => 10, 'passport' => 10, 'driving_license' => 10],
            'colors' => ['#1b3a6b', '#fcd116'], 'flag' => ['h', ['#fcd116', '#fcd116', '#003893', '#ce1126']]],
        'FRA' => ['mrz' => 'FRA', 'iso2' => 'FR', 'name' => 'France', 'name_en' => 'France', 'name_native' => 'France', 'zone' => 'europe', 'region' => 'FRANCE',
            'languages' => ['fr', 'en'], 'authority' => 'République française — Ministère de l’Intérieur', 'validity' => ['id_card' => 10, 'passport' => 10, 'driving_license' => 15],
            'colors' => ['#6d1a2b', '#0055a4'], 'flag' => ['v', ['#0055a4', '#ffffff', '#ef4135']]],
        'BEL' => ['mrz' => 'BEL', 'iso2' => 'BE', 'name' => 'Belgique', 'name_en' => 'Belgium', 'name_native' => 'België · Belgique · Belgien', 'zone' => 'europe', 'region' => 'BELGIUM',
            'languages' => ['fr', 'nl', 'de', 'en'], 'authority' => 'Royaume de Belgique — SPF Intérieur', 'validity' => ['id_card' => 10, 'passport' => 10, 'driving_license' => 10],
            'colors' => ['#6d1a2b', '#fdda24'], 'flag' => ['v', ['#000000', '#fdda24', '#ef3340']]],
        'ESP' => ['mrz' => 'ESP', 'iso2' => 'ES', 'name' => 'Espagne', 'name_en' => 'Spain', 'name_native' => 'España', 'zone' => 'europe', 'region' => 'SPAIN',
            'languages' => ['es', 'en'], 'authority' => 'Reino de España — Ministerio del Interior', 'validity' => ['id_card' => 10, 'passport' => 10, 'driving_license' => 10],
            'colors' => ['#6d1a2b', '#f1bf00'], 'flag' => ['h', ['#aa151b', '#f1bf00', '#f1bf00', '#aa151b']]],
        'POL' => ['mrz' => 'POL', 'iso2' => 'PL', 'name' => 'Pologne', 'name_en' => 'Poland', 'name_native' => 'Polska', 'zone' => 'europe', 'region' => 'POLAND',
            'languages' => ['pl', 'en'], 'authority' => 'Rzeczpospolita Polska — Ministry of Interior', 'validity' => ['id_card' => 10, 'passport' => 10, 'driving_license' => 15],
            'colors' => ['#6d1a2b', '#dc143c'], 'flag' => ['h', ['#ffffff', '#dc143c']]],
        'UKR' => ['mrz' => 'UKR', 'iso2' => 'UA', 'name' => 'Ukraine', 'name_en' => 'Ukraine', 'name_native' => 'Україна', 'zone' => 'other', 'region' => 'UKRAINE',
            'languages' => ['uk', 'en'], 'authority' => 'Ukraine — Ministry of Interior', 'validity' => ['id_card' => 10, 'passport' => 10, 'driving_license' => 10],
            'colors' => ['#1f4d3a', '#ffd700'], 'flag' => ['h', ['#0057b7', '#ffd700']]],
        'RUS' => ['mrz' => 'RUS', 'iso2' => 'RU', 'name' => 'Russie', 'name_en' => 'Russia', 'name_native' => 'Россия', 'zone' => 'other', 'region' => 'RUSSIA_ID',
            'languages' => ['ru', 'en'], 'authority' => 'Russian Federation — Ministry of Interior', 'validity' => ['id_card' => 20, 'passport' => 10, 'driving_license' => 10],
            'colors' => ['#1b1b1b', '#d52b1e'], 'flag' => ['h', ['#ffffff', '#0039a6', '#d52b1e']]],
        'IRQ' => ['mrz' => 'IRQ', 'iso2' => 'IQ', 'name' => 'Irak', 'name_en' => 'Iraq', 'name_native' => 'العراق', 'zone' => 'other', 'region' => 'IRAQ_ID',
            'languages' => ['ar', 'en'], 'authority' => 'Republic of Iraq — Ministry of Interior', 'validity' => ['id_card' => 10, 'passport' => 8, 'driving_license' => 10],
            'colors' => ['#1f4d3a', '#ce1126'], 'flag' => ['h', ['#ce1126', '#ffffff', '#000000']]],
        'SYR' => ['mrz' => 'SYR', 'iso2' => 'SY', 'name' => 'Syrie', 'name_en' => 'Syria', 'name_native' => 'سوريا', 'zone' => 'other', 'region' => 'SYRIA_ID',
            'languages' => ['ar', 'en'], 'authority' => 'Syrian Arab Republic — Ministry of Interior', 'validity' => ['id_card' => 10, 'passport' => 6, 'driving_license' => 10],
            'colors' => ['#1b1b1b', '#007a3d'], 'flag' => ['h', ['#007a3d', '#ffffff', '#000000']]],
        'AFG' => ['mrz' => 'AFG', 'iso2' => 'AF', 'name' => 'Afghanistan', 'name_en' => 'Afghanistan', 'name_native' => 'افغانستان', 'zone' => 'other', 'region' => 'AFGHANISTAN',
            'languages' => ['fa', 'ps', 'en'], 'authority' => 'Afghanistan — Ministry of Interior', 'validity' => ['id_card' => 10, 'passport' => 5, 'driving_license' => 10],
            'colors' => ['#1b1b1b', '#007a36'], 'flag' => ['v', ['#000000', '#d32011', '#007a36']]],
        'LBN' => ['mrz' => 'LBN', 'iso2' => 'LB', 'name' => 'Liban', 'name_en' => 'Lebanon', 'name_native' => 'لبنان', 'zone' => 'other', 'region' => 'LEBANON',
            'languages' => ['ar', 'fr', 'en'], 'authority' => 'Lebanese Republic — Ministry of Interior', 'validity' => ['id_card' => 10, 'passport' => 10, 'driving_license' => 10],
            'colors' => ['#1f4d3a', '#ed1c24'], 'flag' => ['h', ['#ed1c24', '#ffffff', '#ffffff', '#ed1c24']]],
        'YEM' => ['mrz' => 'YEM', 'iso2' => 'YE', 'name' => 'Yémen', 'name_en' => 'Yemen', 'name_native' => 'اليمن', 'zone' => 'other', 'region' => 'YEMEN',
            'languages' => ['ar', 'en'], 'authority' => 'Republic of Yemen — Ministry of Interior', 'validity' => ['id_card' => 10, 'passport' => 6, 'driving_license' => 10],
            'colors' => ['#1b1b1b', '#ce1126'], 'flag' => ['h', ['#ce1126', '#ffffff', '#000000']]],
        'LBY' => ['mrz' => 'LBY', 'iso2' => 'LY', 'name' => 'Libye', 'name_en' => 'Libya', 'name_native' => 'ليبيا', 'zone' => 'other', 'region' => 'LIBYA',
            'languages' => ['ar', 'en'], 'authority' => 'State of Libya — Ministry of Interior', 'validity' => ['id_card' => 10, 'passport' => 8, 'driving_license' => 10],
            'colors' => ['#1b1b1b', '#239e46'], 'flag' => ['h', ['#e70013', '#000000', '#000000', '#239e46']]],
        'MLI' => ['mrz' => 'MLI', 'iso2' => 'ML', 'name' => 'Mali', 'name_en' => 'Mali', 'name_native' => 'Mali', 'zone' => 'other', 'region' => 'MALI',
            'languages' => ['fr', 'en'], 'authority' => 'République du Mali — Ministère de la Sécurité', 'validity' => ['id_card' => 10, 'passport' => 5, 'driving_license' => 10],
            'colors' => ['#1f4d3a', '#fcd116'], 'flag' => ['v', ['#14b53a', '#fcd116', '#ce1126']]],
        'NER' => ['mrz' => 'NER', 'iso2' => 'NE', 'name' => 'Niger', 'name_en' => 'Niger', 'name_native' => 'Niger', 'zone' => 'other', 'region' => 'NIGER',
            'languages' => ['fr', 'en'], 'authority' => 'République du Niger — Ministère de l’Intérieur', 'validity' => ['id_card' => 10, 'passport' => 5, 'driving_license' => 10],
            'colors' => ['#1f4d3a', '#e05206'], 'flag' => ['h', ['#e05206', '#ffffff', '#0db02b']]],
        'TCD' => ['mrz' => 'TCD', 'iso2' => 'TD', 'name' => 'Tchad', 'name_en' => 'Chad', 'name_native' => 'Tchad · تشاد', 'zone' => 'other', 'region' => 'CHAD',
            'languages' => ['fr', 'ar', 'en'], 'authority' => 'République du Tchad — Ministère de la Sécurité publique', 'validity' => ['id_card' => 10, 'passport' => 5, 'driving_license' => 10],
            'colors' => ['#1b2a5c', '#fecb00'], 'flag' => ['v', ['#002664', '#fecb00', '#c60c30']]],
        'SOM' => ['mrz' => 'SOM', 'iso2' => 'SO', 'name' => 'Somalie', 'name_en' => 'Somalia', 'name_native' => 'Soomaaliya', 'zone' => 'other', 'region' => 'SOMALIA',
            'languages' => ['so', 'ar', 'en'], 'authority' => 'Federal Republic of Somalia — Ministry of Interior', 'validity' => ['id_card' => 10, 'passport' => 5, 'driving_license' => 10],
            'colors' => ['#1b2a5c', '#4189dd'], 'flag' => ['h', ['#4189dd', '#ffffff', '#4189dd']]],
        'SRB' => ['mrz' => 'SRB', 'iso2' => 'RS', 'name' => 'Serbie', 'name_en' => 'Serbia', 'name_native' => 'Srbija', 'zone' => 'europe', 'region' => 'SERBIA',
            'languages' => ['sr', 'en'], 'authority' => 'Republika Srbija — Ministry of Interior', 'validity' => ['id_card' => 10, 'passport' => 10, 'driving_license' => 10],
            'colors' => ['#6d1a2b', '#0c4076'], 'flag' => ['h', ['#c6363c', '#0c4076', '#ffffff']]],
        'ALB' => ['mrz' => 'ALB', 'iso2' => 'AL', 'name' => 'Albanie', 'name_en' => 'Albania', 'name_native' => 'Shqipëria', 'zone' => 'other', 'region' => 'ALBANIA',
            'languages' => ['sq', 'en'], 'authority' => 'Republika e Shqipërisë — Ministry of Interior', 'validity' => ['id_card' => 10, 'passport' => 10, 'driving_license' => 10],
            'colors' => ['#6d1a2b', '#e41e20'], 'flag' => ['h', ['#e41e20', '#000000', '#e41e20']]],
        'MEX' => ['mrz' => 'MEX', 'iso2' => 'MX', 'name' => 'Mexique', 'name_en' => 'Mexico', 'name_native' => 'México', 'zone' => 'americas', 'region' => 'MEXICO',
            'languages' => ['es', 'en'], 'authority' => 'Estados Unidos Mexicanos — Secretaría de Gobernación', 'validity' => ['id_card' => 10, 'passport' => 10, 'driving_license' => 5],
            'colors' => ['#1b3a6b', '#006847'], 'flag' => ['v', ['#006847', '#ffffff', '#ce1126']]],
        'PHL' => ['mrz' => 'PHL', 'iso2' => 'PH', 'name' => 'Philippines', 'name_en' => 'Philippines', 'name_native' => 'Pilipinas', 'zone' => 'other', 'region' => 'PHILIPPINES',
            'languages' => ['en', 'fil'], 'authority' => 'Republic of the Philippines — Department of the Interior', 'validity' => ['id_card' => 10, 'passport' => 10, 'driving_license' => 5],
            'colors' => ['#1f4d3a', '#0038a8'], 'flag' => ['h', ['#0038a8', '#ce1126']]],
    ];

    /**
     * Réservoirs de noms par langue / région (mêmes listes dans fn_idRealCountries.sqf). f_suffix : terminaison du nom
     * de famille au féminin (russe : Ivanov → Ivanova).
     *
     * @var array<string, array{m: list<string>, f: list<string>, last: list<string>, places: list<string>, f_suffix?: string}>
     */
    public const POOLS = [
        'UK' => [
            'm' => ['Oliver', 'George', 'Harry', 'Jack', 'Thomas', 'James', 'William', 'Charlie', 'Daniel', 'Samuel', 'Joseph', 'Matthew'],
            'f' => ['Olivia', 'Amelia', 'Emily', 'Isla', 'Sophie', 'Charlotte', 'Grace', 'Lucy', 'Hannah', 'Ellie', 'Chloe', 'Jessica'],
            'last' => ['Smith', 'Jones', 'Taylor', 'Brown', 'Williams', 'Wilson', 'Johnson', 'Davies', 'Robinson', 'Wright', 'Thompson', 'Evans', 'Walker', 'White', 'Roberts', 'Hughes'],
            'places' => ['London', 'Manchester', 'Birmingham', 'Leeds', 'Glasgow', 'Liverpool', 'Bristol', 'Cardiff', 'Edinburgh', 'Belfast', 'Sheffield', 'Newcastle'],
        ],
        'USA' => [
            'm' => ['Michael', 'Christopher', 'Joshua', 'Matthew', 'David', 'Andrew', 'Ryan', 'Tyler', 'Brandon', 'Justin', 'Kevin', 'Jason'],
            'f' => ['Jessica', 'Ashley', 'Jennifer', 'Amanda', 'Sarah', 'Megan', 'Emily', 'Lauren', 'Rachel', 'Nicole', 'Samantha', 'Brittany'],
            'last' => ['Smith', 'Johnson', 'Williams', 'Brown', 'Jones', 'Miller', 'Davis', 'Garcia', 'Rodriguez', 'Wilson', 'Martinez', 'Anderson', 'Taylor', 'Thomas', 'Moore', 'Jackson'],
            'places' => ['New York', 'Los Angeles', 'Chicago', 'Houston', 'Phoenix', 'San Antonio', 'San Diego', 'Dallas', 'Denver', 'Seattle', 'Boston', 'Atlanta'],
        ],
        'GREECE' => [
            'm' => ['Nikos', 'Giorgos', 'Dimitris', 'Kostas', 'Yannis', 'Christos', 'Panagiotis', 'Vasilis', 'Stavros', 'Andreas', 'Michalis', 'Petros'],
            'f' => ['Maria', 'Eleni', 'Katerina', 'Sofia', 'Anna', 'Georgia', 'Despina', 'Ioanna', 'Dimitra', 'Vasiliki', 'Christina', 'Angeliki'],
            'last' => ['Papadopoulos', 'Georgiou', 'Nikolaidis', 'Konstantinou', 'Dimitriou', 'Papadakis', 'Vlachos', 'Karagiannis', 'Oikonomou', 'Makris', 'Antoniou', 'Stavrou', 'Pappas', 'Kyriakou', 'Theodorou', 'Mavridis'],
            'places' => ['Athina', 'Thessaloniki', 'Patra', 'Irakleio', 'Larisa', 'Volos', 'Ioannina', 'Kavala', 'Chania', 'Kalamata', 'Rodos', 'Serres'],
        ],
        'GERMANY' => [
            'm' => ['Lukas', 'Leon', 'Maximilian', 'Felix', 'Jonas', 'Paul', 'Tobias', 'Jan', 'Florian', 'Sebastian', 'Niklas', 'Stefan'],
            'f' => ['Anna', 'Laura', 'Lena', 'Julia', 'Sarah', 'Lisa', 'Katharina', 'Hannah', 'Sophie', 'Lea', 'Johanna', 'Miriam'],
            'last' => ['Müller', 'Schmidt', 'Schneider', 'Fischer', 'Weber', 'Meyer', 'Wagner', 'Becker', 'Schulz', 'Hoffmann', 'Koch', 'Richter', 'Klein', 'Wolf', 'Schröder', 'Neumann'],
            'places' => ['Berlin', 'Hamburg', 'München', 'Köln', 'Frankfurt am Main', 'Stuttgart', 'Düsseldorf', 'Leipzig', 'Dortmund', 'Bremen', 'Dresden', 'Hannover'],
        ],
        'AUSTRIA' => [
            'm' => ['Lukas', 'Florian', 'David', 'Simon', 'Elias', 'Fabian', 'Matthias', 'Markus', 'Michael', 'Alexander', 'Johannes', 'Andreas'],
            'f' => ['Anna', 'Lena', 'Sarah', 'Katharina', 'Julia', 'Theresa', 'Magdalena', 'Elisabeth', 'Sophie', 'Verena', 'Christina', 'Barbara'],
            'last' => ['Gruber', 'Huber', 'Bauer', 'Wagner', 'Müller', 'Pichler', 'Steiner', 'Moser', 'Mayer', 'Hofer', 'Leitner', 'Berger', 'Fuchs', 'Eder', 'Fischer', 'Schmid'],
            'places' => ['Wien', 'Graz', 'Linz', 'Salzburg', 'Innsbruck', 'Klagenfurt', 'Villach', 'Wels', 'Sankt Pölten', 'Dornbirn', 'Steyr', 'Bregenz'],
        ],
        'COLOMBIA' => [
            'm' => [
                'Juan Carlos', 'Andrés', 'Carlos', 'Luis', 'Jorge', 'Diego', 'Santiago', 'Sebastián', 'Camilo', 'Felipe', 'Julián', 'Alejandro', 'Óscar', 'Jhon', 'Mateo', 'Daniel',
                'Cristian', 'Fabián', 'Jairo', 'Esteban', 'Jhon Jairo', 'Wilmer', 'Yeison', 'Duván', 'Edwin', 'Fredy', 'Giovanny', 'Harold', 'Jefferson', 'Leider', 'Norbey', 'Robinson',
                'Stiven', 'Wbeimar', 'Yesid', 'Arley', 'Brayan', 'Dairo', 'Ferney', 'Albeiro', 'Aníbal', 'Darío', 'Gildardo', 'Libardo', 'Hernando', 'José', 'Miguel', 'Pedro',
                'Francisco', 'Antonio', 'Manuel', 'Rafael', 'Fernando', 'Ricardo', 'Roberto', 'Eduardo', 'Javier', 'Sergio', 'Alberto', 'Raúl', 'Héctor', 'Víctor', 'Hugo', 'Iván',
                'Mario', 'Rubén', 'Arturo', 'Ramón', 'Enrique', 'Gustavo', 'Guillermo', 'Rodrigo', 'Pablo', 'Ignacio', 'Gerardo', 'Armando', 'Alfredo', 'Ernesto', 'Marcos', 'César',
                'Adrián', 'Emilio', 'Salvador', 'Agustín', 'Leonardo', 'Nicolás', 'Tomás', 'Gabriel', 'Samuel', 'Emiliano', 'Maximiliano', 'Benjamín', 'Martín', 'Lucas', 'Joaquín',
                'Ángel', 'David', 'Elías', 'Isaac', 'Josué', 'Moisés', 'Abel', 'Abraham', 'Adolfo', 'Alfonso', 'Álvaro', 'Anselmo', 'Aurelio', 'Bernardo', 'Braulio', 'Claudio',
                'Clemente', 'Cornelio', 'Dámaso', 'Domingo', 'Efraín', 'Eliseo', 'Eugenio', 'Ezequiel', 'Fausto', 'Federico', 'Fidel', 'Florencio', 'Genaro', 'Gilberto', 'Gonzalo',
                'Gregorio', 'Heriberto', 'Hernán', 'Horacio', 'Humberto', 'Isidro', 'Ismael', 'Jacinto', 'Jesús', 'Leandro', 'Leopoldo', 'Lorenzo', 'Marcelo', 'Mauricio', 'Nelson',
                'Néstor', 'Octavio', 'Orlando', 'Osvaldo', 'Patricio', 'Reinaldo', 'René', 'Rigoberto', 'Rogelio', 'Rolando', 'Simón', 'Teodoro', 'Ulises', 'Valentín', 'Vicente',
                'Wilson',
            ],
            'f' => [
                'María', 'Luz Marina', 'Ana María', 'Diana', 'Paola', 'Carolina', 'Natalia', 'Valentina', 'Daniela', 'Laura', 'Sandra', 'Claudia', 'Juliana', 'Mariana', 'Lorena',
                'Angie', 'Yuliana', 'Leidy', 'Yenny', 'Dayana', 'Deisy', 'Yurany', 'Maryuri', 'Lina María', 'Luz Dary', 'Nidia', 'Nubia', 'Omaira', 'Yaneth', 'Xiomara', 'Marleny',
                'Aura', 'Dora', 'Flor Alba', 'Gladys', 'Ludivia', 'Mayerly', 'Karol', 'Guadalupe', 'Carmen', 'Rosa', 'Teresa', 'Patricia', 'Gabriela', 'Verónica', 'Alejandra',
                'Adriana', 'Mónica', 'Silvia', 'Leticia', 'Elena', 'Isabel', 'Beatriz', 'Cristina', 'Lucía', 'Sofía', 'Camila', 'Fernanda', 'Andrea', 'Paula', 'Sara', 'Elizabeth',
                'Martha', 'Gloria', 'Alicia', 'Angélica', 'Araceli', 'Blanca', 'Cecilia', 'Consuelo', 'Dolores', 'Esperanza', 'Estela', 'Eugenia', 'Fabiola', 'Graciela', 'Irma',
                'Josefina', 'Julia', 'Liliana', 'Lourdes', 'Magdalena', 'Margarita', 'Maribel', 'Marisol', 'Miriam', 'Norma', 'Olga', 'Pilar', 'Raquel', 'Rebeca', 'Rocío', 'Susana',
                'Victoria', 'Yolanda', 'Ximena', 'Renata', 'Regina', 'Abril', 'Antonia', 'Catalina', 'Constanza', 'Emilia', 'Florencia', 'Isabella', 'Jimena', 'Luciana', 'Manuela',
                'Martina', 'Micaela', 'Noemí', 'Salomé', 'Tatiana', 'Viviana', 'Yesenia', 'Zulema', 'Amparo', 'Aurora', 'Bertha', 'Concepción', 'Dulce', 'Edith', 'Inés', 'Lidia',
                'Mercedes', 'Nohemí', 'Perla', 'Soledad',
            ],
            'last' => [
                'Rodríguez', 'Gómez', 'González', 'Martínez', 'García', 'López', 'Hernández', 'Sánchez', 'Ramírez', 'Pérez', 'Díaz', 'Muñoz', 'Rojas', 'Moreno', 'Jiménez', 'Vargas',
                'Castro', 'Gutiérrez', 'Ortiz', 'Ospina', 'Restrepo', 'Cardona', 'Zapata', 'Osorio', 'Álvarez', 'Arango', 'Arboleda', 'Arias', 'Aristizábal', 'Atehortúa', 'Bedoya',
                'Betancur', 'Bermúdez', 'Bolívar', 'Buitrago', 'Cadavid', 'Caicedo', 'Cano', 'Carvajal', 'Castrillón', 'Ceballos', 'Correa', 'Cuartas', 'Echeverri', 'Estrada', 'Franco',
                'Gallego', 'Garcés', 'Giraldo', 'Henao', 'Hincapié', 'Holguín', 'Hoyos', 'Isaza', 'Jaramillo', 'Londoño', 'Loaiza', 'Marín', 'Mejía', 'Montoya', 'Mosquera', 'Naranjo',
                'Ocampo', 'Orozco', 'Palacio', 'Patiño', 'Peláez', 'Pineda', 'Posada', 'Quintero', 'Rendón', 'Rincón', 'Ríos', 'Rivera', 'Salazar', 'Tabares', 'Tamayo', 'Toro',
                'Trujillo', 'Upegui', 'Valencia', 'Vallejo', 'Vásquez', 'Velásquez', 'Vélez', 'Villa', 'Villegas', 'Yepes', 'Zuluaga', 'Acevedo', 'Agudelo', 'Angarita', 'Barrera',
                'Benítez', 'Cabrera', 'Camacho', 'Chaparro', 'Contreras', 'Cortés', 'Delgado', 'Fajardo', 'Forero', 'Galvis', 'Guerrero', 'Lozano', 'Medina', 'Mendoza', 'Molina',
                'Niño', 'Parra', 'Peña', 'Pinzón', 'Prieto', 'Quiroga', 'Rueda', 'Sarmiento', 'Suárez', 'Torres', 'Triana', 'Urrego', 'Vanegas', 'Vergara', 'Zambrano', 'Cuesta',
                'Córdoba', 'Palacios', 'Perea', 'Rentería', 'Murillo', 'Ibargüen', 'Hinestroza', 'Angulo', 'Banguera', 'Riascos', 'Viáfara',
            ],
            'places' => ['Bogotá', 'Medellín', 'Cali', 'Barranquilla', 'Cartagena', 'Cúcuta', 'Bucaramanga', 'Pereira', 'Santa Marta', 'Ibagué', 'Pasto', 'Villavicencio'],
        ],
        'FRANCE' => [
            'm' => ['Jean', 'Pierre', 'Louis', 'Antoine', 'Mathieu', 'Nicolas', 'Julien', 'Olivier', 'Thomas', 'Laurent', 'Étienne', 'Rémi'],
            'f' => ['Marie', 'Claire', 'Camille', 'Julie', 'Élodie', 'Sophie', 'Anne', 'Céline', 'Manon', 'Hélène', 'Léa', 'Chloé'],
            'last' => ['Martin', 'Bernard', 'Moreau', 'Laurent', 'Lefèvre', 'Girard', 'Rousseau', 'Fournier', 'Morel', 'Guérin', 'Mercier', 'Blanc', 'Faure', 'André', 'Chevalier', 'Leroy'],
            'places' => ['Paris', 'Lyon', 'Marseille', 'Toulouse', 'Lille', 'Bordeaux', 'Nantes', 'Strasbourg', 'Rennes', 'Montpellier', 'Nice', 'Dijon'],
        ],
        'BELGIUM' => [
            'm' => ['Lucas', 'Louis', 'Noah', 'Arthur', 'Thomas', 'Maxime', 'Pieter', 'Jens', 'Wout', 'Antoine', 'Simon', 'Kevin'],
            'f' => ['Emma', 'Louise', 'Julie', 'Marie', 'Laura', 'Lotte', 'Elise', 'Charlotte', 'Sarah', 'Camille', 'Lien', 'Manon'],
            'last' => ['Peeters', 'Janssens', 'Maes', 'Jacobs', 'Mertens', 'Willems', 'Claes', 'Goossens', 'Wouters', 'De Smet', 'Dubois', 'Lambert', 'Dupont', 'Martin', 'Leroy', 'Hermans'],
            'places' => ['Bruxelles', 'Antwerpen', 'Gent', 'Charleroi', 'Liège', 'Brugge', 'Namur', 'Leuven', 'Mons', 'Aalst', 'Mechelen', 'Hasselt'],
        ],
        'SPAIN' => [
            'm' => ['Antonio', 'Manuel', 'José', 'Francisco', 'David', 'Javier', 'Daniel', 'Carlos', 'Alejandro', 'Pablo', 'Sergio', 'Jorge'],
            'f' => ['María', 'Carmen', 'Lucía', 'Laura', 'Marta', 'Ana', 'Cristina', 'Elena', 'Sara', 'Paula', 'Isabel', 'Raquel'],
            'last' => ['García', 'Fernández', 'González', 'Rodríguez', 'López', 'Martínez', 'Sánchez', 'Pérez', 'Gómez', 'Martín', 'Jiménez', 'Ruiz', 'Hernández', 'Díaz', 'Moreno', 'Álvarez'],
            'places' => ['Madrid', 'Barcelona', 'Valencia', 'Sevilla', 'Zaragoza', 'Málaga', 'Murcia', 'Palma', 'Bilbao', 'Alicante', 'Córdoba', 'Valladolid'],
        ],
        'POLAND' => [
            'm' => ['Piotr', 'Tomasz', 'Krzysztof', 'Paweł', 'Michał', 'Marek', 'Jakub', 'Andrzej', 'Grzegorz', 'Łukasz', 'Wojciech', 'Kamil'],
            'f' => ['Anna', 'Katarzyna', 'Magdalena', 'Agnieszka', 'Joanna', 'Monika', 'Ewa', 'Zofia', 'Małgorzata', 'Natalia', 'Aleksandra', 'Karolina'],
            'last' => ['Nowak', 'Wójcik', 'Woźniak', 'Mazur', 'Krawczyk', 'Kaczmarek', 'Zając', 'Król', 'Wieczorek', 'Wróbel', 'Adamczyk', 'Dudek', 'Pawlak', 'Walczak', 'Michalak', 'Sikora'],
            'places' => ['Warszawa', 'Kraków', 'Łódź', 'Wrocław', 'Poznań', 'Gdańsk', 'Szczecin', 'Lublin', 'Białystok', 'Katowice', 'Rzeszów', 'Toruń'],
        ],
        'UKRAINE' => [
            'm' => [
                'Oleksandr', 'Andrii', 'Serhii', 'Volodymyr', 'Dmytro', 'Mykola', 'Ivan', 'Vasyl', 'Yurii', 'Oleh', 'Ihor', 'Maksym', 'Artem', 'Bohdan', 'Taras', 'Denys', 'Vladyslav',
                'Roman', 'Petro', 'Pavlo', 'Mykhailo', 'Viktor', 'Anatolii', 'Yevhen', 'Oleksii', 'Vitalii', 'Ruslan', 'Stanislav', 'Valerii', 'Hryhorii', 'Leonid', 'Borys', 'Stepan',
                'Fedir', 'Semen', 'Ostap', 'Nazar', 'Danylo', 'Kyrylo', 'Matvii', 'Tymofii', 'Illia', 'Marko', 'Lev', 'Zakhar', 'Yaroslav', 'Rostyslav', 'Sviatoslav', 'Myroslav',
                'Liubomyr', 'Orest', 'Markiian', 'Ustym', 'Zenovii', 'Yevstakhii', 'Arsen', 'Davyd', 'Hlib', 'Heorhii', 'Kostiantyn', 'Lukian', 'Makar', 'Nestor', 'Oles', 'Panas',
                'Tymur', 'Vadym', 'Valentyn', 'Viacheslav', 'Yukhym', 'Zynovii', 'Adrian', 'Anton', 'Erik', 'Eduard', 'Hennadii', 'Trokhym', 'Vsevolod', 'Yakiv', 'Yarema', 'Bohuslav',
                'Dobromyl', 'Vlas', 'Sava', 'Demian', 'Kuzma', 'Oleksa', 'Mykyta', 'Prokip', 'Radym', 'Tadei', 'Tykhin', 'Khoma', 'Yulian',
            ],
            'f' => [
                'Olena', 'Nataliia', 'Tetiana', 'Iryna', 'Svitlana', 'Oksana', 'Yuliia', 'Kateryna', 'Anastasiia', 'Mariia', 'Viktoriia', 'Daryna', 'Sofiia', 'Liudmyla', 'Halyna',
                'Khrystyna', 'Alina', 'Anna', 'Olha', 'Nadiia', 'Valentyna', 'Larysa', 'Liubov', 'Vira', 'Tamara', 'Lidiia', 'Zoia', 'Raisa', 'Yevheniia', 'Marharyta', 'Inna', 'Alla',
                'Diana', 'Karyna', 'Veronika', 'Valeriia', 'Yana', 'Milana', 'Polina', 'Varvara', 'Yelyzaveta', 'Oleksandra', 'Uliana', 'Solomiia', 'Zlata', 'Myroslava', 'Yaroslava',
                'Lesia', 'Roksolana', 'Bohdana', 'Ivanna', 'Marta', 'Orysia', 'Stefaniia', 'Zoriana', 'Kvitoslava', 'Liliia', 'Ruslana', 'Snizhana', 'Vladyslava', 'Ahafiia', 'Dariia',
                'Emiliia', 'Yeva', 'Kira', 'Liubava', 'Maryna', 'Melaniia', 'Nelia', 'Odarka', 'Paraskeva', 'Rostyslava', 'Taisiia', 'Vasylyna', 'Zinaida', 'Adriana', 'Antonina',
                'Bozhena', 'Dana', 'Hanna', 'Kalyna', 'Nina', 'Olesia', 'Vlada',
            ],
            'last' => [
                'Shevchenko', 'Kovalenko', 'Bondarenko', 'Tkachenko', 'Kravchenko', 'Melnyk', 'Boiko', 'Kovalchuk', 'Oliinyk', 'Shevchuk', 'Polishchuk', 'Lysenko', 'Savchenko',
                'Rudenko', 'Moroz', 'Marchenko', 'Kovalskyi', 'Petrenko', 'Ivanov', 'Kuznetsov', 'Hrytsenko', 'Zinchenko', 'Pavlenko', 'Levchenko', 'Tkachuk', 'Bondar', 'Koval',
                'Kravets', 'Honchar', 'Kushnir', 'Kolisnyk', 'Mazur', 'Hnatiuk', 'Havryliuk', 'Romaniuk', 'Martyniuk', 'Kharchenko', 'Karpenko', 'Sydorenko', 'Panchenko', 'Yurchenko',
                'Vasylenko', 'Klymenko', 'Ponomarenko', 'Nazarenko', 'Kyrychenko', 'Tereshchenko', 'Fedorenko', 'Mishchenko', 'Naumenko', 'Demchenko', 'Onyshchenko', 'Yakovenko',
                'Herasymenko', 'Ostapenko', 'Prokopenko', 'Mykhailenko', 'Stepanenko', 'Semenenko', 'Ivanenko', 'Danylenko', 'Zaichenko', 'Radchenko', 'Makarenko', 'Kostenko',
                'Yatsenko', 'Nesterenko', 'Honcharenko', 'Tyshchenko', 'Kuzmenko', 'Lytvynenko', 'Mostovyi', 'Bilyk', 'Bilous', 'Chornyi', 'Kozak', 'Kotsiuba', 'Kucher', 'Kulyk',
                'Lytvyn', 'Pavliuk', 'Pylypenko', 'Prykhodko', 'Riabko', 'Sliusar', 'Soroka', 'Stetsenko', 'Sushko', 'Taran', 'Tymchuk', 'Verbytskyi', 'Voitenko', 'Vovk', 'Zadorozhnyi',
                'Zhuk', 'Zubko', 'Babenko', 'Baran', 'Bezruk', 'Bublyk', 'Buriak', 'Chumak', 'Dovhan', 'Drozd', 'Dudnyk', 'Harkusha', 'Holub', 'Horbach', 'Hrebeniuk', 'Ishchenko',
                'Kalynychenko', 'Kyselov', 'Korniienko', 'Korol', 'Kot', 'Kryvenko', 'Kuts', 'Lutsenko', 'Maistrenko', 'Malyk', 'Matviienko', 'Mykytiuk', 'Nahornyi', 'Nechyporuk',
                'Oleksiienko', 'Osadchyi', 'Palamarchuk', 'Parkhomenko', 'Pidhornyi', 'Reshetniak', 'Rybak', 'Sakhno', 'Shapoval', 'Shulha', 'Skrypnyk', 'Stelmakh', 'Sydorchuk',
                'Tarasenko', 'Tsymbaliuk', 'Udovychenko', 'Usenko', 'Vdovychenko', 'Voloshyn', 'Yaremchuk', 'Zabolotnyi', 'Zakharchenko', 'Zelenko', 'Zubenko', 'Andriichuk', 'Bodnar',
                'Datsiuk', 'Fedoruk', 'Hutsul', 'Ilnytskyi', 'Kachur', 'Lesiv', 'Maksymiv', 'Petriv', 'Stasiv', 'Vasylkiv', 'Yaremko',
            ],
            'places' => ['Kyiv', 'Kharkiv', 'Odesa', 'Dnipro', 'Lviv', 'Zaporizhzhia', 'Mykolaiv', 'Vinnytsia', 'Poltava', 'Chernihiv', 'Sumy', 'Rivne'],
        ],
        'RUSSIA_ID' => [
            'm' => [
                'Aleksandr', 'Sergei', 'Dmitry', 'Andrei', 'Aleksei', 'Maksim', 'Vladimir', 'Ivan', 'Mikhail', 'Yevgeny', 'Nikolai', 'Artyom', 'Kirill', 'Denis', 'Roman', 'Ilya',
                'Rustam', 'Timur', 'Pavel', 'Viktor', 'Yuri', 'Oleg', 'Anton', 'Igor', 'Boris', 'Gleb', 'Stepan', 'Ruslan', 'Fyodor', 'Anatoly', 'Vadim', 'Konstantin', 'Grigory',
                'Leonid', 'Vasily', 'Pyotr', 'Gennady', 'Valery', 'Vyacheslav', 'Stanislav', 'Vitaly', 'Eduard', 'Arkady', 'Georgy', 'Daniil', 'Yegor', 'Nikita', 'Matvei', 'Timofei',
                'Semyon', 'Zakhar', 'Lev', 'Mark', 'Arseny', 'Bogdan', 'Vladislav', 'Rostislav', 'Yaroslav', 'Svyatoslav', 'Vsevolod', 'Rodion', 'Platon', 'Makar', 'Savely', 'Tikhon',
                'Trofim', 'Yefim', 'Innokenty', 'Lavrenty', 'Prokhor', 'Demyan', 'Kuzma', 'Nazar', 'Ostap', 'Erik', 'Albert', 'Robert', 'Marat', 'Renat', 'Rinat', 'Ildar', 'Ilnur',
                'Ramil', 'Damir', 'Radik', 'Rafael', 'Azat', 'Aidar', 'Bulat', 'Shamil', 'Magomed', 'Arsen', 'Zaur', 'Murat', 'Aslan', 'Anzor', 'Islam', 'Akhmed', 'Movsar', 'Adam',
                'Tamerlan', 'Alan', 'Soslan', 'Batraz', 'Venyamin', 'Filipp', 'German', 'Emil', 'Yan', 'Sevastyan', 'Luka', 'Miron', 'Klim', 'Taras', 'Nestor',
            ],
            'f' => [
                'Yelena', 'Olga', 'Tatyana', 'Natalya', 'Irina', 'Svetlana', 'Anna', 'Mariya', 'Yekaterina', 'Anastasiya', 'Yuliya', 'Darya', 'Viktoriya', 'Polina', 'Kseniya', 'Alina',
                'Gulnara', 'Aigul', 'Lyudmila', 'Galina', 'Valentina', 'Nina', 'Lyubov', 'Nadezhda', 'Vera', 'Larisa', 'Tamara', 'Raisa', 'Zoya', 'Lidiya', 'Margarita', 'Yevgeniya',
                'Oksana', 'Marina', 'Inna', 'Alla', 'Elvira', 'Diana', 'Karina', 'Kristina', 'Veronika', 'Valeriya', 'Sofiya', 'Alyona', 'Yana', 'Milana', 'Arina', 'Varvara',
                'Yelizaveta', 'Aleksandra', 'Ulyana', 'Vasilisa', 'Taisiya', 'Kira', 'Zlata', 'Miroslava', 'Yaroslava', 'Lada', 'Snezhana', 'Angelina', 'Evelina', 'Regina', 'Liliya',
                'Roza', 'Zarina', 'Dinara', 'Aliya', 'Alsu', 'Leysan', 'Gulshat', 'Ramilya', 'Madina', 'Zalina', 'Fatima', 'Aminat', 'Patimat', 'Khadizhat', 'Luiza', 'Milena', 'Nonna',
                'Emma', 'Ella', 'Zinaida', 'Klavdiya', 'Antonina', 'Serafima', 'Agata', 'Stefaniya', 'Nelli', 'Renata', 'Vlada', 'Anfisa', 'Olesya', 'Marianna', 'Rimma', 'Stanislava',
                'Bella', 'Dominika', 'Yesenia', 'Lyalya',
            ],
            'last' => [
                'Ivanov', 'Smirnov', 'Kuznetsov', 'Popov', 'Vasilyev', 'Petrov', 'Sokolov', 'Mikhailov', 'Novikov', 'Fyodorov', 'Morozov', 'Volkov', 'Alekseyev', 'Lebedev', 'Semyonov',
                'Yegorov', 'Pavlov', 'Kozlov', 'Stepanov', 'Nikolayev', 'Khabibullin', 'Galiev', 'Aliyev', 'Orlov', 'Andreyev', 'Makarov', 'Nikitin', 'Zakharov', 'Zaitsev', 'Solovyov',
                'Borisov', 'Yakovlev', 'Grigoryev', 'Romanov', 'Vorobyov', 'Sergeyev', 'Kuzmin', 'Frolov', 'Aleksandrov', 'Dmitriyev', 'Korolyov', 'Gusev', 'Kiselyov', 'Ilyin',
                'Maksimov', 'Polyakov', 'Sorokin', 'Vinogradov', 'Kovalyov', 'Belov', 'Medvedev', 'Antonov', 'Tarasov', 'Zhukov', 'Baranov', 'Filippov', 'Komarov', 'Davydov',
                'Belyayev', 'Gerasimov', 'Bogdanov', 'Osipov', 'Sidorov', 'Matveyev', 'Titov', 'Markov', 'Mironov', 'Krylov', 'Kulikov', 'Karpov', 'Vlasov', 'Melnikov', 'Denisov',
                'Gavrilov', 'Tikhonov', 'Kazakov', 'Afanasyev', 'Danilov', 'Savelyev', 'Timofeyev', 'Fomin', 'Chernov', 'Abramov', 'Martynov', 'Yefimov', 'Fedotov', 'Shcherbakov',
                'Nazarov', 'Kalinin', 'Isayev', 'Chernyshyov', 'Bykov', 'Maslov', 'Rodionov', 'Konovalov', 'Lazarev', 'Voronin', 'Klimov', 'Filatov', 'Ponomaryov', 'Golubev',
                'Kudryavtsev', 'Prokhorov', 'Naumov', 'Potapov', 'Zhuravlyov', 'Ovchinnikov', 'Trofimov', 'Leonov', 'Sobolev', 'Yermakov', 'Kolesnikov', 'Goncharov', 'Yemelyanov',
                'Nikiforov', 'Grachyov', 'Kotov', 'Grishin', 'Yefremov', 'Arkhipov', 'Gromov', 'Kirillov', 'Malyshev', 'Panov', 'Moiseyev', 'Rumyantsev', 'Akimov', 'Kondratyev',
                'Biryukov', 'Gorbunov', 'Anisimov', 'Yeremin', 'Tikhomirov', 'Galkin', 'Lukyanov', 'Mikheyev', 'Skvortsov', 'Yudin', 'Belousov', 'Nesterov', 'Simonov', 'Prokofyev',
                'Kharitonov', 'Knyazev', 'Tsvetkov', 'Levin', 'Mitrofanov', 'Voronov', 'Aksyonov', 'Sofronov', 'Maltsev', 'Loginov', 'Gorshkov', 'Savin', 'Krasnov', 'Mayorov',
                'Demidov', 'Yeliseyev', 'Rybakov', 'Safonov', 'Pleshakov', 'Dyachkov', 'Mukhametov', 'Gainutdinov', 'Sharipov', 'Valiev', 'Zaripov', 'Nurgaliev', 'Sabirov', 'Yusupov',
                'Magomedov', 'Gadzhiyev', 'Ibragimov', 'Abdulayev', 'Omarov', 'Dzhabrailov',
            ],
            'places' => ['Moskva', 'Sankt-Peterburg', 'Novosibirsk', 'Yekaterinburg', 'Kazan', 'Nizhny Novgorod', 'Samara', 'Omsk', 'Rostov-na-Donu', 'Ufa', 'Krasnoyarsk', 'Volgograd'],
            'f_suffix' => 'a',
        ],
        'IRAQ_ID' => [
            'm' => [
                'Ali', 'Mohammed', 'Hussein', 'Ahmed', 'Hassan', 'Abbas', 'Mustafa', 'Haider', 'Omar', 'Karrar', 'Mohanad', 'Zaid', 'Sajjad', 'Ammar', 'Yaser', 'Kareem', 'Khalid',
                'Saif', 'Rebwar', 'Karwan', 'Kadhim', 'Jaafar', 'Muntadhar', 'Murtadha', 'Baqir', 'Hamid', 'Thaer', 'Uday', 'Laith', 'Mazin', 'Raad', 'Sarmad', 'Firas', 'Dhafer',
                'Ghaith', 'Nawar', 'Azad', 'Dilshad', 'Hemin', 'Sirwan', 'Shwan', 'Aram', 'Barzan', 'Jassim', 'Falah', 'Fadhil', 'Hashim', 'Kamil', 'Latif', 'Naji', 'Nouri', 'Sattar',
                'Jabbar', 'Razzaq', 'Wathiq', 'Ibrahim', 'Yousef', 'Mahmoud', 'Abdullah', 'Karim', 'Samir', 'Tarek', 'Walid', 'Nabil', 'Bilal', 'Faisal', 'Adnan', 'Ziad', 'Bassam',
                'Marwan', 'Sami', 'Anas', 'Othman', 'Jamil', 'Rafik', 'Hamza', 'Yasin', 'Majid', 'Nasser', 'Salim', 'Salem', 'Saleh', 'Rashid', 'Fahd', 'Fouad', 'Adel', 'Akram',
                'Ayman', 'Bashir', 'Bakr', 'Daoud', 'Emad', 'Essam', 'Fadel', 'Fares', 'Fawzi', 'Ghazi', 'Habib', 'Hadi', 'Hafez', 'Haitham', 'Hakim', 'Hani', 'Hatem', 'Hisham',
                'Idris', 'Ihab', 'Imad', 'Ismail', 'Issa', 'Jaber', 'Jalal', 'Jamal', 'Jawad', 'Kamal', 'Kamel', 'Khalil', 'Lutfi', 'Maher', 'Mahdi', 'Mansour', 'Mazen', 'Mounir',
                'Mourad', 'Munther', 'Musa', 'Nader', 'Nadim', 'Naim', 'Najib', 'Nazih', 'Nizar', 'Qasim', 'Qusay', 'Raed', 'Rafiq', 'Raja', 'Rami', 'Ramzi', 'Rashad', 'Riad', 'Sabri',
                'Sadiq', 'Safwan', 'Sharif', 'Shadi', 'Shaker', 'Sufyan', 'Suhail', 'Sultan', 'Taha', 'Taher', 'Talal', 'Tamer', 'Thamer', 'Wael', 'Wajdi', 'Wasim', 'Yahya', 'Younes',
                'Zaki', 'Zakaria', 'Zuhair', 'Abdulaziz', 'Abdulkarim', 'Abdulrahman', 'Abdulhadi', 'Abdulmajid', 'Abdulsalam', 'Abdulwahab', 'Anwar', 'Aref', 'Asaad', 'Ashraf', 'Atef',
                'Aws', 'Badr', 'Burhan', 'Dhia', 'Ghassan', 'Hazem', 'Hilal', 'Hussam', 'Iyad', 'Louay', 'Mamoun', 'Moataz', 'Mohannad', 'Muthanna', 'Nawaf', 'Nidal', 'Numan', 'Radwan',
                'Raouf', 'Saad', 'Sabah', 'Salah', 'Shafiq', 'Subhi', 'Tawfiq', 'Thabit', 'Wissam', 'Yaqoub', 'Zaher',
            ],
            'f' => [
                'Fatima', 'Zainab', 'Maryam', 'Noor', 'Huda', 'Sara', 'Rusul', 'Ruqaya', 'Shahad', 'Hiba', 'Dalia', 'Aya', 'Iman', 'Zahra', 'Sana', 'Layla', 'Shilan', 'Zahraa', 'Ban',
                'Rand', 'Tabarak', 'Baneen', 'Ghufran', 'Sundus', 'Avin', 'Dilnaz', 'Hero', 'Nazdar', 'Rozhin', 'Sazan', 'Tara', 'Vian', 'Anfal', 'Benin', 'Istabraq', 'Malak', 'Mina',
                'Russul', 'Zubaida', 'Nibras', 'Raya', 'Suhad', 'Amal', 'Asma', 'Hana', 'Salma', 'Nada', 'Rania', 'Khadija', 'Aisha', 'Lina', 'Hala', 'Rasha', 'Dima', 'Reem', 'Lama',
                'Maya', 'Yara', 'Heba', 'Ghada', 'Nadia', 'Mona', 'Bushra', 'Samira', 'Nawal', 'Suad', 'Wafa', 'Widad', 'Yasmin', 'Zeinab', 'Abeer', 'Afaf', 'Ahlam', 'Alia', 'Amani',
                'Amira', 'Arwa', 'Basma', 'Dalal', 'Dunya', 'Duha', 'Faten', 'Fadwa', 'Farah', 'Feryal', 'Hadeel', 'Hanan', 'Hayat', 'Hind', 'Ibtisam', 'Ikram', 'Inas', 'Intisar',
                'Jamila', 'Jihan', 'Jumana', 'Kawthar', 'Khawla', 'Lamia', 'Latifa', 'Lubna', 'Maha', 'Majida', 'Manal', 'Marwa', 'May', 'Maysa', 'Mervat', 'Nabila', 'Nahla', 'Najwa',
                'Nisreen', 'Nuha', 'Raghad', 'Rahma', 'Rana', 'Randa', 'Rawan', 'Rima', 'Ruba', 'Sabah', 'Safa', 'Safiya', 'Sahar', 'Sajida', 'Sawsan', 'Shaima', 'Shatha', 'Siham',
                'Suha', 'Sumaya', 'Tahani', 'Tamara', 'Thuraya', 'Wijdan', 'Yusra', 'Zakia', 'Zina', 'Afnan', 'Areej', 'Bayan', 'Ghadeer', 'Hajar', 'Israa', 'Jana', 'Joud', 'Lujain',
                'Nour al-Huda', 'Shahd', 'Tasneem', 'Walaa',
            ],
            'last' => [
                'al-Jubouri', 'al-Tamimi', 'al-Saadi', 'al-Obeidi', 'al-Dulaimi', 'al-Shammari', 'al-Rubaie', 'al-Khafaji', 'al-Musawi', 'al-Hashimi', 'al-Ani', 'al-Azzawi',
                'al-Zubaidi', 'al-Bayati', 'al-Kubaisi', 'al-Janabi', 'al-Lami', 'al-Qaisi', 'Abdullah', 'Rashid', 'Hama Amin', 'Mahmoud', 'al-Hadithi', 'al-Rawi', 'al-Fahdawi',
                'al-Jumaili', 'al-Mashhadani', 'al-Nuaimi', 'al-Samarrai', 'al-Hayali', 'al-Rikabi', 'al-Ghanimi', 'al-Zaidi', 'al-Sharifi', 'al-Yasiri', 'al-Karbalai', 'al-Najafi',
                'al-Hilli', 'al-Basri', 'al-Kinani', 'al-Shuwaili', 'al-Darraji', 'al-Fatlawi', 'al-Khazraji', 'al-Saedi', 'al-Maksousi', 'al-Mayahi', 'al-Budairi', 'al-Jabiri',
                'al-Ghazi', 'al-Asadi', 'al-Hasnawi', 'al-Shaibani', 'al-Rubai', 'al-Taie', 'al-Mousawi', 'al-Sadoun', 'al-Issawi', 'al-Alwani', 'al-Karbouli', 'al-Mahlawi',
                'al-Namrawi', 'al-Rishawi', 'al-Suwaidawi', 'al-Shujairi', 'al-Ajili', 'al-Bazzaz', 'al-Daghistani', 'al-Mufti', 'al-Naqib', 'al-Orfali', 'al-Qazzaz', 'al-Saffar',
                'al-Sarraf', 'al-Timimi', 'al-Zahawi', 'Hama Rashid', 'Hama Salih', 'Qadir', 'Jaff', 'Dizayee', 'Surchi', 'Herki', 'Bradosti', 'Mirani', 'Khoshnaw', 'Doski', 'Goran',
                'Sindi', 'Shekhani', 'Rasul', 'Karim', 'Aziz', 'Majeed', 'Hameed', 'Jasim', 'Kadhim', 'Abbas', 'Hadi', 'Salman', 'Dawood', 'Yousif', 'Mohsen', 'Hasan', 'Jawad', 'Naser',
                'Radhi', 'Sabri', 'Shakir', 'Wahab', 'Yaqoob', 'Zaki', 'Latif', 'Fadhil', 'Jabbar', 'Kareem', 'Sattar', 'Razzaq', 'Abdulhussein', 'Abduljabbar', 'Abdulridha',
                'Abdulzahra', 'Abdulameer', 'Abdulkadhim', 'al-Rahal', 'al-Hamdani', 'al-Shibli', 'al-Nasiri', 'al-Mayali', 'al-Khuzai', 'al-Garawi', 'al-Zamili', 'al-Ghurairi',
                'al-Hamdawi', 'al-Jashami', 'al-Maamouri', 'al-Bahadli', 'al-Fartousi', 'al-Sarray', 'al-Uqaili',
            ],
            'places' => ['Baghdad', 'Basra', 'Mosul', 'Erbil', 'Kirkuk', 'Najaf', 'Karbala', 'Sulaymaniyah', 'Ramadi', 'Fallujah', 'Tikrit', 'Samarra'],
        ],
        'SYRIA_ID' => [
            'm' => [
                'Mohammad', 'Ahmad', 'Omar', 'Khaled', 'Hassan', 'Mahmoud', 'Ali', 'Rami', 'Fadi', 'Samer', 'Tarek', 'Ziad', 'Hussam', 'Majd', 'Yazan', 'Karam', 'Jad', 'Abdulrahman',
                'Firas', 'Bashar', 'Ghiath', 'Ayham', 'Tamim', 'Wisam', 'Hadi', 'Rabie', 'Mouaz', 'Qutaiba', 'Thaer', 'Alaa', 'Diaa', 'Eyad', 'Housam', 'Khaldoun', 'Mamdouh', 'Nawras',
                'Obada', 'Rateb', 'Saria', 'Tayseer', 'Wadah', 'Zein', 'Abdulqader', 'Abdulhamid', 'Jihad', 'Kinan', 'Louai', 'Moayad', 'Nibras', 'Sinan', 'Yamen', 'Ward', 'Mohammed',
                'Ahmed', 'Hussein', 'Khalid', 'Mustafa', 'Ibrahim', 'Yousef', 'Abdullah', 'Karim', 'Samir', 'Walid', 'Nabil', 'Bilal', 'Faisal', 'Adnan', 'Bassam', 'Marwan', 'Sami',
                'Anas', 'Othman', 'Jamil', 'Rafik', 'Hamza', 'Yasin', 'Saif', 'Zaid', 'Ammar', 'Yaser', 'Kareem', 'Majid', 'Nasser', 'Salim', 'Salem', 'Saleh', 'Rashid', 'Fahd',
                'Fouad', 'Adel', 'Akram', 'Ayman', 'Bashir', 'Bakr', 'Daoud', 'Emad', 'Essam', 'Fadel', 'Fares', 'Fawzi', 'Ghazi', 'Habib', 'Hafez', 'Haitham', 'Hakim', 'Hani', 'Hatem',
                'Hisham', 'Idris', 'Ihab', 'Imad', 'Ismail', 'Issa', 'Jaber', 'Jalal', 'Jamal', 'Jawad', 'Kamal', 'Kamel', 'Khalil', 'Lutfi', 'Maher', 'Mahdi', 'Mansour', 'Mazen',
                'Mounir', 'Mourad', 'Munther', 'Musa', 'Nader', 'Nadim', 'Naim', 'Najib', 'Nazih', 'Nizar', 'Qasim', 'Qusay', 'Raed', 'Rafiq', 'Raja', 'Ramzi', 'Rashad', 'Riad',
                'Sabri', 'Sadiq', 'Safwan', 'Sharif', 'Shadi', 'Shaker', 'Sufyan', 'Suhail', 'Sultan', 'Taha', 'Taher', 'Talal', 'Tamer', 'Thamer', 'Wael', 'Wajdi', 'Wasim', 'Yahya',
                'Younes', 'Zaki', 'Zakaria', 'Zuhair', 'Abdulaziz', 'Abdulkarim', 'Abdulhadi', 'Abdulmajid', 'Abdulsalam', 'Abdulwahab', 'Anwar', 'Aref', 'Asaad', 'Ashraf', 'Atef',
                'Aws', 'Badr', 'Burhan', 'Dhia', 'Ghassan', 'Hazem', 'Hilal', 'Iyad', 'Louay', 'Mamoun', 'Moataz', 'Mohannad', 'Muthanna', 'Nawaf', 'Nidal', 'Numan', 'Radwan', 'Raouf',
                'Saad', 'Sabah', 'Salah', 'Shafiq', 'Subhi', 'Tawfiq', 'Thabit', 'Wissam', 'Yaqoub', 'Zaher',
            ],
            'f' => [
                'Fatima', 'Rania', 'Lina', 'Hala', 'Rasha', 'Dima', 'Nour', 'Sara', 'Reem', 'Lama', 'Maya', 'Raghad', 'Yara', 'Heba', 'Ghada', 'Joudi', 'Ghalia', 'Lujain', 'Mais',
                'Nagham', 'Rahaf', 'Rimas', 'Sham', 'Tala', 'Batoul', 'Carmen', 'Dania', 'Ghina', 'Haya', 'Judy', 'Kinda', 'Lana', 'Maram', 'Massa', 'Nermin', 'Ola', 'Rama', 'Rouba',
                'Salam', 'Sidra', 'Taima', 'Waad', 'Yamam', 'Zeina', 'Zainab', 'Maryam', 'Noor', 'Huda', 'Hiba', 'Dalia', 'Aya', 'Iman', 'Zahra', 'Sana', 'Layla', 'Amal', 'Asma',
                'Hana', 'Salma', 'Nada', 'Khadija', 'Aisha', 'Nadia', 'Mona', 'Bushra', 'Samira', 'Nawal', 'Suad', 'Wafa', 'Widad', 'Yasmin', 'Zeinab', 'Abeer', 'Afaf', 'Ahlam', 'Alia',
                'Amani', 'Amira', 'Arwa', 'Basma', 'Dalal', 'Dunya', 'Duha', 'Faten', 'Fadwa', 'Farah', 'Feryal', 'Hadeel', 'Hanan', 'Hayat', 'Hind', 'Ibtisam', 'Ikram', 'Inas',
                'Intisar', 'Jamila', 'Jihan', 'Jumana', 'Kawthar', 'Khawla', 'Lamia', 'Latifa', 'Lubna', 'Maha', 'Majida', 'Malak', 'Manal', 'Marwa', 'May', 'Maysa', 'Mervat', 'Nabila',
                'Nahla', 'Najwa', 'Nisreen', 'Nuha', 'Rahma', 'Rana', 'Randa', 'Rawan', 'Rima', 'Ruba', 'Sabah', 'Safa', 'Safiya', 'Sahar', 'Sajida', 'Sawsan', 'Shaima', 'Shatha',
                'Siham', 'Suha', 'Sumaya', 'Tahani', 'Tamara', 'Thuraya', 'Wijdan', 'Yusra', 'Zakia', 'Zina', 'Afnan', 'Areej', 'Bayan', 'Ghadeer', 'Hajar', 'Israa', 'Jana', 'Joud',
                'Nour al-Huda', 'Ruqaya', 'Shahd', 'Tasneem', 'Walaa',
            ],
            'last' => [
                'al-Halabi', 'al-Masri', 'Haddad', 'Khoury', 'al-Ahmad', 'al-Hamwi', 'al-Shami', 'Kassab', 'al-Khatib', 'Darwish', 'Hassan', 'al-Hussein', 'Mansour', 'Zein', 'Sabbagh',
                'Nasser', 'Jaber', 'Saleh', 'al-Ali', 'Qassem', 'Barakat', 'Rifai', 'al-Homsi', 'al-Idlibi', 'al-Dimashqi', 'al-Hourani', 'al-Deiri', 'al-Raqqawi', 'al-Lathqani',
                'al-Tartousi', 'al-Jazairi', 'al-Qudsi', 'al-Nabulsi', 'al-Bitar', 'al-Attar', 'al-Sawwaf', 'al-Qabbani', 'al-Midani', 'al-Kurdi', 'al-Turkmani', 'al-Sheikh', 'al-Hajj',
                'al-Sayed', 'al-Mohammad', 'al-Ibrahim', 'al-Khalil', 'al-Yousef', 'al-Omar', 'al-Khalaf', 'al-Jasem', 'al-Abdullah', 'al-Hamad', 'al-Saleh', 'al-Mustafa', 'al-Mahmoud',
                'al-Issa', 'al-Salem', 'al-Faraj', 'al-Najjar', 'al-Haddad', 'al-Khayyat', 'al-Lahham', 'al-Tabbaa', 'al-Zoubi', 'al-Mikdad', 'al-Masalmeh', 'al-Hariri', 'al-Rifai',
                'Abazid', 'Akkad', 'Arnaout', 'Azem', 'Bakdash', 'Bakkour', 'Bayrakdar', 'Dabbagh', 'Dakkak', 'Daaboul', 'Fakhouri', 'Farra', 'Ghazal', 'Habbal', 'Hakki', 'Hamdan',
                'Idlibi', 'Jabri', 'Jarrah', 'Jouejati', 'Kabbani', 'Kanaan', 'Kayali', 'Keilani', 'Khayat', 'Kuzbari', 'Mardini', 'Maarawi', 'Malas', 'Mouazen', 'Murad', 'Nashawati',
                'Olabi', 'Qaddour', 'Qalaji', 'Rajab', 'Sabouni', 'Safadi', 'Sakka', 'Shaar', 'Shalabi', 'Shehabi', 'Sibai', 'Sukkar', 'Tabbakh', 'Tarabishi', 'Tayara', 'Wannous',
                'Yazigi', 'Zaim', 'Zarzour', 'Zaza', 'Zuhair', 'Abboud', 'Ajami', 'Asfour', 'Ayoubi', 'Dandashi', 'Droubi', 'Ghadban', 'Hallak', 'Jundi', 'Kalthoum', 'Naddaf',
                'Sarmini', 'Shihabi', 'Zakariya', 'Hasaka', 'Sheikho', 'Hesso', 'Muslim', 'Ibo', 'Bozan', 'Kalo', 'Hajo', 'Shekhmous', 'Derki',
            ],
            'places' => ['Dimashq', 'Halab', 'Homs', 'Hama', 'Latakia', 'Tartus', 'Deir ez-Zor', 'Raqqa', 'Idlib', 'Daraa', 'Al-Hasakah', 'Qamishli'],
        ],
        'AFGHANISTAN' => [
            'm' => [
                'Ahmad', 'Mohammad', 'Abdullah', 'Hamid', 'Zalmai', 'Farhad', 'Jawad', 'Ramin', 'Mustafa', 'Habib', 'Wahid', 'Naveed', 'Sami', 'Bilal', 'Rahim', 'Gul Agha', 'Nasrullah',
                'Khalid', 'Hamidullah', 'Basir', 'Abdul Wali', 'Abdul Qadir', 'Abdul Hadi', 'Abdul Rauf', 'Abdul Satar', 'Ajmal', 'Akbar', 'Amanullah', 'Aminullah', 'Arif', 'Asadullah',
                'Ashraf', 'Atiqullah', 'Azizullah', 'Baryalai', 'Daoud', 'Dawlat', 'Ehsan', 'Emal', 'Esmatullah', 'Faiz Mohammad', 'Farid', 'Fawad', 'Fazal', 'Ghulam Nabi',
                'Ghulam Sakhi', 'Gul Mohammad', 'Haroon', 'Hashmat', 'Hekmat', 'Hedayat', 'Homayoun', 'Ibrahim', 'Idrees', 'Ismail', 'Jamal', 'Jamshid', 'Javed', 'Kabir', 'Karim',
                'Khan Mohammad', 'Khoshal', 'Latif', 'Mahmood', 'Malik', 'Mansoor', 'Masood', 'Matiullah', 'Mirwais', 'Mohib', 'Mujib', 'Najib', 'Naqib', 'Naseer', 'Nazar Mohammad',
                'Nematullah', 'Noor Agha', 'Noorullah', 'Obaidullah', 'Omid', 'Parwiz', 'Qais', 'Qudratullah', 'Rafi', 'Rahmatullah', 'Rashid', 'Rohullah', 'Sabir', 'Safiullah',
                'Said Rahman', 'Sardar', 'Shafiq', 'Shah Wali', 'Shahpoor', 'Sharif', 'Sher Agha', 'Sher Mohammad', 'Shoaib', 'Sulaiman', 'Tahir', 'Tamim', 'Tariq', 'Wais',
                'Wali Mohammad', 'Yama', 'Yaqub', 'Yunus', 'Zabihullah', 'Zaher', 'Zakir', 'Zia', 'Zubair', 'Abdul Baqi', 'Abdul Ghafar', 'Abdul Jalil', 'Abdul Manan', 'Abdul Wahab',
                'Dost Mohammad', 'Juma Khan', 'Lal Mohammad', 'Mir Agha', 'Pacha Gul', 'Taj Mohammad', 'Toryalai', 'Zmaray',
            ],
            'f' => [
                'Fatima', 'Maryam', 'Zahra', 'Nadia', 'Farzana', 'Shabnam', 'Mursal', 'Freshta', 'Laila', 'Parwana', 'Roya', 'Mahbooba', 'Zarghona', 'Nazanin', 'Sima', 'Hasina',
                'Adela', 'Aziza', 'Bahara', 'Benafsha', 'Bibi Gul', 'Diba', 'Durkhanai', 'Fahima', 'Farida', 'Fariha', 'Fawzia', 'Gulalai', 'Gulnar', 'Habiba', 'Hamida', 'Hosai',
                'Jamila', 'Khatera', 'Khadija', 'Lailuma', 'Latifa', 'Lida', 'Mahjabin', 'Malalai', 'Marzia', 'Masooma', 'Mina', 'Monira', 'Morsal', 'Najiba', 'Nargis', 'Nasima',
                'Nilofar', 'Palwasha', 'Parisa', 'Rahila', 'Rangina', 'Razia', 'Rokhshana', 'Sabera', 'Sahar', 'Sakina', 'Salma', 'Samira', 'Sanam', 'Shafiqa', 'Shahla', 'Shakila',
                'Sharifa', 'Shukria', 'Soraya', 'Spogmai', 'Tahmina', 'Tamana', 'Wahida', 'Wazhma', 'Yalda', 'Zainab', 'Zakia', 'Zarifa', 'Zarmina', 'Zuhal', 'Arezo', 'Basira',
                'Homaira', 'Maliha', 'Nahid', 'Sediqa', 'Storai', 'Toorpekai', 'Zohra', 'Gulsoom', 'Shaima', 'Halima',
            ],
            'last' => [
                'Ahmadzai', 'Popal', 'Stanekzai', 'Wardak', 'Hakimi', 'Rahimi', 'Noori', 'Karimi', 'Sultani', 'Safi', 'Mohmand', 'Barakzai', 'Amiri', 'Haidari', 'Hashimi', 'Mohammadi',
                'Sadat', 'Yusufzai', 'Niazi', 'Jalali', 'Kakar', 'Rezaei', 'Achakzai', 'Afridi', 'Ahmadi', 'Akbari', 'Alizai', 'Alokozai', 'Amarkhel', 'Aminzai', 'Andar', 'Ansari',
                'Azimi', 'Babakarkhel', 'Bahrami', 'Baheer', 'Balkhi', 'Bamyani', 'Daudzai', 'Durrani', 'Ebrahimi', 'Faizi', 'Faqiri', 'Farahi', 'Ghaznawi', 'Ghilzai', 'Ghori',
                'Habibi', 'Hamdard', 'Hamidi', 'Haqmal', 'Hotak', 'Hussaini', 'Ibrahimkhel', 'Ishaqzai', 'Jabarkhel', 'Jaji', 'Jawadi', 'Kamali', 'Karokhel', 'Kazemi', 'Kharoti',
                'Khogyani', 'Khostai', 'Kohistani', 'Kunduzi', 'Laghmani', 'Lodin', 'Logari', 'Mahmoodi', 'Maiwandi', 'Mangal', 'Mansoori', 'Mayar', 'Mirzai', 'Momand', 'Muradi',
                'Naseri', 'Nazari', 'Noorzai', 'Nuristani', 'Omari', 'Omarkhel', 'Paktiawal', 'Panjshiri', 'Parwani', 'Payenda', 'Qaderi', 'Qaderdan', 'Qasimi', 'Rasooli', 'Sabawoon',
                'Sadiqi', 'Safari', 'Sahak', 'Salarzai', 'Samadi', 'Sarwari', 'Shinwari', 'Sherzad', 'Sherzai', 'Shirzad', 'Siddiqi', 'Stanikzai', 'Sulaimankhel', 'Tahiri', 'Tarin',
                'Tokhi', 'Totakhel', 'Wafa', 'Wahidi', 'Waziri', 'Yaqubi', 'Zadran', 'Zaheer', 'Zamani', 'Zazai', 'Zurmati', 'Andarabi', 'Badakhshi', 'Baghlani', 'Faryabi', 'Herawi',
                'Jowzjani', 'Kandahari', 'Nangarhari', 'Takhari', 'Samangani', 'Uruzgani', 'Helmandi', 'Daikundi', 'Hazara', 'Tajik', 'Turkmani', 'Aimaq', 'Baluch', 'Pashai', 'Sayedi',
                'Mousavi', 'Alavi', 'Naderi', 'Akhlaqi', 'Danish', 'Erfani', 'Fayaz', 'Sharifi', 'Tawakoli',
            ],
            'places' => ['Kabul', 'Kandahar', 'Herat', 'Mazar-i-Sharif', 'Jalalabad', 'Kunduz', 'Ghazni', 'Lashkar Gah', 'Bamyan', 'Khost', 'Gardez', 'Taloqan'],
        ],
        'LEBANON' => [
            'm' => [
                'Elie', 'Georges', 'Charbel', 'Tony', 'Joseph', 'Pierre', 'Michel', 'Antoine', 'Fadi', 'Ziad', 'Rabih', 'Wassim', 'Jad', 'Karim', 'Nadim', 'Marwan', 'Ghassan', 'Hadi',
                'Hussein', 'Bilal', 'Rami', 'Chadi', 'Maroun', 'Boutros', 'Hanna', 'Elias', 'Roger', 'Walid', 'Samer', 'Bassel', 'Kamil', 'Raymond', 'Selim', 'Toufic', 'Wadih',
                'Youssef', 'Naji', 'Mazen', 'Imad', 'Abbas', 'Ali', 'Mohammed', 'Ahmed', 'Hassan', 'Omar', 'Khalid', 'Mustafa', 'Ibrahim', 'Yousef', 'Mahmoud', 'Abdullah', 'Samir',
                'Tarek', 'Nabil', 'Faisal', 'Adnan', 'Bassam', 'Sami', 'Anas', 'Othman', 'Jamil', 'Rafik', 'Hamza', 'Yasin', 'Saif', 'Zaid', 'Ammar', 'Yaser', 'Kareem', 'Majid',
                'Nasser', 'Salim', 'Salem', 'Saleh', 'Rashid', 'Fahd', 'Fouad', 'Adel', 'Akram', 'Ayman', 'Bashir', 'Bakr', 'Daoud', 'Emad', 'Essam', 'Fadel', 'Fares', 'Fawzi', 'Ghazi',
                'Habib', 'Hafez', 'Haitham', 'Hakim', 'Hani', 'Hatem', 'Hisham', 'Idris', 'Ihab', 'Ismail', 'Issa', 'Jaber', 'Jalal', 'Jamal', 'Jawad', 'Kamal', 'Kamel', 'Khalil',
                'Lutfi', 'Maher', 'Mahdi', 'Mansour', 'Mounir', 'Mourad', 'Munther', 'Musa', 'Nader', 'Naim', 'Najib', 'Nazih', 'Nizar', 'Qasim', 'Qusay', 'Raed', 'Rafiq', 'Raja',
                'Ramzi', 'Rashad', 'Riad', 'Sabri', 'Sadiq', 'Safwan', 'Sharif', 'Shadi', 'Shaker', 'Sufyan', 'Suhail', 'Sultan', 'Taha', 'Taher', 'Talal', 'Tamer', 'Thamer', 'Wael',
                'Wajdi', 'Wasim', 'Yahya', 'Younes', 'Zaki', 'Zakaria', 'Zuhair', 'Abdulaziz', 'Abdulkarim', 'Abdulrahman', 'Abdulhadi', 'Abdulmajid', 'Abdulsalam', 'Abdulwahab',
                'Anwar', 'Aref', 'Asaad', 'Ashraf', 'Atef', 'Aws', 'Badr', 'Burhan', 'Dhia', 'Hazem', 'Hilal', 'Hussam', 'Iyad', 'Louay', 'Mamoun', 'Moataz', 'Mohannad', 'Muthanna',
                'Nawaf', 'Nidal', 'Numan', 'Radwan', 'Raouf', 'Saad', 'Sabah', 'Salah', 'Shafiq', 'Subhi', 'Tawfiq', 'Thabit', 'Wissam', 'Yaqoub', 'Zaher',
            ],
            'f' => [
                'Rita', 'Nadine', 'Carla', 'Maya', 'Rima', 'Joumana', 'Ghada', 'Zeina', 'Layal', 'Mirna', 'Nayla', 'Pascale', 'Rola', 'Sabine', 'Tania', 'Yara', 'Cynthia', 'Christelle',
                'Elissar', 'Hiba', 'Josiane', 'Karen', 'Lara', 'Marianne', 'Nour', 'Racha', 'Stephanie', 'Vera', 'Batoul', 'Fatme', 'Fatima', 'Zainab', 'Maryam', 'Noor', 'Huda', 'Sara',
                'Dalia', 'Aya', 'Iman', 'Zahra', 'Sana', 'Layla', 'Amal', 'Asma', 'Hana', 'Salma', 'Nada', 'Rania', 'Khadija', 'Aisha', 'Lina', 'Hala', 'Rasha', 'Dima', 'Reem', 'Lama',
                'Heba', 'Nadia', 'Mona', 'Bushra', 'Samira', 'Nawal', 'Suad', 'Wafa', 'Widad', 'Yasmin', 'Zeinab', 'Abeer', 'Afaf', 'Ahlam', 'Alia', 'Amani', 'Amira', 'Arwa', 'Basma',
                'Dalal', 'Dunya', 'Duha', 'Faten', 'Fadwa', 'Farah', 'Feryal', 'Hadeel', 'Hanan', 'Hayat', 'Hind', 'Ibtisam', 'Ikram', 'Inas', 'Intisar', 'Jamila', 'Jihan', 'Jumana',
                'Kawthar', 'Khawla', 'Lamia', 'Latifa', 'Lubna', 'Maha', 'Majida', 'Malak', 'Manal', 'Marwa', 'May', 'Maysa', 'Mervat', 'Nabila', 'Nahla', 'Najwa', 'Nisreen', 'Nuha',
                'Raghad', 'Rahma', 'Rana', 'Randa', 'Rawan', 'Ruba', 'Sabah', 'Safa', 'Safiya', 'Sahar', 'Sajida', 'Sawsan', 'Shaima', 'Shatha', 'Siham', 'Suha', 'Sumaya', 'Tahani',
                'Tamara', 'Thuraya', 'Wijdan', 'Yusra', 'Zakia', 'Zina', 'Afnan', 'Areej', 'Bayan', 'Ghadeer', 'Hajar', 'Israa', 'Jana', 'Joud', 'Lujain', 'Nour al-Huda', 'Ruqaya',
                'Shahd', 'Tasneem', 'Walaa',
            ],
            'last' => [
                'Khoury', 'Haddad', 'Saad', 'Nassar', 'Karam', 'Sfeir', 'Hayek', 'Rizk', 'Chidiac', 'Abi Nader', 'Abou Jaoude', 'Azar', 'Bitar', 'Daher', 'Eid', 'Fakhoury', 'Farah',
                'Ghanem', 'Habib', 'Jabbour', 'Kanaan', 'Keyrouz', 'Khalil', 'Maalouf', 'Mansour', 'Matar', 'Mouawad', 'Najjar', 'Nasr', 'Rahme', 'Raad', 'Saliba', 'Sarkis', 'Tannous',
                'Tohme', 'Yammine', 'Younes', 'Zgheib', 'Zogheib', 'Abdallah', 'Ayoub', 'Baz', 'Boustani', 'Chahine', 'Dagher', 'Dib', 'Fares', 'Ghosn', 'Harb', 'Helou', 'Issa',
                'Jreige', 'Kassis', 'Makhoul', 'Mouannes', 'Nakhle', 'Obeid', 'Rahal', 'Salloum', 'Semaan', 'Skaff', 'Tabet', 'Tarabay', 'Wehbe', 'Zakhour', 'Atwi', 'Bazzi', 'Charara',
                'Dakroub', 'Fneish', 'Hamdan', 'Hammoud', 'Jaber', 'Kanso', 'Kobeissi', 'Mroueh', 'Noureddine', 'Saleh', 'Sbeiti', 'Shams', 'Srour', 'Zeaiter', 'Itani', 'Kabbani',
                'Sinno', 'Tabbara', 'Yamout', 'Daouk', 'Jisr', 'Kronfol', 'Safadi', 'Sidani', 'Hamadeh', 'Abou Faour', 'Talhouk', 'Abi Saab', 'Al Awar', 'Bou Hamdan', 'Halabi',
                'Zeineddine', 'Bedrossian', 'Demirjian', 'Kazanjian', 'Sarkissian',
            ],
            'places' => ['Beirut', 'Tripoli', 'Sidon', 'Tyre', 'Zahle', 'Jounieh', 'Baalbek', 'Nabatieh', 'Byblos', 'Aley', 'Batroun', 'Halba'],
        ],
        'YEMEN' => [
            'm' => [
                'Mohammed', 'Ahmed', 'Ali', 'Abdullah', 'Saleh', 'Abdulrahman', 'Hamoud', 'Fuad', 'Nabil', 'Waleed', 'Khaled', 'Ibrahim', 'Yahya', 'Ammar', 'Abdulkarim', 'Mansour',
                'Hussein', 'Adel', 'Abdulmalik', 'Abdulghani', 'Abdulqawi', 'Aidarous', 'Akram', 'Arafat', 'Bakil', 'Dhaifallah', 'Fahmi', 'Ghaleb', 'Hamdi', 'Hussain', 'Jubran',
                'Mabkhout', 'Mujahid', 'Murad', 'Nashwan', 'Qaid', 'Radfan', 'Sadeq', 'Shawqi', 'Tawfik', 'Wadhah', 'Yaslam', 'Zayed', 'Abdo', 'Ahmad Ali', 'Naji', 'Muhsin', 'Hizam',
                'Thabet', 'Hassan', 'Omar', 'Khalid', 'Mustafa', 'Yousef', 'Mahmoud', 'Karim', 'Samir', 'Tarek', 'Walid', 'Bilal', 'Faisal', 'Adnan', 'Ziad', 'Bassam', 'Marwan', 'Sami',
                'Anas', 'Othman', 'Jamil', 'Rafik', 'Hamza', 'Yasin', 'Saif', 'Zaid', 'Yaser', 'Kareem', 'Majid', 'Nasser', 'Salim', 'Salem', 'Rashid', 'Fahd', 'Fouad', 'Ayman',
                'Bashir', 'Bakr', 'Daoud', 'Emad', 'Essam', 'Fadel', 'Fares', 'Fawzi', 'Ghazi', 'Habib', 'Hadi', 'Hafez', 'Haitham', 'Hakim', 'Hani', 'Hatem', 'Hisham', 'Idris', 'Ihab',
                'Imad', 'Ismail', 'Issa', 'Jaber', 'Jalal', 'Jamal', 'Jawad', 'Kamal', 'Kamel', 'Khalil', 'Lutfi', 'Maher', 'Mahdi', 'Mazen', 'Mounir', 'Mourad', 'Munther', 'Musa',
                'Nader', 'Nadim', 'Naim', 'Najib', 'Nazih', 'Nizar', 'Qasim', 'Qusay', 'Raed', 'Rafiq', 'Raja', 'Rami', 'Ramzi', 'Rashad', 'Riad', 'Sabri', 'Sadiq', 'Safwan', 'Sharif',
                'Shadi', 'Shaker', 'Sufyan', 'Suhail', 'Sultan', 'Taha', 'Taher', 'Talal', 'Tamer', 'Thamer', 'Wael', 'Wajdi', 'Wasim', 'Younes', 'Zaki', 'Zakaria', 'Zuhair',
                'Abdulaziz', 'Abdulhadi', 'Abdulmajid', 'Abdulsalam', 'Abdulwahab', 'Anwar', 'Aref', 'Asaad', 'Ashraf', 'Atef', 'Aws', 'Badr', 'Burhan', 'Dhia', 'Ghassan', 'Hazem',
                'Hilal', 'Hussam', 'Iyad', 'Louay', 'Mamoun', 'Moataz', 'Mohannad', 'Muthanna', 'Nawaf', 'Nidal', 'Numan', 'Radwan', 'Raouf', 'Saad', 'Sabah', 'Salah', 'Shafiq',
                'Subhi', 'Tawfiq', 'Thabit', 'Wissam', 'Yaqoub', 'Zaher',
            ],
            'f' => [
                'Fatima', 'Amal', 'Arwa', 'Bushra', 'Huda', 'Nadia', 'Afrah', 'Ibtisam', 'Sumaya', 'Asma', 'Mona', 'Rasha', 'Bilqis', 'Hana', 'Khadija', 'Zainab', 'Altaf', 'Ashwaq',
                'Atiqa', 'Belqees', 'Dhikra', 'Fathiya', 'Gamila', 'Hooria', 'Intisar', 'Jalila', 'Kifah', 'Lutfiya', 'Maeen', 'Nabiha', 'Raufa', 'Sabriya', 'Shadha', 'Taqiya', 'Ulfat',
                'Wahiba', 'Yumna', 'Zahra', 'Takwa', 'Maryam', 'Noor', 'Sara', 'Hiba', 'Dalia', 'Aya', 'Iman', 'Sana', 'Layla', 'Salma', 'Nada', 'Rania', 'Aisha', 'Lina', 'Hala',
                'Dima', 'Reem', 'Lama', 'Maya', 'Yara', 'Heba', 'Ghada', 'Samira', 'Nawal', 'Suad', 'Wafa', 'Widad', 'Yasmin', 'Zeinab', 'Abeer', 'Afaf', 'Ahlam', 'Alia', 'Amani',
                'Amira', 'Basma', 'Dalal', 'Dunya', 'Duha', 'Faten', 'Fadwa', 'Farah', 'Feryal', 'Hadeel', 'Hanan', 'Hayat', 'Hind', 'Ikram', 'Inas', 'Jamila', 'Jihan', 'Jumana',
                'Kawthar', 'Khawla', 'Lamia', 'Latifa', 'Lubna', 'Maha', 'Majida', 'Malak', 'Manal', 'Marwa', 'May', 'Maysa', 'Mervat', 'Nabila', 'Nahla', 'Najwa', 'Nisreen', 'Nuha',
                'Raghad', 'Rahma', 'Rana', 'Randa', 'Rawan', 'Rima', 'Ruba', 'Sabah', 'Safa', 'Safiya', 'Sahar', 'Sajida', 'Sawsan', 'Shaima', 'Shatha', 'Siham', 'Suha', 'Tahani',
                'Tamara', 'Thuraya', 'Wijdan', 'Yusra', 'Zakia', 'Zina', 'Afnan', 'Areej', 'Bayan', 'Ghadeer', 'Hajar', 'Israa', 'Jana', 'Joud', 'Lujain', 'Nour al-Huda', 'Ruqaya',
                'Shahd', 'Tasneem', 'Walaa',
            ],
            'last' => [
                'al-Hamdani', 'al-Sanani', 'al-Adeni', 'al-Hadrami', 'al-Maqtari', 'al-Shaibani', 'al-Qubati', 'al-Absi', 'al-Yafei', 'al-Mikhlafi', 'Saeed', 'al-Haddad', 'al-Kibsi',
                'al-Aghbari', 'al-Sharabi', 'Bamatraf', 'Basalem', 'al-Kaff', 'al-Waziri', 'al-Junaid', 'al-Dhubhani', 'al-Sururi', 'al-Amrani', 'al-Arhabi', 'al-Baidhani',
                'al-Dhamari', 'al-Dhalei', 'al-Hajri', 'al-Hakimi', 'al-Harazi', 'al-Hubaishi', 'al-Ibbi', 'al-Jaadi', 'al-Jawfi', 'al-Khawlani', 'al-Mahwiti', 'al-Maribi', 'al-Muradi',
                'al-Nahdi', 'al-Qadhi', 'al-Rubaidi', 'al-Sabri', 'al-Saqqaf', 'al-Shami', 'al-Shargabi', 'al-Taizi', 'al-Udaini', 'al-Wesabi', 'al-Yarimi', 'al-Zubairi', 'al-Zaidi',
                'al-Ansi', 'al-Ashwal', 'al-Asbahi', 'al-Dailami', 'al-Faqih', 'al-Ghurbani', 'al-Himyari', 'al-Jaifi', 'al-Jarmouzi', 'al-Kuhlani', 'al-Maamari', 'al-Madhaji',
                'al-Marwani', 'al-Moayad', 'al-Nuzaili', 'al-Qaifi', 'al-Qirbi', 'al-Ruwaishan', 'al-Samawi', 'al-Sharafi', 'al-Shawkani', 'al-Sufi', 'al-Thawr', 'al-Wajih', 'Baabbad',
                'Baharoon', 'Bajubair', 'Bakhashwain', 'Balfaqih', 'Bamakhrama', 'Banaja', 'Baobaid', 'Basharahil', 'Bawazir', 'Bin Shamlan', 'Bin Talib', 'Bin Yahya', 'Bahashwan',
                'Bazara', 'al-Attas', 'al-Aidarous', 'al-Habshi', 'al-Jifri', 'al-Mihdhar', 'al-Shatiri', 'Noman', 'Qahtan', 'Muqbil', 'Thabit', 'Ghalib', 'Hizam', 'Mujalli', 'Saif',
                'Farea', 'Dabwan', 'Shamsan', 'Qasim', 'Murshid', 'Rajeh', 'Obad', 'Humaid', 'Shuja', 'Dughaish', 'Jahaf', 'Abu Ras',
            ],
            'places' => ['Sanaa', 'Aden', 'Taiz', 'Hodeidah', 'Ibb', 'Mukalla', 'Dhamar', 'Seiyun', 'Amran', 'Marib', 'Saada', 'Hajjah'],
        ],
        'LIBYA' => [
            'm' => [
                'Mohammed', 'Ahmed', 'Ali', 'Abdulrahman', 'Mustafa', 'Khaled', 'Omar', 'Salem', 'Faraj', 'Mahmoud', 'Ibrahim', 'Abdullah', 'Hamza', 'Tarek', 'Osama', 'Abubakr',
                'Hisham', 'Anas', 'Abdulbaset', 'Abdulhakim', 'Abdulmonem', 'Abdulnaser', 'Agila', 'Aiman', 'Ashour', 'Awad', 'Belgasem', 'Bubaker', 'Emhemed', 'Ezzedin', 'Fathi',
                'Gaith', 'Hamad', 'Hasan', 'Idris', 'Jibril', 'Khalifa', 'Mabrouk', 'Masoud', 'Miftah', 'Milad', 'Muftah', 'Naji', 'Ramadan', 'Saadi', 'Saleh', 'Senussi', 'Suleiman',
                'Taher', 'Wanis', 'Younis', 'Zaid', 'Moftah', 'Abdelati', 'Hassan', 'Hussein', 'Khalid', 'Yousef', 'Karim', 'Samir', 'Walid', 'Nabil', 'Bilal', 'Faisal', 'Adnan',
                'Ziad', 'Bassam', 'Marwan', 'Sami', 'Othman', 'Jamil', 'Rafik', 'Yasin', 'Saif', 'Ammar', 'Yaser', 'Kareem', 'Majid', 'Nasser', 'Salim', 'Rashid', 'Fahd', 'Fouad',
                'Adel', 'Akram', 'Ayman', 'Bashir', 'Bakr', 'Daoud', 'Emad', 'Essam', 'Fadel', 'Fares', 'Fawzi', 'Ghazi', 'Habib', 'Hadi', 'Hafez', 'Haitham', 'Hakim', 'Hani', 'Hatem',
                'Ihab', 'Imad', 'Ismail', 'Issa', 'Jaber', 'Jalal', 'Jamal', 'Jawad', 'Kamal', 'Kamel', 'Khalil', 'Lutfi', 'Maher', 'Mahdi', 'Mansour', 'Mazen', 'Mounir', 'Mourad',
                'Munther', 'Musa', 'Nader', 'Nadim', 'Naim', 'Najib', 'Nazih', 'Nizar', 'Qasim', 'Qusay', 'Raed', 'Rafiq', 'Raja', 'Rami', 'Ramzi', 'Rashad', 'Riad', 'Sabri', 'Sadiq',
                'Safwan', 'Sharif', 'Shadi', 'Shaker', 'Sufyan', 'Suhail', 'Sultan', 'Taha', 'Talal', 'Tamer', 'Thamer', 'Wael', 'Wajdi', 'Wasim', 'Yahya', 'Younes', 'Zaki', 'Zakaria',
                'Zuhair', 'Abdulaziz', 'Abdulkarim', 'Abdulhadi', 'Abdulmajid', 'Abdulsalam', 'Abdulwahab', 'Anwar', 'Aref', 'Asaad', 'Ashraf', 'Atef', 'Aws', 'Badr', 'Burhan', 'Dhia',
                'Ghassan', 'Hazem', 'Hilal', 'Hussam', 'Iyad', 'Louay', 'Mamoun', 'Moataz', 'Mohannad', 'Muthanna', 'Nawaf', 'Nidal', 'Numan', 'Radwan', 'Raouf', 'Saad', 'Sabah',
                'Salah', 'Shafiq', 'Subhi', 'Tawfiq', 'Thabit', 'Wissam', 'Yaqoub', 'Zaher',
            ],
            'f' => [
                'Fatima', 'Aisha', 'Khadija', 'Amal', 'Asma', 'Hana', 'Mabrouka', 'Salma', 'Nada', 'Sara', 'Rania', 'Huda', 'Iman', 'Nour', 'Mariam', 'Fatma', 'Aziza', 'Fawzia',
                'Ghalia', 'Hawa', 'Intisar', 'Jazia', 'Karima', 'Khairia', 'Masouda', 'Naima', 'Najat', 'Rabia', 'Salha', 'Salima', 'Tibra', 'Wafia', 'Zahia', 'Zakia', 'Zohra',
                'Halima', 'Mufida', 'Hamida', 'Ebtisam', 'Entisar', 'Ghazala', 'Zainab', 'Maryam', 'Noor', 'Hiba', 'Dalia', 'Aya', 'Zahra', 'Sana', 'Layla', 'Lina', 'Hala', 'Rasha',
                'Dima', 'Reem', 'Lama', 'Maya', 'Yara', 'Heba', 'Ghada', 'Nadia', 'Mona', 'Bushra', 'Samira', 'Nawal', 'Suad', 'Wafa', 'Widad', 'Yasmin', 'Zeinab', 'Abeer', 'Afaf',
                'Ahlam', 'Alia', 'Amani', 'Amira', 'Arwa', 'Basma', 'Dalal', 'Dunya', 'Duha', 'Faten', 'Fadwa', 'Farah', 'Feryal', 'Hadeel', 'Hanan', 'Hayat', 'Hind', 'Ibtisam',
                'Ikram', 'Inas', 'Jamila', 'Jihan', 'Jumana', 'Kawthar', 'Khawla', 'Lamia', 'Latifa', 'Lubna', 'Maha', 'Majida', 'Malak', 'Manal', 'Marwa', 'May', 'Maysa', 'Mervat',
                'Nabila', 'Nahla', 'Najwa', 'Nisreen', 'Nuha', 'Raghad', 'Rahma', 'Rana', 'Randa', 'Rawan', 'Rima', 'Ruba', 'Sabah', 'Safa', 'Safiya', 'Sahar', 'Sajida', 'Sawsan',
                'Shaima', 'Shatha', 'Siham', 'Suha', 'Sumaya', 'Tahani', 'Tamara', 'Thuraya', 'Wijdan', 'Yusra', 'Zina', 'Afnan', 'Areej', 'Bayan', 'Ghadeer', 'Hajar', 'Israa', 'Jana',
                'Joud', 'Lujain', 'Nour al-Huda', 'Ruqaya', 'Shahd', 'Tasneem', 'Walaa',
            ],
            'last' => [
                'al-Zawi', 'Ben Ali', 'al-Mismari', 'al-Fitouri', 'al-Barassi', 'al-Tarhouni', 'al-Mahmoudi', 'Abuzaid', 'al-Shibani', 'Bugaighis', 'Elmansouri', 'al-Ferjani',
                'al-Masrati', 'al-Ghadamsi', 'al-Sharif', 'al-Zintani', 'Abushaala', 'al-Kabir', 'al-Hassi', 'al-Turki', 'Shalabi', 'al-Warfalli', 'al-Obeidi', 'al-Awami',
                'al-Maghrabi', 'al-Darsi', 'al-Mugharbi', 'al-Qatrani', 'al-Majbri', 'al-Zwai', 'al-Tabawi', 'al-Fezzani', 'al-Sabhawi', 'al-Gharyani', 'al-Nalouti', 'al-Jadui',
                'al-Zuwari', 'al-Khumsi', 'al-Zlitni', 'al-Sirtawi', 'al-Jufri', 'al-Hasnawi', 'al-Sharkasi', 'al-Mukhtar', 'al-Baruni', 'al-Naas', 'al-Faitouri', 'al-Aswad',
                'al-Abyad', 'al-Tajouri', 'al-Gatrani', 'al-Lafi', 'al-Huni', 'al-Orfi', 'al-Abbar', 'al-Agouri', 'al-Drissi', 'al-Mesmari', 'al-Saiti', 'al-Shaeri', 'al-Shwehdi',
                'Abudabbous', 'Abufanas', 'Abughula', 'Abujazia', 'Abukhzam', 'Abuzakouk', 'Ben Amer', 'Ben Ashour', 'Ben Ghalbon', 'Ben Halim', 'Ben Khayal', 'Ben Omran', 'Ben Othman',
                'Ben Saud', 'Ben Shaaban', 'Ben Younis', 'Bushnaf', 'Buera', 'Bukatif', 'Elbadri', 'Elfeituri', 'Elgazali', 'Elhouni', 'Ellafi', 'Elmahdi', 'Elsahli', 'Eltarhoni',
                'Gnaidi', 'Gwaider', 'Hwili', 'Jehani', 'Krekshi', 'Madi', 'Mletan', 'Naker', 'Shtewi', 'Swessi', 'Tantoush', 'Zaptia', 'Zletni', 'Zubi',
            ],
            'places' => ['Tripoli', 'Benghazi', 'Misrata', 'Zawiya', 'Bayda', 'Tobruk', 'Sabha', 'Zliten', 'Sirte', 'Ajdabiya', 'Derna', 'Gharyan'],
        ],
        'MALI' => [
            'm' => [
                'Mamadou', 'Moussa', 'Ibrahim', 'Boubacar', 'Seydou', 'Amadou', 'Oumar', 'Bakary', 'Modibo', 'Souleymane', 'Adama', 'Drissa', 'Abdoulaye', 'Sidiki', 'Lassana',
                'Youssouf', 'Cheick', 'Issa', 'Bréhima', 'Kassoum', 'Madani', 'Mahamadou', 'Mamady', 'Namory', 'N\'Faly', 'Sambou', 'Soumaïla', 'Tiécoura', 'Zan', 'Bandiougou',
                'Diakaridia', 'Fodé', 'Kalifa', 'Makan', 'Moriba', 'Sory', 'Tiéman', 'Wali', 'Alhader', 'Hamza', 'Idrissa', 'Aboubacar', 'Sidi', 'Ali', 'Hamidou', 'Harouna', 'Yacouba',
                'Ousmane', 'Abdou', 'Aliou', 'Alassane', 'Almamy', 'Amidou', 'Arouna', 'Badara', 'Baba', 'Balla', 'Bassirou', 'Bourama', 'Dramane', 'Fousseyni', 'Gaoussou', 'Habib',
                'Hamadoun', 'Ismaël', 'Kalilou', 'Karim', 'Kassim', 'Lamine', 'Madou', 'Mahamane', 'Malick', 'Mamoutou', 'Mohamed', 'Moctar', 'Mory', 'Nouhoum', 'Oumarou', 'Salif',
                'Samba', 'Sékou', 'Siaka', 'Tidiane', 'Tiémoko', 'Yaya', 'Zakaria', 'Zoumana', 'Abdramane', 'Aguibou', 'Almoustapha', 'Attaher', 'Bilal', 'Daouda', 'Djibril', 'Hassane',
                'Housseini', 'Mahmoud', 'Nouhou', 'Saïdou', 'Sanoussi', 'Tahirou', 'Younoussa', 'Abdourahamane', 'Aboubakar', 'Bachir', 'Elhadj', 'Habibou', 'Inoussa', 'Kader',
                'Mounkaila', 'Rachid', 'Sadou', 'Tanimoune', 'Yahaya', 'Zakari', 'Alhassane', 'Boureima', 'Hamma', 'Mossa', 'Alhousseini', 'Rhissa',
            ],
            'f' => [
                'Fatoumata', 'Aminata', 'Mariam', 'Kadiatou', 'Awa', 'Oumou', 'Rokia', 'Assitan', 'Djeneba', 'Hawa', 'Bintou', 'Salimata', 'Kadidia', 'Nana', 'Aïssata', 'Korotoumou',
                'Batoma', 'Djénéba', 'Nakany', 'Niagalé', 'Sanata', 'Sayon', 'Téné', 'Wassa', 'Yiriba', 'Nana Kadidia', 'Tahara', 'Sata', 'Mama', 'Goundo', 'Maïmouna', 'Founè',
                'Aïchatou', 'Mariama', 'Fati', 'Hadiza', 'Rakia', 'Zeinabou', 'Halima', 'Ramatou', 'Safiatou', 'Balkissa', 'Adja', 'Alimatou', 'Aminatou', 'Assa', 'Bana', 'Coumba',
                'Dado', 'Diahara', 'Djelika', 'Fanta', 'Fatouma', 'Habibatou', 'Hadja', 'Haoua', 'Hassana', 'Kadia', 'Kani', 'Khadija', 'Lalla', 'Mah', 'Maman', 'Massaran',
                'Nafissatou', 'Nènè', 'Niamoye', 'Oumou Kaltoum', 'Ouleymatou', 'Penda', 'Ramata', 'Sadio', 'Saran', 'Sira', 'Sitan', 'Tata', 'Tenin', 'Yaye', 'Zara', 'Zeinab', 'Aïssa',
                'Biba', 'Dicko', 'Fadimata', 'Hindou', 'Jamila', 'Leila', 'Nafissa', 'Rabi', 'Rahinatou', 'Roukaya', 'Saadatou', 'Salamatou', 'Samira', 'Zalika', 'Zouera', 'Achta',
                'Amina', 'Falmata', 'Hapsatou', 'Kaltouma', 'Khadidja', 'Zenaba',
            ],
            'last' => [
                'Traoré', 'Diarra', 'Coulibaly', 'Keïta', 'Konaté', 'Sissoko', 'Touré', 'Diallo', 'Camara', 'Sidibé', 'Doumbia', 'Sangaré', 'Maïga', 'Cissé', 'Dembélé', 'Kanté', 'Koné',
                'Sanogo', 'Dicko', 'Bah', 'Haïdara', 'Samaké', 'Bagayoko', 'Ballo', 'Bamba', 'Berthé', 'Bouaré', 'Dao', 'Daou', 'Diabaté', 'Diakité', 'Diawara', 'Diop', 'Dolo', 'Fané',
                'Fofana', 'Gakou', 'Guindo', 'Kamissoko', 'Kansaye', 'Karembé', 'Kassogué', 'Kéita', 'Kodio', 'Koïta', 'Kouma', 'Kouyaté', 'Ly', 'Magassa', 'Mariko', 'Minta',
                'N\'Diaye', 'Niang', 'Niaré', 'Ongoïba', 'Ouattara', 'Ouologuem', 'Poudiougou', 'Sacko', 'Sagara', 'Samassékou', 'Sanou', 'Sarr', 'Sidibe', 'Simpara', 'Sinayoko',
                'Soumaoro', 'Soumaré', 'Sow', 'Sylla', 'Tall', 'Tamboura', 'Tangara', 'Tembely', 'Tessougué', 'Thiam', 'Timbiné', 'Togo', 'Togola', 'Tolo', 'Tounkara', 'Wagué',
                'Yattara', 'Zerbo', 'Ag Mohamed', 'Ag Ahmed', 'Ag Ibrahim', 'Ag Alhassane', 'Ag Hamma', 'Ag Attaher', 'Ag Rhissa', 'Ag Sidi', 'Ould Mohamed', 'Ould Sidi', 'Ould Ahmed',
                'Ould Cheikh', 'Cissouma', 'Dagnoko', 'Dembaga', 'Djiré', 'Gassama', 'Kébé', 'Macalou', 'Niakaté', 'Soukouna', 'Timbo', 'Wadidié', 'Yalcouyé', 'Banou', 'Bocoum',
                'Cissoko', 'Dabo', 'Danioko', 'Diané', 'Kida',
            ],
            'places' => ['Bamako', 'Sikasso', 'Ségou', 'Mopti', 'Kayes', 'Koutiala', 'Gao', 'Tombouctou', 'Kidal', 'San', 'Koulikoro', 'Kati'],
        ],
        'NIGER' => [
            'm' => [
                'Moussa', 'Abdou', 'Issoufou', 'Ibrahim', 'Amadou', 'Hamidou', 'Souley', 'Mahamadou', 'Idrissa', 'Abdoulaye', 'Oumarou', 'Boubacar', 'Ali', 'Sani', 'Harouna', 'Yacouba',
                'Salissou', 'Moctar', 'Mahamane', 'Maman', 'Laouali', 'Ibro', 'Illa', 'Garba', 'Djibo', 'Chaibou', 'Boukari', 'Bachirou', 'Adamou', 'Abdoul Aziz', 'Hama', 'Kimba',
                'Moumouni', 'Nassirou', 'Rabiou', 'Seyni', 'Zabeirou', 'Ghoumour', 'Agali', 'Rhissa', 'Oumar', 'Seydou', 'Mamadou', 'Issa', 'Bakary', 'Cheick', 'Modibo', 'Youssouf',
                'Souleymane', 'Hamza', 'Aboubacar', 'Sidi', 'Lassana', 'Adama', 'Drissa', 'Sidiki', 'Ousmane', 'Aliou', 'Alassane', 'Almamy', 'Amidou', 'Arouna', 'Badara', 'Baba',
                'Balla', 'Bassirou', 'Bourama', 'Dramane', 'Fousseyni', 'Gaoussou', 'Habib', 'Hamadoun', 'Ismaël', 'Kalilou', 'Karim', 'Kassim', 'Lamine', 'Madou', 'Malick', 'Mamoutou',
                'Mohamed', 'Mory', 'Nouhoum', 'Salif', 'Samba', 'Sékou', 'Siaka', 'Tidiane', 'Tiémoko', 'Yaya', 'Zakaria', 'Zoumana', 'Abdramane', 'Aguibou', 'Almoustapha', 'Attaher',
                'Bilal', 'Daouda', 'Djibril', 'Hassane', 'Housseini', 'Mahmoud', 'Nouhou', 'Saïdou', 'Sanoussi', 'Tahirou', 'Younoussa', 'Abdourahamane', 'Aboubakar', 'Bachir',
                'Elhadj', 'Habibou', 'Inoussa', 'Kader', 'Mounkaila', 'Rachid', 'Sadou', 'Soumaïla', 'Tanimoune', 'Yahaya', 'Zakari', 'Alhassane', 'Boureima', 'Hamma', 'Mossa',
                'Alhousseini',
            ],
            'f' => [
                'Aïchatou', 'Mariama', 'Fati', 'Hadiza', 'Rakia', 'Zeinabou', 'Halima', 'Ramatou', 'Safiatou', 'Hawa', 'Balkissa', 'Aminatou', 'Fatouma', 'Habsatou', 'Salamatou',
                'Nafissa', 'Hassia', 'Rabi', 'Zalika', 'Zouera', 'Saadatou', 'Roukaya', 'Hindou', 'Biba', 'Aïssa', 'Fadji', 'Tchima', 'Hadjara', 'Lami', 'Fatoumata', 'Aminata',
                'Mariam', 'Kadiatou', 'Awa', 'Oumou', 'Rokia', 'Assitan', 'Djeneba', 'Bintou', 'Salimata', 'Kadidia', 'Nana', 'Aïssata', 'Korotoumou', 'Adja', 'Alimatou', 'Assa',
                'Bana', 'Batoma', 'Coumba', 'Dado', 'Diahara', 'Djelika', 'Fanta', 'Habibatou', 'Hadja', 'Haoua', 'Hassana', 'Kadia', 'Kani', 'Khadija', 'Lalla', 'Maïmouna', 'Mah',
                'Maman', 'Massaran', 'Nafissatou', 'Nènè', 'Niamoye', 'Oumou Kaltoum', 'Ouleymatou', 'Penda', 'Ramata', 'Sadio', 'Saran', 'Sira', 'Sitan', 'Tata', 'Tenin', 'Yaye',
                'Zara', 'Zeinab', 'Dicko', 'Fadimata', 'Jamila', 'Leila', 'Rahinatou', 'Samira', 'Achta', 'Amina', 'Falmata', 'Hapsatou', 'Kaltouma', 'Khadidja', 'Zenaba',
            ],
            'last' => [
                'Abdou', 'Issa', 'Moussa', 'Mahamadou', 'Amadou', 'Hamani', 'Garba', 'Maïga', 'Souley', 'Boubacar', 'Idrissa', 'Oumarou', 'Sani', 'Harouna', 'Tahirou', 'Ousmane',
                'Adamou', 'Hassane', 'Djibo', 'Yacouba', 'Mamane', 'Saidou', 'Abdoulaye', 'Aboubacar', 'Alassane', 'Ali', 'Alio', 'Arouna', 'Bako', 'Barké', 'Boukari', 'Chaibou',
                'Dan Dano', 'Dan Malam', 'Daouda', 'Dodo', 'Elhadji', 'Gado', 'Gambo', 'Gaya', 'Habou', 'Halidou', 'Hamidou', 'Ibrahim', 'Illiassou', 'Inoussa', 'Issaka', 'Issoufou',
                'Kaka', 'Kané', 'Karimou', 'Kimba', 'Laouali', 'Lawan', 'Mahaman', 'Malam', 'Mamadou', 'Moumouni', 'Mounkaila', 'Nomao', 'Nouhou', 'Rabiou', 'Sabo', 'Salifou',
                'Salissou', 'Sanda', 'Seyni', 'Sidikou', 'Soumana', 'Tanko', 'Tinni', 'Younoussa', 'Zakari', 'Zakou', 'Zada', 'Ag Alhassane', 'Ag Mohamed', 'Ag Rhissa', 'Ag Ghoumour',
                'Ag Agali', 'Ag Elhadj', 'Dan Baba', 'Dan Ladi', 'Mai Moussa', 'Maï Daji', 'Abdourahamane', 'Adam', 'Albadé', 'Amani', 'Bagué', 'Cissé', 'Diallo', 'Gazibo', 'Hima',
                'Maman', 'Oumara', 'Sidibé', 'Wassiri', 'Yahaya', 'Abarchi', 'Almou', 'Assoumane', 'Dandobi', 'Djibrilla', 'Kalla', 'Massaoudou',
            ],
            'places' => ['Niamey', 'Zinder', 'Maradi', 'Agadez', 'Tahoua', 'Dosso', 'Diffa', 'Tillabéri', 'Arlit', 'Birni N\'Konni', 'Tessaoua', 'Gaya'],
        ],
        'CHAD' => [
            'm' => [
                'Mahamat', 'Abakar', 'Moussa', 'Ahmat', 'Hassan', 'Brahim', 'Youssouf', 'Haroun', 'Adoum', 'Saleh', 'Issa', 'Oumar', 'Abdelkerim', 'Djibrine', 'Ngarta', 'Djimadoum',
                'Nadjingar', 'Allarassem', 'Abdelaziz', 'Abderahim', 'Acheikh', 'Annour', 'Bichara', 'Dillo', 'Hissein', 'Idriss', 'Kebir', 'Nasser', 'Ousmane', 'Tahir', 'Togoï',
                'Zakaria', 'Bétoubam', 'Dingamadji', 'Mbaïnaïssem', 'Ndilyam', 'Nodjitoloum', 'Ronelyam', 'Djékoundadé', 'Laoukein', 'Madjirébaye', 'Ngarmadji', 'Tolngar', 'Amadou',
                'Ibrahim', 'Abdoulaye', 'Boubacar', 'Seydou', 'Mamadou', 'Bakary', 'Cheick', 'Modibo', 'Souleymane', 'Hamza', 'Idrissa', 'Aboubacar', 'Mahamadou', 'Sidi', 'Lassana',
                'Adama', 'Drissa', 'Sidiki', 'Ali', 'Hamidou', 'Harouna', 'Yacouba', 'Abdou', 'Aliou', 'Alassane', 'Almamy', 'Amidou', 'Arouna', 'Badara', 'Baba', 'Balla', 'Bassirou',
                'Bourama', 'Dramane', 'Fousseyni', 'Gaoussou', 'Habib', 'Hamadoun', 'Ismaël', 'Kalilou', 'Karim', 'Kassim', 'Lamine', 'Madou', 'Mahamane', 'Malick', 'Mamoutou',
                'Mohamed', 'Moctar', 'Mory', 'Nouhoum', 'Oumarou', 'Salif', 'Samba', 'Sékou', 'Siaka', 'Tidiane', 'Tiémoko', 'Yaya', 'Zoumana', 'Abdramane', 'Aguibou', 'Almoustapha',
                'Attaher', 'Bilal', 'Daouda', 'Djibril', 'Hassane', 'Housseini', 'Mahmoud', 'Nouhou', 'Saïdou', 'Sanoussi', 'Tahirou', 'Younoussa', 'Abdourahamane', 'Aboubakar',
                'Bachir', 'Elhadj', 'Habibou', 'Inoussa', 'Kader', 'Mounkaila', 'Rachid', 'Sadou', 'Soumaïla', 'Tanimoune', 'Yahaya', 'Zakari', 'Alhassane', 'Boureima', 'Hamma',
                'Mossa', 'Alhousseini', 'Rhissa',
            ],
            'f' => [
                'Achta', 'Fatimé', 'Hawa', 'Zara', 'Khadidja', 'Mariam', 'Halimé', 'Kaltouma', 'Amina', 'Asta', 'Djénéba', 'Rachel', 'Esther', 'Martine', 'Clarisse', 'Mariamé', 'Haoua',
                'Kadidja', 'Zenaba', 'Zouhoura', 'Falmata', 'Ardjoun', 'Bintou', 'Denemadji', 'Ndolembaye', 'Mémadji', 'Remadji', 'Solkem', 'Koumbo', 'Larlem', 'Nékarmbaye',
                'Fatoumata', 'Aminata', 'Kadiatou', 'Awa', 'Oumou', 'Rokia', 'Assitan', 'Djeneba', 'Salimata', 'Kadidia', 'Nana', 'Aïssata', 'Korotoumou', 'Aïchatou', 'Mariama', 'Fati',
                'Hadiza', 'Rakia', 'Zeinabou', 'Halima', 'Ramatou', 'Safiatou', 'Balkissa', 'Adja', 'Alimatou', 'Aminatou', 'Assa', 'Bana', 'Batoma', 'Coumba', 'Dado', 'Diahara',
                'Djelika', 'Fanta', 'Fatouma', 'Habibatou', 'Hadja', 'Hassana', 'Kadia', 'Kani', 'Khadija', 'Lalla', 'Maïmouna', 'Mah', 'Maman', 'Massaran', 'Nafissatou', 'Nènè',
                'Niamoye', 'Oumou Kaltoum', 'Ouleymatou', 'Penda', 'Ramata', 'Sadio', 'Saran', 'Sira', 'Sitan', 'Tata', 'Tenin', 'Yaye', 'Zeinab', 'Aïssa', 'Biba', 'Dicko', 'Fadimata',
                'Hindou', 'Jamila', 'Leila', 'Nafissa', 'Rabi', 'Rahinatou', 'Roukaya', 'Saadatou', 'Salamatou', 'Samira', 'Zalika', 'Zouera', 'Hapsatou',
            ],
            'last' => [
                'Mahamat', 'Abakar', 'Ahmat', 'Hassane', 'Youssouf', 'Haroun', 'Adoum', 'Saleh', 'Issa', 'Oumar', 'Brahim', 'Djibrine', 'Ngarta', 'Nadjingar', 'Allarassem', 'Djimadoum',
                'Ngaradoum', 'Moussa', 'Abdoulaye', 'Idriss', 'Abdelkerim', 'Abderahman', 'Ali', 'Alio', 'Annadif', 'Bachar', 'Barka', 'Béchir', 'Bichara', 'Djerou', 'Hissein',
                'Nassour', 'Orozi', 'Ourada', 'Souleymane', 'Tahir', 'Wardougou', 'Zène', 'Alladoum', 'Béassoum', 'Bédoum', 'Béndjo', 'Dadnadji', 'Djasnabaille', 'Dingamnayel',
                'Djimrangar', 'Doumgor', 'Gondjé', 'Koibla', 'Laoukoura', 'Madjimbaye', 'Mbaïlemdana', 'Mbaïramadji', 'Miandoum', 'Moungar', 'Nadjita', 'Naïmbaye', 'Nassarmadji',
                'Ndoubayo', 'Ngarndigui', 'Nodjigoto', 'Ratou', 'Rimadjita', 'Saboun', 'Yoadimnadji', 'Abdallah', 'Adam', 'Ahmed', 'Annour', 'Dagache', 'Doungous', 'Fadoul', 'Gadaya',
                'Hamid', 'Kodi', 'Liman', 'Outman', 'Rozi', 'Sougui', 'Tchéré', 'Yacoub', 'Zakaria', 'Beremadji', 'Djékila', 'Guelpina', 'Kladoumngar', 'Mbaïdessem', 'Ngardoum',
                'Reounodji', 'Tatola',
            ],
            'places' => ['N\'Djamena', 'Moundou', 'Sarh', 'Abéché', 'Kélo', 'Koumra', 'Pala', 'Am Timan', 'Bongor', 'Mongo', 'Doba', 'Ati'],
        ],
        'SOMALIA' => [
            'm' => [
                'Abdullahi', 'Mohamed', 'Ahmed', 'Abdirahman', 'Hassan', 'Ali', 'Omar', 'Abdi', 'Yusuf', 'Ibrahim', 'Mahad', 'Abdikarim', 'Hussein', 'Farah', 'Liban', 'Bashir', 'Said',
                'Mukhtar', 'Abdifatah', 'Abdihakim', 'Abdinasir', 'Abdiqadir', 'Abdirashid', 'Abdirizak', 'Abdisalam', 'Abdiweli', 'Abshir', 'Adan', 'Aweys', 'Ayanle', 'Bile', 'Burhan',
                'Cabdi', 'Dahir', 'Daud', 'Deeq', 'Guled', 'Hamse', 'Hirsi', 'Idiris', 'Ilyas', 'Isse', 'Jamal', 'Jibril', 'Khadar', 'Khalif', 'Mahamed', 'Mahdi', 'Mohamud', 'Muse',
                'Mustafe', 'Nuur', 'Osman', 'Qasim', 'Ridwan', 'Saciid', 'Sadiq', 'Salad', 'Shire', 'Suleiman', 'Warsame', 'Yahye', 'Yasin', 'Zakariye', 'Abdullah', 'Aadan', 'Bare',
                'Barre', 'Bootaan', 'Cali', 'Cumar', 'Dalmar', 'Diriye', 'Duale', 'Faysal', 'Geedi', 'Hanad', 'Haybe', 'Ismaaciil', 'Jaamac', 'Kaahin', 'Keynaan', 'Liibaan', 'Maxamed',
                'Nasir', 'Raage', 'Samatar', 'Sharmarke', 'Siyaad', 'Taliye', 'Ubax', 'Xasan', 'Xuseen', 'Yuusuf', 'Zayid', 'Awil', 'Bashe', 'Cawil', 'Gaas', 'Guuleed', 'Hogan',
                'Kulmiye', 'Roble', 'Waabberi', 'Warfa',
            ],
            'f' => [
                'Fadumo', 'Hodan', 'Amina', 'Asha', 'Hawa', 'Ifrah', 'Sahra', 'Nimco', 'Khadija', 'Hibo', 'Ayaan', 'Ubah', 'Maryan', 'Sagal', 'Halima', 'Deeqa', 'Naima', 'Anab', 'Asli',
                'Aamina', 'Bilan', 'Bisharo', 'Caasha', 'Canab', 'Dahabo', 'Deka', 'Edna', 'Faduma', 'Falis', 'Farhiya', 'Fardowsa', 'Fowsiya', 'Habiba', 'Hali', 'Hamdi', 'Haweya',
                'Idil', 'Ikran', 'Ilhan', 'Istar', 'Jamila', 'Kaltun', 'Khadra', 'Ladan', 'Leyla', 'Luul', 'Maandeeq', 'Magool', 'Maryama', 'Muna', 'Nasra', 'Nasteexo', 'Rahma',
                'Raxma', 'Rukia', 'Saado', 'Sacdiyo', 'Safiya', 'Shukri', 'Sucaad', 'Suleekha', 'Xaawo', 'Xaliimo', 'Yasmin', 'Zamzam', 'Zeynab', 'Ardo', 'Barwaaqo', 'Cambaro', 'Dhudi',
                'Filsan', 'Gacalo', 'Hoodo', 'Iftin', 'Kowsar', 'Mako', 'Qali', 'Raaxo', 'Shamso', 'Timiro', 'Warda', 'Xamdi',
            ],
            'last' => [
                'Mohamed', 'Ahmed', 'Hassan', 'Ali', 'Abdi', 'Warsame', 'Farah', 'Jama', 'Osman', 'Yusuf', 'Ismail', 'Nur', 'Aden', 'Hirsi', 'Elmi', 'Duale', 'Abdullahi', 'Omar',
                'Gure', 'Samatar', 'Mohamud', 'Abdille', 'Abdirahman', 'Abshir', 'Adan', 'Afrah', 'Awale', 'Aweys', 'Bile', 'Botan', 'Dahir', 'Diriye', 'Dirie', 'Dualeh', 'Egeh',
                'Fidow', 'Gabeyre', 'Galayr', 'Geedi', 'Gedi', 'Guled', 'Haji', 'Handulle', 'Hashi', 'Hersi', 'Ibrahim', 'Igal', 'Ilmi', 'Isse', 'Jibril', 'Kahin', 'Khalif', 'Liban',
                'Maalim', 'Mahamud', 'Mire', 'Muse', 'Nageeye', 'Noor', 'Olow', 'Qasim', 'Raage', 'Robleh', 'Roble', 'Saeed', 'Salad', 'Shire', 'Shirwa', 'Sugulle', 'Suleiman',
                'Tahlil', 'Ugas', 'Wardhere', 'Warfa', 'Yasin', 'Yare', 'Zubeyr', 'Abdulle', 'Abukar', 'Addow', 'Arale', 'Bashir', 'Bulhan', 'Cige', 'Dalmar', 'Dhuhulow', 'Dini',
                'Garad', 'Gelle', 'Halane', 'Hilowle', 'Hussein', 'Idle', 'Jimale', 'Kulmiye', 'Mumin', 'Odowa', 'Qorane', 'Rage', 'Sahal', 'Sheikh', 'Shuriye', 'Tifow', 'Waberi',
                'Xalane', 'Guleid', 'Nuh', 'Awad', 'Koshin', 'Mataan', 'Qalinle', 'Sandheere', 'Cabdulle', 'Diiriye', 'Faarax', 'Gaas', 'Hilaal', 'Jaamac', 'Kaariye', 'Maxamuud',
                'Nuur', 'Cilmi', 'Xirsi',
            ],
            'places' => ['Mogadishu', 'Hargeisa', 'Bosaso', 'Kismayo', 'Baidoa', 'Beledweyne', 'Galkayo', 'Garowe', 'Berbera', 'Burao', 'Jowhar', 'Marka'],
        ],
        'SERBIA' => [
            'm' => [
                'Nikola', 'Marko', 'Stefan', 'Aleksandar', 'Milan', 'Dragan', 'Zoran', 'Nenad', 'Dejan', 'Miloš', 'Luka', 'Lazar', 'Uroš', 'Vuk', 'Goran', 'Đorđe', 'Emir', 'Aleksa',
                'Andrija', 'Bogdan', 'Bojan', 'Boris', 'Borislav', 'Branislav', 'Branko', 'Danilo', 'Darko', 'David', 'Dimitrije', 'Dušan', 'Filip', 'Gavrilo', 'Igor', 'Ilija', 'Ivan',
                'Jovan', 'Konstantin', 'Kosta', 'Ljubomir', 'Matija', 'Mihailo', 'Milenko', 'Miodrag', 'Mirko', 'Miroslav', 'Mladen', 'Momčilo', 'Nebojša', 'Nemanja', 'Ognjen', 'Pavle',
                'Petar', 'Predrag', 'Radovan', 'Rade', 'Ratko', 'Relja', 'Sava', 'Slavko', 'Slobodan', 'Srđan', 'Stevan', 'Strahinja', 'Svetozar', 'Tadija', 'Teodor', 'Tomislav',
                'Vasilije', 'Veljko', 'Vladan', 'Vladimir', 'Vojislav', 'Vukašin', 'Željko', 'Živko', 'Zdravko', 'Zlatko', 'Admir', 'Adnan', 'Amar', 'Damir', 'Edin', 'Elvir', 'Enes',
                'Haris', 'Jasmin', 'Kenan', 'Mirza', 'Nermin', 'Samir', 'Senad', 'Tarik', 'Arpad', 'Laslo', 'Tibor', 'Ištvan', 'Šandor', 'Časlav', 'Dalibor', 'Dobrica', 'Jugoslav',
                'Krsto', 'Obrad', 'Rajko', 'Stojan', 'Vidoje',
            ],
            'f' => [
                'Jelena', 'Milica', 'Ana', 'Marija', 'Ivana', 'Dragana', 'Snežana', 'Jovana', 'Teodora', 'Sara', 'Anđela', 'Katarina', 'Tijana', 'Mila', 'Gordana', 'Dunja', 'Amra',
                'Aleksandra', 'Biljana', 'Bojana', 'Branka', 'Danijela', 'Danica', 'Dijana', 'Dušanka', 'Emilija', 'Isidora', 'Ivona', 'Jasmina', 'Jelica', 'Kristina', 'Ksenija',
                'Lena', 'Lidija', 'Ljiljana', 'Ljubica', 'Maja', 'Marina', 'Milena', 'Miljana', 'Mirjana', 'Nada', 'Nataša', 'Nevena', 'Nina', 'Olivera', 'Petra', 'Radmila', 'Sanja',
                'Slavica', 'Slađana', 'Sofija', 'Stanislava', 'Svetlana', 'Tamara', 'Tatjana', 'Una', 'Valentina', 'Vesna', 'Violeta', 'Vladana', 'Zorica', 'Zorana', 'Željka', 'Živana',
                'Aida', 'Alma', 'Azra', 'Dženana', 'Emina', 'Lejla', 'Merima', 'Selma', 'Zehra', 'Eržebet', 'Ilona', 'Kata', 'Hana', 'Iskra', 'Kalina', 'Lenka', 'Nikolina', 'Staša',
                'Vanja', 'Višnja', 'Bosiljka', 'Darinka', 'Jagoda', 'Kosara', 'Latinka', 'Ružica', 'Smiljana', 'Stojanka', 'Zagorka',
            ],
            'last' => [
                'Jovanović', 'Petrović', 'Nikolić', 'Marković', 'Đorđević', 'Stojanović', 'Ilić', 'Stanković', 'Pavlović', 'Popović', 'Kostić', 'Živković', 'Todorović', 'Ristić',
                'Savić', 'Mitrović', 'Lazić', 'Đukić', 'Kovačević', 'Nagy', 'Hodžić', 'Ljajić', 'Aleksić', 'Andrić', 'Antić', 'Arsić', 'Babić', 'Bajić', 'Blagojević', 'Bogdanović',
                'Bošković', 'Božić', 'Branković', 'Cvetković', 'Ćirić', 'Ćosić', 'Damjanović', 'Dimitrijević', 'Dragić', 'Filipović', 'Gajić', 'Gavrilović', 'Golubović', 'Grujić',
                'Ivanović', 'Ivković', 'Janković', 'Jakšić', 'Jevtić', 'Jokić', 'Jović', 'Jovičić', 'Knežević', 'Kojić', 'Krstić', 'Lukić', 'Maksimović', 'Marinković', 'Matić',
                'Mihajlović', 'Mijatović', 'Milenković', 'Milić', 'Milojević', 'Milovanović', 'Milutinović', 'Mišić', 'Mladenović', 'Nedeljković', 'Nešić', 'Novaković', 'Obradović',
                'Pantić', 'Paunović', 'Pejić', 'Perić', 'Petković', 'Radić', 'Radivojević', 'Radosavljević', 'Radovanović', 'Rajković', 'Rakić', 'Ranđelović', 'Simić', 'Spasić',
                'Stamenković', 'Stefanović', 'Stevanović', 'Stojković', 'Subotić', 'Tasić', 'Tomić', 'Trajković', 'Trifunović', 'Vasić', 'Vasiljević', 'Veličković', 'Veljković',
                'Vesić', 'Vidaković', 'Vučković', 'Vujić', 'Vukić', 'Vuković', 'Zdravković', 'Zlatković', 'Zorić', 'Avramović', 'Bakić', 'Banjac', 'Bjelica', 'Bulatović', 'Čolić',
                'Čović', 'Dabić', 'Dobrić', 'Erić', 'Garić', 'Jelić', 'Kalinić', 'Lalić', 'Lončar', 'Malić', 'Marić', 'Ninković', 'Ognjanović', 'Pajić', 'Prodanović', 'Rašić',
                'Sekulić', 'Šarić', 'Šević', 'Tošić', 'Ugrinović', 'Zarić', 'Zeković', 'Zukić', 'Hadžić', 'Mujić', 'Halilović', 'Bećirović', 'Dolovac', 'Kurtović', 'Ramić', 'Kovač',
                'Varga', 'Tot', 'Horvat', 'Molnar', 'Sabo', 'Balaž', 'Farkaš',
            ],
            'places' => ['Beograd', 'Novi Sad', 'Niš', 'Kragujevac', 'Subotica', 'Zrenjanin', 'Pančevo', 'Čačak', 'Kraljevo', 'Novi Pazar', 'Smederevo', 'Leskovac'],
        ],
        'ALBANIA' => [
            'm' => [
                'Arben', 'Besnik', 'Dritan', 'Erion', 'Fatmir', 'Gentian', 'Ilir', 'Klodian', 'Luan', 'Mirel', 'Agim', 'Altin', 'Ardit', 'Artan', 'Astrit', 'Bardhyl', 'Bashkim',
                'Bledar', 'Blerim', 'Bujar', 'Dardan', 'Defrim', 'Durim', 'Edmond', 'Elton', 'Endrit', 'Enver', 'Ermal', 'Fation', 'Festim', 'Flamur', 'Florian', 'Genc', 'Gëzim',
                'Gjergj', 'Hekuran', 'Hysen', 'Idriz', 'Jeton', 'Kastriot', 'Kreshnik', 'Kujtim', 'Labinot', 'Leonard', 'Lulzim', 'Marin', 'Mentor', 'Muharrem', 'Naim', 'Ndriçim',
                'Nikollë', 'Osman', 'Pëllumb', 'Petrit', 'Qemal', 'Ramiz', 'Rexhep', 'Rinor', 'Sabri', 'Saimir', 'Shkëlqim', 'Shpëtim', 'Skënder', 'Sokol', 'Taulant', 'Valon',
                'Vangjel', 'Visar', 'Xhevdet', 'Ylli', 'Zamir', 'Zef', 'Adem', 'Afrim', 'Alban', 'Avni', 'Burim', 'Driton', 'Faton', 'Granit', 'Liridon', 'Shpend', 'Arjan', 'Elvis',
                'Emiljano', 'Kristi', 'Marsel', 'Orges', 'Redi', 'Renato',
            ],
            'f' => [
                'Albana', 'Anila', 'Arta', 'Blerta', 'Bora', 'Dafina', 'Donika', 'Edona', 'Elira', 'Enkeleda', 'Era', 'Ermira', 'Fatbardha', 'Flutura', 'Genta', 'Ilda', 'Jeta',
                'Jonida', 'Klea', 'Lindita', 'Liridona', 'Majlinda', 'Manjola', 'Mimoza', 'Mirela', 'Nertila', 'Ornela', 'Rudina', 'Shpresa', 'Teuta', 'Valbona', 'Vjollca', 'Xhuljeta',
                'Zana', 'Adelina', 'Aferdita', 'Alketa', 'Brikena', 'Diellza', 'Drita', 'Entela', 'Eriona', 'Fjolla', 'Gresa', 'Hana', 'Kaltrina', 'Lule', 'Luljeta', 'Marsela',
                'Merita', 'Miranda', 'Nora', 'Rita', 'Rovena', 'Suela', 'Vesa', 'Vlora', 'Yllka', 'Besa', 'Doruntina', 'Elona', 'Flora', 'Greta', 'Ina', 'Juliana', 'Kejsi', 'Lediana',
                'Megi', 'Olta', 'Pranvera', 'Rezarta', 'Sidorela', 'Tringa', 'Uarda', 'Vera', 'Zamira',
            ],
            'last' => [
                'Shehu', 'Dervishi', 'Kola', 'Leka', 'Gjoka', 'Marku', 'Prifti', 'Rama', 'Bajrami', 'Krasniqi', 'Gashi', 'Morina', 'Shala', 'Kastrati', 'Rexhepi', 'Hasani', 'Ahmeti',
                'Aliu', 'Bytyqi', 'Halili', 'Hyseni', 'Ibrahimi', 'Ismaili', 'Kelmendi', 'Maloku', 'Musliu', 'Osmani', 'Rrustemi', 'Sadiku', 'Selimi', 'Zeqiri', 'Balla', 'Bardhi',
                'Beqiri', 'Biba', 'Bushati', 'Cela', 'Çela', 'Dedja', 'Dema', 'Demiri', 'Doda', 'Duka', 'Elezi', 'Gjini', 'Gjoni', 'Hajdari', 'Haxhiu', 'Hila', 'Kaci', 'Kadiu',
                'Kamberi', 'Kapllani', 'Koçi', 'Kodra', 'Kuka', 'Lala', 'Lika', 'Luca', 'Mema', 'Muça', 'Murati', 'Ndoja', 'Nika', 'Pali', 'Peci', 'Pepa', 'Qosja', 'Ruci', 'Sula',
                'Tafa', 'Tahiri', 'Topalli', 'Toska', 'Troka', 'Vata', 'Xhaferi', 'Xhafa', 'Ymeri', 'Zaimi', 'Zefi', 'Agolli', 'Bejko', 'Bregu', 'Braho', 'Çaushi', 'Dibra', 'Gega',
                'Golemi', 'Idrizi', 'Jaku', 'Kraja', 'Lleshi', 'Mara', 'Meksi', 'Myftiu', 'Ndreu', 'Pasha', 'Pulaj', 'Rakipi', 'Selmani', 'Shabani', 'Spahiu', 'Sulaj', 'Tola',
                'Trebicka', 'Vrapi', 'Vukaj', 'Zogaj', 'Zhupa', 'Bala', 'Cani', 'Daja', 'Fejzo', 'Guri', 'Hodo', 'Islami', 'Lamaj', 'Mehmeti', 'Nasufi', 'Pjetri', 'Qorri', 'Sinani',
            ],
            'places' => ['Tiranë', 'Durrës', 'Vlorë', 'Shkodër', 'Elbasan', 'Korçë', 'Fier', 'Berat', 'Lushnjë', 'Kukës', 'Gjirokastër', 'Sarandë'],
        ],
        'MEXICO' => [
            'm' => [
                'José Luis', 'Juan Manuel', 'Miguel Ángel', 'Luis Fernando', 'Jesús Alberto', 'José Guadalupe', 'Cuauhtémoc', 'Xavier', 'Omar', 'Édgar', 'Erick', 'Israel', 'Noé',
                'Saúl', 'Uriel', 'Abelardo', 'Baltazar', 'Crescencio', 'Epifanio', 'Fortino', 'Melchor', 'Nicandro', 'Refugio', 'Santos', 'Tiburcio', 'Juan Carlos', 'Andrés', 'Carlos',
                'Luis', 'Jorge', 'Diego', 'Santiago', 'Sebastián', 'Camilo', 'Felipe', 'Julián', 'Alejandro', 'Óscar', 'Mateo', 'Daniel', 'Cristian', 'Fabián', 'Jairo', 'Esteban',
                'José', 'Miguel', 'Pedro', 'Francisco', 'Antonio', 'Manuel', 'Rafael', 'Fernando', 'Ricardo', 'Roberto', 'Eduardo', 'Javier', 'Sergio', 'Alberto', 'Raúl', 'Héctor',
                'Víctor', 'Hugo', 'Iván', 'Mario', 'Rubén', 'Arturo', 'Ramón', 'Enrique', 'Gustavo', 'Guillermo', 'Rodrigo', 'Pablo', 'Ignacio', 'Gerardo', 'Armando', 'Alfredo',
                'Ernesto', 'Marcos', 'César', 'Adrián', 'Emilio', 'Salvador', 'Agustín', 'Leonardo', 'Nicolás', 'Tomás', 'Gabriel', 'Samuel', 'Emiliano', 'Maximiliano', 'Benjamín',
                'Martín', 'Lucas', 'Joaquín', 'Ángel', 'David', 'Elías', 'Isaac', 'Josué', 'Moisés', 'Abel', 'Abraham', 'Adolfo', 'Alfonso', 'Álvaro', 'Anselmo', 'Aurelio', 'Bernardo',
                'Braulio', 'Claudio', 'Clemente', 'Cornelio', 'Dámaso', 'Domingo', 'Efraín', 'Eliseo', 'Eugenio', 'Ezequiel', 'Fausto', 'Federico', 'Fidel', 'Florencio', 'Genaro',
                'Gilberto', 'Gonzalo', 'Gregorio', 'Heriberto', 'Hernán', 'Horacio', 'Humberto', 'Isidro', 'Ismael', 'Jacinto', 'Jesús', 'Leandro', 'Leopoldo', 'Lorenzo', 'Marcelo',
                'Mauricio', 'Nelson', 'Néstor', 'Octavio', 'Orlando', 'Osvaldo', 'Patricio', 'Reinaldo', 'René', 'Rigoberto', 'Rogelio', 'Rolando', 'Simón', 'Teodoro', 'Ulises',
                'Valentín', 'Vicente', 'Wilson',
            ],
            'f' => [
                'María Guadalupe', 'María José', 'Ana Karen', 'Brenda', 'Citlali', 'Dulce María', 'Erika', 'Itzel', 'Jazmín', 'Karla', 'Lizbeth', 'Marisela', 'Nayeli', 'Paloma',
                'Ximena', 'Xóchitl', 'Yaretzi', 'Zyanya', 'Monserrat', 'Rosario', 'Socorro', 'Refugio', 'Eréndira', 'Ivonne', 'María', 'Ana María', 'Diana', 'Paola', 'Carolina',
                'Natalia', 'Valentina', 'Daniela', 'Laura', 'Sandra', 'Claudia', 'Juliana', 'Mariana', 'Lorena', 'Guadalupe', 'Carmen', 'Rosa', 'Teresa', 'Patricia', 'Gabriela',
                'Verónica', 'Alejandra', 'Adriana', 'Mónica', 'Silvia', 'Leticia', 'Elena', 'Isabel', 'Beatriz', 'Cristina', 'Lucía', 'Sofía', 'Camila', 'Fernanda', 'Andrea', 'Paula',
                'Sara', 'Elizabeth', 'Martha', 'Gloria', 'Alicia', 'Angélica', 'Araceli', 'Blanca', 'Cecilia', 'Consuelo', 'Dolores', 'Esperanza', 'Estela', 'Eugenia', 'Fabiola',
                'Graciela', 'Irma', 'Josefina', 'Julia', 'Liliana', 'Lourdes', 'Magdalena', 'Margarita', 'Maribel', 'Marisol', 'Miriam', 'Norma', 'Olga', 'Pilar', 'Raquel', 'Rebeca',
                'Rocío', 'Susana', 'Victoria', 'Yolanda', 'Renata', 'Regina', 'Abril', 'Antonia', 'Catalina', 'Constanza', 'Emilia', 'Florencia', 'Isabella', 'Jimena', 'Luciana',
                'Manuela', 'Martina', 'Micaela', 'Noemí', 'Salomé', 'Tatiana', 'Viviana', 'Yesenia', 'Zulema', 'Amparo', 'Aurora', 'Bertha', 'Concepción', 'Dulce', 'Edith', 'Inés',
                'Lidia', 'Mercedes', 'Nohemí', 'Perla', 'Soledad',
            ],
            'last' => [
                'Hernández', 'García', 'Martínez', 'López', 'González', 'Pérez', 'Rodríguez', 'Sánchez', 'Ramírez', 'Cruz', 'Flores', 'Gómez', 'Morales', 'Vázquez', 'Reyes', 'Jiménez',
                'Torres', 'Díaz', 'Gutiérrez', 'Ruiz', 'Mendoza', 'Aguilar', 'Ortiz', 'Moreno', 'Castillo', 'Romero', 'Álvarez', 'Méndez', 'Chávez', 'Rivera', 'Juárez', 'Ramos',
                'Domínguez', 'Herrera', 'Medina', 'Castro', 'Vargas', 'Velázquez', 'Muñoz', 'Rojas', 'De la Cruz', 'Contreras', 'Salazar', 'Luna', 'Ortega', 'Santiago', 'Guerrero',
                'Estrada', 'Bautista', 'Cortés', 'Soto', 'Alvarado', 'Espinoza', 'Lara', 'Ávila', 'Ríos', 'Cervantes', 'Silva', 'Delgado', 'Vega', 'Márquez', 'Sandoval', 'Fernández',
                'León', 'Carrillo', 'Mejía', 'Solís', 'Núñez', 'Rosas', 'Valdez', 'Ibarra', 'Campos', 'Santos', 'Camacho', 'Navarro', 'Peña', 'Maldonado', 'Rosales', 'Acosta',
                'Miranda', 'Trejo', 'Valencia', 'Nava', 'Pacheco', 'Robles', 'Molina', 'Rangel', 'Fuentes', 'Cabrera', 'Huerta', 'Padilla', 'Espinosa', 'Aguirre', 'Salas', 'Orozco',
                'Leyva', 'Zamora', 'Bravo', 'Villegas', 'Ayala', 'Montes', 'Meza', 'Tapia', 'Olvera', 'Cisneros', 'Barrera', 'Zúñiga', 'Tovar', 'Esquivel', 'Gallegos', 'Quintero',
                'Villarreal', 'Garza', 'Cantú', 'Elizondo', 'Lozano', 'Saldaña', 'Zavala', 'Ochoa', 'Bernal', 'Corona', 'Duarte', 'Escobedo', 'Galindo', 'Jaramillo', 'Lugo', 'Monroy',
                'Ocampo', 'Palacios', 'Quiroz', 'Rentería', 'Sepúlveda', 'Téllez', 'Verdugo', 'Yáñez', 'Zepeda', 'Canul', 'Chan', 'Dzul', 'Ek', 'May', 'Pech', 'Poot', 'Uc', 'Tzuc',
                'Xool',
            ],
            'places' => ['Ciudad de México', 'Guadalajara', 'Monterrey', 'Puebla', 'Tijuana', 'León', 'Ciudad Juárez', 'Mérida', 'Culiacán', 'Veracruz', 'Morelia', 'Hermosillo'],
        ],
        'PHILIPPINES' => [
            'm' => [
                'Jose', 'Juan', 'Antonio', 'Francisco', 'Manuel', 'Pedro', 'Ricardo', 'Roberto', 'Eduardo', 'Rolando', 'Romeo', 'Rodolfo', 'Renato', 'Reynaldo', 'Ramon', 'Danilo',
                'Ernesto', 'Fernando', 'Alfredo', 'Arnel', 'Jerome', 'Jayson', 'Joel', 'Jonathan', 'Joseph', 'Joshua', 'Mark', 'Michael', 'Christian', 'John Paul', 'John Mark', 'Jun',
                'Junior', 'Bong', 'Dodong', 'Nonoy', 'Efren', 'Edgardo', 'Emmanuel', 'Gerardo', 'Gregorio', 'Honesto', 'Isidro', 'Leonardo', 'Marlon', 'Nestor', 'Noel', 'Orlando',
                'Oscar', 'Paolo', 'Rafael', 'Raul', 'Rey', 'Rommel', 'Ronaldo', 'Rogelio', 'Samuel', 'Teodoro', 'Vicente', 'Virgilio', 'Wilfredo', 'Zaldy', 'Angelo', 'Bernardo',
                'Carlito', 'Crisanto', 'Dionisio', 'Elmer', 'Felipe', 'Gilbert', 'Herminio', 'Ireneo', 'Jaime', 'Lito', 'Mario', 'Nilo', 'Pablo', 'Quirino', 'Ruben', 'Santiago',
                'Tomas', 'Ulysses', 'Venancio', 'Abdul', 'Ahmad', 'Datu', 'Jamil', 'Nasser', 'Omar', 'Salim', 'Yusop', 'Alvin', 'Bryan', 'Dennis', 'Erwin', 'Jeffrey', 'Kevin', 'Lester',
                'Melvin', 'Nelson', 'Ryan', 'Sherwin', 'Warren', 'Aldrin', 'Jomar', 'Jerald', 'Ariel', 'Benjie',
            ],
            'f' => [
                'Maria', 'Ana', 'Rosario', 'Corazon', 'Imelda', 'Lourdes', 'Teresita', 'Remedios', 'Milagros', 'Josefina', 'Carmelita', 'Cristina', 'Elena', 'Evelyn', 'Gloria',
                'Jocelyn', 'Lilibeth', 'Marites', 'Marivic', 'Maricel', 'Maribel', 'Mylene', 'Rowena', 'Rosalinda', 'Susana', 'Angelica', 'Bernadette', 'Catherine', 'Charmaine',
                'Divina', 'Editha', 'Fe', 'Grace', 'Hazel', 'Irene', 'Jennifer', 'Joy', 'Juliet', 'Kristine', 'Liza', 'Lorna', 'Luz', 'Mae', 'Michelle', 'Nenita', 'Norma', 'Ofelia',
                'Perla', 'Precious', 'Queenie', 'Rachelle', 'Regina', 'Rhea', 'Rosemarie', 'Sheila', 'Shirley', 'Tala', 'Trinidad', 'Veronica', 'Vilma', 'Wilma', 'Yolanda', 'Zenaida',
                'Analyn', 'Arlene', 'Cherry', 'Dolores', 'Erlinda', 'Flordeliza', 'Gemma', 'Honeylyn', 'Jasmin', 'Katrina', 'Ligaya', 'Luningning', 'Mayumi', 'Nimfa', 'Pilar',
                'Rosalie', 'Salvacion', 'Violeta', 'Aisha', 'Fatima', 'Noraida', 'Rohaina', 'Sittie', 'Amihan', 'Dalisay', 'Bituin', 'Diwata',
            ],
            'last' => [
                'Santos', 'Reyes', 'Cruz', 'Bautista', 'Ocampo', 'Garcia', 'Mendoza', 'Torres', 'Tomas', 'Andrada', 'Castillo', 'Flores', 'Villanueva', 'Ramos', 'Castro', 'Rivera',
                'Navarro', 'Salazar', 'Mercado', 'Dela Cruz', 'De Leon', 'Del Rosario', 'De Guzman', 'Delos Santos', 'Gonzales', 'Lopez', 'Hernandez', 'Perez', 'Fernandez', 'Francisco',
                'Pascual', 'Santiago', 'Soriano', 'Domingo', 'Marquez', 'Valdez', 'Corpuz', 'Agustin', 'Manalo', 'Magno', 'Dizon', 'Pangilinan', 'Tolentino', 'Lacson', 'Macaraeg',
                'Dimaculangan', 'Dimagiba', 'Batungbakal', 'Katigbak', 'Lumibao', 'Magbanua', 'Malonzo', 'Manansala', 'Panganiban', 'Sumulong', 'Tanglao', 'Tumulak', 'Abad', 'Abella',
                'Abrenica', 'Acosta', 'Aguilar', 'Alcantara', 'Alvarez', 'Angeles', 'Aragon', 'Asuncion', 'Atienza', 'Balagtas', 'Baltazar', 'Belmonte', 'Bernardo', 'Buenaventura',
                'Cabrera', 'Calderon', 'Camacho', 'Caballero', 'Cortez', 'Custodio', 'Dacanay', 'Enriquez', 'Espino', 'Estrada', 'Evangelista', 'Fajardo', 'Ferrer', 'Gatdula',
                'Guevarra', 'Ignacio', 'Jacinto', 'Javier', 'Lacsamana', 'Legaspi', 'Lim', 'Lualhati', 'Mabini', 'Maglaya', 'Malabanan', 'Marasigan', 'Miranda', 'Natividad',
                'Nepomuceno', 'Ong', 'Padilla', 'Pacheco', 'Paguio', 'Quiambao', 'Quizon', 'Rosales', 'Sison', 'Tan', 'Tiongson', 'Umali', 'Velasco', 'Vergara', 'Yap', 'Zamora',
                'Zulueta', 'Abubakar', 'Balindong', 'Lucman', 'Macabangon', 'Maruhom', 'Tamano', 'Usman', 'Amilbangsa',
            ],
            'places' => ['Manila', 'Quezon City', 'Cebu City', 'Davao City', 'Zamboanga City', 'Cagayan de Oro', 'Iloilo City', 'Bacolod', 'General Santos', 'Baguio', 'Jolo', 'Puerto Princesa'],
        ],
    ];

    public static function has(string $code): bool
    {
        return isset(self::COUNTRIES[strtoupper(trim($code))]);
    }

    /** @return array<string, mixed>|null */
    public static function get(string $code): ?array
    {
        $code = strtoupper(trim($code));

        return isset(self::COUNTRIES[$code]) ? ['code' => $code] + self::COUNTRIES[$code] : null;
    }

    /** Code MRZ OACI d'un pays du jeu stylisé (Allemagne : « D »), sinon null. */
    public static function mrzFor(string $code): ?string
    {
        return self::COUNTRIES[strtoupper(trim($code))]['mrz'] ?? null;
    }

    /** Format de numéro générique du jeu : code pays à deux lettres + une lettre + six chiffres (9 signes). */
    public static function numberFormat(string $code): string
    {
        return (self::COUNTRIES[strtoupper(trim($code))]['iso2'] ?? 'XX') . '@######';
    }

    public static function zone(string $code): string
    {
        return self::COUNTRIES[strtoupper(trim($code))]['zone'] ?? 'other';
    }

    /**
     * Ligne prête pour SseIdCountryRepository::create() (pays réel stylisé, univers réaliste-simulation).
     *
     * @return array<string, mixed>
     */
    public static function countryData(string $code): array
    {
        $c = self::get($code);
        if ($c === null) {
            return [];
        }

        // Papiers délivrés : ceux du registre du mod pour ce pays (titre de séjour, carte grise… selon le pays).
        $types = SseDocumentTypes::servedBy($c['code']);
        if ($types === []) {
            $types = array_merge(array_keys($c['validity']), ['military_id']);
        }
        $validity = $c['validity'];
        // Titre de séjour : même durée que la carte d'identité (ou le passeport, pour un pays sans carte).
        if (in_array('residence_permit', $types, true)) {
            $validity += ['residence_permit' => $validity['id_card'] ?? $validity['passport'] ?? 10];
        }

        return [
            'code' => $c['code'],
            'name' => $c['name'],
            'name_en' => $c['name_en'],
            'mrz_code' => $c['mrz'],
            'number_format' => self::numberFormat($c['code']),
            'authority' => $c['authority'],
            'region' => $c['region'],
            'theme' => self::UNIVERSE,
            'languages' => $c['languages'],
            'doc_types' => $types,
            'validity' => $validity,
            'color_primary' => strtoupper($c['colors'][0]),
            'color_secondary' => strtoupper($c['colors'][1]),
            'flag_path' => null,
        ];
    }

    /** Drapeau simple (bandes, sans armoiries ni inscriptions) en SVG. */
    public static function flagSvg(string $code): string
    {
        $c = self::COUNTRIES[strtoupper(trim($code))] ?? null;
        if ($c === null) {
            return '';
        }
        [$kind, $cols] = $c['flag'];
        $rects = '';
        $n = count($cols);
        switch ($kind) {
            case 'v':
                foreach ($cols as $i => $col) {
                    $rects .= sprintf('<rect x="%.3f" y="0" width="%.3f" height="20" fill="%s"/>', 30 / $n * $i, 30 / $n + 0.05, $col);
                }
                break;
            case 'cross':
                $rects = '<rect width="30" height="20" fill="' . $cols[0] . '"/><rect x="0" y="7.5" width="30" height="5" fill="#ffffff"/>'
                    . '<rect x="12.5" y="0" width="5" height="20" fill="#ffffff"/><rect x="0" y="8.5" width="30" height="3" fill="' . $cols[1] . '"/>'
                    . '<rect x="13.5" y="0" width="3" height="20" fill="' . $cols[1] . '"/>';
                break;
            case 'canton':
                for ($i = 0; $i < 7; $i++) {
                    $rects .= sprintf('<rect x="0" y="%.3f" width="30" height="%.3f" fill="%s"/>', 20 / 7 * $i, 20 / 7 + 0.05, $cols[$i % 2]);
                }
                $rects .= '<rect x="0" y="0" width="12" height="' . sprintf('%.3f', 20 / 7 * 4) . '" fill="' . $cols[2] . '"/>';
                break;
            default:
                foreach ($cols as $i => $col) {
                    $rects .= sprintf('<rect x="0" y="%.3f" width="30" height="%.3f" fill="%s"/>', 20 / $n * $i, 20 / $n + 0.05, $col);
                }
        }

        return '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 30 20" width="30" height="20">' . $rects . '</svg>';
    }

    /** Drapeau simple en URI data: (aperçu, export PNG). */
    public static function flagDataUri(string $code): ?string
    {
        $svg = self::flagSvg($code);

        return $svg !== '' ? 'data:image/svg+xml;base64,' . base64_encode($svg) : null;
    }
}
