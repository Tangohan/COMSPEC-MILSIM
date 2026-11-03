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
 * - chaque face porte « SIMULATION — DOCUMENT DE JEU », l'export PNG garde le filigrane diagonal et le bandeau
 *   « DOCUMENT DE JEU – SANS VALEUR » ;
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
    public const FACE_MENTION = 'SIMULATION — DOCUMENT DE JEU';

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
            'm' => ['Santiago', 'Sebastián', 'Andrés', 'Carlos', 'Juan', 'Luis', 'Jorge', 'Diego', 'Alejandro', 'Camilo', 'Felipe', 'Mateo'],
            'f' => ['Valentina', 'Daniela', 'Camila', 'María', 'Laura', 'Natalia', 'Paola', 'Carolina', 'Andrea', 'Juliana', 'Sofía', 'Catalina'],
            'last' => ['Rodríguez', 'Gómez', 'González', 'Martínez', 'García', 'López', 'Hernández', 'Sánchez', 'Ramírez', 'Pérez', 'Díaz', 'Muñoz', 'Rojas', 'Moreno', 'Jiménez', 'Castro'],
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
            'm' => ['Oleksandr', 'Andriy', 'Dmytro', 'Serhiy', 'Mykola', 'Volodymyr', 'Yuriy', 'Taras', 'Bohdan', 'Ihor', 'Vasyl', 'Maksym'],
            'f' => ['Olena', 'Oksana', 'Nataliya', 'Iryna', 'Tetiana', 'Yuliya', 'Kateryna', 'Svitlana', 'Halyna', 'Mariya', 'Anastasiya', 'Viktoriya'],
            'last' => ['Melnyk', 'Shevchenko', 'Boyko', 'Kovalenko', 'Bondarenko', 'Tkachenko', 'Kovalchuk', 'Kravchenko', 'Oliynyk', 'Shevchuk', 'Polishchuk', 'Lysenko', 'Tkachuk', 'Savchenko', 'Rudenko', 'Marchenko'],
            'places' => ['Kyiv', 'Kharkiv', 'Odesa', 'Dnipro', 'Lviv', 'Zaporizhzhia', 'Mykolaiv', 'Vinnytsia', 'Poltava', 'Chernihiv', 'Sumy', 'Rivne'],
        ],
        'RUSSIA_ID' => [
            'm' => ['Ivan', 'Dmitri', 'Sergei', 'Andrei', 'Pavel', 'Alexei', 'Nikolai', 'Viktor', 'Mikhail', 'Yuri', 'Oleg', 'Roman'],
            'f' => ['Anna', 'Elena', 'Olga', 'Natalia', 'Irina', 'Maria', 'Tatiana', 'Svetlana', 'Ekaterina', 'Yulia', 'Daria', 'Ksenia'],
            'last' => ['Ivanov', 'Petrov', 'Smirnov', 'Kuznetsov', 'Popov', 'Vasiliev', 'Sokolov', 'Mikhailov', 'Novikov', 'Fedorov', 'Morozov', 'Volkov', 'Lebedev', 'Kozlov', 'Orlov', 'Makarov'],
            'places' => ['Moskva', 'Sankt-Peterburg', 'Novosibirsk', 'Yekaterinburg', 'Kazan', 'Nizhny Novgorod', 'Samara', 'Omsk', 'Rostov-na-Donu', 'Ufa', 'Krasnoyarsk', 'Volgograd'],
            'f_suffix' => 'a',
        ],
        'IRAQ_ID' => [
            'm' => ['Ahmed', 'Mohammed', 'Ali', 'Hussein', 'Hassan', 'Omar', 'Mustafa', 'Haider', 'Abbas', 'Karim', 'Saad', 'Yasir'],
            'f' => ['Fatima', 'Zainab', 'Maryam', 'Noor', 'Sara', 'Huda', 'Aisha', 'Rana', 'Shaima', 'Iman', 'Duaa', 'Layla'],
            'last' => ['Al-Jubouri', 'Al-Obaidi', 'Al-Dulaimi', 'Al-Tamimi', 'Al-Khafaji', 'Al-Saadi', 'Al-Rubaie', 'Al-Zubaidi', 'Al-Hashimi', 'Al-Shammari', 'Al-Bayati', 'Al-Ani', 'Al-Kubaisi', 'Al-Mousawi', 'Al-Hamdani', 'Al-Azzawi'],
            'places' => ['Baghdad', 'Basra', 'Mosul', 'Erbil', 'Kirkuk', 'Najaf', 'Karbala', 'Sulaymaniyah', 'Ramadi', 'Fallujah', 'Tikrit', 'Samarra'],
        ],
        'SYRIA_ID' => [
            'm' => ['Mohammad', 'Ahmad', 'Khaled', 'Omar', 'Bilal', 'Rami', 'Fadi', 'Bassam', 'Ghassan', 'Hassan', 'Tarek', 'Ziad'],
            'f' => ['Rania', 'Lina', 'Hala', 'Reem', 'Dima', 'Nour', 'Yara', 'Rasha', 'Salma', 'Ghada', 'Maha', 'Joud'],
            'last' => ['Haddad', 'Khoury', 'Nasser', 'Saleh', 'Darwish', 'Hamdan', 'Jaber', 'Najjar', 'Sabbagh', 'Halabi', 'Shami', 'Hourani', 'Kassab', 'Attar', 'Masri', 'Zein'],
            'places' => ['Dimashq', 'Halab', 'Homs', 'Hama', 'Latakia', 'Tartus', 'Deir ez-Zor', 'Raqqa', 'Idlib', 'Daraa', 'Al-Hasakah', 'Qamishli'],
        ],
        'AFGHANISTAN' => [
            'm' => ['Ahmad', 'Mohammad', 'Abdul', 'Najib', 'Farid', 'Hamid', 'Rahim', 'Karim', 'Wahid', 'Jawad', 'Sami', 'Nasir'],
            'f' => ['Fatima', 'Mariam', 'Zahra', 'Fereshta', 'Laila', 'Nargis', 'Shabnam', 'Parwana', 'Sima', 'Roya', 'Habiba', 'Malalai'],
            'last' => ['Ahmadzai', 'Popal', 'Barakzai', 'Zazai', 'Stanikzai', 'Noorzai', 'Wardak', 'Safi', 'Mohammadi', 'Rahimi', 'Hakimi', 'Sultani', 'Nazari', 'Haidari', 'Amiri', 'Qaderi'],
            'places' => ['Kabul', 'Kandahar', 'Herat', 'Mazar-i-Sharif', 'Jalalabad', 'Kunduz', 'Ghazni', 'Lashkar Gah', 'Bamyan', 'Khost', 'Gardez', 'Taloqan'],
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
            'doc_types' => array_keys($c['validity']),
            'validity' => $c['validity'],
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
