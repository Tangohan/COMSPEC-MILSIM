<?php

declare(strict_types=1);

/**
 * FICHIER GÉNÉRÉ : ne pas modifier à la main.
 *
 * Miroir du registre des papiers du mod SSE (CfgComspecDocuments.hpp, extensions, countries.json).
 * Régénérer : php tools/sse/sync_document_types.php (voir App\Support\SseDocumentTypes).
 */
return [
    'types' => [
        'id_card' => [
            'name' => 'Carte d’identité',
            'name_en' => 'Identity card',
            'category' => 'identity',
            'sides' => 2,
            'countries' => [],
            'mrz' => 'td1',
            'mrz_side' => 'verso',
            'chip' => false,
            'photo' => true,
            'extension' => '',
        ],
        'residence_permit' => [
            'name' => 'Titre de séjour',
            'name_en' => 'Residence permit',
            'category' => 'identity',
            'sides' => 2,
            'countries' => [],
            'mrz' => 'td1',
            'mrz_side' => 'verso',
            'chip' => true,
            'photo' => true,
            'extension' => '',
        ],
        'military_id' => [
            'name' => 'Carte militaire',
            'name_en' => 'Military ID',
            'category' => 'identity',
            'sides' => 2,
            'countries' => [],
            'mrz' => 'td1',
            'mrz_side' => 'verso',
            'chip' => false,
            'photo' => true,
            'extension' => '',
        ],
        'passport' => [
            'name' => 'Passeport',
            'name_en' => 'Passport',
            'category' => 'travel',
            'sides' => 1,
            'countries' => [],
            'mrz' => 'td3',
            'mrz_side' => 'recto',
            'chip' => false,
            'photo' => true,
            'extension' => '',
        ],
        'vehicle_registration' => [
            'name' => 'Certificat d’immatriculation',
            'name_en' => 'Vehicle registration',
            'category' => 'permit',
            'sides' => 1,
            'countries' => [],
            'mrz' => '',
            'mrz_side' => 'verso',
            'chip' => false,
            'photo' => false,
            'extension' => '',
        ],
        'driving_license' => [
            'name' => 'Permis de conduire',
            'name_en' => 'Driving licence',
            'category' => 'permit',
            'sides' => 2,
            'countries' => [],
            'mrz' => 'td1',
            'mrz_side' => 'verso',
            'chip' => false,
            'photo' => true,
            'extension' => '',
        ],
        'press_card' => [
            'name' => 'Carte de presse',
            'name_en' => 'Press card',
            'category' => 'professional',
            'sides' => 1,
            'countries' => [],
            'mrz' => '',
            'mrz_side' => 'verso',
            'chip' => false,
            'photo' => true,
            'extension' => 'example_documents',
        ],
        'laissez_passer' => [
            'name' => 'Laissez-passer',
            'name_en' => 'Pass',
            'category' => 'pass',
            'sides' => 1,
            'countries' => [],
            'mrz' => '',
            'mrz_side' => 'verso',
            'chip' => false,
            'photo' => false,
            'extension' => 'example_documents',
        ],
    ],
    'countries' => [
        'FRA' => [
            'tier' => 'full',
            'types' => ['id_card', 'passport', 'driving_license', 'residence_permit', 'vehicle_registration', 'military_id'],
        ],
        'DEU' => [
            'tier' => 'full',
            'types' => ['id_card', 'passport', 'driving_license', 'residence_permit', 'vehicle_registration', 'military_id'],
        ],
        'USA' => [
            'tier' => 'full',
            'types' => ['id_card', 'passport', 'driving_license', 'residence_permit', 'vehicle_registration', 'military_id'],
        ],
        'GBR' => [
            'tier' => 'full',
            'types' => ['passport', 'driving_license', 'residence_permit', 'vehicle_registration', 'military_id'],
        ],
        'BEL' => [
            'tier' => 'full',
            'types' => ['id_card', 'passport', 'driving_license', 'residence_permit', 'vehicle_registration', 'military_id'],
        ],
        'AUT' => [
            'tier' => 'full',
            'types' => ['id_card', 'passport', 'driving_license', 'residence_permit', 'vehicle_registration', 'military_id'],
        ],
        'CHE' => [
            'tier' => 'full',
            'types' => ['id_card', 'passport', 'driving_license', 'residence_permit', 'vehicle_registration', 'military_id'],
        ],
        'NLD' => [
            'tier' => 'full',
            'types' => ['id_card', 'passport', 'driving_license', 'residence_permit', 'vehicle_registration', 'military_id'],
        ],
        'LUX' => [
            'tier' => 'generic',
            'types' => ['id_card', 'passport', 'driving_license'],
        ],
        'ITA' => [
            'tier' => 'full',
            'types' => ['id_card', 'passport', 'driving_license', 'residence_permit', 'vehicle_registration', 'military_id'],
        ],
        'ESP' => [
            'tier' => 'full',
            'types' => ['id_card', 'passport', 'driving_license', 'residence_permit', 'vehicle_registration', 'military_id'],
        ],
        'PRT' => [
            'tier' => 'generic',
            'types' => ['id_card', 'passport', 'driving_license'],
        ],
        'POL' => [
            'tier' => 'full',
            'types' => ['id_card', 'passport', 'driving_license', 'residence_permit', 'vehicle_registration', 'military_id'],
        ],
        'CZE' => [
            'tier' => 'generic',
            'types' => ['id_card', 'passport', 'driving_license'],
        ],
        'SVK' => [
            'tier' => 'generic',
            'types' => ['id_card', 'passport', 'driving_license'],
        ],
        'HUN' => [
            'tier' => 'generic',
            'types' => ['id_card', 'passport', 'driving_license'],
        ],
        'ROU' => [
            'tier' => 'generic',
            'types' => ['id_card', 'passport', 'driving_license'],
        ],
        'BGR' => [
            'tier' => 'generic',
            'types' => ['id_card', 'passport', 'driving_license'],
        ],
        'GRC' => [
            'tier' => 'generic',
            'types' => ['id_card', 'passport', 'driving_license', 'military_id'],
        ],
        'TUR' => [
            'tier' => 'generic',
            'types' => ['id_card', 'passport', 'driving_license', 'military_id'],
        ],
        'UKR' => [
            'tier' => 'generic',
            'types' => ['id_card', 'passport', 'driving_license', 'military_id'],
        ],
        'RUS' => [
            'tier' => 'generic',
            'types' => ['id_card', 'passport', 'driving_license', 'military_id'],
        ],
        'BLR' => [
            'tier' => 'generic',
            'types' => ['id_card', 'passport', 'driving_license', 'military_id'],
        ],
        'DNK' => [
            'tier' => 'generic',
            'types' => ['id_card', 'passport', 'driving_license', 'military_id'],
        ],
        'SWE' => [
            'tier' => 'generic',
            'types' => ['id_card', 'passport', 'driving_license', 'military_id'],
        ],
        'NOR' => [
            'tier' => 'generic',
            'types' => ['id_card', 'passport', 'driving_license', 'military_id'],
        ],
        'FIN' => [
            'tier' => 'generic',
            'types' => ['id_card', 'passport', 'driving_license', 'military_id'],
        ],
        'EST' => [
            'tier' => 'generic',
            'types' => ['id_card', 'passport', 'driving_license', 'military_id'],
        ],
        'LVA' => [
            'tier' => 'generic',
            'types' => ['id_card', 'passport', 'driving_license', 'military_id'],
        ],
        'LTU' => [
            'tier' => 'generic',
            'types' => ['id_card', 'passport', 'driving_license', 'military_id'],
        ],
        'HRV' => [
            'tier' => 'generic',
            'types' => ['id_card', 'passport', 'driving_license'],
        ],
        'SRB' => [
            'tier' => 'generic',
            'types' => ['id_card', 'passport', 'driving_license'],
        ],
        'CAN' => [
            'tier' => 'generic',
            'types' => ['passport', 'driving_license'],
        ],
        'AUS' => [
            'tier' => 'generic',
            'types' => ['passport', 'driving_license'],
        ],
        'ISR' => [
            'tier' => 'generic',
            'types' => ['id_card', 'passport', 'driving_license', 'military_id'],
        ],
        'IRQ' => [
            'tier' => 'generic',
            'types' => ['id_card', 'passport', 'driving_license'],
        ],
        'AFG' => [
            'tier' => 'generic',
            'types' => ['id_card', 'passport', 'driving_license'],
        ],
        'SYR' => [
            'tier' => 'generic',
            'types' => ['id_card', 'passport', 'driving_license'],
        ],
        'MLI' => [
            'tier' => 'generic',
            'types' => ['id_card', 'passport', 'driving_license'],
        ],
        'NER' => [
            'tier' => 'generic',
            'types' => ['id_card', 'passport', 'driving_license'],
        ],
        'TCD' => [
            'tier' => 'generic',
            'types' => ['id_card', 'passport', 'driving_license'],
        ],
        'SOM' => [
            'tier' => 'generic',
            'types' => ['id_card', 'passport', 'driving_license'],
        ],
        'LBY' => [
            'tier' => 'generic',
            'types' => ['id_card', 'passport', 'driving_license'],
        ],
        'YEM' => [
            'tier' => 'generic',
            'types' => ['id_card', 'passport', 'driving_license'],
        ],
        'COL' => [
            'tier' => 'generic',
            'types' => ['id_card', 'passport', 'driving_license'],
        ],
        'ALT' => [
            'tier' => 'fictional',
            'types' => ['id_card', 'passport', 'driving_license', 'military_id'],
        ],
        'TNA' => [
            'tier' => 'fictional',
            'types' => ['id_card', 'passport', 'driving_license', 'military_id'],
        ],
        'LVN' => [
            'tier' => 'fictional',
            'types' => ['id_card', 'passport', 'driving_license', 'military_id'],
        ],
        'MLD' => [
            'tier' => 'fictional',
            'types' => ['id_card', 'passport', 'driving_license', 'military_id'],
        ],
    ],
];
