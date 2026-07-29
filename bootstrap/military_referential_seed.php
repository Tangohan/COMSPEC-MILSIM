<?php

declare(strict_types=1);

/**
 * Peuplement / synchronisation idempotente du référentiel militaire.
 * Peut être rappelé : ajoute les codes manquants et resynchronise identité + hiérarchie des codes connus.
 */
function military_referential_seed(PDO $pdo): void
{
    $ins = static function (PDO $pdo, string $sql, array $params = []): void {
        $st = $pdo->prepare($sql);
        $st->execute($params);
    };

    $idByCode = static function (PDO $pdo, string $table, string $code): ?int {
        $st = $pdo->prepare("SELECT id FROM {$table} WHERE code = ? LIMIT 1");
        $st->execute([$code]);
        $v = $st->fetchColumn();

        return $v === false ? null : (int) $v;
    };

    // --- Countries ---
    $countries = [
        ['FR', 'FRA', 'France', 'France', 'fr', 10],
        ['US', 'USA', 'États-Unis', 'United States', 'us', 20],
        ['DE', 'DEU', 'Allemagne', 'Germany', 'de', 30],
        ['BE', 'BEL', 'Belgique', 'Belgium', 'be', 40],
        ['ES', 'ESP', 'Espagne', 'Spain', 'es', 50],
    ];
    foreach ($countries as [$iso2, $iso3, $fr, $en, $flag, $sort]) {
        $ins($pdo, 'INSERT INTO countries (iso2, iso3, name_fr, name_en, flag_code, sort_order, active) VALUES (?,?,?,?,?,?,1)
            ON DUPLICATE KEY UPDATE name_fr = VALUES(name_fr), name_en = VALUES(name_en), flag_code = VALUES(flag_code), sort_order = VALUES(sort_order), active = 1',
            [$iso2, $iso3, $fr, $en, $flag, $sort]);
    }
    $countryId = [];
    foreach ($pdo->query('SELECT id, iso2 FROM countries')->fetchAll(PDO::FETCH_ASSOC) as $r) {
        $countryId[$r['iso2']] = (int) $r['id'];
    }

    // --- Services ---
    $services = [
        ['fr-terre', 'FR', 'Armée de Terre', 'ADT', 'Armée de Terre', 'ARMY', 10],
        ['fr-marine', 'FR', 'Marine nationale', 'MN', 'Marine nationale', 'NAVY', 20],
        ['fr-aae', 'FR', 'Armée de l’Air et de l’Espace', 'AAE', 'Armée de l’Air et de l’Espace', 'AIR', 30],
        ['fr-gendarmerie', 'FR', 'Gendarmerie nationale', 'GN', 'Gendarmerie nationale', 'GENDARMERIE', 40],
        ['fr-interarmees', 'FR', 'Interarmées', 'IA', 'État-major des armées / structures interarmées', 'JOINT', 5],
        ['us-army', 'US', 'United States Army', 'USA', 'United States Army', 'ARMY', 10],
        ['us-navy', 'US', 'United States Navy', 'USN', 'United States Navy', 'NAVY', 20],
        ['us-airforce', 'US', 'United States Air Force', 'USAF', 'United States Air Force', 'AIR', 30],
        ['us-marines', 'US', 'United States Marine Corps', 'USMC', 'United States Marine Corps', 'MARINES', 40],
        ['us-dod', 'US', 'Department of Defense', 'DoD', 'United States Department of Defense', 'DEFENSE', 1],
        ['us-intel', 'US', 'U.S. Intelligence Community', 'IC', 'United States Intelligence Community', 'INTELLIGENCE', 2],
        ['fr-renseignement', 'FR', 'Communauté française du renseignement', 'RENS', 'Services de renseignement français', 'INTELLIGENCE', 2],
        ['fr-ssa', 'FR', 'Service de santé des armées', 'SSA', 'Service de santé des armées', 'JOINT', 35],
        ['de-heer', 'DE', 'Deutsches Heer', 'Heer', 'Deutsches Heer', 'ARMY', 10],
        ['de-marine', 'DE', 'Deutsche Marine', 'Marine', 'Deutsche Marine', 'NAVY', 20],
        ['be-land', 'BE', 'Composante Terre', 'Land', 'Composante Terre belge', 'ARMY', 10],
        ['es-ejercito', 'ES', 'Ejército de Tierra', 'ET', 'Ejército de Tierra', 'ARMY', 10],
        ['es-armada', 'ES', 'Armada', 'Armada', 'Armada Española', 'NAVY', 20],
        ['es-aire', 'ES', 'Ejército del Aire y del Espacio', 'EA', 'Ejército del Aire y del Espacio', 'AIR', 30],
        ['es-gc', 'ES', 'Guardia Civil', 'GC', 'Guardia Civil', 'GENDARMERIE', 40],
    ];
    foreach ($services as [$code, $iso, $name, $short, $official, $type, $sort]) {
        $ins($pdo, 'INSERT INTO military_services (country_id, code, name, short_name, official_name, service_type, sort_order, active)
            VALUES (?,?,?,?,?,?,?,1)
            ON DUPLICATE KEY UPDATE country_id = VALUES(country_id), name = VALUES(name), short_name = VALUES(short_name),
              official_name = VALUES(official_name), service_type = VALUES(service_type), sort_order = VALUES(sort_order), active = 1',
            [$countryId[$iso], $code, $name, $short, $official, $type, $sort]);
    }
    $serviceId = [];
    foreach ($pdo->query('SELECT id, code FROM military_services')->fetchAll(PDO::FETCH_ASSOC) as $r) {
        $serviceId[$r['code']] = (int) $r['id'];
    }

    // --- Entity types ---
    $types = [
        ['COMMAND', 'Commandement', 'Command', 10],
        ['JOINT_COMMAND', 'Commandement interarmées', 'Joint command', 15],
        ['COMPONENT_COMMAND', 'Commandement de composante', 'Component command', 20],
        ['FORCE', 'Force', 'Force', 30],
        ['BRIGADE', 'Brigade', 'Brigade', 40],
        ['REGIMENT', 'Régiment', 'Regiment', 50],
        ['GROUP', 'Groupe', 'Group', 60],
        ['BATTALION', 'Bataillon', 'Battalion', 70],
        ['SQUADRON', 'Escadron', 'Squadron', 80],
        ['COMPANY', 'Compagnie', 'Company', 90],
        ['COMMANDO', 'Commando', 'Commando', 100],
        ['DETACHMENT', 'Détachement', 'Detachment', 110],
        ['WING', 'Escadre / Wing', 'Wing', 120],
        ['AIR_SQUADRON', 'Escadron aérien', 'Air squadron', 130],
        ['MARITIME_UNIT', 'Unité maritime', 'Maritime unit', 140],
        ['SUPPORT_UNIT', 'Unité d’appui', 'Support unit', 150],
        ['TRAINING_UNIT', 'Unité de formation', 'Training unit', 160],
        ['INTELLIGENCE_UNIT', 'Unité de renseignement', 'Intelligence unit', 170],
        ['AGENCY', 'Agence / service', 'Agency / service', 180],
        ['CENTER', 'Centre', 'Center', 190],
        ['OTHER', 'Autre structure', 'Other', 999],
    ];
    foreach ($types as [$code, $fr, $en, $sort]) {
        $ins($pdo, 'INSERT INTO military_entity_types (code, label_fr, label_en, sort_order) VALUES (?,?,?,?)
            ON DUPLICATE KEY UPDATE label_fr = VALUES(label_fr), label_en = VALUES(label_en), sort_order = VALUES(sort_order)',
            [$code, $fr, $en, $sort]);
    }
    $typeId = [];
    foreach ($pdo->query('SELECT id, code FROM military_entity_types')->fetchAll(PDO::FETCH_ASSOC) as $r) {
        $typeId[$r['code']] = (int) $r['id'];
    }

    // --- Domains ---
    $domains = [
        ['LAND', 'Terre', 'Land', 10],
        ['AIR', 'Air', 'Air', 20],
        ['MARITIME', 'Maritime', 'Maritime', 30],
        ['UNDERWATER', 'Sous-marin / plongée', 'Underwater', 40],
        ['CYBER', 'Cyber', 'Cyber', 50],
        ['SPACE', 'Espace', 'Space', 60],
        ['INFORMATION', 'Information', 'Information', 70],
    ];
    foreach ($domains as [$code, $fr, $en, $sort]) {
        $ins($pdo, 'INSERT INTO military_domains (code, label_fr, label_en, sort_order) VALUES (?,?,?,?)
            ON DUPLICATE KEY UPDATE label_fr = VALUES(label_fr), label_en = VALUES(label_en), sort_order = VALUES(sort_order)',
            [$code, $fr, $en, $sort]);
    }
    $domainId = [];
    foreach ($pdo->query('SELECT id, code FROM military_domains')->fetchAll(PDO::FETCH_ASSOC) as $r) {
        $domainId[$r['code']] = (int) $r['id'];
    }

    // --- Classifications ---
    $classifs = [
        ['SOF', 'Forces spéciales', 'Special Operations Forces', 10],
        ['SOF_SUPPORT', 'Appui aux forces spéciales', 'SOF support', 20],
        ['SOF_COMMAND', 'Commandement SOF', 'SOF command', 30],
        ['SPECIALIZED', 'Spécialisée', 'Specialized', 40],
        ['ELITE_CONVENTIONAL', 'Élite conventionnelle', 'Elite conventional', 50],
        ['INTERVENTION', 'Intervention', 'Intervention', 60],
        ['RECONNAISSANCE', 'Renseignement / reconnaissance', 'Reconnaissance', 70],
        ['INTELLIGENCE', 'Renseignement', 'Intelligence', 80],
        ['AVIATION', 'Aviation', 'Aviation', 90],
        ['TRAINING', 'Formation', 'Training', 100],
    ];
    foreach ($classifs as [$code, $fr, $en, $sort]) {
        $ins($pdo, 'INSERT INTO military_classifications (code, label_fr, label_en, sort_order) VALUES (?,?,?,?)
            ON DUPLICATE KEY UPDATE label_fr = VALUES(label_fr), label_en = VALUES(label_en), sort_order = VALUES(sort_order)',
            [$code, $fr, $en, $sort]);
    }
    $classId = [];
    foreach ($pdo->query('SELECT id, code FROM military_classifications')->fetchAll(PDO::FETCH_ASSOC) as $r) {
        $classId[$r['code']] = (int) $r['id'];
    }

    // --- Functions ---
    $functions = [
        ['COMBAT', 'Combat', 'Combat', 'ops', 10],
        ['RECONNAISSANCE', 'Reconnaissance', 'Reconnaissance', 'ops', 20],
        ['INTELLIGENCE', 'Renseignement', 'Intelligence', 'ops', 30],
        ['AVIATION', 'Aviation', 'Aviation', 'ops', 40],
        ['COMMAND', 'Commandement', 'Command', 'cmd', 50],
        ['SUPPORT', 'Appui', 'Support', 'support', 60],
        ['TRAINING', 'Formation', 'Training', 'support', 70],
        ['LOGISTICS', 'Logistique', 'Logistics', 'support', 80],
        ['COMMUNICATIONS', 'Communications', 'Communications', 'support', 90],
        ['MEDICAL_SUPPORT', 'Appui médical', 'Medical support', 'support', 100],
        ['FIRE_SUPPORT', 'Appui-feu', 'Fire support', 'ops', 110],
        ['CIVIL_AFFAIRS', 'Affaires civiles', 'Civil affairs', 'ops', 120],
        ['PSYCHOLOGICAL_OPERATIONS', 'Opérations psychologiques', 'Psychological operations', 'ops', 130],
        ['INFORMATION_OPERATIONS', 'Opérations d’information', 'Information operations', 'ops', 140],
    ];
    foreach ($functions as [$code, $fr, $en, $cat, $sort]) {
        $ins($pdo, 'INSERT INTO military_functions (code, label_fr, label_en, category, sort_order) VALUES (?,?,?,?,?)
            ON DUPLICATE KEY UPDATE label_fr = VALUES(label_fr), label_en = VALUES(label_en), category = VALUES(category), sort_order = VALUES(sort_order)',
            [$code, $fr, $en, $cat, $sort]);
    }
    $functionId = [];
    foreach ($pdo->query('SELECT id, code FROM military_functions')->fetchAll(PDO::FETCH_ASSOC) as $r) {
        $functionId[$r['code']] = (int) $r['id'];
    }

    // --- Specialties ---
    $specialties = [
        ['DIRECT_ACTION', 'Action directe', 'Direct action', 'sof', 10],
        ['SPECIAL_RECONNAISSANCE', 'Reconnaissance spéciale', 'Special reconnaissance', 'sof', 20],
        ['COUNTER_TERRORISM', 'Contre-terrorisme', 'Counter-terrorism', 'sof', 30],
        ['HOSTAGE_RESCUE', 'Libération d’otages', 'Hostage rescue', 'sof', 40],
        ['MILITARY_ASSISTANCE', 'Assistance militaire', 'Military assistance', 'sof', 50],
        ['UNCONVENTIONAL_WARFARE', 'Guerre non conventionnelle', 'Unconventional warfare', 'sof', 60],
        ['MARITIME_OPERATIONS', 'Opérations maritimes', 'Maritime operations', 'maritime', 70],
        ['COMBAT_DIVING', 'Plongée de combat', 'Combat diving', 'maritime', 80],
        ['UNDERWATER_OPERATIONS', 'Opérations sous-marines', 'Underwater operations', 'maritime', 90],
        ['MARITIME_ASSAULT', 'Assaut maritime', 'Maritime assault', 'maritime', 100],
        ['AIRBORNE', 'Aéroporté', 'Airborne', 'air', 110],
        ['MILITARY_FREE_FALL', 'Chute libre militaire', 'Military free fall', 'air', 120],
        ['SPECIAL_OPERATIONS_AVIATION', 'Aviation d’opérations spéciales', 'Special operations aviation', 'air', 130],
        ['HELICOPTER_OPERATIONS', 'Opérations héliportées', 'Helicopter operations', 'air', 140],
        ['TACTICAL_AIRLIFT', 'Transport aérien tactique', 'Tactical airlift', 'air', 150],
        ['INTELLIGENCE', 'Renseignement', 'Intelligence', 'intel', 160],
        ['ELECTRONIC_WARFARE', 'Guerre électronique', 'Electronic warfare', 'intel', 170],
        ['COMMUNICATIONS', 'Communications', 'Communications', 'support', 180],
        ['CIVIL_AFFAIRS', 'Affaires civiles', 'Civil affairs', 'sof', 190],
        ['PSYCHOLOGICAL_OPERATIONS', 'Opérations psychologiques / PSYOP', 'Psychological operations', 'sof', 200],
        ['INFORMATION_OPERATIONS', 'Opérations d’information', 'Information operations', 'sof', 210],
        ['SPECIAL_TACTICS', 'Special Tactics', 'Special Tactics', 'air', 220],
        ['COMBAT_CONTROL', 'Combat Control', 'Combat Control', 'air', 230],
        ['PARARESCUE', 'Pararescue', 'Pararescue', 'air', 240],
        ['PERSONNEL_RECOVERY', 'Récupération de personnel', 'Personnel recovery', 'air', 250],
        ['CLOSE_AIR_SUPPORT', 'Appui aérien rapproché', 'Close air support', 'air', 260],
        ['FOREIGN_INTERNAL_DEFENSE', 'Défense intérieure étrangère', 'Foreign internal defense', 'sof', 270],
        ['SECURITY_FORCE_ASSISTANCE', 'Assistance aux forces de sécurité', 'Security force assistance', 'sof', 280],
    ];
    foreach ($specialties as [$code, $fr, $en, $cat, $sort]) {
        $ins($pdo, 'INSERT INTO military_specialties (code, label_fr, label_en, category, sort_order) VALUES (?,?,?,?,?)
            ON DUPLICATE KEY UPDATE label_fr = VALUES(label_fr), label_en = VALUES(label_en), category = VALUES(category), sort_order = VALUES(sort_order)',
            [$code, $fr, $en, $cat, $sort]);
    }
    $specialtyId = [];
    foreach ($pdo->query('SELECT id, code FROM military_specialties')->fetchAll(PDO::FETCH_ASSOC) as $r) {
        $specialtyId[$r['code']] = (int) $r['id'];
    }

    // --- Sources (institutionnelles / publiques) ---
    $sources = [
        ['defense-gouv-cos', 'Commandement des opérations spéciales', 'Ministère des Armées', 'https://www.defense.gouv.fr/', 'institutional'],
        ['defense-gouv-marine', 'Marine nationale — forces spéciales', 'Ministère des Armées', 'https://www.defense.gouv.fr/', 'institutional'],
        ['defense-gouv-air', 'Armée de l’Air et de l’Espace — forces spéciales', 'Ministère des Armées', 'https://www.defense.gouv.fr/', 'institutional'],
        ['defense-gouv-dgse', 'Direction générale de la sécurité extérieure', 'Ministère des Armées', 'https://www.defense.gouv.fr/', 'institutional'],
        ['socom-mil', 'USSOCOM', 'U.S. Special Operations Command', 'https://www.socom.mil/', 'institutional'],
        ['army-mil-usasoc', 'USASOC', 'U.S. Army', 'https://www.army.mil/usasoc', 'institutional'],
        ['navy-mil-nsw', 'Naval Special Warfare', 'U.S. Navy', 'https://www.nsw.navy.mil/', 'institutional'],
        ['afsoc-af-mil', 'AFSOC', 'U.S. Air Force', 'https://www.afsoc.af.mil/', 'institutional'],
        ['marsoc-marines', 'MARSOC', 'U.S. Marine Corps', 'https://www.marsoc.marines.mil/', 'institutional'],
        ['cia-gov', 'Central Intelligence Agency', 'CIA', 'https://www.cia.gov/', 'institutional'],
    ];
    foreach ($sources as [$code, $name, $pub, $url, $type]) {
        $fullName = $name . ' (' . $code . ')';
        $existingSrc = $pdo->prepare('SELECT id FROM military_sources WHERE name = ? LIMIT 1');
        $existingSrc->execute([$fullName]);
        if (!$existingSrc->fetchColumn()) {
            $ins($pdo, 'INSERT INTO military_sources (name, publisher, url, source_type, checked_at) VALUES (?,?,?,?,CURDATE())',
                [$fullName, $pub, $url, $type]);
        }
    }
    $sourceId = [];
    foreach ($pdo->query('SELECT id, name FROM military_sources')->fetchAll(PDO::FETCH_ASSOC) as $r) {
        if (preg_match('/\(([^)]+)\)\s*$/', (string) $r['name'], $m)) {
            $sourceId[$m[1]] = (int) $r['id'];
        }
    }

    $slugify = static function (string $code): string {
        return strtolower(preg_replace('/[^a-zA-Z0-9]+/', '-', $code) ?? $code);
    };

    /**
     * @param list<array{0:string,1:string,2:?string}> $aliases [alias, type, lang]
     * @param list<string> $domains
     * @param list<string> $classifs
     * @param list<string> $functions
     * @param list<string> $specialties
     */
    $addUnit = static function (
        PDO $pdo,
        array $countryId,
        array $serviceId,
        array $typeId,
        array $domainId,
        array $classId,
        array $functionId,
        array $specialtyId,
        array $sourceId,
        string $code,
        string $iso,
        ?string $serviceCode,
        string $entityType,
        string $official,
        string $short,
        string $display,
        ?string $international,
        ?string $parentCode,
        int $level,
        int $sort,
        array $aliases = [],
        array $domains = [],
        array $classifs = [],
        array $functions = [],
        array $specialties = [],
        ?string $sourceKey = null,
        array $sourceInfoTypes = ['IDENTITY', 'HIERARCHY']
    ) use ($slugify, $ins, $idByCode): int {
        $parentId = $parentCode !== null ? $idByCode($pdo, 'military_units', $parentCode) : null;
        $existing = $idByCode($pdo, 'military_units', $code);
        $svcId = $serviceCode !== null ? ($serviceId[$serviceCode] ?? null) : null;
        $etypeId = $typeId[$entityType] ?? null;
        if ($etypeId === null) {
            throw new RuntimeException("Entity type inconnu : {$entityType}");
        }
        if ($existing !== null) {
            $uid = $existing;
            $ins($pdo, 'UPDATE military_units SET
                parent_id = ?,
                country_id = ?,
                service_id = ?,
                entity_type_id = ?,
                official_name = ?,
                short_name = ?,
                display_name = ?,
                international_name = ?,
                hierarchy_level = ?,
                sort_order = ?,
                status = \'active\',
                active = 1,
                verified_at = NOW(),
                updated_at = NOW()
                WHERE id = ?',
                [
                    $parentId,
                    $countryId[$iso],
                    $svcId,
                    $etypeId,
                    $official,
                    $short !== '' ? $short : null,
                    $display,
                    $international,
                    $level,
                    $sort,
                    $uid,
                ]);
        } else {
            $ins($pdo, 'INSERT INTO military_units
            (parent_id, country_id, service_id, entity_type_id, code, slug, official_name, short_name, display_name, international_name,
             status, active, hierarchy_level, sort_order, verified_at, created_at)
            VALUES (?,?,?,?,?,?,?,?,?,?, \'active\', 1, ?, ?, NOW(), NOW())',
            [
                $parentId,
                $countryId[$iso],
                $svcId,
                $etypeId,
                $code,
                $slugify($code),
                $official,
                $short !== '' ? $short : null,
                $display,
                $international,
                $level,
                $sort,
            ]);
            $uid = (int) $pdo->lastInsertId();
        }

        foreach ($aliases as [$alias, $atype, $lang]) {
            $ins($pdo, 'INSERT IGNORE INTO military_unit_aliases (unit_id, alias, alias_type, language, is_primary, searchable)
                VALUES (?,?,?,?,0,1)', [$uid, $alias, $atype, $lang]);
        }
        foreach ($domains as $d) {
            if (isset($domainId[$d])) {
                $ins($pdo, 'INSERT IGNORE INTO military_unit_domains (unit_id, domain_id) VALUES (?,?)', [$uid, $domainId[$d]]);
            }
        }
        foreach ($classifs as $c) {
            if (isset($classId[$c])) {
                $ins($pdo, 'INSERT IGNORE INTO military_unit_classifications (unit_id, classification_id, is_primary) VALUES (?,?,1)',
                    [$uid, $classId[$c]]);
            }
        }
        foreach ($functions as $i => $f) {
            if (isset($functionId[$f])) {
                $ins($pdo, 'INSERT IGNORE INTO military_unit_functions (unit_id, function_id, is_primary) VALUES (?,?,?)',
                    [$uid, $functionId[$f], $i === 0 ? 1 : 0]);
            }
        }
        foreach ($specialties as $i => $s) {
            if (isset($specialtyId[$s])) {
                $ins($pdo, 'INSERT IGNORE INTO military_unit_specialties (unit_id, specialty_id, is_primary) VALUES (?,?,?)',
                    [$uid, $specialtyId[$s], $i === 0 ? 1 : 0]);
            }
        }
        if ($sourceKey !== null && isset($sourceId[$sourceKey])) {
            foreach ($sourceInfoTypes as $it) {
                $ins($pdo, 'INSERT IGNORE INTO military_unit_sources (unit_id, source_id, information_type) VALUES (?,?,?)',
                    [$uid, $sourceId[$sourceKey], $it]);
            }
        }

        return $uid;
    };

    $U = static function (...$args) use ($addUnit, $pdo, $countryId, $serviceId, $typeId, $domainId, $classId, $functionId, $specialtyId, $sourceId): int {
        return $addUnit($pdo, $countryId, $serviceId, $typeId, $domainId, $classId, $functionId, $specialtyId, $sourceId, ...$args);
    };

    // ===================== FRANCE =====================
    // COS — commandement opérationnel interarmées (CEMA)
    $U('fr-cos', 'FR', 'fr-interarmees', 'JOINT_COMMAND',
        'Commandement des opérations spéciales', 'COS', 'COS',
        'Special Operations Command', null, 0, 10,
        [['COS', 'ACRONYM', 'fr'], ['Commandement des opérations spéciales (COS)', 'COMMON_NAME', 'fr']],
        ['LAND', 'AIR', 'MARITIME'], ['SOF_COMMAND'], ['COMMAND'], [], 'defense-gouv-cos');

    // --- CAST (ex-BFST / CFST) — code legacy fr-bfst conservé ---
    $U('fr-bfst', 'FR', 'fr-terre', 'COMPONENT_COMMAND',
        'Commandement des actions spéciales Terre', 'CAST', 'CAST',
        'Army Special Actions Command', 'fr-cos', 1, 20,
        [
            ['CAST', 'ACRONYM', 'fr'],
            ['Commandement des actions spéciales Terre', 'COMMON_NAME', 'fr'],
            ['BFST', 'FORMER_NAME', 'fr'],
            ['Brigade des forces spéciales Terre', 'FORMER_NAME', 'fr'],
            ['CFST', 'FORMER_NAME', 'fr'],
            ['Commandement des forces spéciales Terre', 'FORMER_NAME', 'fr'],
        ],
        ['LAND'], ['SOF_COMMAND'], ['COMMAND'], [], 'defense-gouv-cos');

    $U('fr-cctfs', 'FR', 'fr-terre', 'SUPPORT_UNIT',
        'Compagnie de commandement et de transmissions des forces spéciales', 'CCTFS', 'CCTFS',
        null, 'fr-bfst', 2, 25,
        [['CCTFS', 'ACRONYM', 'fr']],
        ['LAND'], ['SOF_SUPPORT'], ['COMMAND', 'COMMUNICATIONS', 'SUPPORT'], ['COMMUNICATIONS'], 'defense-gouv-cos');

    $U('fr-1rpima', 'FR', 'fr-terre', 'REGIMENT',
        '1er régiment de parachutistes d’infanterie de marine', '1er RPIMa', '1er RPIMa',
        '1st Marine Infantry Parachute Regiment', 'fr-bfst', 2, 40,
        [['1er RPIMa', 'SHORT_NAME', 'fr'], ['1RPIMA', 'ALTERNATIVE_SPELLING', 'fr'], ['1 RPIMa', 'ALTERNATIVE_SPELLING', 'fr'], ['RPIMa', 'ACRONYM', 'fr'], ['RAPAS', 'CODE_NAME', 'fr']],
        ['LAND'], ['SOF'], ['COMBAT'], ['DIRECT_ACTION', 'AIRBORNE', 'COUNTER_TERRORISM'], 'defense-gouv-cos');

    $U('fr-13rdp', 'FR', 'fr-terre', 'REGIMENT',
        '13e régiment de dragons parachutistes', '13e RDP', '13e RDP',
        null, 'fr-bfst', 2, 50,
        [['13e RDP', 'SHORT_NAME', 'fr'], ['13RDP', 'ALTERNATIVE_SPELLING', 'fr'], ['RDP', 'ACRONYM', 'fr']],
        ['LAND'], ['SOF', 'RECONNAISSANCE'], ['RECONNAISSANCE', 'INTELLIGENCE'], ['SPECIAL_RECONNAISSANCE', 'AIRBORNE'], 'defense-gouv-cos');

    $U('fr-4rhfs', 'FR', 'fr-terre', 'REGIMENT',
        '4e régiment d’hélicoptères des forces spéciales', '4e RHFS', '4e RHFS',
        null, 'fr-bfst', 2, 55,
        [['4e RHFS', 'SHORT_NAME', 'fr'], ['4RHFS', 'ALTERNATIVE_SPELLING', 'fr'], ['RHFS', 'ACRONYM', 'fr']],
        ['AIR', 'LAND'], ['SOF', 'AVIATION'], ['AVIATION', 'SUPPORT'], ['SPECIAL_OPERATIONS_AVIATION', 'HELICOPTER_OPERATIONS'], 'defense-gouv-cos');

    $U('fr-ciae', 'FR', 'fr-terre', 'CENTER',
        'Centre interarmées des actions sur l’environnement', 'CIAE', 'CIAE',
        null, 'fr-bfst', 2, 58,
        [['CIAE', 'ACRONYM', 'fr']],
        ['LAND', 'INFORMATION'], ['SOF_SUPPORT', 'SPECIALIZED'], ['SUPPORT', 'INFORMATION_OPERATIONS'], ['INFORMATION_OPERATIONS', 'MILITARY_ASSISTANCE'], 'defense-gouv-cos');

    $U('fr-712ct', 'FR', 'fr-terre', 'SUPPORT_UNIT',
        '712e compagnie de transmissions', '712e CT', '712e compagnie de transmissions',
        null, 'fr-bfst', 2, 59,
        [['712e CT', 'SHORT_NAME', 'fr'], ['712 CT', 'ALTERNATIVE_SPELLING', 'fr']],
        ['LAND'], ['SOF_SUPPORT'], ['COMMUNICATIONS', 'SUPPORT'], ['COMMUNICATIONS'], 'defense-gouv-cos');

    $U('fr-cfor', 'FR', 'fr-terre', 'TRAINING_UNIT',
        'Centre de formation des forces spéciales / Académie ARES', 'CFOR / ARES', 'CFOR — Académie ARES',
        null, 'fr-bfst', 2, 60,
        [['CFOR', 'ACRONYM', 'fr'], ['ARES', 'ACRONYM', 'fr'], ['Académie ARES', 'COMMON_NAME', 'fr']],
        ['LAND'], ['TRAINING', 'SOF_SUPPORT'], ['TRAINING'], [], 'defense-gouv-cos');

    // --- FORFUSCO ---
    $U('fr-forfusco', 'FR', 'fr-marine', 'COMPONENT_COMMAND',
        'Force maritime des fusiliers marins et commandos', 'FORFUSCO', 'FORFUSCO',
        null, 'fr-cos', 1, 70,
        [['FORFUSCO', 'ACRONYM', 'fr'], ['ALFUSCO', 'FORMER_NAME', 'fr']],
        ['MARITIME'], ['SOF_COMMAND'], ['COMMAND'], ['MARITIME_OPERATIONS'], 'defense-gouv-marine');

    $commandos = [
        ['fr-cdo-hubert', 'Commando Hubert', 'Hubert', [['Hubert', 'SHORT_NAME', 'fr'], ['Commando d’action sous-marine Hubert', 'COMMON_NAME', 'fr']], ['MARITIME', 'UNDERWATER'], ['COMBAT', 'RECONNAISSANCE'], ['COMBAT_DIVING', 'UNDERWATER_OPERATIONS', 'MARITIME_OPERATIONS', 'DIRECT_ACTION']],
        ['fr-cdo-jaubert', 'Commando Jaubert', 'Jaubert', [['Jaubert', 'SHORT_NAME', 'fr']], ['MARITIME'], ['COMBAT'], ['MARITIME_ASSAULT', 'MARITIME_OPERATIONS', 'COUNTER_TERRORISM']],
        ['fr-cdo-trepel', 'Commando Trépel', 'Trépel', [['Trépel', 'SHORT_NAME', 'fr'], ['Trepel', 'ALTERNATIVE_SPELLING', 'fr']], ['MARITIME'], ['COMBAT'], ['MARITIME_ASSAULT', 'MARITIME_OPERATIONS', 'COUNTER_TERRORISM']],
        ['fr-cdo-penfentenyo', 'Commando de Penfentenyo', 'Penfentenyo', [['Penfentenyo', 'SHORT_NAME', 'fr'], ['de Penfentenyo', 'COMMON_NAME', 'fr']], ['MARITIME'], ['COMBAT', 'RECONNAISSANCE'], ['MARITIME_OPERATIONS', 'SPECIAL_RECONNAISSANCE']],
        ['fr-cdo-montfort', 'Commando de Montfort', 'Montfort', [['Montfort', 'SHORT_NAME', 'fr'], ['de Montfort', 'COMMON_NAME', 'fr']], ['MARITIME'], ['COMBAT', 'FIRE_SUPPORT'], ['MARITIME_OPERATIONS']],
        ['fr-cdo-kieffer', 'Commando Kieffer', 'Kieffer', [['Kieffer', 'SHORT_NAME', 'fr']], ['MARITIME'], ['SUPPORT', 'COMMAND', 'INTELLIGENCE'], ['MARITIME_OPERATIONS', 'COMMUNICATIONS', 'INTELLIGENCE']],
        ['fr-cdo-ponchardier', 'Commando Ponchardier', 'Ponchardier', [['Ponchardier', 'SHORT_NAME', 'fr']], ['MARITIME'], ['SUPPORT', 'LOGISTICS'], ['MARITIME_OPERATIONS']],
    ];
    $sort = 80;
    foreach ($commandos as [$code, $official, $short, $aliases, $doms, $fns, $specs]) {
        $U($code, 'FR', 'fr-marine', 'COMMANDO',
            $official, $short, $official, null, 'fr-forfusco', 2, $sort,
            $aliases, $doms, ['SOF'], $fns, $specs, 'defense-gouv-marine');
        $sort += 10;
    }

    // --- BFSA (composante air) ---
    $U('fr-bfsa', 'FR', 'fr-aae', 'COMPONENT_COMMAND',
        'Brigade des forces spéciales air', 'BFSA', 'BFSA',
        'Air Special Forces Brigade', 'fr-cos', 1, 150,
        [['BFSA', 'ACRONYM', 'fr'], ['BFS', 'FORMER_NAME', 'fr'], ['Brigade des forces spéciales', 'FORMER_NAME', 'fr']],
        ['AIR', 'LAND'], ['SOF_COMMAND', 'AVIATION'], ['COMMAND', 'AVIATION'], ['SPECIAL_OPERATIONS_AVIATION'], 'defense-gouv-air');

    $U('fr-cpa10', 'FR', 'fr-aae', 'COMMANDO',
        'Commando parachutiste de l’air n° 10', 'CPA 10', 'CPA 10',
        null, 'fr-bfsa', 2, 160,
        [['CPA 10', 'SHORT_NAME', 'fr'], ['CPA10', 'ALTERNATIVE_SPELLING', 'fr']],
        ['AIR', 'LAND'], ['SOF'], ['COMBAT', 'FIRE_SUPPORT'], ['AIRBORNE', 'DIRECT_ACTION', 'COUNTER_TERRORISM', 'CLOSE_AIR_SUPPORT'], 'defense-gouv-air');

    $U('fr-cpa20', 'FR', 'fr-aae', 'COMMANDO',
        'Commando parachutiste de l’air n° 20', 'CPA 20', 'CPA 20',
        null, 'fr-bfsa', 2, 165,
        [['CPA 20', 'SHORT_NAME', 'fr'], ['CPA20', 'ALTERNATIVE_SPELLING', 'fr']],
        ['AIR', 'LAND'], ['SOF', 'SOF_SUPPORT'], ['COMBAT', 'SUPPORT'], ['AIRBORNE', 'PERSONNEL_RECOVERY'], 'defense-gouv-air');

    $U('fr-cpa30', 'FR', 'fr-aae', 'COMMANDO',
        'Commando parachutiste de l’air n° 30', 'CPA 30', 'CPA 30',
        null, 'fr-bfsa', 2, 170,
        [['CPA 30', 'SHORT_NAME', 'fr'], ['CPA30', 'ALTERNATIVE_SPELLING', 'fr']],
        ['AIR', 'LAND'], ['SOF', 'SOF_SUPPORT'], ['COMBAT', 'SUPPORT', 'INTELLIGENCE'], ['AIRBORNE', 'PERSONNEL_RECOVERY', 'SPECIAL_RECONNAISSANCE'], 'defense-gouv-air');

    $U('fr-et-poitou', 'FR', 'fr-aae', 'AIR_SQUADRON',
        'Escadron de transport 3/61 Poitou', 'ET 3/61 Poitou', 'ET 3/61 Poitou',
        null, 'fr-bfsa', 2, 175,
        [['ET 3/61', 'SHORT_NAME', 'fr'], ['Poitou', 'NICKNAME', 'fr'], ['Escadron Poitou', 'COMMON_NAME', 'fr']],
        ['AIR'], ['SOF', 'AVIATION'], ['AVIATION', 'SUPPORT'], ['TACTICAL_AIRLIFT', 'SPECIAL_OPERATIONS_AVIATION'], 'defense-gouv-air');

    $U('fr-eh-pyrenees', 'FR', 'fr-aae', 'AIR_SQUADRON',
        'Escadron d’hélicoptères 1/67 Pyrénées', 'EH 1/67 Pyrénées', 'EH 1/67 Pyrénées',
        null, 'fr-bfsa', 2, 180,
        [['EH 1/67', 'SHORT_NAME', 'fr'], ['Pyrénées', 'NICKNAME', 'fr'], ['EH Pyrénées', 'COMMON_NAME', 'fr']],
        ['AIR'], ['SOF', 'AVIATION'], ['AVIATION', 'SUPPORT'], ['HELICOPTER_OPERATIONS', 'SPECIAL_OPERATIONS_AVIATION', 'PERSONNEL_RECOVERY'], 'defense-gouv-air');

    // --- Appui santé FS ---
    $U('fr-1css-fs', 'FR', 'fr-ssa', 'SUPPORT_UNIT',
        '1re chefferie du service de santé — forces spéciales', '1 CSS-FS', '1 CSS-FS',
        null, 'fr-cos', 1, 190,
        [['1 CSS-FS', 'ACRONYM', 'fr'], ['1CSS-FS', 'ALTERNATIVE_SPELLING', 'fr']],
        ['LAND'], ['SOF_SUPPORT'], ['MEDICAL_SUPPORT', 'SUPPORT'], [], 'defense-gouv-cos');

    // --- Intervention / renseignement (hors COS organique) ---
    $U('fr-gign', 'FR', 'fr-gendarmerie', 'GROUP',
        'Groupe d’intervention de la Gendarmerie nationale', 'GIGN', 'GIGN',
        null, null, 0, 200,
        [['GIGN', 'ACRONYM', 'fr']],
        ['LAND'], ['INTERVENTION'], ['COMBAT'], ['COUNTER_TERRORISM', 'HOSTAGE_RESCUE'], 'defense-gouv-cos');

    $U('fr-dgse', 'FR', 'fr-renseignement', 'AGENCY',
        'Direction générale de la sécurité extérieure', 'DGSE', 'DGSE',
        'General Directorate for External Security', null, 0, 210,
        [['DGSE', 'ACRONYM', 'fr']],
        ['LAND', 'INFORMATION', 'CYBER'], ['INTELLIGENCE'], ['INTELLIGENCE', 'COMMAND'], ['INTELLIGENCE'], 'defense-gouv-dgse');

    $U('fr-dgse-sa', 'FR', 'fr-renseignement', 'DETACHMENT',
        'Service Action', 'SA', 'DGSE — Service Action',
        'Action Service', 'fr-dgse', 1, 220,
        [['Service Action', 'COMMON_NAME', 'fr'], ['SA', 'ACRONYM', 'fr']],
        ['LAND'], ['INTELLIGENCE', 'SOF'], ['COMBAT', 'INTELLIGENCE'], ['DIRECT_ACTION', 'SPECIAL_RECONNAISSANCE', 'COUNTER_TERRORISM'], 'defense-gouv-dgse');

    // ===================== USA =====================
    $U('us-dod', 'US', 'us-dod', 'COMMAND',
        'United States Department of Defense', 'DoD', 'Department of Defense',
        'Department of Defense', null, 0, 10,
        [['DoD', 'ACRONYM', 'en'], ['Department of Defense', 'COMMON_NAME', 'en']],
        [], [], ['COMMAND'], [], 'socom-mil');

    $U('us-ussocom', 'US', 'us-dod', 'JOINT_COMMAND',
        'United States Special Operations Command', 'USSOCOM', 'USSOCOM',
        'United States Special Operations Command', 'us-dod', 1, 20,
        [['USSOCOM', 'ACRONYM', 'en'], ['SOCOM', 'ACRONYM', 'en']],
        ['LAND', 'AIR', 'MARITIME'], ['SOF_COMMAND'], ['COMMAND'], [], 'socom-mil');

    // --- JSOC (sub-unified) — unités publiquement documentées uniquement ---
    $U('us-jsoc', 'US', 'us-dod', 'JOINT_COMMAND',
        'Joint Special Operations Command', 'JSOC', 'JSOC',
        'Joint Special Operations Command', 'us-ussocom', 2, 30,
        [['JSOC', 'ACRONYM', 'en']],
        ['LAND', 'AIR', 'MARITIME'], ['SOF_COMMAND'], ['COMMAND'], [], 'socom-mil');

    $U('us-delta', 'US', 'us-army', 'DETACHMENT',
        '1st Special Forces Operational Detachment–Delta', '1st SFOD-D', '1st SFOD-D',
        '1st Special Forces Operational Detachment-Delta', 'us-jsoc', 3, 40,
        [['1st SFOD-D', 'ACRONYM', 'en'], ['SFOD-D', 'ACRONYM', 'en'], ['Delta Force', 'NICKNAME', 'en'], ['CAG', 'ACRONYM', 'en'], ['Combat Applications Group', 'FORMER_NAME', 'en']],
        ['LAND'], ['SOF'], ['COMBAT'], ['DIRECT_ACTION', 'COUNTER_TERRORISM', 'HOSTAGE_RESCUE'], 'socom-mil');

    $U('us-24sts', 'US', 'us-airforce', 'SQUADRON',
        '24th Special Tactics Squadron', '24th STS', '24th Special Tactics Squadron',
        '24th Special Tactics Squadron', 'us-jsoc', 3, 45,
        [['24th STS', 'SHORT_NAME', 'en'], ['24 STS', 'ALTERNATIVE_SPELLING', 'en']],
        ['AIR', 'LAND'], ['SOF'], ['COMBAT', 'SUPPORT'], ['SPECIAL_TACTICS', 'COMBAT_CONTROL', 'PARARESCUE', 'DIRECT_ACTION'], 'afsoc-af-mil');

    // --- Special Reconnaissance and Enabling Command (Fact Book USSOCOM) ---
    $U('us-srec', 'US', 'us-dod', 'JOINT_COMMAND',
        'Special Reconnaissance and Enabling Command', 'SREC', 'Special Reconnaissance and Enabling Command',
        'Special Reconnaissance and Enabling Command', 'us-ussocom', 2, 48,
        [['SREC', 'ACRONYM', 'en'], ['Joint Task Force SREC', 'COMMON_NAME', 'en']],
        ['LAND', 'INFORMATION'], ['SOF_COMMAND', 'RECONNAISSANCE'], ['COMMAND', 'RECONNAISSANCE', 'INTELLIGENCE'], ['SPECIAL_RECONNAISSANCE', 'INTELLIGENCE'], 'socom-mil');

    // --- TSOCs ---
    $tsocs = [
        ['us-socafrica', 'Special Operations Command Africa', 'SOCAFRICA', 50],
        ['us-soccent', 'Special Operations Command Central', 'SOCCENT', 51],
        ['us-soceur', 'Special Operations Command Europe', 'SOCEUR', 52],
        ['us-sockor', 'Special Operations Command Korea', 'SOCKOR', 53],
        ['us-socnorth', 'Special Operations Command North', 'SOCNORTH', 54],
        ['us-socpac', 'Special Operations Command Pacific', 'SOCPAC', 55],
        ['us-socsouth', 'Special Operations Command South', 'SOCSOUTH', 56],
    ];
    foreach ($tsocs as [$code, $official, $short, $s]) {
        $U($code, 'US', 'us-dod', 'JOINT_COMMAND',
            $official, $short, $short, $official, 'us-ussocom', 2, $s,
            [[$short, 'ACRONYM', 'en']],
            ['LAND', 'AIR', 'MARITIME'], ['SOF_COMMAND'], ['COMMAND'], [], 'socom-mil');
    }

    // --- USASOC ---
    $U('us-usasoc', 'US', 'us-army', 'COMPONENT_COMMAND',
        'U.S. Army Special Operations Command', 'USASOC', 'USASOC',
        'United States Army Special Operations Command', 'us-ussocom', 2, 60,
        [['USASOC', 'ACRONYM', 'en'], ['US Army Special Operations Command', 'COMMON_NAME', 'en']],
        ['LAND'], ['SOF_COMMAND'], ['COMMAND'], [], 'army-mil-usasoc');

    $U('us-1sfc', 'US', 'us-army', 'COMMAND',
        '1st Special Forces Command (Airborne)', '1st SFC', '1st Special Forces Command',
        '1st Special Forces Command (Airborne)', 'us-usasoc', 3, 70,
        [['1st SFC', 'SHORT_NAME', 'en'], ['1SFC', 'ALTERNATIVE_SPELLING', 'en'], ['1st SFC(A)', 'ALTERNATIVE_SPELLING', 'en']],
        ['LAND'], ['SOF_COMMAND'], ['COMMAND'], [], 'army-mil-usasoc');

    $sfgs = [
        ['us-1sfg', '1st Special Forces Group (Airborne)', '1st SFG', 80],
        ['us-3sfg', '3rd Special Forces Group (Airborne)', '3rd SFG', 90],
        ['us-5sfg', '5th Special Forces Group (Airborne)', '5th SFG', 100],
        ['us-7sfg', '7th Special Forces Group (Airborne)', '7th SFG', 110],
        ['us-10sfg', '10th Special Forces Group (Airborne)', '10th SFG', 120],
        ['us-19sfg', '19th Special Forces Group (Airborne)', '19th SFG', 125],
        ['us-20sfg', '20th Special Forces Group (Airborne)', '20th SFG', 128],
    ];
    foreach ($sfgs as [$code, $official, $short, $s]) {
        $aliases = [[$short, 'SHORT_NAME', 'en'], [str_replace(' ', '', $short), 'ALTERNATIVE_SPELLING', 'en'], ['Green Berets', 'NICKNAME', 'en']];
        if ($code === 'us-19sfg' || $code === 'us-20sfg') {
            $aliases[] = ['ARNG', 'COMMON_NAME', 'en'];
            $aliases[] = ['Army National Guard', 'COMMON_NAME', 'en'];
        }
        $U($code, 'US', 'us-army', 'GROUP',
            $official, $short, $short, $official, 'us-1sfc', 4, $s,
            $aliases,
            ['LAND'], ['SOF'], ['COMBAT'], ['DIRECT_ACTION', 'UNCONVENTIONAL_WARFARE', 'SPECIAL_RECONNAISSANCE', 'AIRBORNE', 'MILITARY_ASSISTANCE', 'FOREIGN_INTERNAL_DEFENSE'], 'army-mil-usasoc');
    }

    $U('us-4pog', 'US', 'us-army', 'GROUP',
        '4th Psychological Operations Group (Airborne)', '4th POG', '4th Psychological Operations Group',
        '4th Psychological Operations Group (Airborne)', 'us-1sfc', 4, 130,
        [['4th POG', 'SHORT_NAME', 'en'], ['4th PSYOP Group', 'COMMON_NAME', 'en'], ['PSYOP', 'ACRONYM', 'en']],
        ['LAND', 'INFORMATION'], ['SOF', 'SPECIALIZED'], ['PSYCHOLOGICAL_OPERATIONS', 'INFORMATION_OPERATIONS'], ['PSYCHOLOGICAL_OPERATIONS', 'INFORMATION_OPERATIONS'], 'army-mil-usasoc');

    $U('us-8pog', 'US', 'us-army', 'GROUP',
        '8th Psychological Operations Group (Airborne)', '8th POG', '8th Psychological Operations Group',
        '8th Psychological Operations Group (Airborne)', 'us-1sfc', 4, 132,
        [['8th POG', 'SHORT_NAME', 'en'], ['8th PSYOP Group', 'COMMON_NAME', 'en']],
        ['LAND', 'INFORMATION'], ['SOF', 'SPECIALIZED'], ['PSYCHOLOGICAL_OPERATIONS', 'INFORMATION_OPERATIONS'], ['PSYCHOLOGICAL_OPERATIONS', 'INFORMATION_OPERATIONS'], 'army-mil-usasoc');

    $U('us-95cab', 'US', 'us-army', 'BRIGADE',
        '95th Civil Affairs Brigade (Special Operations) (Airborne)', '95th CAB', '95th Civil Affairs Brigade',
        '95th Civil Affairs Brigade (Airborne)', 'us-1sfc', 4, 134,
        [['95th CAB', 'SHORT_NAME', 'en'], ['95th Civil Affairs', 'COMMON_NAME', 'en']],
        ['LAND'], ['SOF', 'SPECIALIZED'], ['CIVIL_AFFAIRS', 'SUPPORT'], ['CIVIL_AFFAIRS', 'MILITARY_ASSISTANCE'], 'army-mil-usasoc');

    $U('us-528sb', 'US', 'us-army', 'BRIGADE',
        '528th Sustainment Brigade (Special Operations) (Airborne)', '528th SB', '528th Sustainment Brigade',
        '528th Sustainment Brigade (Airborne)', 'us-1sfc', 4, 136,
        [['528th SB', 'SHORT_NAME', 'en'], ['528th Sustainment Brigade', 'COMMON_NAME', 'en']],
        ['LAND'], ['SOF_SUPPORT'], ['LOGISTICS', 'SUPPORT', 'MEDICAL_SUPPORT', 'COMMUNICATIONS'], [], 'army-mil-usasoc');

    $U('us-389mib', 'US', 'us-army', 'BATTALION',
        '389th Military Intelligence Battalion (Airborne)', '389th MIB', '389th Military Intelligence Battalion',
        '389th Military Intelligence Battalion', 'us-1sfc', 4, 138,
        [['389th MIB', 'SHORT_NAME', 'en']],
        ['LAND', 'INFORMATION'], ['SOF_SUPPORT', 'INTELLIGENCE'], ['INTELLIGENCE'], ['INTELLIGENCE'], 'army-mil-usasoc');

    $U('us-75rr', 'US', 'us-army', 'REGIMENT',
        '75th Ranger Regiment', '75th RR', '75th Ranger Regiment',
        '75th Ranger Regiment', 'us-usasoc', 3, 140,
        [['75th Ranger Regiment', 'COMMON_NAME', 'en'], ['Rangers', 'NICKNAME', 'en'], ['75 RR', 'ALTERNATIVE_SPELLING', 'en']],
        ['LAND'], ['SOF'], ['COMBAT'], ['DIRECT_ACTION', 'AIRBORNE'], 'army-mil-usasoc');

    foreach ([
        ['us-75rr-1bn', '1st Battalion', 141],
        ['us-75rr-2bn', '2nd Battalion', 142],
        ['us-75rr-3bn', '3rd Battalion', 143],
        ['us-75rr-stb', 'Regimental Special Troops Battalion', 144],
        ['us-75rr-mib', 'Regimental Military Intelligence Battalion', 145],
    ] as [$code, $bn, $s]) {
        $etype = str_contains($bn, 'Intelligence') ? 'INTELLIGENCE_UNIT' : 'BATTALION';
        $U($code, 'US', 'us-army', $etype,
            '75th Ranger Regiment — ' . $bn, $bn, '75th Ranger Regiment — ' . $bn,
            null, 'us-75rr', 4, $s,
            [[$bn, 'SHORT_NAME', 'en']],
            ['LAND'], ['SOF'], ['COMBAT'], ['DIRECT_ACTION', 'AIRBORNE'], 'army-mil-usasoc');
    }

    $U('us-usasoc-avn', 'US', 'us-army', 'COMMAND',
        'U.S. Army Special Operations Aviation Command (Airborne)', 'USASOAC', 'USASOAC',
        'U.S. Army Special Operations Aviation Command', 'us-usasoc', 3, 150,
        [['USASOAC', 'ACRONYM', 'en'], ['ARSOAC', 'ACRONYM', 'en']],
        ['AIR'], ['SOF_COMMAND', 'AVIATION'], ['COMMAND', 'AVIATION'], ['SPECIAL_OPERATIONS_AVIATION'], 'army-mil-usasoc');

    $U('us-160soar', 'US', 'us-army', 'REGIMENT',
        '160th Special Operations Aviation Regiment (Airborne)', '160th SOAR', '160th SOAR',
        '160th Special Operations Aviation Regiment', 'us-usasoc-avn', 4, 155,
        [['160th SOAR', 'SHORT_NAME', 'en'], ['SOAR', 'ACRONYM', 'en'], ['Night Stalkers', 'NICKNAME', 'en']],
        ['AIR'], ['SOF', 'AVIATION'], ['AVIATION', 'SUPPORT'], ['SPECIAL_OPERATIONS_AVIATION', 'HELICOPTER_OPERATIONS'], 'army-mil-usasoc');

    $U('us-jfk-swcs', 'US', 'us-army', 'TRAINING_UNIT',
        'U.S. Army John F. Kennedy Special Warfare Center and School', 'SWCS', 'JFK Special Warfare Center and School',
        'John F. Kennedy Special Warfare Center and School', 'us-usasoc', 3, 158,
        [['SWCS', 'ACRONYM', 'en'], ['JFK SWCS', 'SHORT_NAME', 'en'], ['USAJFKSWCS', 'ACRONYM', 'en']],
        ['LAND'], ['TRAINING', 'SOF_SUPPORT'], ['TRAINING'], [], 'army-mil-usasoc');

    // --- Naval Special Warfare Command ---
    $U('us-nswc', 'US', 'us-navy', 'COMPONENT_COMMAND',
        'Naval Special Warfare Command', 'NSWC', 'NSWC',
        'Naval Special Warfare Command', 'us-ussocom', 2, 170,
        [['NSWC', 'ACRONYM', 'en'], ['NSW', 'ACRONYM', 'en'], ['NAVSPECWARCOM', 'ACRONYM', 'en'], ['WARCOM', 'NICKNAME', 'en']],
        ['MARITIME'], ['SOF_COMMAND'], ['COMMAND'], ['MARITIME_OPERATIONS'], 'navy-mil-nsw');

    $U('us-nswg1', 'US', 'us-navy', 'GROUP',
        'Naval Special Warfare Group One', 'NSWG-1', 'NSWG-1',
        'Naval Special Warfare Group 1', 'us-nswc', 3, 175,
        [['NSWG-1', 'ACRONYM', 'en'], ['NSWG 1', 'ALTERNATIVE_SPELLING', 'en']],
        ['MARITIME'], ['SOF_COMMAND'], ['COMMAND'], ['MARITIME_OPERATIONS'], 'navy-mil-nsw');

    $U('us-nswg2', 'US', 'us-navy', 'GROUP',
        'Naval Special Warfare Group Two', 'NSWG-2', 'NSWG-2',
        'Naval Special Warfare Group 2', 'us-nswc', 3, 176,
        [['NSWG-2', 'ACRONYM', 'en']],
        ['MARITIME'], ['SOF_COMMAND'], ['COMMAND'], ['MARITIME_OPERATIONS'], 'navy-mil-nsw');

    $U('us-nswg4', 'US', 'us-navy', 'GROUP',
        'Naval Special Warfare Group Four', 'NSWG-4', 'NSWG-4',
        'Naval Special Warfare Group 4', 'us-nswc', 3, 177,
        [['NSWG-4', 'ACRONYM', 'en']],
        ['MARITIME'], ['SOF_COMMAND'], ['COMMAND'], ['MARITIME_OPERATIONS'], 'navy-mil-nsw');

    $U('us-nswg8', 'US', 'us-navy', 'GROUP',
        'Naval Special Warfare Group Eight', 'NSWG-8', 'NSWG-8',
        'Naval Special Warfare Group 8', 'us-nswc', 3, 178,
        [['NSWG-8', 'ACRONYM', 'en']],
        ['MARITIME', 'UNDERWATER'], ['SOF_COMMAND', 'SOF_SUPPORT'], ['COMMAND', 'SUPPORT', 'INTELLIGENCE'], ['UNDERWATER_OPERATIONS', 'SPECIAL_RECONNAISSANCE'], 'navy-mil-nsw');

    $U('us-nswg11', 'US', 'us-navy', 'GROUP',
        'Naval Special Warfare Group Eleven', 'NSWG-11', 'NSWG-11',
        'Naval Special Warfare Group 11', 'us-nswc', 3, 179,
        [['NSWG-11', 'ACRONYM', 'en']],
        ['MARITIME'], ['SOF_COMMAND'], ['COMMAND'], ['MARITIME_OPERATIONS'], 'navy-mil-nsw');

    $U('us-nsw-center', 'US', 'us-navy', 'TRAINING_UNIT',
        'Naval Special Warfare Center', 'NSWCEN', 'Naval Special Warfare Center',
        'Naval Special Warfare Center', 'us-nswc', 3, 180,
        [['NSWCEN', 'ACRONYM', 'en'], ['NSW Center', 'COMMON_NAME', 'en']],
        ['MARITIME'], ['TRAINING', 'SOF_SUPPORT'], ['TRAINING'], ['MARITIME_OPERATIONS'], 'navy-mil-nsw');

    // DEVGRU : rattachement organique NSWC (Fact Book) ; emploi fréquent sous OPCON JSOC
    $U('us-devgru', 'US', 'us-navy', 'GROUP',
        'Naval Special Warfare Development Group', 'DEVGRU', 'DEVGRU',
        'Naval Special Warfare Development Group', 'us-nswc', 3, 181,
        [['DEVGRU', 'ACRONYM', 'en'], ['SEAL Team Six', 'NICKNAME', 'en'], ['ST6', 'ACRONYM', 'en'], ['NSWDG', 'ACRONYM', 'en']],
        ['MARITIME', 'LAND'], ['SOF'], ['COMBAT'], ['DIRECT_ACTION', 'COUNTER_TERRORISM', 'MARITIME_OPERATIONS', 'HOSTAGE_RESCUE'], 'navy-mil-nsw');

    foreach ([1, 3, 5, 7] as $i => $n) {
        $U('us-seal-team-' . $n, 'US', 'us-navy', 'BATTALION',
            'SEAL Team ' . $n, 'ST' . $n, 'SEAL Team ' . $n,
            'SEAL Team ' . $n, 'us-nswg1', 4, 185 + $i,
            [['SEAL Team ' . $n, 'COMMON_NAME', 'en'], ['ST' . $n, 'ACRONYM', 'en']],
            ['MARITIME', 'LAND'], ['SOF'], ['COMBAT'], ['DIRECT_ACTION', 'MARITIME_OPERATIONS', 'SPECIAL_RECONNAISSANCE'], 'navy-mil-nsw');
    }
    foreach ([2, 4, 8, 10] as $i => $n) {
        $U('us-seal-team-' . $n, 'US', 'us-navy', 'BATTALION',
            'SEAL Team ' . $n, 'ST' . $n, 'SEAL Team ' . $n,
            'SEAL Team ' . $n, 'us-nswg2', 4, 190 + $i,
            [['SEAL Team ' . $n, 'COMMON_NAME', 'en'], ['ST' . $n, 'ACRONYM', 'en']],
            ['MARITIME', 'LAND'], ['SOF'], ['COMBAT'], ['DIRECT_ACTION', 'MARITIME_OPERATIONS', 'SPECIAL_RECONNAISSANCE'], 'navy-mil-nsw');
    }
    foreach ([['us-seal-team-17', 17], ['us-seal-team-18', 18]] as [$code, $n]) {
        $U($code, 'US', 'us-navy', 'BATTALION',
            'SEAL Team ' . $n, 'ST' . $n, 'SEAL Team ' . $n . ' (Reserve)',
            'SEAL Team ' . $n, 'us-nswg11', 4, 195 + $n,
            [['SEAL Team ' . $n, 'COMMON_NAME', 'en'], ['ST' . $n, 'ACRONYM', 'en']],
            ['MARITIME', 'LAND'], ['SOF'], ['COMBAT'], ['DIRECT_ACTION', 'MARITIME_OPERATIONS'], 'navy-mil-nsw');
    }

    foreach ([
        ['us-sbt-12', 'Special Boat Team 12', 'SBT-12', 200],
        ['us-sbt-20', 'Special Boat Team 20', 'SBT-20', 201],
        ['us-sbt-22', 'Special Boat Team 22', 'SBT-22', 202],
    ] as [$code, $official, $short, $s]) {
        $U($code, 'US', 'us-navy', 'MARITIME_UNIT',
            $official, $short, $official, $official, 'us-nswg4', 4, $s,
            [[$short, 'ACRONYM', 'en'], ['SWCC', 'COMMON_NAME', 'en']],
            ['MARITIME'], ['SOF'], ['COMBAT', 'SUPPORT'], ['MARITIME_OPERATIONS', 'MARITIME_ASSAULT'], 'navy-mil-nsw');
    }

    foreach ([
        ['us-sdvt-1', 'SEAL Delivery Vehicle Team 1', 'SDVT-1', 205],
        ['us-sdvt-2', 'SEAL Delivery Vehicle Team 2', 'SDVT-2', 206],
    ] as [$code, $official, $short, $s]) {
        $U($code, 'US', 'us-navy', 'MARITIME_UNIT',
            $official, $short, $official, $official, 'us-nswg8', 4, $s,
            [[$short, 'ACRONYM', 'en']],
            ['MARITIME', 'UNDERWATER'], ['SOF'], ['COMBAT'], ['UNDERWATER_OPERATIONS', 'COMBAT_DIVING', 'MARITIME_OPERATIONS'], 'navy-mil-nsw');
    }

    // --- AFSOC ---
    $U('us-afsoc', 'US', 'us-airforce', 'COMPONENT_COMMAND',
        'Air Force Special Operations Command', 'AFSOC', 'AFSOC',
        'Air Force Special Operations Command', 'us-ussocom', 2, 220,
        [['AFSOC', 'ACRONYM', 'en']],
        ['AIR'], ['SOF_COMMAND', 'AVIATION'], ['COMMAND', 'AVIATION'], ['SPECIAL_OPERATIONS_AVIATION'], 'afsoc-af-mil');

    $afsocWings = [
        ['us-1sow', '1st Special Operations Wing', '1st SOW', 221],
        ['us-24sow', '24th Special Operations Wing', '24th SOW', 222],
        ['us-27sow', '27th Special Operations Wing', '27th SOW', 223],
        ['us-352sow', '352nd Special Operations Wing', '352nd SOW', 224],
        ['us-353sow', '353rd Special Operations Wing', '353rd SOW', 225],
        ['us-492sow', '492nd Special Operations Wing', '492nd SOW', 226],
        ['us-137sow', '137th Special Operations Wing', '137th SOW', 227],
        ['us-193sow', '193rd Special Operations Wing', '193rd SOW', 228],
        ['us-919sow', '919th Special Operations Wing', '919th SOW', 229],
    ];
    foreach ($afsocWings as [$code, $official, $short, $s]) {
        $aliases = [[$short, 'SHORT_NAME', 'en'], [str_replace(' ', '', $short), 'ALTERNATIVE_SPELLING', 'en']];
        if (in_array($code, ['us-137sow', 'us-193sow'], true)) {
            $aliases[] = ['Air National Guard', 'COMMON_NAME', 'en'];
            $aliases[] = ['ANG', 'ACRONYM', 'en'];
        }
        if ($code === 'us-919sow') {
            $aliases[] = ['Air Force Reserve', 'COMMON_NAME', 'en'];
            $aliases[] = ['AFRC', 'ACRONYM', 'en'];
        }
        $U($code, 'US', 'us-airforce', 'WING',
            $official, $short, $official, $official, 'us-afsoc', 3, $s,
            $aliases,
            ['AIR'], ['SOF', 'AVIATION'], ['AVIATION', 'COMMAND'], ['SPECIAL_OPERATIONS_AVIATION'], 'afsoc-af-mil');
    }

    $U('us-720stg', 'US', 'us-airforce', 'GROUP',
        '720th Special Tactics Group', '720th STG', '720th Special Tactics Group',
        '720th Special Tactics Group', 'us-24sow', 4, 230,
        [['720th STG', 'SHORT_NAME', 'en']],
        ['AIR', 'LAND'], ['SOF'], ['COMBAT', 'SUPPORT'], ['SPECIAL_TACTICS', 'COMBAT_CONTROL', 'PARARESCUE'], 'afsoc-af-mil');

    $U('us-724stg', 'US', 'us-airforce', 'GROUP',
        '724th Special Tactics Group', '724th STG', '724th Special Tactics Group',
        '724th Special Tactics Group', 'us-24sow', 4, 231,
        [['724th STG', 'SHORT_NAME', 'en']],
        ['AIR', 'LAND'], ['SOF'], ['COMBAT', 'SUPPORT'], ['SPECIAL_TACTICS', 'COMBAT_CONTROL', 'PARARESCUE'], 'afsoc-af-mil');

    foreach ([
        ['us-21sts', '21st Special Tactics Squadron', '21st STS', 'us-720stg', 232],
        ['us-22sts', '22nd Special Tactics Squadron', '22nd STS', 'us-720stg', 233],
        ['us-23sts', '23rd Special Tactics Squadron', '23rd STS', 'us-720stg', 234],
        ['us-26sts', '26th Special Tactics Squadron', '26th STS', 'us-720stg', 235],
        ['us-17sts', '17th Special Tactics Squadron', '17th STS', 'us-724stg', 236],
    ] as [$code, $official, $short, $parent, $s]) {
        $U($code, 'US', 'us-airforce', 'SQUADRON',
            $official, $short, $official, $official, $parent, 5, $s,
            [[$short, 'SHORT_NAME', 'en']],
            ['AIR', 'LAND'], ['SOF'], ['COMBAT', 'SUPPORT'], ['SPECIAL_TACTICS', 'COMBAT_CONTROL', 'PARARESCUE', 'AIRBORNE'], 'afsoc-af-mil');
    }

    // --- MARSOC ---
    $U('us-marsoc', 'US', 'us-marines', 'COMPONENT_COMMAND',
        'Marine Forces Special Operations Command', 'MARSOC', 'MARSOC',
        'Marine Forces Special Operations Command', 'us-ussocom', 2, 250,
        [['MARSOC', 'ACRONYM', 'en'], ['Raiders', 'NICKNAME', 'en'], ['Marine Raiders', 'COMMON_NAME', 'en']],
        ['LAND', 'MARITIME'], ['SOF_COMMAND'], ['COMMAND', 'COMBAT'], ['DIRECT_ACTION'], 'marsoc-marines');

    $U('us-mrr', 'US', 'us-marines', 'REGIMENT',
        'Marine Raider Regiment', 'MRR', 'Marine Raider Regiment',
        'Marine Raider Regiment', 'us-marsoc', 3, 251,
        [['MRR', 'ACRONYM', 'en'], ['MSOR', 'FORMER_NAME', 'en'], ['Marine Special Operations Regiment', 'FORMER_NAME', 'en']],
        ['LAND', 'MARITIME'], ['SOF'], ['COMBAT', 'COMMAND'], ['DIRECT_ACTION', 'SPECIAL_RECONNAISSANCE', 'FOREIGN_INTERNAL_DEFENSE'], 'marsoc-marines');

    foreach ([
        ['us-mrb-1', '1st Marine Raider Battalion', '1st MRB', 252],
        ['us-mrb-2', '2nd Marine Raider Battalion', '2nd MRB', 253],
        ['us-mrb-3', '3rd Marine Raider Battalion', '3rd MRB', 254],
    ] as [$code, $official, $short, $s]) {
        $U($code, 'US', 'us-marines', 'BATTALION',
            $official, $short, $official, $official, 'us-mrr', 4, $s,
            [[$short, 'SHORT_NAME', 'en'], [str_replace('MRB', 'MSOB', $short), 'FORMER_NAME', 'en']],
            ['LAND', 'MARITIME'], ['SOF'], ['COMBAT'], ['DIRECT_ACTION', 'SPECIAL_RECONNAISSANCE', 'UNCONVENTIONAL_WARFARE'], 'marsoc-marines');
    }

    $U('us-mrg', 'US', 'us-marines', 'GROUP',
        'Marine Raider Group', 'MRG', 'Marine Raider Group',
        'Marine Raider Group', 'us-marsoc', 3, 255,
        [['MRG', 'ACRONYM', 'en'], ['MRSG', 'FORMER_NAME', 'en'], ['Marine Raider Support Group', 'FORMER_NAME', 'en'], ['MSOSG', 'FORMER_NAME', 'en']],
        ['LAND'], ['SOF_SUPPORT'], ['SUPPORT', 'LOGISTICS', 'INTELLIGENCE', 'COMMUNICATIONS'], [], 'marsoc-marines');

    $U('us-mrtc', 'US', 'us-marines', 'TRAINING_UNIT',
        'Marine Raider Training Center', 'MRTC', 'Marine Raider Training Center',
        'Marine Raider Training Center', 'us-marsoc', 3, 256,
        [['MRTC', 'ACRONYM', 'en'], ['MSOS', 'FORMER_NAME', 'en'], ['Marine Special Operations School', 'FORMER_NAME', 'en']],
        ['LAND'], ['TRAINING', 'SOF_SUPPORT'], ['TRAINING'], [], 'marsoc-marines');

    // --- Intelligence Community (interagences pertinentes pour le milsim SOF) ---
    $U('us-cia', 'US', 'us-intel', 'AGENCY',
        'Central Intelligence Agency', 'CIA', 'CIA',
        'Central Intelligence Agency', null, 0, 300,
        [['CIA', 'ACRONYM', 'en'], ['The Agency', 'NICKNAME', 'en']],
        ['LAND', 'INFORMATION', 'CYBER'], ['INTELLIGENCE'], ['INTELLIGENCE', 'COMMAND'], ['INTELLIGENCE'], 'cia-gov');

    $U('us-cia-sad', 'US', 'us-intel', 'DETACHMENT',
        'Special Activities Center', 'SAC', 'CIA — Special Activities Center',
        'Special Activities Center', 'us-cia', 1, 301,
        [['SAC', 'ACRONYM', 'en'], ['SAD', 'FORMER_NAME', 'en'], ['Special Activities Division', 'FORMER_NAME', 'en']],
        ['LAND'], ['INTELLIGENCE', 'SOF'], ['COMBAT', 'INTELLIGENCE'], ['DIRECT_ACTION', 'SPECIAL_RECONNAISSANCE', 'UNCONVENTIONAL_WARFARE'], 'cia-gov');

    $U('us-dia', 'US', 'us-intel', 'AGENCY',
        'Defense Intelligence Agency', 'DIA', 'DIA',
        'Defense Intelligence Agency', 'us-dod', 1, 310,
        [['DIA', 'ACRONYM', 'en']],
        ['LAND', 'INFORMATION'], ['INTELLIGENCE'], ['INTELLIGENCE'], ['INTELLIGENCE'], 'socom-mil');

    $U('us-nsa', 'US', 'us-intel', 'AGENCY',
        'National Security Agency', 'NSA', 'NSA',
        'National Security Agency', null, 0, 320,
        [['NSA', 'ACRONYM', 'en']],
        ['CYBER', 'INFORMATION'], ['INTELLIGENCE'], ['INTELLIGENCE'], ['INTELLIGENCE', 'ELECTRONIC_WARFARE'], 'socom-mil');

    // ===================== GERMANY =====================
    $U('de-kdo-sok', 'DE', 'de-heer', 'COMMAND',
        'Kommando Spezialkräfte', 'KSK', 'KSK',
        'Special Forces Command', null, 0, 10,
        [['KSK', 'ACRONYM', 'de'], ['Kommando Spezialkräfte (KSK)', 'COMMON_NAME', 'de']],
        ['LAND'], ['SOF_COMMAND'], ['COMMAND', 'COMBAT'], ['DIRECT_ACTION'], null);

    $U('de-ksk-hq', 'DE', 'de-heer', 'COMPONENT_COMMAND',
        'KSK — État-major et état-major interarmées', 'KSK HQ', 'KSK — État-major',
        null, 'de-kdo-sok', 1, 20,
        [], ['LAND'], ['SOF_COMMAND'], ['COMMAND'], [], null);

    $U('de-ksk-kompanien', 'DE', 'de-heer', 'COMPANY',
        'KSK — Compagnies opérationnelles', 'KSK Kompanien', 'KSK — Compagnies opérationnelles',
        null, 'de-kdo-sok', 1, 30,
        [], ['LAND'], ['SOF'], ['COMBAT'], ['DIRECT_ACTION'], null);

    $U('de-ksm', 'DE', 'de-marine', 'COMMAND',
        'Kommando Spezialoperationen der Marine', 'KSM', 'KSM',
        null, null, 0, 40,
        [['KSM', 'ACRONYM', 'de']],
        ['MARITIME', 'UNDERWATER'], ['SOF_COMMAND'], ['COMMAND'], ['COMBAT_DIVING', 'MARITIME_OPERATIONS'], null);

    $U('de-ksm-kompanien', 'DE', 'de-marine', 'COMPANY',
        'KSM — Compagnies de combat swimmers', 'KSM Kompanien', 'KSM — Compagnies de combat swimmers',
        null, 'de-ksm', 1, 50,
        [], ['MARITIME', 'UNDERWATER'], ['SOF'], ['COMBAT'], ['COMBAT_DIVING', 'UNDERWATER_OPERATIONS'], null);

    // ===================== BELGIUM =====================
    $U('be-sf-gp', 'BE', 'be-land', 'COMMAND',
        'Special Forces Group', 'SF Gp', 'Special Forces Group (SF Gp)',
        'Special Forces Group', null, 0, 10,
        [['SF Gp', 'SHORT_NAME', 'en'], ['SFG', 'ACRONYM', 'en']],
        ['LAND'], ['SOF_COMMAND'], ['COMMAND'], [], null);

    $U('be-sf-gp-hq', 'BE', 'be-land', 'COMPONENT_COMMAND',
        'SF Gp — État-major', 'SF Gp HQ', 'SF Gp — État-major',
        null, 'be-sf-gp', 1, 20,
        [], ['LAND'], ['SOF_COMMAND'], ['COMMAND'], [], null);

    foreach ([['be-sf-gp-1st', '1re', 30], ['be-sf-gp-2nd', '2e', 40], ['be-sf-gp-3rd', '3e', 50]] as [$code, $ord, $s]) {
        $U($code, 'BE', 'be-land', 'COMPANY',
            'SF Gp — ' . $ord . ' compagnie opérationnelle', $ord . ' cie', 'SF Gp — ' . $ord . ' compagnie opérationnelle',
            null, 'be-sf-gp', 1, $s,
            [], ['LAND'], ['SOF'], ['COMBAT'], ['DIRECT_ACTION'], null);
    }

    $U('be-para-cdo', 'BE', 'be-land', 'REGIMENT',
        'Régiment Para-Commando', 'Para-Cdo', 'Régiment Para-Commando',
        null, null, 0, 60,
        [['Para-Commando', 'COMMON_NAME', 'fr']],
        ['LAND'], ['ELITE_CONVENTIONAL'], ['COMBAT'], ['AIRBORNE'], null);

    $U('be-2para', 'BE', 'be-land', 'BATTALION',
        '2e Bataillon Para', '2 Para', '2e Bataillon Para',
        null, 'be-para-cdo', 1, 70,
        [['2 Para', 'SHORT_NAME', 'fr']],
        ['LAND'], ['ELITE_CONVENTIONAL'], ['COMBAT'], ['AIRBORNE'], null);

    $U('be-3para', 'BE', 'be-land', 'BATTALION',
        '3e Bataillon Para', '3 Para', '3e Bataillon Para',
        null, 'be-para-cdo', 1, 80,
        [['3 Para', 'SHORT_NAME', 'fr']],
        ['LAND'], ['ELITE_CONVENTIONAL'], ['COMBAT'], ['AIRBORNE'], null);

    // ===================== SPAIN =====================
    $U('es-moe', 'ES', 'es-ejercito', 'COMMAND',
        'Mando de Operaciones Especiales', 'MOE', 'MOE',
        'Special Operations Command', null, 0, 10,
        [['MOE', 'ACRONYM', 'es']],
        ['LAND'], ['SOF_COMMAND'], ['COMMAND'], [], null);

    foreach ([['es-boe-i', 'I', 20], ['es-boe-ii', 'II', 30], ['es-boe-iii', 'III', 40]] as [$code, $num, $s]) {
        $U($code, 'ES', 'es-ejercito', 'BATTALION',
            'MOE — Bataillon d’opérations spéciales « Órdenes » ' . $num,
            'BOE ' . $num, 'MOE — BOE « Órdenes » ' . $num,
            null, 'es-moe', 1, $s,
            [['BOE ' . $num, 'SHORT_NAME', 'es']],
            ['LAND'], ['SOF'], ['COMBAT'], ['DIRECT_ACTION'], null);
    }

    $U('es-uoe', 'ES', 'es-armada', 'COMMAND',
        'Unidad de Operaciones Especiales', 'UOE', 'UOE — Armada',
        null, null, 0, 50,
        [['UOE', 'ACRONYM', 'es']],
        ['MARITIME'], ['SOF_COMMAND'], ['COMMAND'], ['MARITIME_OPERATIONS'], null);

    $U('es-ezapac', 'ES', 'es-aire', 'SQUADRON',
        'Escuadrón de Apoyo al Despliegue Inmediato', 'EZAPAC', 'EZAPAC',
        null, null, 0, 60,
        [['EZAPAC', 'ACRONYM', 'es']],
        ['AIR', 'LAND'], ['SOF'], ['COMBAT', 'SUPPORT'], ['AIRBORNE'], null);

    $U('es-grupo-oe', 'ES', 'es-gc', 'GROUP',
        'Grupo de Operaciones Especiales', 'GOE', 'GOE — Guardia Civil',
        null, null, 0, 70,
        [['GOE', 'ACRONYM', 'es']],
        ['LAND'], ['INTERVENTION'], ['COMBAT'], ['COUNTER_TERRORISM'], null);

    $total = (int) $pdo->query('SELECT COUNT(*) FROM military_units')->fetchColumn();
    echo "  Seed synchronisé : {$total} entités militaires.\n";
}
