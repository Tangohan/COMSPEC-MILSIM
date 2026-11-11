<?php

declare(strict_types=1);

namespace App\Support;

/**
 * Passage aux fichiers — packs pays : formats nationaux (dates, téléphones, plaques, papiers, codes postaux), systèmes
 * de consultation et libellés du terminal dans la langue du pays, réservoirs de données pour générer des fiches.
 *
 * Codes = ISO 3166 alpha-3, alignés sur SseIdRealCountryCatalog. Le pack « ALT » (Altis) est entièrement fictif et
 * n'est JAMAIS le pays par défaut (DEFAULT = USA).
 *
 * Garde-fous : toutes les valeurs d'exemple sont FABRIQUÉES (SSN en 9xx jamais attribués, téléphones US en 555-01xx,
 * FR dans la plage réservée à la fiction 06 39 98, UK dans la plage Ofcom 07700 900xxx, NI « QQ » réservé…). Les
 * domaines de courriel finissent en .example ou .test. Aucune personne réelle n'est visée.
 *
 * Irak, Afghanistan : libellés du terminal en anglais (langue de travail de la coalition dans le jeu).
 *
 * Chaque pack a exactement les clés : code, label, label_en, fictional, units, date_format, date_hint, phone, plate,
 * ids, postal, address_format, records, names, cities, streets, carriers, mail_domains, vehicles, colors.
 */
final class SseRecordsCountryPacks
{
    public const DEFAULT = 'USA';

    /** Pays travaillés en priorité (listes complètes). */
    public const PRIORITY = ['USA', 'DEU', 'FRA'];

    /** Ordre d'affichage des pays réels. */
    private const ORDER = ['USA', 'DEU', 'FRA', 'GBR', 'NLD', 'BEL', 'ITA', 'ESP', 'POL', 'UKR', 'IRQ', 'AFG', 'MLI'];

    /** Pack fictif, proposé en option seulement. */
    private const FICTIONAL = ['ALT'];

    /** Mentions générales en anglais (repris par les packs anglophones). */
    private const STATUS_EN = [
        'armed_dangerous' => 'ARMED AND DANGEROUS',
        'warrant' => 'WARRANT',
        'wanted' => 'WANTED',
        'missing' => 'MISSING PERSON',
        'flagged' => 'FLAGGED',
        'caution' => 'CAUTION',
        'watchlist' => 'WATCHLIST',
        'hvt' => 'HIGH VALUE TARGET',
    ];

    private const CAUTION_EN = [
        'officer_safety' => 'OFFICER SAFETY',
        'armed' => 'ARMED',
        'violent' => 'VIOLENT TENDENCIES',
        'flight_risk' => 'ESCAPE RISK',
        'mental_health' => 'MENTAL HEALTH',
        'medical' => 'MEDICAL CONDITION',
        'gang' => 'GANG AFFILIATION',
        'explosives' => 'EXPLOSIVES EXPERTISE',
    ];

    private const STATUS_FR = [
        'armed_dangerous' => 'ARMÉ ET DANGEREUX',
        'warrant' => 'MANDAT',
        'wanted' => 'RECHERCHÉ',
        'missing' => 'DISPARU',
        'flagged' => 'SIGNALÉ',
        'caution' => 'PRUDENCE',
        'watchlist' => 'FICHE S (FICTIVE)',
        'hvt' => 'CIBLE PRIORITAIRE',
    ];

    private const CAUTION_FR = [
        'officer_safety' => 'SÉCURITÉ DES AGENTS',
        'armed' => 'ARMÉ',
        'violent' => 'VIOLENT',
        'flight_risk' => 'RISQUE DE FUITE',
        'mental_health' => 'TROUBLES PSYCHIATRIQUES',
        'medical' => 'RISQUE MÉDICAL',
        'gang' => 'APPARTENANCE À UNE BANDE',
        'explosives' => 'EXPLOSIFS',
    ];

    private const COLORS_EN = ['Black', 'White', 'Silver', 'Gray', 'Blue', 'Red', 'Green', 'Brown', 'Beige', 'Maroon', 'Gold', 'Yellow'];

    private const COLORS_FR = ['Noir', 'Blanc', 'Gris', 'Argent', 'Bleu', 'Rouge', 'Vert', 'Marron', 'Beige', 'Jaune', 'Bordeaux', 'Orange'];

    /** @var array<string, array<string, mixed>> */
    private const PACKS = [
        // ─────────────────────────────────────────────────────────────── États-Unis
        'USA' => [
            'code' => 'USA',
            'label' => 'États-Unis',
            'label_en' => 'United States',
            'fictional' => false,
            'units' => 'imperial',
            'date_format' => 'm/d/Y',
            'date_hint' => 'mm/dd/yyyy',
            'phone' => [
                'prefix' => '+1',
                'pattern' => '/^(?:\+?1[\s.-]?)?(?:\([2-9]\d{2}\)|[2-9]\d{2})[\s.-]?[2-9]\d{2}[\s.-]?\d{4}$/',
                'example' => '(213) 555-0147',
                'national_format' => '(NPA) NXX-XXXX — fictif : 555-0100 à 555-0199',
            ],
            'plate' => [
                'label' => 'License Plate',
                'pattern' => '/^[A-Z0-9]{1,4}[\s-]?[A-Z0-9]{1,4}$/',
                'example' => '7ABC123',
                'hint' => 'Format propre à chaque État (CA : 7ABC123 ; TX, NY : ABC-1234)',
            ],
            'ids' => [
                // SSN : 000, 666 et 00/0000 jamais attribués ; les exemples sont en 9xx (jamais délivrés comme SSN).
                'national_id' => [
                    'label' => 'Social Security Number',
                    'short' => 'SSN',
                    'pattern' => '/^(?!000|666)\d{3}[\s-]?(?!00)\d{2}[\s-]?(?!0000)\d{4}$/',
                    'example' => '912-48-3071',
                ],
                // Permis par État : CA = 1 lettre + 7 chiffres (B6402187), TX = 8 chiffres, NY = 9 chiffres,
                // IL = 1 lettre + 11 chiffres, FL = 1 lettre + 12 chiffres ; motif tolérant (au moins un chiffre).
                'driving_license' => [
                    'label' => 'Driver License',
                    'short' => 'DL',
                    'pattern' => '/^(?=[A-Z0-9-]*\d)[A-Z0-9](?:-?[A-Z0-9]){4,15}$/',
                    'example' => 'B6402187',
                ],
                'passport' => [
                    'label' => 'U.S. Passport',
                    'short' => 'PPT',
                    'pattern' => '/^(?:[A-Z]\d{8}|\d{9})$/',
                    'example' => 'A48213907',
                ],
            ],
            'postal' => ['label' => 'ZIP Code', 'pattern' => '/^\d{5}(?:-\d{4})?$/', 'example' => '90012'],
            'address_format' => '{number} {street}, {city}, {region} {postal}',
            'records' => [
                'system' => 'NCIC',
                'systems' => [
                    'NCIC' => 'National Crime Information Center',
                    'NLETS' => 'International Justice and Public Safety Network (Nlets)',
                    'DMV' => 'Department of Motor Vehicles',
                    'III' => 'Interstate Identification Index',
                ],
                'vehicle_system' => 'DMV',
                'no_record' => 'NO RECORD',
                'hit' => 'HIT — CONFIRM WITH ORIGINATING AGENCY',
                'possible' => 'POSSIBLE MATCH — VERIFY IDENTITY',
                'wants_label' => 'Wants & Warrants',
                'status' => self::STATUS_EN,
                'caution' => self::CAUTION_EN,
                'resident_status' => [
                    'citizen' => 'U.S. Citizen',
                    'lpr' => 'Lawful Permanent Resident',
                    'visa' => 'Nonimmigrant Visa Holder',
                    'asylum' => 'Asylum Applicant',
                    'no_status' => 'No Lawful Status',
                ],
            ],
            'names' => [
                'male' => ['James', 'John', 'Robert', 'Michael', 'William', 'David', 'Richard', 'Joseph', 'Thomas', 'Christopher',
                    'Charles', 'Daniel', 'Matthew', 'Anthony', 'Mark', 'Steven', 'Paul', 'Andrew', 'Joshua', 'Kenneth',
                    'Kevin', 'Brian', 'George', 'Timothy', 'Ronald', 'Jason', 'Edward', 'Jeffrey', 'Ryan', 'Jacob',
                    'Gary', 'Nicholas', 'Eric', 'Jonathan', 'Justin', 'Brandon', 'Tyler', 'Aaron'],
                'female' => ['Mary', 'Patricia', 'Jennifer', 'Linda', 'Elizabeth', 'Barbara', 'Susan', 'Jessica', 'Sarah', 'Karen',
                    'Lisa', 'Nancy', 'Sandra', 'Margaret', 'Ashley', 'Kimberly', 'Emily', 'Donna', 'Michelle', 'Carol',
                    'Amanda', 'Melissa', 'Deborah', 'Stephanie', 'Rebecca', 'Laura', 'Sharon', 'Cynthia', 'Kathleen', 'Amy',
                    'Angela', 'Megan', 'Nicole', 'Brittany', 'Rachel', 'Heather'],
                'last' => ['Smith', 'Johnson', 'Williams', 'Brown', 'Jones', 'Garcia', 'Miller', 'Davis', 'Rodriguez', 'Martinez',
                    'Hernandez', 'Lopez', 'Gonzalez', 'Wilson', 'Anderson', 'Thomas', 'Taylor', 'Moore', 'Jackson', 'Martin',
                    'Lee', 'Perez', 'Thompson', 'White', 'Harris', 'Sanchez', 'Clark', 'Ramirez', 'Lewis', 'Robinson',
                    'Walker', 'Young', 'Allen', 'King', 'Wright', 'Scott', 'Torres', 'Nguyen', 'Hill', 'Flores',
                    'Green', 'Adams', 'Nelson', 'Baker', 'Hall', 'Rivera', 'Campbell', 'Mitchell', 'Carter', 'Roberts'],
            ],
            'cities' => [
                ['name' => 'New York', 'region' => 'NY', 'postal' => '10001'],
                ['name' => 'Albany', 'region' => 'NY', 'postal' => '12207'],
                ['name' => 'Los Angeles', 'region' => 'CA', 'postal' => '90012'],
                ['name' => 'San Diego', 'region' => 'CA', 'postal' => '92101'],
                ['name' => 'Sacramento', 'region' => 'CA', 'postal' => '95814'],
                ['name' => 'Houston', 'region' => 'TX', 'postal' => '77002'],
                ['name' => 'Dallas', 'region' => 'TX', 'postal' => '75201'],
                ['name' => 'Austin', 'region' => 'TX', 'postal' => '78701'],
                ['name' => 'San Antonio', 'region' => 'TX', 'postal' => '78205'],
                ['name' => 'Chicago', 'region' => 'IL', 'postal' => '60601'],
                ['name' => 'Phoenix', 'region' => 'AZ', 'postal' => '85004'],
                ['name' => 'Denver', 'region' => 'CO', 'postal' => '80202'],
                ['name' => 'Seattle', 'region' => 'WA', 'postal' => '98101'],
                ['name' => 'Boston', 'region' => 'MA', 'postal' => '02108'],
                ['name' => 'Atlanta', 'region' => 'GA', 'postal' => '30303'],
                ['name' => 'Miami', 'region' => 'FL', 'postal' => '33130'],
            ],
            'streets' => ['Maple Street', 'Oak Avenue', 'Pine Street', 'Cedar Lane', 'Elm Street', 'Washington Avenue', 'Main Street',
                'Park Avenue', 'Lake Drive', 'Hillcrest Road', 'Lincoln Avenue', 'Jefferson Street', 'Highland Avenue', 'Willow Way',
                'Ridge Road', 'Church Street', 'Mill Road', 'River Road', '2nd Street', 'Spring Street'],
            'carriers' => ['Verizon', 'AT&T', 'T-Mobile', 'US Cellular'],
            'mail_domains' => ['mailbox.example', 'webmail.test', 'netpost.example'],
            'vehicles' => [
                ['make' => 'Ford', 'model' => 'F-150'], ['make' => 'Chevrolet', 'model' => 'Silverado'], ['make' => 'Toyota', 'model' => 'Camry'],
                ['make' => 'Honda', 'model' => 'Civic'], ['make' => 'Toyota', 'model' => 'RAV4'], ['make' => 'Honda', 'model' => 'Accord'],
                ['make' => 'Nissan', 'model' => 'Altima'], ['make' => 'Jeep', 'model' => 'Wrangler'], ['make' => 'Ram', 'model' => '1500'],
                ['make' => 'Tesla', 'model' => 'Model 3'], ['make' => 'Chevrolet', 'model' => 'Malibu'], ['make' => 'Ford', 'model' => 'Explorer'],
                ['make' => 'Dodge', 'model' => 'Charger'], ['make' => 'GMC', 'model' => 'Sierra'],
            ],
            'colors' => self::COLORS_EN,
        ],

        // ─────────────────────────────────────────────────────────────── Allemagne
        'DEU' => [
            'code' => 'DEU',
            'label' => 'Allemagne',
            'label_en' => 'Germany',
            'fictional' => false,
            'units' => 'metric',
            'date_format' => 'd.m.Y',
            'date_hint' => 'tt.mm.jjjj',
            'phone' => [
                'prefix' => '+49',
                'pattern' => '/^(?:(?:\+|00)49[\s-]?(?:\(0\)[\s-]?)?|0)[1-9](?:[\s\/-]?\d){5,12}$/',
                'example' => '0151 2048317',
                'national_format' => '0 + Vorwahl + Rufnummer (Mobil 015x/016x/017x) — fictif : 0151 2xxxxxx',
            ],
            'plate' => [
                'label' => 'Kfz-Kennzeichen',
                'pattern' => '/^[A-ZÄÖÜ]{1,3}[\s-][A-Z]{1,2}\s?[1-9]\d{0,3}[EH]?$/u',
                'example' => 'B-AB 1234',
                'hint' => 'Unterscheidungszeichen (Stadt/Kreis) + 1–2 Buchstaben + 1–4 Ziffern (E = Elektro, H = Oldtimer)',
            ],
            'ids' => [
                // Numéros de série : chiffres et consonnes C F G H J K L M N P R T V W X Y Z (pas de voyelles).
                'id_card' => [
                    'label' => 'Personalausweis',
                    'short' => 'PA',
                    'pattern' => '/^[CFGHJKLMNPRTVWXYZ][CFGHJKLMNPRTVWXYZ0-9]{8}$/',
                    'example' => 'LH4R7C2N9',
                ],
                'passport' => [
                    'label' => 'Reisepass',
                    'short' => 'RP',
                    'pattern' => '/^[CFGHJK][CFGHJKLMNPRTVWXYZ0-9]{8}$/',
                    'example' => 'C4J8RN2X7',
                ],
                'driving_license' => [
                    'label' => 'Führerschein',
                    'short' => 'FS',
                    'pattern' => '/^[A-Z0-9]{11}$/',
                    'example' => 'F2K7019XN41',
                ],
            ],
            'postal' => ['label' => 'PLZ', 'pattern' => '/^(?!00)\d{5}$/', 'example' => '10115'],
            'address_format' => '{street} {number}, {postal} {city}',
            'records' => [
                'system' => 'INPOL',
                'systems' => [
                    'INPOL' => 'Informationssystem der Polizei (BKA)',
                    'SIS' => 'Schengener Informationssystem',
                    'ZEVIS' => 'Zentrales Verkehrsinformationssystem (KBA)',
                    'AZR' => 'Ausländerzentralregister (BAMF)',
                ],
                'vehicle_system' => 'ZEVIS',
                'no_record' => 'KEIN TREFFER',
                'hit' => 'TREFFER — FAHNDUNGSNOTIERUNG',
                'possible' => 'MÖGLICHER TREFFER — IDENTITÄT PRÜFEN',
                'wants_label' => 'Fahndung / Haftbefehl',
                'status' => [
                    'armed_dangerous' => 'BEWAFFNET UND GEFÄHRLICH',
                    'warrant' => 'HAFTBEFEHL',
                    'wanted' => 'AUSSCHREIBUNG ZUR FESTNAHME',
                    'missing' => 'VERMISST',
                    'flagged' => 'AUSGESCHRIEBEN',
                    'caution' => 'VORSICHT',
                    'watchlist' => 'GEFÄHRDER (FIKTIV)',
                    'hvt' => 'ZIELPERSON — HOHE PRIORITÄT',
                ],
                // Personengebundene Hinweise (PHW) d'INPOL, abréviation + libellé.
                'caution' => [
                    'officer_safety' => 'EIGENSICHERUNG BEACHTEN',
                    'armed' => 'BEWA — BEWAFFNET',
                    'violent' => 'GEWA — GEWALTTÄTIG',
                    'flight_risk' => 'AUSB — AUSBRECHER',
                    'mental_health' => 'PSYV — PSYCHISCHE UND VERHALTENSSTÖRUNG',
                    'medical' => 'ANST — ANSTECKUNGSGEFAHR',
                    'gang' => 'ROCKER / BANDENZUGEHÖRIGKEIT',
                    'explosives' => 'SPRENGSTOFFKENNTNISSE',
                ],
                'resident_status' => [
                    'citizen' => 'Deutsche Staatsangehörigkeit',
                    'eu' => 'Unionsbürger (Freizügigkeit)',
                    'residence_permit' => 'Aufenthaltserlaubnis',
                    'settlement_permit' => 'Niederlassungserlaubnis',
                    'blue_card' => 'Blaue Karte EU',
                    'visa' => 'Visum',
                    'asylum' => 'Aufenthaltsgestattung (Asylverfahren)',
                    'tolerated' => 'Duldung',
                    'removal' => 'Vollziehbar ausreisepflichtig',
                ],
            ],
            'names' => [
                'male' => ['Thomas', 'Michael', 'Andreas', 'Stefan', 'Christian', 'Markus', 'Daniel', 'Jan', 'Sebastian', 'Tobias',
                    'Florian', 'Alexander', 'Matthias', 'Martin', 'Peter', 'Frank', 'Jürgen', 'Uwe', 'Klaus', 'Wolfgang',
                    'Lukas', 'Leon', 'Felix', 'Jonas', 'Paul', 'Maximilian', 'Niklas', 'Tim', 'Philipp', 'Dennis',
                    'Marcel', 'Sven', 'Dirk', 'Torsten'],
                'female' => ['Anna', 'Laura', 'Lena', 'Julia', 'Sarah', 'Lisa', 'Katharina', 'Hannah', 'Sophie', 'Lea',
                    'Johanna', 'Miriam', 'Sabine', 'Petra', 'Andrea', 'Claudia', 'Susanne', 'Nicole', 'Stefanie', 'Christina',
                    'Sandra', 'Melanie', 'Jennifer', 'Jessica', 'Nadine', 'Monika', 'Ursula', 'Birgit', 'Kerstin', 'Anja',
                    'Franziska', 'Marie', 'Emma', 'Carina'],
                'last' => ['Müller', 'Schmidt', 'Schneider', 'Fischer', 'Weber', 'Meyer', 'Wagner', 'Becker', 'Schulz', 'Hoffmann',
                    'Schäfer', 'Koch', 'Bauer', 'Richter', 'Klein', 'Wolf', 'Schröder', 'Neumann', 'Schwarz', 'Zimmermann',
                    'Braun', 'Krüger', 'Hofmann', 'Hartmann', 'Lange', 'Schmitt', 'Werner', 'Schmitz', 'Krause', 'Meier',
                    'Lehmann', 'Schmid', 'Schulze', 'Maier', 'Köhler', 'Herrmann', 'König', 'Walter', 'Mayer', 'Huber',
                    'Kaiser', 'Fuchs', 'Peters', 'Lang', 'Scholz'],
            ],
            'cities' => [
                ['name' => 'Berlin', 'region' => 'Berlin', 'postal' => '10115'],
                ['name' => 'Hamburg', 'region' => 'Hamburg', 'postal' => '20095'],
                ['name' => 'München', 'region' => 'Bayern', 'postal' => '80331'],
                ['name' => 'Köln', 'region' => 'Nordrhein-Westfalen', 'postal' => '50667'],
                ['name' => 'Frankfurt am Main', 'region' => 'Hessen', 'postal' => '60311'],
                ['name' => 'Stuttgart', 'region' => 'Baden-Württemberg', 'postal' => '70173'],
                ['name' => 'Düsseldorf', 'region' => 'Nordrhein-Westfalen', 'postal' => '40213'],
                ['name' => 'Leipzig', 'region' => 'Sachsen', 'postal' => '04109'],
                ['name' => 'Dortmund', 'region' => 'Nordrhein-Westfalen', 'postal' => '44135'],
                ['name' => 'Bremen', 'region' => 'Bremen', 'postal' => '28195'],
                ['name' => 'Dresden', 'region' => 'Sachsen', 'postal' => '01067'],
                ['name' => 'Hannover', 'region' => 'Niedersachsen', 'postal' => '30159'],
                ['name' => 'Nürnberg', 'region' => 'Bayern', 'postal' => '90403'],
                ['name' => 'Essen', 'region' => 'Nordrhein-Westfalen', 'postal' => '45127'],
            ],
            'streets' => ['Hauptstraße', 'Schulstraße', 'Gartenstraße', 'Bahnhofstraße', 'Dorfstraße', 'Bergstraße', 'Birkenweg',
                'Lindenstraße', 'Kirchstraße', 'Waldstraße', 'Ringstraße', 'Schillerstraße', 'Goethestraße', 'Mühlenweg',
                'Wiesenweg', 'Am Markt', 'Feldstraße', 'Rosenstraße'],
            'carriers' => ['Telekom', 'Vodafone', 'O2 (Telefónica)', '1&1'],
            'mail_domains' => ['postfach.example', 'mailnetz.test', 'webpost.example'],
            'vehicles' => [
                ['make' => 'Volkswagen', 'model' => 'Golf'], ['make' => 'Volkswagen', 'model' => 'Passat'], ['make' => 'Opel', 'model' => 'Corsa'],
                ['make' => 'Opel', 'model' => 'Astra'], ['make' => 'BMW', 'model' => '3er'], ['make' => 'Mercedes-Benz', 'model' => 'C-Klasse'],
                ['make' => 'Audi', 'model' => 'A4'], ['make' => 'Ford', 'model' => 'Focus'], ['make' => 'Škoda', 'model' => 'Octavia'],
                ['make' => 'Volkswagen', 'model' => 'Polo'], ['make' => 'Seat', 'model' => 'Leon'], ['make' => 'Mercedes-Benz', 'model' => 'E-Klasse'],
                ['make' => 'Volkswagen', 'model' => 'Transporter'],
            ],
            'colors' => ['Schwarz', 'Weiß', 'Silber', 'Grau', 'Blau', 'Rot', 'Grün', 'Braun', 'Beige', 'Gelb', 'Orange'],
        ],

        // ─────────────────────────────────────────────────────────────── France
        'FRA' => [
            'code' => 'FRA',
            'label' => 'France',
            'label_en' => 'France',
            'fictional' => false,
            'units' => 'metric',
            'date_format' => 'd/m/Y',
            'date_hint' => 'jj/mm/aaaa',
            'phone' => [
                'prefix' => '+33',
                'pattern' => '/^(?:(?:\+|00)33[\s.-]?(?:\(0\)[\s.-]?)?|0)[1-9](?:[\s.-]?\d{2}){4}$/',
                'example' => '06 39 98 12 34',
                'national_format' => '0X XX XX XX XX — fictif : plage ARCEP réservée 06 39 98',
            ],
            'plate' => [
                'label' => 'Immatriculation',
                // SIV (depuis 2009) : AB-123-CD, sans I, O ni U, 000 exclu ; ancien FNI : 123 ABC 75 toléré.
                'pattern' => '/^(?:[A-HJ-NP-TV-Z]{2}[\s-]?(?!000)\d{3}[\s-]?[A-HJ-NP-TV-Z]{2}|\d{1,4}\s?[A-Z]{2,3}\s?(?:\d{2}|2[AB]|97\d))$/',
                'example' => 'AB-123-CD',
                'hint' => 'SIV : AB-123-CD (sans I, O, U) ; ancien format FNI 123 ABC 75',
            ],
            'ids' => [
                // NIR : sexe, année, mois, département, commune, rang, clé (97 − n mod 97 ; Corse 2A/2B).
                'national_id' => [
                    'label' => 'Numéro de sécurité sociale (NIR)',
                    'short' => 'NIR',
                    'pattern' => '/^[12]\s?\d{2}\s?(?:0[1-9]|1[0-2])\s?(?:\d{2}|2[AB])\s?\d{3}\s?\d{3}\s?\d{2}$/',
                    'example' => '1 85 07 75 115 423 87',
                ],
                // CNI : format 2021 à 9 caractères alphanumériques, ancien format à 12 chiffres.
                'id_card' => [
                    'label' => 'Carte nationale d’identité',
                    'short' => 'CNI',
                    'pattern' => '/^(?:[A-Z0-9]{9}|\d{12})$/',
                    'example' => 'XK4R92TP7',
                ],
                'driving_license' => [
                    'label' => 'Permis de conduire',
                    'short' => 'PC',
                    'pattern' => '/^(?:\d{2}[A-Z]{2}\d{5}|\d{12})$/',
                    'example' => '19KR03851',
                ],
                'passport' => [
                    'label' => 'Passeport',
                    'short' => 'PSP',
                    'pattern' => '/^\d{2}[A-Z]{2}\d{5}$/',
                    'example' => '21CX48715',
                ],
            ],
            'postal' => ['label' => 'Code postal', 'pattern' => '/^(?:0[1-9]|[1-8]\d|9[0-8])\d{3}$/', 'example' => '75001'],
            'address_format' => '{number} {street}, {postal} {city}',
            'records' => [
                'system' => 'FPR',
                'systems' => [
                    'FPR' => 'Fichier des personnes recherchées',
                    'TAJ' => 'Traitement d’antécédents judiciaires',
                    'FNPC' => 'Fichier national des permis de conduire',
                    'SIV' => 'Système d’immatriculation des véhicules',
                    'FOVeS' => 'Fichier des objets et des véhicules signalés',
                ],
                'vehicle_system' => 'SIV',
                'no_record' => 'NÉANT',
                'hit' => 'INSCRIT — CONFIRMER AUPRÈS DU SERVICE',
                'possible' => 'IDENTITÉ VOISINE — VÉRIFIER L’IDENTITÉ',
                'wants_label' => 'Fiches de recherche',
                'status' => self::STATUS_FR,
                'caution' => self::CAUTION_FR,
                'resident_status' => [
                    'citizen' => 'Nationalité française',
                    'eu' => 'Ressortissant de l’Union européenne',
                    'residence_permit' => 'Titre de séjour',
                    'resident_card' => 'Carte de résident',
                    'visa' => 'Visa long séjour',
                    'asylum' => 'Demandeur d’asile',
                    'removal' => 'Obligation de quitter le territoire',
                ],
            ],
            'names' => [
                'male' => ['Jean', 'Pierre', 'Michel', 'Philippe', 'Alain', 'Nicolas', 'Christophe', 'Patrick', 'Laurent', 'Frédéric',
                    'Stéphane', 'Julien', 'Sébastien', 'David', 'Olivier', 'Thomas', 'Antoine', 'Mathieu', 'Romain', 'Maxime',
                    'Alexandre', 'Guillaume', 'Vincent', 'François', 'Éric', 'Thierry', 'Pascal', 'Hugo', 'Lucas', 'Louis',
                    'Théo', 'Kévin', 'Jérôme', 'Benoît'],
                'female' => ['Marie', 'Nathalie', 'Isabelle', 'Sylvie', 'Catherine', 'Sandrine', 'Valérie', 'Christine', 'Stéphanie', 'Céline',
                    'Julie', 'Aurélie', 'Émilie', 'Camille', 'Sophie', 'Élodie', 'Laura', 'Manon', 'Léa', 'Chloé',
                    'Pauline', 'Mathilde', 'Claire', 'Anne', 'Hélène', 'Caroline', 'Virginie', 'Audrey', 'Laëtitia', 'Inès',
                    'Sarah', 'Emma', 'Lucie', 'Charlotte'],
                'last' => ['Martin', 'Bernard', 'Thomas', 'Petit', 'Robert', 'Richard', 'Durand', 'Dubois', 'Moreau', 'Laurent',
                    'Simon', 'Michel', 'Lefebvre', 'Leroy', 'Roux', 'David', 'Bertrand', 'Morel', 'Fournier', 'Girard',
                    'Bonnet', 'Dupont', 'Lambert', 'Fontaine', 'Rousseau', 'Vincent', 'Muller', 'Lefèvre', 'Faure', 'André',
                    'Mercier', 'Blanc', 'Guérin', 'Boyer', 'Garnier', 'Chevalier', 'François', 'Legrand', 'Gauthier', 'Garcia',
                    'Perrin', 'Robin', 'Clément', 'Morin'],
            ],
            'cities' => [
                ['name' => 'Paris', 'region' => 'Paris', 'postal' => '75011'],
                ['name' => 'Lyon', 'region' => 'Rhône', 'postal' => '69003'],
                ['name' => 'Marseille', 'region' => 'Bouches-du-Rhône', 'postal' => '13005'],
                ['name' => 'Toulouse', 'region' => 'Haute-Garonne', 'postal' => '31000'],
                ['name' => 'Lille', 'region' => 'Nord', 'postal' => '59000'],
                ['name' => 'Bordeaux', 'region' => 'Gironde', 'postal' => '33000'],
                ['name' => 'Nantes', 'region' => 'Loire-Atlantique', 'postal' => '44000'],
                ['name' => 'Strasbourg', 'region' => 'Bas-Rhin', 'postal' => '67000'],
                ['name' => 'Rennes', 'region' => 'Ille-et-Vilaine', 'postal' => '35000'],
                ['name' => 'Montpellier', 'region' => 'Hérault', 'postal' => '34000'],
                ['name' => 'Nice', 'region' => 'Alpes-Maritimes', 'postal' => '06000'],
                ['name' => 'Dijon', 'region' => 'Côte-d’Or', 'postal' => '21000'],
                ['name' => 'Grenoble', 'region' => 'Isère', 'postal' => '38000'],
                ['name' => 'Reims', 'region' => 'Marne', 'postal' => '51100'],
                ['name' => 'Ajaccio', 'region' => 'Corse-du-Sud', 'postal' => '20000'],
            ],
            'streets' => ['rue de la République', 'rue Victor Hugo', 'avenue Jean Jaurès', 'rue de la Gare', 'rue Pasteur', 'rue du Moulin',
                'place de l’Église', 'rue des Écoles', 'boulevard Gambetta', 'rue Jules Ferry', 'avenue du Général de Gaulle', 'rue de la Paix',
                'chemin des Vignes', 'allée des Tilleuls', 'rue Nationale', 'rue du Stade', 'impasse des Lilas', 'rue Voltaire'],
            'carriers' => ['Orange', 'SFR', 'Bouygues Telecom', 'Free Mobile'],
            'mail_domains' => ['courriel.example', 'boitemail.test', 'messagerie.example'],
            'vehicles' => [
                ['make' => 'Peugeot', 'model' => '208'], ['make' => 'Renault', 'model' => 'Clio'], ['make' => 'Peugeot', 'model' => '308'],
                ['make' => 'Citroën', 'model' => 'C3'], ['make' => 'Renault', 'model' => 'Mégane'], ['make' => 'Dacia', 'model' => 'Sandero'],
                ['make' => 'Peugeot', 'model' => '3008'], ['make' => 'Renault', 'model' => 'Captur'], ['make' => 'Citroën', 'model' => 'C4'],
                ['make' => 'Toyota', 'model' => 'Yaris'], ['make' => 'Dacia', 'model' => 'Duster'], ['make' => 'Volkswagen', 'model' => 'Golf'],
                ['make' => 'Renault', 'model' => 'Kangoo'],
            ],
            'colors' => self::COLORS_FR,
        ],

        // ─────────────────────────────────────────────────────────────── Royaume-Uni
        'GBR' => [
            'code' => 'GBR',
            'label' => 'Royaume-Uni',
            'label_en' => 'United Kingdom',
            'fictional' => false,
            'units' => 'metric',
            'date_format' => 'd/m/Y',
            'date_hint' => 'dd/mm/yyyy',
            'phone' => [
                'prefix' => '+44',
                'pattern' => '/^(?:(?:\+|00)44[\s-]?(?:\(0\)[\s-]?)?|0)[1-9](?:[\s-]?\d){8,9}$/',
                'example' => '07700 900123',
                'national_format' => '0XXXX XXXXXX — fictif : plage Ofcom 07700 900000 à 900999',
            ],
            'plate' => [
                'label' => 'Registration Number',
                'pattern' => '/^[A-Z]{2}\d{2}\s?[A-Z]{3}$/',
                'example' => 'LK71 XRN',
                'hint' => 'Deux lettres (zone) + deux chiffres (période) + trois lettres',
            ],
            'ids' => [
                // NINO : préfixe QQ réservé (jamais attribué).
                'national_id' => [
                    'label' => 'National Insurance Number',
                    'short' => 'NINO',
                    'pattern' => '/^[A-Z]{2}\s?\d{2}\s?\d{2}\s?\d{2}\s?[A-D]$/',
                    'example' => 'QQ 12 34 56 C',
                ],
                'passport' => [
                    'label' => 'UK Passport',
                    'short' => 'PPT',
                    'pattern' => '/^\d{9}$/',
                    'example' => '538204719',
                ],
                // Permis DVLA : 5 lettres du nom (complétées par 9), date codée, initiales, chiffre, 2 lettres.
                'driving_license' => [
                    'label' => 'Driving Licence',
                    'short' => 'DL',
                    'pattern' => '/^[A-Z9]{5}\d{6}[A-Z9]{2}\d[A-Z]{2}$/',
                    'example' => 'TAYLO805146J99KM',
                ],
            ],
            'postal' => ['label' => 'Postcode', 'pattern' => '/^[A-Z]{1,2}\d[A-Z\d]?\s?\d[A-Z]{2}$/', 'example' => 'M1 1AE'],
            'address_format' => '{number} {street}, {city} {postal}',
            'records' => [
                'system' => 'PNC',
                'systems' => [
                    'PNC' => 'Police National Computer',
                    'PND' => 'Police National Database',
                    'DVLA' => 'Driver and Vehicle Licensing Agency',
                    'IDENT1' => 'National Fingerprint Database',
                ],
                'vehicle_system' => 'DVLA',
                'no_record' => 'NO TRACE',
                'hit' => 'TRACE — CONFIRM WITH OWNING FORCE',
                'possible' => 'POSSIBLE TRACE — CONFIRM IDENTITY',
                'wants_label' => 'Wanted / Missing',
                'status' => [
                    'armed_dangerous' => 'ARMED AND DANGEROUS',
                    'warrant' => 'WARRANT',
                    'wanted' => 'WANTED',
                    'missing' => 'MISSING PERSON',
                    'flagged' => 'MARKER',
                    'caution' => 'CAUTION',
                    'watchlist' => 'WATCHLIST',
                    'hvt' => 'HIGH VALUE TARGET',
                ],
                // Warning signals du PNC (code + libellé).
                'caution' => [
                    'officer_safety' => 'OFFICER SAFETY',
                    'armed' => 'FI — FIREARMS',
                    'violent' => 'VI — VIOLENT',
                    'flight_risk' => 'ES — ESCAPER',
                    'mental_health' => 'XM — MENTAL HEALTH',
                    'medical' => 'CO — CONTAGIOUS',
                    'gang' => 'GANG ASSOCIATE',
                    'explosives' => 'EX — EXPLOSIVES',
                ],
                'resident_status' => [],
            ],
            'names' => [
                'male' => SseIdRealCountryCatalog::POOLS['UK']['m'],
                'female' => SseIdRealCountryCatalog::POOLS['UK']['f'],
                'last' => SseIdRealCountryCatalog::POOLS['UK']['last'],
            ],
            'cities' => [
                ['name' => 'London', 'region' => 'England', 'postal' => 'E1 6AN'],
                ['name' => 'Manchester', 'region' => 'England', 'postal' => 'M1 1AE'],
                ['name' => 'Birmingham', 'region' => 'England', 'postal' => 'B1 1BB'],
                ['name' => 'Leeds', 'region' => 'England', 'postal' => 'LS1 4AP'],
                ['name' => 'Bristol', 'region' => 'England', 'postal' => 'BS1 4DJ'],
                ['name' => 'Glasgow', 'region' => 'Scotland', 'postal' => 'G1 1XQ'],
                ['name' => 'Edinburgh', 'region' => 'Scotland', 'postal' => 'EH1 1YZ'],
                ['name' => 'Cardiff', 'region' => 'Wales', 'postal' => 'CF10 1EP'],
                ['name' => 'Belfast', 'region' => 'Northern Ireland', 'postal' => 'BT1 5GS'],
            ],
            'streets' => ['High Street', 'Station Road', 'Church Street', 'Victoria Road', 'Green Lane', 'Manor Road', 'Church Lane',
                'Park Road', 'Queens Road', 'The Avenue', 'New Road', 'Grange Road', 'Kings Road', 'Mill Lane', 'School Lane',
                'Springfield Road', 'North Street'],
            'carriers' => ['EE', 'Vodafone', 'O2', 'Three'],
            'mail_domains' => ['mailbox.example', 'postbox.test'],
            'vehicles' => [
                ['make' => 'Ford', 'model' => 'Fiesta'], ['make' => 'Vauxhall', 'model' => 'Corsa'], ['make' => 'Ford', 'model' => 'Focus'],
                ['make' => 'Volkswagen', 'model' => 'Golf'], ['make' => 'Nissan', 'model' => 'Qashqai'], ['make' => 'Vauxhall', 'model' => 'Astra'],
                ['make' => 'Mini', 'model' => 'Hatch'], ['make' => 'BMW', 'model' => '1 Series'], ['make' => 'Kia', 'model' => 'Sportage'],
                ['make' => 'Toyota', 'model' => 'Yaris'], ['make' => 'Land Rover', 'model' => 'Discovery'], ['make' => 'Audi', 'model' => 'A3'],
            ],
            'colors' => ['Black', 'White', 'Silver', 'Grey', 'Blue', 'Red', 'Green', 'Brown', 'Beige', 'Yellow'],
        ],

        // ─────────────────────────────────────────────────────────────── Pays-Bas
        'NLD' => [
            'code' => 'NLD',
            'label' => 'Pays-Bas',
            'label_en' => 'Netherlands',
            'fictional' => false,
            'units' => 'metric',
            'date_format' => 'd-m-Y',
            'date_hint' => 'dd-mm-jjjj',
            'phone' => [
                'prefix' => '+31',
                'pattern' => '/^(?:(?:\+|00)31[\s-]?(?:\(0\)[\s-]?)?|0)[1-9](?:[\s-]?\d){8}$/',
                'example' => '06 1234 5017',
                'national_format' => '0X XXXX XXXX (mobiel 06) — numéro fabriqué',
            ],
            'plate' => [
                'label' => 'Kenteken',
                'pattern' => '/^(?=(?:[A-Z0-9]-?){6}$)[A-Z0-9]{1,3}-[A-Z0-9]{1,3}-[A-Z0-9]{1,3}$/',
                'example' => 'HT-712-L',
                'hint' => 'Six signes en trois groupes (sidecode), ex. HT-712-L ou 1-KLM-23',
            ],
            'ids' => [
                'national_id' => ['label' => 'Burgerservicenummer', 'short' => 'BSN', 'pattern' => '/^\d{9}$/', 'example' => '913482705'],
                'id_card' => ['label' => 'Identiteitskaart', 'short' => 'IDK', 'pattern' => '/^[A-NP-Z]{2}[A-NP-Z0-9]{6}\d$/', 'example' => 'NX7RK43P2'],
                'passport' => ['label' => 'Paspoort', 'short' => 'PP', 'pattern' => '/^[A-NP-Z]{2}[A-NP-Z0-9]{6}\d$/', 'example' => 'NW3KH81T5'],
                'driving_license' => ['label' => 'Rijbewijs', 'short' => 'RB', 'pattern' => '/^\d{10}$/', 'example' => '5102847391'],
            ],
            'postal' => ['label' => 'Postcode', 'pattern' => '/^[1-9]\d{3}\s?(?!SA|SD|SS)[A-Z]{2}$/', 'example' => '3511 LX'],
            'address_format' => '{street} {number}, {postal} {city}',
            'records' => [
                'system' => 'OPS',
                'systems' => [
                    'OPS' => 'Opsporingsregister',
                    'HKS' => 'Herkenningsdienstsysteem',
                    'RDW' => 'Rijksdienst voor het Wegverkeer',
                    'BRP' => 'Basisregistratie Personen',
                    'SIS' => 'Schengen Informatiesysteem',
                ],
                'vehicle_system' => 'RDW',
                'no_record' => 'GEEN SIGNALERING',
                'hit' => 'SIGNALERING — CONTACT MET SIGNALERENDE DIENST',
                'possible' => 'MOGELIJKE MATCH — IDENTITEIT VERIFIËREN',
                'wants_label' => 'Signaleringen',
                'status' => [
                    'armed_dangerous' => 'GEWAPEND EN GEVAARLIJK',
                    'warrant' => 'ARRESTATIEBEVEL',
                    'wanted' => 'GESIGNALEERD TER AANHOUDING',
                    'missing' => 'VERMIST',
                    'flagged' => 'GESIGNALEERD',
                    'caution' => 'VOORZICHTIG',
                    'watchlist' => 'AANDACHTSLIJST',
                    'hvt' => 'PRIORITAIR DOELWIT',
                ],
                'caution' => [
                    'officer_safety' => 'EIGEN VEILIGHEID',
                    'armed' => 'VUURWAPENGEVAARLIJK',
                    'violent' => 'GEWELDDADIG',
                    'flight_risk' => 'VLUCHTGEVAAR',
                    'mental_health' => 'PSYCHISCHE PROBLEMATIEK',
                    'medical' => 'MEDISCH',
                    'gang' => 'BENDELID',
                    'explosives' => 'EXPLOSIEVEN',
                ],
                'resident_status' => [],
            ],
            'names' => [
                'male' => ['Daan', 'Sem', 'Lucas', 'Jesse', 'Thomas', 'Bram', 'Ruben', 'Lars', 'Tim', 'Niels', 'Joost', 'Pieter', 'Jeroen', 'Bas'],
                'female' => ['Emma', 'Sophie', 'Julia', 'Anna', 'Lisa', 'Eva', 'Sanne', 'Fleur', 'Lotte', 'Iris', 'Femke', 'Anouk'],
                'last' => ['de Jong', 'Jansen', 'de Vries', 'van den Berg', 'van Dijk', 'Bakker', 'Janssen', 'Visser', 'Smit', 'Meijer',
                    'de Boer', 'Mulder', 'de Groot', 'Bos', 'Vos', 'Peters'],
            ],
            'cities' => [
                ['name' => 'Amsterdam', 'region' => 'Noord-Holland', 'postal' => '1012 AB'],
                ['name' => 'Rotterdam', 'region' => 'Zuid-Holland', 'postal' => '3011 AD'],
                ['name' => 'Den Haag', 'region' => 'Zuid-Holland', 'postal' => '2511 CV'],
                ['name' => 'Utrecht', 'region' => 'Utrecht', 'postal' => '3511 LX'],
                ['name' => 'Eindhoven', 'region' => 'Noord-Brabant', 'postal' => '5611 AB'],
                ['name' => 'Groningen', 'region' => 'Groningen', 'postal' => '9711 LM'],
            ],
            'streets' => ['Kerkstraat', 'Schoolstraat', 'Molenstraat', 'Dorpsstraat', 'Stationsweg', 'Julianastraat', 'Beatrixstraat',
                'Wilhelminastraat', 'Nieuwstraat', 'Markt', 'Kastanjelaan', 'Eikenlaan', 'Industrieweg', 'Sportlaan', 'Lindenlaan', 'Prins Bernhardstraat'],
            'carriers' => ['KPN', 'Vodafone', 'Odido'],
            'mail_domains' => ['postvak.example', 'mailbox.test'],
            'vehicles' => [
                ['make' => 'Volkswagen', 'model' => 'Polo'], ['make' => 'Kia', 'model' => 'Picanto'], ['make' => 'Toyota', 'model' => 'Aygo'],
                ['make' => 'Opel', 'model' => 'Corsa'], ['make' => 'Peugeot', 'model' => '208'], ['make' => 'Renault', 'model' => 'Clio'],
                ['make' => 'Volkswagen', 'model' => 'Golf'], ['make' => 'Ford', 'model' => 'Fiesta'], ['make' => 'Kia', 'model' => 'Niro'],
                ['make' => 'Tesla', 'model' => 'Model Y'], ['make' => 'Škoda', 'model' => 'Octavia'], ['make' => 'Volvo', 'model' => 'XC40'],
            ],
            'colors' => ['Zwart', 'Wit', 'Grijs', 'Zilver', 'Blauw', 'Rood', 'Groen', 'Bruin', 'Beige', 'Geel'],
        ],

        // ─────────────────────────────────────────────────────────────── Belgique
        'BEL' => [
            'code' => 'BEL',
            'label' => 'Belgique',
            'label_en' => 'Belgium',
            'fictional' => false,
            'units' => 'metric',
            'date_format' => 'd/m/Y',
            'date_hint' => 'jj/mm/aaaa',
            'phone' => [
                'prefix' => '+32',
                'pattern' => '/^(?:(?:\+|00)32[\s-]?|0)[1-9](?:[\s.\/-]?\d){7,8}$/',
                'example' => '0470 12 34 05',
                'national_format' => '04XX XX XX XX (mobile) — numéro fabriqué',
            ],
            'plate' => [
                'label' => 'Plaque d’immatriculation',
                'pattern' => '/^[1-9][\s-]?[A-Z]{3}[\s-]?\d{3}$/',
                'example' => '1-KLM-482',
                'hint' => 'Chiffre + trois lettres + trois chiffres (1-ABC-123)',
            ],
            'ids' => [
                // Numéro de registre national : AA.MM.JJ-RRR.CC (clé modulo 97 ; préfixe 2 pour les naissances depuis 2000).
                'national_id' => ['label' => 'Numéro de registre national', 'short' => 'NRN', 'pattern' => '/^\d{2}\.?\d{2}\.?\d{2}-?\d{3}\.?\d{2}$/', 'example' => '91.03.14-123.31'],
                'id_card' => ['label' => 'Carte d’identité électronique', 'short' => 'eID', 'pattern' => '/^\d{3}-?\d{7}-?\d{2}$/', 'example' => '592-1048273-61'],
                'passport' => ['label' => 'Passeport', 'short' => 'PSP', 'pattern' => '/^[A-Z]{2}\d{6}$/', 'example' => 'EK482917'],
                'driving_license' => ['label' => 'Permis de conduire', 'short' => 'PC', 'pattern' => '/^\d{10}$/', 'example' => '6203918452'],
            ],
            'postal' => ['label' => 'Code postal', 'pattern' => '/^[1-9]\d{3}$/', 'example' => '1000'],
            'address_format' => '{street} {number}, {postal} {city}',
            'records' => [
                'system' => 'BNG',
                'systems' => [
                    'BNG' => 'Banque de données nationale générale',
                    'SIS' => 'Système d’information Schengen',
                    'DIV' => 'Direction pour l’immatriculation des véhicules',
                    'RN' => 'Registre national des personnes physiques',
                ],
                'vehicle_system' => 'DIV',
                'no_record' => 'NÉANT — AUCUN SIGNALEMENT',
                'hit' => 'SIGNALÉ — CONTACTER LE SERVICE SIGNALANT',
                'possible' => 'CORRESPONDANCE POSSIBLE — VÉRIFIER L’IDENTITÉ',
                'wants_label' => 'Signalements',
                'status' => [
                    'armed_dangerous' => 'ARMÉ ET DANGEREUX',
                    'warrant' => 'MANDAT D’ARRÊT',
                    'wanted' => 'SIGNALÉ POUR ARRESTATION',
                    'missing' => 'PERSONNE DISPARUE',
                    'flagged' => 'SIGNALÉ',
                    'caution' => 'PRUDENCE',
                    'watchlist' => 'LISTE DE SURVEILLANCE',
                    'hvt' => 'CIBLE PRIORITAIRE',
                ],
                'caution' => self::CAUTION_FR,
                'resident_status' => [],
            ],
            'names' => [
                'male' => SseIdRealCountryCatalog::POOLS['BELGIUM']['m'],
                'female' => SseIdRealCountryCatalog::POOLS['BELGIUM']['f'],
                'last' => SseIdRealCountryCatalog::POOLS['BELGIUM']['last'],
            ],
            'cities' => [
                ['name' => 'Bruxelles', 'region' => 'Bruxelles-Capitale', 'postal' => '1000'],
                ['name' => 'Antwerpen', 'region' => 'Anvers', 'postal' => '2000'],
                ['name' => 'Gent', 'region' => 'Flandre-Orientale', 'postal' => '9000'],
                ['name' => 'Charleroi', 'region' => 'Hainaut', 'postal' => '6000'],
                ['name' => 'Liège', 'region' => 'Liège', 'postal' => '4000'],
                ['name' => 'Namur', 'region' => 'Namur', 'postal' => '5000'],
                ['name' => 'Brugge', 'region' => 'Flandre-Occidentale', 'postal' => '8000'],
            ],
            'streets' => ['Rue de la Station', 'Kerkstraat', 'Rue de l’Église', 'Stationsstraat', 'Rue Haute', 'Dorpstraat', 'Rue du Moulin',
                'Nieuwstraat', 'Avenue des Tilleuls', 'Kapelstraat', 'Rue des Champs', 'Molenstraat', 'Rue de la Gare', 'Rue Royale',
                'Schoolstraat', 'Rue du Pont'],
            'carriers' => ['Proximus', 'Orange Belgium', 'Telenet', 'Base'],
            'mail_domains' => ['courriel.example', 'postbus.test'],
            'vehicles' => [
                ['make' => 'Volkswagen', 'model' => 'Golf'], ['make' => 'BMW', 'model' => 'Série 1'], ['make' => 'Peugeot', 'model' => '208'],
                ['make' => 'Volkswagen', 'model' => 'Polo'], ['make' => 'Mercedes-Benz', 'model' => 'Classe A'], ['make' => 'Audi', 'model' => 'A3'],
                ['make' => 'Renault', 'model' => 'Clio'], ['make' => 'Toyota', 'model' => 'Yaris'], ['make' => 'Škoda', 'model' => 'Octavia'],
                ['make' => 'Volvo', 'model' => 'XC40'], ['make' => 'Citroën', 'model' => 'C3'], ['make' => 'Dacia', 'model' => 'Sandero'],
            ],
            'colors' => self::COLORS_FR,
        ],

        // ─────────────────────────────────────────────────────────────── Italie
        'ITA' => [
            'code' => 'ITA',
            'label' => 'Italie',
            'label_en' => 'Italy',
            'fictional' => false,
            'units' => 'metric',
            'date_format' => 'd/m/Y',
            'date_hint' => 'gg/mm/aaaa',
            'phone' => [
                'prefix' => '+39',
                'pattern' => '/^(?:(?:\+|00)39[\s-]?)?(?:3\d{2}|0\d{1,3})(?:[\s.-]?\d){5,8}$/',
                'example' => '347 482 1053',
                'national_format' => '3XX XXX XXXX (cellulare) — numéro fabriqué',
            ],
            'plate' => [
                'label' => 'Targa',
                'pattern' => '/^[A-HJ-NPR-TV-Z]{2}\s?\d{3}\s?[A-HJ-NPR-TV-Z]{2}$/',
                'example' => 'GK 482 TL',
                'hint' => 'AA 123 AA (sans I, O, Q, U)',
            ],
            'ids' => [
                'national_id' => ['label' => 'Codice fiscale', 'short' => 'CF', 'pattern' => '/^[A-Z]{6}\d{2}[A-EHLMPRST]\d{2}[A-Z]\d{3}[A-Z]$/', 'example' => 'BNCLCU80A41H501X'],
                'id_card' => ['label' => 'Carta d’identità elettronica', 'short' => 'CIE', 'pattern' => '/^[A-Z]{2}\d{5}[A-Z]{2}$/', 'example' => 'CA48217KX'],
                'passport' => ['label' => 'Passaporto', 'short' => 'PASS', 'pattern' => '/^[A-Z]{2}\d{7}$/', 'example' => 'YC4821739'],
                'driving_license' => ['label' => 'Patente di guida', 'short' => 'PAT', 'pattern' => '/^[A-Z0-9]{10}$/', 'example' => 'U1H7K2X49N'],
            ],
            'postal' => ['label' => 'CAP', 'pattern' => '/^\d{5}$/', 'example' => '20121'],
            'address_format' => '{street} {number}, {postal} {city}',
            'records' => [
                'system' => 'SDI',
                'systems' => [
                    'SDI' => 'Sistema di Indagine (Banca dati interforze)',
                    'SIS' => 'Sistema d’informazione Schengen',
                    'PRA' => 'Pubblico Registro Automobilistico',
                    'MCTC' => 'Motorizzazione Civile (patenti)',
                ],
                'vehicle_system' => 'PRA',
                'no_record' => 'NESSUN PRECEDENTE',
                'hit' => 'POSITIVO — CONTATTARE L’UFFICIO SEGNALANTE',
                'possible' => 'POSSIBILE CORRISPONDENZA — VERIFICARE L’IDENTITÀ',
                'wants_label' => 'Ricerche',
                'status' => [
                    'armed_dangerous' => 'ARMATO E PERICOLOSO',
                    'warrant' => 'MANDATO DI ARRESTO',
                    'wanted' => 'RICERCATO',
                    'missing' => 'PERSONA SCOMPARSA',
                    'flagged' => 'SEGNALATO',
                    'caution' => 'CAUTELA',
                    'watchlist' => 'LISTA DI SORVEGLIANZA',
                    'hvt' => 'OBIETTIVO PRIORITARIO',
                ],
                'caution' => [
                    'officer_safety' => 'SICUREZZA OPERATORI',
                    'armed' => 'ARMATO',
                    'violent' => 'VIOLENTO',
                    'flight_risk' => 'PERICOLO DI FUGA',
                    'mental_health' => 'DISTURBI PSICHICI',
                    'medical' => 'RISCHIO SANITARIO',
                    'gang' => 'AFFILIAZIONE CRIMINALE',
                    'explosives' => 'ESPLOSIVI',
                ],
                'resident_status' => [],
            ],
            'names' => [
                'male' => ['Marco', 'Luca', 'Andrea', 'Giuseppe', 'Francesco', 'Alessandro', 'Matteo', 'Lorenzo', 'Davide', 'Simone', 'Federico', 'Stefano', 'Paolo', 'Giovanni'],
                'female' => ['Giulia', 'Francesca', 'Sara', 'Martina', 'Chiara', 'Federica', 'Valentina', 'Alessia', 'Elena', 'Silvia', 'Laura', 'Anna', 'Elisa'],
                'last' => ['Rossi', 'Russo', 'Ferrari', 'Esposito', 'Bianchi', 'Romano', 'Colombo', 'Ricci', 'Marino', 'Greco', 'Bruno', 'Gallo',
                    'Conti', 'De Luca', 'Costa', 'Giordano'],
            ],
            'cities' => [
                ['name' => 'Roma', 'region' => 'Lazio', 'postal' => '00184'],
                ['name' => 'Milano', 'region' => 'Lombardia', 'postal' => '20121'],
                ['name' => 'Napoli', 'region' => 'Campania', 'postal' => '80133'],
                ['name' => 'Torino', 'region' => 'Piemonte', 'postal' => '10121'],
                ['name' => 'Palermo', 'region' => 'Sicilia', 'postal' => '90133'],
                ['name' => 'Bologna', 'region' => 'Emilia-Romagna', 'postal' => '40121'],
                ['name' => 'Firenze', 'region' => 'Toscana', 'postal' => '50122'],
            ],
            'streets' => ['Via Roma', 'Via Garibaldi', 'Via Mazzini', 'Via Cavour', 'Via Dante', 'Via Verdi', 'Corso Italia', 'Via Marconi',
                'Via Matteotti', 'Via XX Settembre', 'Piazza della Repubblica', 'Via Vittorio Emanuele', 'Via dei Mille', 'Via San Francesco',
                'Viale Europa', 'Via della Stazione'],
            'carriers' => ['TIM', 'Vodafone', 'WindTre', 'Iliad'],
            'mail_domains' => ['posta.example', 'casella.test'],
            'vehicles' => [
                ['make' => 'Fiat', 'model' => 'Panda'], ['make' => 'Fiat', 'model' => '500'], ['make' => 'Lancia', 'model' => 'Ypsilon'],
                ['make' => 'Dacia', 'model' => 'Sandero'], ['make' => 'Toyota', 'model' => 'Yaris'], ['make' => 'Fiat', 'model' => 'Punto'],
                ['make' => 'Jeep', 'model' => 'Renegade'], ['make' => 'Volkswagen', 'model' => 'T-Roc'], ['make' => 'Peugeot', 'model' => '208'],
                ['make' => 'Renault', 'model' => 'Clio'], ['make' => 'Alfa Romeo', 'model' => 'Giulietta'], ['make' => 'Citroën', 'model' => 'C3'],
            ],
            'colors' => ['Nero', 'Bianco', 'Grigio', 'Argento', 'Blu', 'Rosso', 'Verde', 'Marrone', 'Beige', 'Giallo'],
        ],

        // ─────────────────────────────────────────────────────────────── Espagne
        'ESP' => [
            'code' => 'ESP',
            'label' => 'Espagne',
            'label_en' => 'Spain',
            'fictional' => false,
            'units' => 'metric',
            'date_format' => 'd/m/Y',
            'date_hint' => 'dd/mm/aaaa',
            'phone' => [
                'prefix' => '+34',
                'pattern' => '/^(?:(?:\+|00)34[\s-]?)?[6-9](?:[\s.-]?\d){8}$/',
                'example' => '612 482 053',
                'national_format' => '6XX XXX XXX (móvil) — numéro fabriqué',
            ],
            'plate' => [
                'label' => 'Matrícula',
                'pattern' => '/^\d{4}[\s-]?[BCDFGHJKLMNPRSTVWXYZ]{3}$/',
                'example' => '4821 KTL',
                'hint' => 'Quatre chiffres + trois consonnes (sans voyelles, Ñ ni Q)',
            ],
            'ids' => [
                // DNI : 8 chiffres + lettre de contrôle (n mod 23) ; NIE X/Y/Z accepté. Le permis reprend le numéro du DNI.
                'national_id' => ['label' => 'Documento Nacional de Identidad', 'short' => 'DNI', 'pattern' => '/^[XYZ\d]\d{7}[\s-]?[A-Z]$/', 'example' => '48213907L'],
                'id_card' => ['label' => 'Número de soporte del DNI', 'short' => 'SOP', 'pattern' => '/^[A-Z]{3}\d{6}$/', 'example' => 'CKT482173'],
                'passport' => ['label' => 'Pasaporte', 'short' => 'PAS', 'pattern' => '/^[A-Z]{3}\d{6}$/', 'example' => 'PKR204817'],
                'driving_license' => ['label' => 'Permiso de conducción', 'short' => 'PC', 'pattern' => '/^[XYZ\d]\d{7}[\s-]?[A-Z]$/', 'example' => '48213907L'],
            ],
            'postal' => ['label' => 'Código postal', 'pattern' => '/^(?:0[1-9]|[1-4]\d|5[0-2])\d{3}$/', 'example' => '28013'],
            'address_format' => '{street}, {number}, {postal} {city}',
            'records' => [
                'system' => 'BDSN',
                'systems' => [
                    'BDSN' => 'Base de Datos de Señalamientos Nacionales',
                    'PERPOL' => 'Registro de personas (Policía)',
                    'SIS' => 'Sistema de Información de Schengen',
                    'DGT' => 'Dirección General de Tráfico',
                ],
                'vehicle_system' => 'DGT',
                'no_record' => 'NEGATIVO — SIN SEÑALAMIENTOS',
                'hit' => 'POSITIVO — CONTACTAR CON LA UNIDAD REQUIRENTE',
                'possible' => 'POSIBLE COINCIDENCIA — VERIFICAR IDENTIDAD',
                'wants_label' => 'Requisitorias y señalamientos',
                'status' => [
                    'armed_dangerous' => 'ARMADO Y PELIGROSO',
                    'warrant' => 'ORDEN DE DETENCIÓN',
                    'wanted' => 'BUSCADO',
                    'missing' => 'DESAPARECIDO',
                    'flagged' => 'SEÑALADO',
                    'caution' => 'PRECAUCIÓN',
                    'watchlist' => 'LISTA DE VIGILANCIA',
                    'hvt' => 'OBJETIVO PRIORITARIO',
                ],
                'caution' => [
                    'officer_safety' => 'SEGURIDAD DE LOS AGENTES',
                    'armed' => 'ARMADO',
                    'violent' => 'VIOLENTO',
                    'flight_risk' => 'RIESGO DE FUGA',
                    'mental_health' => 'TRASTORNO MENTAL',
                    'medical' => 'RIESGO SANITARIO',
                    'gang' => 'BANDA ORGANIZADA',
                    'explosives' => 'EXPLOSIVOS',
                ],
                'resident_status' => [],
            ],
            'names' => [
                'male' => SseIdRealCountryCatalog::POOLS['SPAIN']['m'],
                'female' => SseIdRealCountryCatalog::POOLS['SPAIN']['f'],
                'last' => SseIdRealCountryCatalog::POOLS['SPAIN']['last'],
            ],
            'cities' => [
                ['name' => 'Madrid', 'region' => 'Comunidad de Madrid', 'postal' => '28013'],
                ['name' => 'Barcelona', 'region' => 'Cataluña', 'postal' => '08002'],
                ['name' => 'Valencia', 'region' => 'Comunidad Valenciana', 'postal' => '46002'],
                ['name' => 'Sevilla', 'region' => 'Andalucía', 'postal' => '41004'],
                ['name' => 'Zaragoza', 'region' => 'Aragón', 'postal' => '50001'],
                ['name' => 'Bilbao', 'region' => 'País Vasco', 'postal' => '48005'],
                ['name' => 'Málaga', 'region' => 'Andalucía', 'postal' => '29015'],
            ],
            'streets' => ['Calle Mayor', 'Calle Real', 'Calle de la Iglesia', 'Avenida de la Constitución', 'Plaza Mayor', 'Calle del Sol',
                'Calle Nueva', 'Calle de San José', 'Avenida de Andalucía', 'Calle del Carmen', 'Calle de la Paz', 'Calle Alta', 'Calle Ancha',
                'Paseo de la Estación', 'Calle de los Olivos', 'Calle de Cervantes'],
            'carriers' => ['Movistar', 'Vodafone', 'Orange', 'MásMóvil'],
            'mail_domains' => ['correo.example', 'buzon.test'],
            'vehicles' => [
                ['make' => 'SEAT', 'model' => 'Ibiza'], ['make' => 'SEAT', 'model' => 'León'], ['make' => 'Dacia', 'model' => 'Sandero'],
                ['make' => 'Toyota', 'model' => 'Corolla'], ['make' => 'Renault', 'model' => 'Clio'], ['make' => 'Volkswagen', 'model' => 'Polo'],
                ['make' => 'Peugeot', 'model' => '208'], ['make' => 'Hyundai', 'model' => 'Tucson'], ['make' => 'Nissan', 'model' => 'Qashqai'],
                ['make' => 'Kia', 'model' => 'Sportage'], ['make' => 'Citroën', 'model' => 'C4'], ['make' => 'Toyota', 'model' => 'C-HR'],
            ],
            'colors' => ['Negro', 'Blanco', 'Gris', 'Plata', 'Azul', 'Rojo', 'Verde', 'Marrón', 'Beige', 'Amarillo'],
        ],

        // ─────────────────────────────────────────────────────────────── Pologne
        'POL' => [
            'code' => 'POL',
            'label' => 'Pologne',
            'label_en' => 'Poland',
            'fictional' => false,
            'units' => 'metric',
            'date_format' => 'd.m.Y',
            'date_hint' => 'dd.mm.rrrr',
            'phone' => [
                'prefix' => '+48',
                'pattern' => '/^(?:(?:\+|00)48[\s-]?)?[1-9](?:[\s-]?\d){8}$/',
                'example' => '512 482 037',
                'national_format' => 'XXX XXX XXX — numéro fabriqué',
            ],
            'plate' => [
                'label' => 'Tablica rejestracyjna',
                'pattern' => '/^[A-Z]{2,3}\s?[A-Z0-9]{4,5}$/',
                'example' => 'WX 51273',
                'hint' => 'Wyróżnik powiatu (2–3 lettres) + 4–5 signes',
            ],
            'ids' => [
                // PESEL : AAMMJJ (mois +20 depuis 2000) + série + chiffre du sexe + clé (poids 1-3-7-9).
                'national_id' => ['label' => 'Numer PESEL', 'short' => 'PESEL', 'pattern' => '/^\d{11}$/', 'example' => '91031412348'],
                'id_card' => ['label' => 'Dowód osobisty', 'short' => 'DO', 'pattern' => '/^[A-Z]{3}\s?\d{6}$/', 'example' => 'CKR482173'],
                'passport' => ['label' => 'Paszport', 'short' => 'PASZ', 'pattern' => '/^[A-Z]{2}\s?\d{7}$/', 'example' => 'EK4821739'],
                'driving_license' => ['label' => 'Prawo jazdy', 'short' => 'PJ', 'pattern' => '/^\d{5}\/\d{2}\/\d{4}$/', 'example' => '04821/19/1462'],
            ],
            'postal' => ['label' => 'Kod pocztowy', 'pattern' => '/^\d{2}-\d{3}$/', 'example' => '00-950'],
            'address_format' => '{street} {number}, {postal} {city}',
            'records' => [
                'system' => 'KSIP',
                'systems' => [
                    'KSIP' => 'Krajowy System Informacyjny Policji',
                    'SIS' => 'System Informacyjny Schengen',
                    'CEPIK' => 'Centralna Ewidencja Pojazdów i Kierowców',
                    'PESEL' => 'Rejestr PESEL',
                ],
                'vehicle_system' => 'CEPIK',
                'no_record' => 'NIE FIGURUJE',
                'hit' => 'FIGURUJE — POTWIERDZIĆ W JEDNOSTCE WPROWADZAJĄCEJ',
                'possible' => 'MOŻLIWA ZGODNOŚĆ — ZWERYFIKOWAĆ TOŻSAMOŚĆ',
                'wants_label' => 'Poszukiwania',
                'status' => [
                    'armed_dangerous' => 'UZBROJONY I NIEBEZPIECZNY',
                    'warrant' => 'NAKAZ ARESZTOWANIA',
                    'wanted' => 'POSZUKIWANY',
                    'missing' => 'ZAGINIONY',
                    'flagged' => 'OZNACZONY',
                    'caution' => 'OSTROŻNIE',
                    'watchlist' => 'LISTA OBSERWACYJNA',
                    'hvt' => 'CEL PRIORYTETOWY',
                ],
                'caution' => [
                    'officer_safety' => 'BEZPIECZEŃSTWO FUNKCJONARIUSZY',
                    'armed' => 'UZBROJONY',
                    'violent' => 'AGRESYWNY',
                    'flight_risk' => 'RYZYKO UCIECZKI',
                    'mental_health' => 'ZABURZENIA PSYCHICZNE',
                    'medical' => 'ZAGROŻENIE MEDYCZNE',
                    'gang' => 'GRUPA PRZESTĘPCZA',
                    'explosives' => 'MATERIAŁY WYBUCHOWE',
                ],
                'resident_status' => [],
            ],
            'names' => [
                'male' => SseIdRealCountryCatalog::POOLS['POLAND']['m'],
                'female' => SseIdRealCountryCatalog::POOLS['POLAND']['f'],
                'last' => SseIdRealCountryCatalog::POOLS['POLAND']['last'],
            ],
            'cities' => [
                ['name' => 'Warszawa', 'region' => 'Mazowieckie', 'postal' => '00-950'],
                ['name' => 'Kraków', 'region' => 'Małopolskie', 'postal' => '31-005'],
                ['name' => 'Łódź', 'region' => 'Łódzkie', 'postal' => '90-001'],
                ['name' => 'Wrocław', 'region' => 'Dolnośląskie', 'postal' => '50-001'],
                ['name' => 'Poznań', 'region' => 'Wielkopolskie', 'postal' => '61-001'],
                ['name' => 'Gdańsk', 'region' => 'Pomorskie', 'postal' => '80-001'],
            ],
            'streets' => ['ul. Polna', 'ul. Leśna', 'ul. Słoneczna', 'ul. Krótka', 'ul. Szkolna', 'ul. Ogrodowa', 'ul. Lipowa', 'ul. Brzozowa',
                'ul. Łąkowa', 'ul. Kwiatowa', 'ul. Kościelna', 'ul. Sportowa', 'ul. Długa', 'ul. Kolejowa', 'ul. Parkowa', 'ul. Mickiewicza'],
            'carriers' => ['Orange', 'Play', 'Plus', 'T-Mobile'],
            'mail_domains' => ['poczta.example', 'skrzynka.test'],
            'vehicles' => [
                ['make' => 'Škoda', 'model' => 'Octavia'], ['make' => 'Toyota', 'model' => 'Corolla'], ['make' => 'Volkswagen', 'model' => 'Golf'],
                ['make' => 'Opel', 'model' => 'Astra'], ['make' => 'Škoda', 'model' => 'Fabia'], ['make' => 'Toyota', 'model' => 'Yaris'],
                ['make' => 'Kia', 'model' => 'Ceed'], ['make' => 'Dacia', 'model' => 'Duster'], ['make' => 'Hyundai', 'model' => 'i30'],
                ['make' => 'Ford', 'model' => 'Focus'], ['make' => 'Volkswagen', 'model' => 'Passat'], ['make' => 'Renault', 'model' => 'Clio'],
            ],
            'colors' => ['Czarny', 'Biały', 'Szary', 'Srebrny', 'Niebieski', 'Czerwony', 'Zielony', 'Brązowy', 'Beżowy', 'Żółty'],
        ],

        // ─────────────────────────────────────────────────────────────── Ukraine
        'UKR' => [
            'code' => 'UKR',
            'label' => 'Ukraine',
            'label_en' => 'Ukraine',
            'fictional' => false,
            'units' => 'metric',
            'date_format' => 'd.m.Y',
            'date_hint' => 'дд.мм.рррр',
            'phone' => [
                'prefix' => '+380',
                'pattern' => '/^(?:(?:\+|00)380[\s-]?|0)\(?\d{2}\)?(?:[\s-]?\d){7}$/',
                'example' => '067 482 1053',
                'national_format' => '0XX XXX XXXX — numéro fabriqué',
            ],
            'plate' => [
                'label' => 'Номерний знак',
                // Lettres latines équivalentes aux lettres cyrilliques autorisées (А В С Е Н І К М О Р Т Х).
                'pattern' => '/^[ABCEHIKMOPTX]{2}\s?\d{4}\s?[ABCEHIKMOPTX]{2}$/',
                'example' => 'KA 4821 HM',
                'hint' => 'Code de région (2 lettres) + 4 chiffres + 2 lettres',
            ],
            'ids' => [
                'national_id' => ['label' => 'РНОКПП (податковий номер)', 'short' => 'РНОКПП', 'pattern' => '/^\d{10}$/', 'example' => '3482107561'],
                'id_card' => ['label' => 'ID-картка', 'short' => 'ID', 'pattern' => '/^\d{9}$/', 'example' => '004821739'],
                'passport' => ['label' => 'Закордонний паспорт', 'short' => 'ЗП', 'pattern' => '/^[A-Z]{2}\d{6}$/', 'example' => 'FT482173'],
                'driving_license' => ['label' => 'Посвідчення водія', 'short' => 'ПВ', 'pattern' => '/^[A-Z]{3}\d{6}$/', 'example' => 'KXH204817'],
            ],
            'postal' => ['label' => 'Поштовий індекс', 'pattern' => '/^\d{5}$/', 'example' => '01001'],
            'address_format' => '{street}, {number}, {city}, {postal}',
            'records' => [
                'system' => 'IPNP',
                'systems' => [
                    'IPNP' => 'Інформаційний портал Національної поліції',
                    'ARMOR' => 'Інформаційна підсистема «Армор»',
                    'NAIS' => 'Реєстр транспортних засобів (НАІС МВС)',
                    'DMS' => 'Державна міграційна служба',
                ],
                'vehicle_system' => 'NAIS',
                'no_record' => 'ВІДОМОСТІ ВІДСУТНІ',
                'hit' => 'ЗБІГ — ПІДТВЕРДИТИ В ІНІЦІАТОРА',
                'possible' => 'МОЖЛИВИЙ ЗБІГ — ПЕРЕВІРИТИ ОСОБУ',
                'wants_label' => 'Розшук',
                'status' => [
                    'armed_dangerous' => 'ОЗБРОЄНИЙ І НЕБЕЗПЕЧНИЙ',
                    'warrant' => 'УХВАЛА ПРО ЗАТРИМАННЯ',
                    'wanted' => 'У РОЗШУКУ',
                    'missing' => 'ЗНИКЛИЙ БЕЗВІСТИ',
                    'flagged' => 'ПОЗНАЧЕНИЙ',
                    'caution' => 'ОБЕРЕЖНО',
                    'watchlist' => 'СПИСОК СПОСТЕРЕЖЕННЯ',
                    'hvt' => 'ПРІОРИТЕТНА ЦІЛЬ',
                ],
                'caution' => [
                    'officer_safety' => 'БЕЗПЕКА ПОЛІЦЕЙСЬКИХ',
                    'armed' => 'ОЗБРОЄНИЙ',
                    'violent' => 'СХИЛЬНИЙ ДО НАСИЛЬСТВА',
                    'flight_risk' => 'РИЗИК ВТЕЧІ',
                    'mental_health' => 'ПСИХІЧНІ РОЗЛАДИ',
                    'medical' => 'МЕДИЧНИЙ РИЗИК',
                    'gang' => 'ЗЛОЧИННЕ УГРУПОВАННЯ',
                    'explosives' => 'ВИБУХІВКА',
                ],
                'resident_status' => [],
            ],
            'names' => [
                'male' => SseIdRealCountryCatalog::POOLS['UKRAINE']['m'],
                'female' => SseIdRealCountryCatalog::POOLS['UKRAINE']['f'],
                'last' => SseIdRealCountryCatalog::POOLS['UKRAINE']['last'],
            ],
            'cities' => [
                ['name' => 'Kyiv', 'region' => 'Kyiv', 'postal' => '01001'],
                ['name' => 'Kharkiv', 'region' => 'Kharkivska oblast', 'postal' => '61001'],
                ['name' => 'Odesa', 'region' => 'Odeska oblast', 'postal' => '65001'],
                ['name' => 'Dnipro', 'region' => 'Dnipropetrovska oblast', 'postal' => '49000'],
                ['name' => 'Lviv', 'region' => 'Lvivska oblast', 'postal' => '79000'],
                ['name' => 'Poltava', 'region' => 'Poltavska oblast', 'postal' => '36000'],
            ],
            'streets' => ['vul. Shevchenka', 'vul. Franka', 'vul. Sadova', 'vul. Tsentralna', 'vul. Shkilna', 'vul. Myru', 'vul. Molodizhna',
                'vul. Lisova', 'vul. Zelena', 'vul. Naberezhna', 'vul. Hrushevskoho', 'vul. Soborna', 'prosp. Nezalezhnosti', 'vul. Vyshneva',
                'vul. Polova', 'vul. Sonyachna'],
            'carriers' => ['Kyivstar', 'Vodafone Ukraine', 'lifecell'],
            'mail_domains' => ['poshta.example', 'skrynka.test'],
            'vehicles' => [
                ['make' => 'Daewoo', 'model' => 'Lanos'], ['make' => 'ZAZ', 'model' => 'Sens'], ['make' => 'VAZ', 'model' => '2107'],
                ['make' => 'Škoda', 'model' => 'Octavia'], ['make' => 'Toyota', 'model' => 'Camry'], ['make' => 'Renault', 'model' => 'Duster'],
                ['make' => 'Volkswagen', 'model' => 'Passat'], ['make' => 'Hyundai', 'model' => 'Tucson'], ['make' => 'Toyota', 'model' => 'RAV4'],
                ['make' => 'Renault', 'model' => 'Logan'], ['make' => 'Chevrolet', 'model' => 'Aveo'], ['make' => 'Mitsubishi', 'model' => 'L200'],
            ],
            'colors' => ['Чорний', 'Білий', 'Сірий', 'Сріблястий', 'Синій', 'Червоний', 'Зелений', 'Коричневий', 'Бежевий', 'Жовтий'],
        ],

        // ─────────────────────────────────────────────────────────────── Irak
        'IRQ' => [
            'code' => 'IRQ',
            'label' => 'Irak',
            'label_en' => 'Iraq',
            'fictional' => false,
            'units' => 'metric',
            'date_format' => 'd/m/Y',
            'date_hint' => 'dd/mm/yyyy',
            'phone' => [
                'prefix' => '+964',
                'pattern' => '/^(?:(?:\+|00)964[\s-]?|0)7(?:[\s-]?\d){9}$/',
                'example' => '0770 482 1053',
                'national_format' => '07XX XXX XXXX (mobile) — numéro fabriqué',
            ],
            'plate' => [
                'label' => 'Vehicle Plate',
                'pattern' => '/^[A-Z]{3}[\s-]?[A-Z][\s-]?\d{4,5}$/',
                'example' => 'BGD A 48213',
                'hint' => 'Gouvernorat (3 lettres) + lettre de série + 4–5 chiffres (translittération)',
            ],
            'ids' => [
                'id_card' => ['label' => 'National Card', 'short' => 'NC', 'pattern' => '/^\d{12}$/', 'example' => '199048213907'],
                'passport' => ['label' => 'Iraqi Passport', 'short' => 'PPT', 'pattern' => '/^[A-Z]\d{7}$/', 'example' => 'A4821739'],
                'driving_license' => ['label' => 'Driving License', 'short' => 'DL', 'pattern' => '/^\d{8}$/', 'example' => '48213907'],
            ],
            'postal' => ['label' => 'Postal Code', 'pattern' => '/^\d{5}$/', 'example' => '10001'],
            'address_format' => '{street}, No. {number}, {city} {postal}',
            'records' => [
                'system' => 'MOI-CID',
                'systems' => [
                    'MOI-CID' => 'Ministry of Interior — Criminal Records',
                    'HIIDE' => 'Biometric Enrollment Database',
                    'NCD' => 'National Card Directorate',
                    'TPD' => 'Traffic Police Directorate',
                ],
                'vehicle_system' => 'TPD',
                'no_record' => 'NO RECORD',
                'hit' => 'HIT — CONFIRM WITH MOI',
                'possible' => 'POSSIBLE MATCH — VERIFY IDENTITY',
                'wants_label' => 'Wanted List',
                'status' => self::STATUS_EN,
                'caution' => self::CAUTION_EN,
                'resident_status' => [],
            ],
            'names' => [
                'male' => SseIdRealCountryCatalog::POOLS['IRAQ_ID']['m'],
                'female' => SseIdRealCountryCatalog::POOLS['IRAQ_ID']['f'],
                'last' => SseIdRealCountryCatalog::POOLS['IRAQ_ID']['last'],
            ],
            'cities' => [
                ['name' => 'Baghdad', 'region' => 'Baghdad', 'postal' => '10001'],
                ['name' => 'Basra', 'region' => 'Basra', 'postal' => '61001'],
                ['name' => 'Mosul', 'region' => 'Nineveh', 'postal' => '41001'],
                ['name' => 'Erbil', 'region' => 'Erbil', 'postal' => '44001'],
                ['name' => 'Kirkuk', 'region' => 'Kirkuk', 'postal' => '36001'],
                ['name' => 'Najaf', 'region' => 'Najaf', 'postal' => '54001'],
            ],
            'streets' => ['Al-Rasheed Street', 'Al-Mutanabbi Street', 'Palestine Street', 'Al-Karrada Street', 'Haifa Street', 'Al-Saadoun Street',
                'Al-Jumhuriya Street', 'Al-Kindi Street', 'Abu Nuwas Street', 'Al-Rabee Street', 'Al-Mansour Street', 'Al-Zahra Street',
                'Al-Andalus Street', 'Al-Nahr Street', 'Al-Sinaa Street'],
            'carriers' => ['Zain Iraq', 'Asiacell', 'Korek'],
            'mail_domains' => ['mail.example', 'inbox.test'],
            'vehicles' => [
                ['make' => 'Toyota', 'model' => 'Land Cruiser'], ['make' => 'Toyota', 'model' => 'Hilux'], ['make' => 'Kia', 'model' => 'Cerato'],
                ['make' => 'Hyundai', 'model' => 'Elantra'], ['make' => 'Toyota', 'model' => 'Corolla'], ['make' => 'Nissan', 'model' => 'Sunny'],
                ['make' => 'Chevrolet', 'model' => 'Caprice'], ['make' => 'Kia', 'model' => 'Rio'], ['make' => 'Hyundai', 'model' => 'Sonata'],
                ['make' => 'Toyota', 'model' => 'Camry'], ['make' => 'Mitsubishi', 'model' => 'Pajero'], ['make' => 'Peugeot', 'model' => '405'],
            ],
            'colors' => self::COLORS_EN,
        ],

        // ─────────────────────────────────────────────────────────────── Afghanistan
        'AFG' => [
            'code' => 'AFG',
            'label' => 'Afghanistan',
            'label_en' => 'Afghanistan',
            'fictional' => false,
            'units' => 'metric',
            'date_format' => 'd/m/Y',
            'date_hint' => 'dd/mm/yyyy',
            'phone' => [
                'prefix' => '+93',
                'pattern' => '/^(?:(?:\+|00)93[\s-]?|0)7(?:[\s-]?\d){8}$/',
                'example' => '0700 482 105',
                'national_format' => '07XX XXX XXX (mobile) — numéro fabriqué',
            ],
            'plate' => [
                'label' => 'Vehicle Plate',
                'pattern' => '/^[A-Z]{3}[\s-]?\d{4,5}$/',
                'example' => 'KBL 48213',
                'hint' => 'Province (3 lettres, translittération) + 4–5 chiffres',
            ],
            'ids' => [
                'id_card' => ['label' => 'e-Tazkira', 'short' => 'TZK', 'pattern' => '/^\d{4}-?\d{4}-?\d{5}$/', 'example' => '1399-4821-73905'],
                'passport' => ['label' => 'Afghan Passport', 'short' => 'PPT', 'pattern' => '/^[A-Z]\d{7}$/', 'example' => 'P4821739'],
                'driving_license' => ['label' => 'Driving License', 'short' => 'DL', 'pattern' => '/^\d{6,8}$/', 'example' => '4821739'],
            ],
            'postal' => ['label' => 'Postal Code', 'pattern' => '/^\d{4}$/', 'example' => '1001'],
            'address_format' => '{street}, House {number}, {city}',
            'records' => [
                'system' => 'ABIS',
                'systems' => [
                    'ABIS' => 'Afghan Automated Biometric Identification System',
                    'MOI-CRD' => 'Ministry of Interior — Criminal Records',
                    'NSIA' => 'National Statistics and Information Authority (Tazkira)',
                    'VRD' => 'Vehicle Registration Directorate',
                ],
                'vehicle_system' => 'VRD',
                'no_record' => 'NO RECORD',
                'hit' => 'HIT — CONFIRM WITH ORIGINATING UNIT',
                'possible' => 'POSSIBLE MATCH — VERIFY IDENTITY',
                'wants_label' => 'Watch List',
                'status' => self::STATUS_EN,
                'caution' => self::CAUTION_EN,
                'resident_status' => [],
            ],
            'names' => [
                'male' => SseIdRealCountryCatalog::POOLS['AFGHANISTAN']['m'],
                'female' => SseIdRealCountryCatalog::POOLS['AFGHANISTAN']['f'],
                'last' => SseIdRealCountryCatalog::POOLS['AFGHANISTAN']['last'],
            ],
            'cities' => [
                ['name' => 'Kabul', 'region' => 'Kabul', 'postal' => '1001'],
                ['name' => 'Kandahar', 'region' => 'Kandahar', 'postal' => '3801'],
                ['name' => 'Herat', 'region' => 'Herat', 'postal' => '3001'],
                ['name' => 'Mazar-i-Sharif', 'region' => 'Balkh', 'postal' => '1701'],
                ['name' => 'Jalalabad', 'region' => 'Nangarhar', 'postal' => '2601'],
                ['name' => 'Kunduz', 'region' => 'Kunduz', 'postal' => '3501'],
            ],
            'streets' => ['Darulaman Road', 'Jalalabad Road', 'Shahr-e Naw Street', 'Kart-e Char Street', 'Airport Road', 'Salang Watt',
                'Jada-e Maiwand', 'Bagh-e Bala Road', 'Wazir Akbar Khan Street', 'Kolola Pushta Road', 'Taimani Street', 'Qargha Road',
                'Pul-e Surkh Road', 'Microrayon Street', 'Kart-e Seh Street'],
            'carriers' => ['Roshan', 'AWCC', 'Etisalat', 'Salaam'],
            'mail_domains' => ['mail.example', 'inbox.test'],
            'vehicles' => [
                ['make' => 'Toyota', 'model' => 'Corolla'], ['make' => 'Toyota', 'model' => 'Hilux'], ['make' => 'Toyota', 'model' => 'Land Cruiser'],
                ['make' => 'Toyota', 'model' => 'TownAce'], ['make' => 'Toyota', 'model' => 'Hiace'], ['make' => 'Toyota', 'model' => 'Hilux Surf'],
                ['make' => 'Toyota', 'model' => 'Premio'], ['make' => 'Lexus', 'model' => 'RX'], ['make' => 'Suzuki', 'model' => 'Mehran'],
                ['make' => 'Mitsubishi', 'model' => 'Pajero'], ['make' => 'Ford', 'model' => 'Ranger'], ['make' => 'Honda', 'model' => 'CG125'],
            ],
            'colors' => self::COLORS_EN,
        ],

        // ─────────────────────────────────────────────────────────────── Mali
        'MLI' => [
            'code' => 'MLI',
            'label' => 'Mali',
            'label_en' => 'Mali',
            'fictional' => false,
            'units' => 'metric',
            'date_format' => 'd/m/Y',
            'date_hint' => 'jj/mm/aaaa',
            'phone' => [
                'prefix' => '+223',
                'pattern' => '/^(?:(?:\+|00)223[\s-]?)?[2-9](?:[\s-]?\d){7}$/',
                'example' => '76 48 21 05',
                'national_format' => 'XX XX XX XX — numéro fabriqué',
            ],
            'plate' => [
                'label' => 'Plaque d’immatriculation',
                'pattern' => '/^[A-Z]{2}[\s-]?\d{4}[\s-]?[A-Z]{1,2}$/',
                'example' => 'AK 4821 MD',
                'hint' => 'Format simplifié : deux lettres + quatre chiffres + code',
            ],
            'ids' => [
                'national_id' => ['label' => 'Numéro d’identification nationale', 'short' => 'NINA', 'pattern' => '/^\d{14}[A-Z]$/', 'example' => '18904821739052K'],
                'passport' => ['label' => 'Passeport', 'short' => 'PSP', 'pattern' => '/^[A-Z]{1,2}\d{7}$/', 'example' => 'AA4821739'],
                'driving_license' => ['label' => 'Permis de conduire', 'short' => 'PC', 'pattern' => '/^[A-Z0-9]{6,12}$/', 'example' => 'BKO482173'],
            ],
            'postal' => ['label' => 'Boîte postale', 'pattern' => '/^(?:BP\s?)?\d{1,4}$/', 'example' => 'BP 2014'],
            'address_format' => '{street}, porte {number}, {city} ({postal})',
            'records' => [
                'system' => 'WAPIS',
                'systems' => [
                    'WAPIS' => 'Système d’information policière pour l’Afrique de l’Ouest',
                    'FCP' => 'Fichier central de la police nationale',
                    'RAVEC' => 'Recensement administratif à vocation d’état civil',
                    'DNTT' => 'Direction nationale des transports terrestres (cartes grises)',
                ],
                'vehicle_system' => 'DNTT',
                'no_record' => 'NÉANT',
                'hit' => 'SIGNALÉ — CONFIRMER AUPRÈS DU SERVICE',
                'possible' => 'CORRESPONDANCE POSSIBLE — VÉRIFIER L’IDENTITÉ',
                'wants_label' => 'Avis de recherche',
                'status' => [
                    'armed_dangerous' => 'ARMÉ ET DANGEREUX',
                    'warrant' => 'MANDAT D’ARRÊT',
                    'wanted' => 'RECHERCHÉ',
                    'missing' => 'DISPARU',
                    'flagged' => 'SIGNALÉ',
                    'caution' => 'PRUDENCE',
                    'watchlist' => 'LISTE DE SURVEILLANCE',
                    'hvt' => 'CIBLE PRIORITAIRE',
                ],
                'caution' => self::CAUTION_FR,
                'resident_status' => [],
            ],
            'names' => [
                'male' => ['Moussa', 'Mamadou', 'Ibrahim', 'Oumar', 'Amadou', 'Boubacar', 'Seydou', 'Souleymane', 'Abdoulaye', 'Bakary',
                    'Modibo', 'Adama', 'Issa', 'Sékou'],
                'female' => ['Aminata', 'Fatoumata', 'Mariam', 'Awa', 'Kadiatou', 'Oumou', 'Hawa', 'Djeneba', 'Aïssata', 'Rokia', 'Assitan', 'Salimata'],
                'last' => ['Traoré', 'Coulibaly', 'Diarra', 'Keïta', 'Konaté', 'Sangaré', 'Touré', 'Cissé', 'Diallo', 'Sidibé', 'Dembélé',
                    'Koné', 'Sissoko', 'Maïga', 'Doumbia', 'Diakité'],
            ],
            'cities' => [
                ['name' => 'Bamako', 'region' => 'District de Bamako', 'postal' => 'BP 120'],
                ['name' => 'Sikasso', 'region' => 'Sikasso', 'postal' => 'BP 34'],
                ['name' => 'Ségou', 'region' => 'Ségou', 'postal' => 'BP 21'],
                ['name' => 'Mopti', 'region' => 'Mopti', 'postal' => 'BP 45'],
                ['name' => 'Kayes', 'region' => 'Kayes', 'postal' => 'BP 12'],
                ['name' => 'Gao', 'region' => 'Gao', 'postal' => 'BP 8'],
            ],
            'streets' => ['avenue de l’Indépendance', 'boulevard du Peuple', 'avenue de la Nation', 'route de Koulikoro', 'route de Sotuba',
                'rue du Marché', 'rue de la Mosquée', 'rue du Fleuve', 'avenue des Martyrs', 'rue 124', 'rue 229', 'rue 311', 'rue 405',
                'rue 518', 'rue du Commerce'],
            'carriers' => ['Orange Mali', 'Malitel', 'Telecel Mali'],
            'mail_domains' => ['courriel.example', 'boitemail.test'],
            'vehicles' => [
                ['make' => 'Toyota', 'model' => 'Hilux'], ['make' => 'Toyota', 'model' => 'Land Cruiser'], ['make' => 'Toyota', 'model' => 'Corolla'],
                ['make' => 'Mercedes-Benz', 'model' => '190'], ['make' => 'Peugeot', 'model' => '406'], ['make' => 'Renault', 'model' => 'Logan'],
                ['make' => 'Nissan', 'model' => 'Patrol'], ['make' => 'Toyota', 'model' => 'RAV4'], ['make' => 'Hyundai', 'model' => 'Accent'],
                ['make' => 'Kia', 'model' => 'Picanto'], ['make' => 'Suzuki', 'model' => 'Vitara'], ['make' => 'Peugeot', 'model' => '504'],
            ],
            'colors' => self::COLORS_FR,
        ],

        // ─────────────────────────────────────────────────────────────── Altis (fictif)
        'ALT' => [
            'code' => 'ALT',
            'label' => 'Altis (fictif)',
            'label_en' => 'Altis (fictional)',
            'fictional' => true,
            'units' => 'metric',
            'date_format' => 'd/m/Y',
            'date_hint' => 'dd/mm/yyyy',
            'phone' => [
                // +999 : indicatif non attribué, propre au pack fictif.
                'prefix' => '+999',
                'pattern' => '/^(?:(?:\+|00)999[\s-]?|0)[2-9](?:[\s-]?\d){7}$/',
                'example' => '022 55 01 23',
                'national_format' => '0XX XX XX XX — indicatif fictif +999',
            ],
            'plate' => [
                'label' => 'Vehicle Plate',
                'pattern' => '/^(?:AL|ST)[\s-]?\d{4,5}$/',
                'example' => 'AL 48213',
                'hint' => 'AL (Altis) ou ST (Stratis) + 4–5 chiffres',
            ],
            'ids' => [
                'id_card' => ['label' => 'Altian Identity Card', 'short' => 'AIC', 'pattern' => '/^[A-Z]{2}\s?\d{6}$/', 'example' => 'AK 482173'],
                'passport' => ['label' => 'Altian Passport', 'short' => 'APP', 'pattern' => '/^[A-Z]{2}\d{7}$/', 'example' => 'AP4821739'],
                'driving_license' => ['label' => 'Altis Driving Permit', 'short' => 'ADP', 'pattern' => '/^\d{9}$/', 'example' => '482173905'],
            ],
            'postal' => ['label' => 'Postal Code', 'pattern' => '/^\d{3}\s?\d{2}$/', 'example' => '850 01'],
            'address_format' => '{street} {number}, {postal} {city}, {region}',
            'records' => [
                'system' => 'ACIS',
                'systems' => [
                    'ACIS' => 'Altis Criminal Information System',
                    'APR' => 'Altis Population Register',
                    'AVR' => 'Altis Vehicle Registry',
                    'IBX' => 'Island Bureau Exchange',
                ],
                'vehicle_system' => 'AVR',
                'no_record' => 'NO RECORD',
                'hit' => 'HIT — CONFIRM WITH ACIS DESK',
                'possible' => 'POSSIBLE MATCH — VERIFY IDENTITY',
                'wants_label' => 'Wanted Notices',
                'status' => self::STATUS_EN,
                'caution' => self::CAUTION_EN,
                'resident_status' => [],
            ],
            'names' => [
                'male' => SseIdRealCountryCatalog::POOLS['GREECE']['m'],
                'female' => SseIdRealCountryCatalog::POOLS['GREECE']['f'],
                'last' => SseIdRealCountryCatalog::POOLS['GREECE']['last'],
            ],
            'cities' => [
                ['name' => 'Kavala', 'region' => 'Altis', 'postal' => '850 01'],
                ['name' => 'Pyrgos', 'region' => 'Altis', 'postal' => '851 00'],
                ['name' => 'Athira', 'region' => 'Altis', 'postal' => '852 00'],
                ['name' => 'Sofia', 'region' => 'Altis', 'postal' => '853 00'],
                ['name' => 'Zaros', 'region' => 'Altis', 'postal' => '854 00'],
                ['name' => 'Agia Marina', 'region' => 'Stratis', 'postal' => '860 01'],
                ['name' => 'Girna', 'region' => 'Stratis', 'postal' => '861 00'],
            ],
            'streets' => ['Odos Kastrou', 'Odos Elias', 'Odos Thalassis', 'Odos Akropoleos', 'Odos Agiou Georgiou', 'Leoforos Kavalas',
                'Odos Limenos', 'Odos Pyrgou', 'Odos Neochoriou', 'Odos Zarou', 'Odos Sofias', 'Odos Molou', 'Odos Charkias',
                'Odos Oreokastrou', 'Plateia Kentriki'],
            'carriers' => ['AltisTel', 'Stratis Mobile'],
            'mail_domains' => ['altis.test', 'kavala-mail.example'],
            'vehicles' => [
                ['make' => 'Altia', 'model' => 'Hatchback'], ['make' => 'Altia', 'model' => 'Hatchback Sport'], ['make' => 'Altia', 'model' => 'SUV'],
                ['make' => 'Altia', 'model' => 'Offroad'], ['make' => 'Altia', 'model' => 'Van'], ['make' => 'Altia', 'model' => 'Truck'],
                ['make' => 'Altia', 'model' => 'Quad Bike'], ['make' => 'Kavala Motors', 'model' => 'Minivan'], ['make' => 'Kavala Motors', 'model' => 'Taxi'],
                ['make' => 'Kavala Motors', 'model' => 'Boxer Truck'],
            ],
            'colors' => self::COLORS_EN,
        ],
    ];

    /**
     * Codes des packs : pays réels dans l'ordre d'affichage, puis le pack fictif si demandé.
     *
     * @return list<string>
     */
    public static function codes(bool $withFictional = true): array
    {
        return $withFictional ? array_merge(self::ORDER, self::FICTIONAL) : self::ORDER;
    }

    public static function exists(string $iso3): bool
    {
        return isset(self::PACKS[strtoupper(trim($iso3))]);
    }

    /** Code ISO alpha-3 connu (majuscules), sinon DEFAULT. */
    public static function normalize(?string $iso3): string
    {
        $code = strtoupper(trim((string) $iso3));

        return isset(self::PACKS[$code]) ? $code : self::DEFAULT;
    }

    /**
     * Pack du pays (repli sur DEFAULT si inconnu).
     *
     * @return array<string, mixed>
     */
    public static function get(string $iso3): array
    {
        return self::PACKS[self::normalize($iso3)];
    }

    /** Nom du pays en français. */
    public static function label(string $iso3): string
    {
        return (string) self::get($iso3)['label'];
    }
}
