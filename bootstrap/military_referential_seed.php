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

    // =====================================================================
    // ENRICHISSEMENT / CORRECTIONS — vérifié octobre 2026
    // À coller dans military_referential_seed() juste AVANT la ligne `$total = ...`.
    // Le fragment est autonome : il ajoute pays / services / types / spécialités / sources,
    // recharge les tables de correspondance puis REDÉFINIT $U (la closure d'origine a capturé
    // les tableaux par valeur et ne verrait pas les nouveaux codes).
    // =====================================================================

    // --- Pays supplémentaires ---
    foreach ([
        ['GB', 'GBR', 'Royaume-Uni', 'United Kingdom', 'gb', 60],
        ['CA', 'CAN', 'Canada', 'Canada', 'ca', 70],
        ['AU', 'AUS', 'Australie', 'Australia', 'au', 80],
        ['PL', 'POL', 'Pologne', 'Poland', 'pl', 90],
        ['IT', 'ITA', 'Italie', 'Italy', 'it', 100],
        ['NL', 'NLD', 'Pays-Bas', 'Netherlands', 'nl', 110],
        ['NO', 'NOR', 'Norvège', 'Norway', 'no', 120],
    ] as [$iso2, $iso3, $fr, $en, $flag, $sort]) {
        $ins($pdo, 'INSERT INTO countries (iso2, iso3, name_fr, name_en, flag_code, sort_order, active) VALUES (?,?,?,?,?,?,1)
            ON DUPLICATE KEY UPDATE name_fr = VALUES(name_fr), name_en = VALUES(name_en), flag_code = VALUES(flag_code), sort_order = VALUES(sort_order), active = 1',
            [$iso2, $iso3, $fr, $en, $flag, $sort]);
    }
    $countryId = [];
    foreach ($pdo->query('SELECT id, iso2 FROM countries')->fetchAll(PDO::FETCH_ASSOC) as $r) {
        $countryId[$r['iso2']] = (int) $r['id'];
    }

    // --- Services supplémentaires ---
    foreach ([
        ['be-defence', 'BE', 'Défense — structures interarmées', 'Def', 'Défense belge / Defensie — état-major et structures interarmées', 'JOINT', 5],
        ['es-emad', 'ES', 'Estado Mayor de la Defensa', 'EMAD', 'Estado Mayor de la Defensa', 'JOINT', 5],
        ['es-pn', 'ES', 'Policía Nacional', 'PN', 'Cuerpo Nacional de Policía', 'POLICE', 50],
        ['gb-defence', 'GB', 'UK Defence — joint', 'MOD', 'Ministry of Defence — joint organisations', 'JOINT', 5],
        ['gb-army', 'GB', 'British Army', 'BA', 'British Army', 'ARMY', 10],
        ['gb-rn', 'GB', 'Royal Navy', 'RN', 'Royal Navy', 'NAVY', 20],
        ['gb-rm', 'GB', 'Royal Marines', 'RM', 'Royal Marines (Naval Service)', 'MARINES', 25],
        ['gb-raf', 'GB', 'Royal Air Force', 'RAF', 'Royal Air Force', 'AIR', 30],
        ['ca-caf', 'CA', 'Forces armées canadiennes', 'FAC', 'Canadian Armed Forces / Forces armées canadiennes', 'JOINT', 5],
        ['au-army', 'AU', 'Australian Army', 'AA', 'Australian Army', 'ARMY', 10],
        ['pl-ws', 'PL', 'Wojska Specjalne', 'WS', 'Wojska Specjalne (forces spéciales polonaises)', 'SPECIAL_FORCES', 15],
        ['pl-sp', 'PL', 'Siły Powietrzne', 'SP', 'Siły Powietrzne (armée de l’air polonaise)', 'AIR', 30],
        ['it-difesa', 'IT', 'Stato Maggiore della Difesa', 'SMD', 'Stato Maggiore della Difesa — strutture interforze', 'JOINT', 5],
        ['it-esercito', 'IT', 'Esercito Italiano', 'EI', 'Esercito Italiano', 'ARMY', 10],
        ['it-marina', 'IT', 'Marina Militare', 'MM', 'Marina Militare', 'NAVY', 20],
        ['it-am', 'IT', 'Aeronautica Militare', 'AM', 'Aeronautica Militare', 'AIR', 30],
        ['it-cc', 'IT', 'Arma dei Carabinieri', 'CC', 'Arma dei Carabinieri', 'GENDARMERIE', 40],
        ['nl-defensie', 'NL', 'Defensie — joint', 'CDS', 'Ministerie van Defensie — Commandant der Strijdkrachten', 'JOINT', 5],
        ['nl-kl', 'NL', 'Koninklijke Landmacht', 'KL', 'Koninklijke Landmacht', 'ARMY', 10],
        ['nl-km', 'NL', 'Koninklijke Marine', 'KM', 'Koninklijke Marine (incl. Korps Mariniers)', 'NAVY', 20],
        ['no-forsvaret', 'NO', 'Forsvaret', 'FMS', 'Forsvaret — strukturer felles', 'JOINT', 5],
        ['no-spesialstyrker', 'NO', 'Forsvarets spesialstyrker', 'FS', 'Forsvarets spesialstyrker (NORSOCOM)', 'SPECIAL_FORCES', 15],
        ['no-luftforsvaret', 'NO', 'Luftforsvaret', 'LV', 'Luftforsvaret', 'AIR', 30],
    ] as [$code, $iso, $name, $short, $official, $type, $sort]) {
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

    // --- Types d'entité supplémentaires ---
    foreach ([
        ['DIVISION', 'Division', 'Division', 35],
    ] as [$code, $fr, $en, $sort]) {
        $ins($pdo, 'INSERT INTO military_entity_types (code, label_fr, label_en, sort_order) VALUES (?,?,?,?)
            ON DUPLICATE KEY UPDATE label_fr = VALUES(label_fr), label_en = VALUES(label_en), sort_order = VALUES(sort_order)',
            [$code, $fr, $en, $sort]);
    }
    $typeId = [];
    foreach ($pdo->query('SELECT id, code FROM military_entity_types')->fetchAll(PDO::FETCH_ASSOC) as $r) {
        $typeId[$r['code']] = (int) $r['id'];
    }

    // --- Spécialités supplémentaires ---
    foreach ([
        ['AIR_ASSAULT', 'Assaut aérien / aéromobile', 'Air assault', 'air', 290],
        ['MOUNTAIN_WARFARE', 'Combat en montagne', 'Mountain warfare', 'land', 300],
        ['AMPHIBIOUS_OPERATIONS', 'Opérations amphibies', 'Amphibious operations', 'maritime', 310],
        ['ARCTIC_WARFARE', 'Combat grand froid / arctique', 'Arctic warfare', 'land', 320],
        ['CBRN_DEFENSE', 'Défense NRBC', 'CBRN defence', 'support', 330],
        ['COMBAT_ENGINEERING', 'Génie de combat', 'Combat engineering', 'support', 340],
    ] as [$code, $fr, $en, $cat, $sort]) {
        $ins($pdo, 'INSERT INTO military_specialties (code, label_fr, label_en, category, sort_order) VALUES (?,?,?,?,?)
            ON DUPLICATE KEY UPDATE label_fr = VALUES(label_fr), label_en = VALUES(label_en), category = VALUES(category), sort_order = VALUES(sort_order)',
            [$code, $fr, $en, $cat, $sort]);
    }
    $specialtyId = [];
    foreach ($pdo->query('SELECT id, code FROM military_specialties')->fetchAll(PDO::FETCH_ASSOC) as $r) {
        $specialtyId[$r['code']] = (int) $r['id'];
    }

    // --- Sources supplémentaires ---
    foreach ([
        ['defense-gouv-cast', 'Commandement des actions spéciales Terre', 'Ministère des Armées', 'https://www.defense.gouv.fr/terre/unites-larmee-terre/nos-brigades/commandement-actions-speciales-terre', 'institutional'],
        ['defense-gouv-ciae', 'Centre interarmées des actions sur l’environnement', 'Ministère des Armées', 'https://www.defense.gouv.fr/terre/unites-larmee-terre/nos-brigades/commandement-actions-speciales-terre/centre-interarmees-actions', 'institutional'],
        ['defense-gouv-terre', 'Armée de Terre — unités', 'Ministère des Armées', 'https://www.defense.gouv.fr/terre', 'institutional'],
        ['defense-gouv-11bp', '11e brigade parachutiste', 'Ministère des Armées', 'https://www.defense.gouv.fr/en/node/12300', 'institutional'],
        ['gendarmerie-gign', 'GIGN', 'Gendarmerie nationale', 'https://www.gendarmerie.interieur.gouv.fr/gign', 'institutional'],
        ['army-mil', 'U.S. Army', 'U.S. Army', 'https://www.army.mil/', 'institutional'],
        ['army-mil-8pog', '8th POG inactivation', 'U.S. Army', 'https://www.army.mil/article/295782', 'institutional'],
        ['marines-mil', 'U.S. Marine Corps', 'U.S. Marine Corps', 'https://www.marines.mil/', 'institutional'],
        ['afsoc-17sts', '17th STS moves to 724th STG', 'AFSOC', 'https://www.afsoc.af.mil/News/Tag/159929/17-sts/', 'institutional'],
        ['bundeswehr-dsk', 'Division Schnelle Kräfte', 'Bundeswehr', 'https://www.bundeswehr.de/de/organisation/heer/struktur/division-schnelle-kraefte', 'institutional'],
        ['bundeswehr-ksk', 'Kommando Spezialkräfte', 'Bundeswehr', 'https://www.bundeswehr.de/de/organisation/heer/struktur/division-schnelle-kraefte/kommando-spezialkraefte', 'institutional'],
        ['bundeswehr-ksm', 'Kommando Spezialkräfte der Marine', 'Bundeswehr', 'https://www.bundeswehr.de/de/organisation/marine/struktur/einsatzflottille-1/kommando-spezialkraefte-marine', 'institutional'],
        ['bundeswehr-llbrig1', 'Luftlandebrigade 1', 'Bundeswehr', 'https://www.bundeswehr.de/de/organisation/heer/struktur/division-schnelle-kraefte/luftlandebrigade-1', 'institutional'],
        ['mil-be', 'Défense belge', 'Défense belge / Defensie', 'https://www.mil.be/', 'institutional'],
        ['ejercito-moe', 'Mando de Operaciones Especiales — organización', 'Ejército de Tierra', 'https://ejercito.defensa.gob.es/unidades/Alicante/cg_moe/Organizacion/index.html', 'institutional'],
        ['ejercito-es', 'Ejército de Tierra', 'Ejército de Tierra', 'https://ejercito.defensa.gob.es/', 'institutional'],
        ['emad-mcoe', 'Mando Conjunto de Operaciones Especiales', 'Estado Mayor de la Defensa', 'https://emad.defensa.gob.es/', 'institutional'],
        ['armada-es', 'Armada Española', 'Armada Española', 'https://armada.defensa.gob.es/', 'institutional'],
        ['ejercitodelaire-es', 'Ejército del Aire y del Espacio', 'Ministerio de Defensa', 'https://ejercitodelaire.defensa.gob.es/', 'institutional'],
        ['guardiacivil-es', 'Guardia Civil', 'Guardia Civil', 'https://www.guardiacivil.es/', 'institutional'],
        ['policia-es', 'Policía Nacional', 'Policía Nacional', 'https://www.policia.es/', 'institutional'],
        ['gov-uk-mod', 'Ministry of Defence', 'UK Government', 'https://www.gov.uk/government/organisations/ministry-of-defence', 'institutional'],
        ['army-mod-uk', 'British Army', 'British Army', 'https://www.army.mod.uk/', 'institutional'],
        ['royalnavy-mod-uk', 'Royal Navy / Royal Marines', 'Royal Navy', 'https://www.royalnavy.mod.uk/', 'institutional'],
        ['royalnavy-ukcf', 'UK Commando Force renaming (June 2026)', 'Royal Navy', 'https://www.royalnavy.mod.uk/news/2026/june/09/20260609-commando-name-change', 'institutional'],
        ['raf-mod-uk', 'Royal Air Force', 'Royal Air Force', 'https://www.raf.mod.uk/', 'institutional'],
        ['canada-cansofcom', 'Canadian Special Operations Forces Command', 'Government of Canada', 'https://www.canada.ca/en/department-national-defence/corporate/organizational-structure/canadian-special-operations-forces-command.html', 'institutional'],
        ['defence-gov-au-socomd', 'Special Operations Command (fact sheet)', 'Australian Department of Defence', 'https://www.defence.gov.au/sites/default/files/2021-07/special_operations_command.pdf', 'institutional'],
        ['wojsko-polskie', 'Wojsko Polskie', 'Ministerstwo Obrony Narodowej', 'https://www.wojsko-polskie.pl/', 'institutional'],
        ['difesa-it', 'Ministero della Difesa', 'Ministero della Difesa', 'https://www.difesa.it/', 'institutional'],
        ['esercito-it', 'Esercito Italiano', 'Esercito Italiano', 'https://www.esercito.difesa.it/', 'institutional'],
        ['marina-it', 'Marina Militare', 'Marina Militare', 'https://www.marina.difesa.it/', 'institutional'],
        ['aeronautica-it', 'Aeronautica Militare', 'Aeronautica Militare', 'https://www.aeronautica.difesa.it/', 'institutional'],
        ['carabinieri-it', 'Arma dei Carabinieri', 'Arma dei Carabinieri', 'https://www.carabinieri.it/', 'institutional'],
        ['defensie-nlsocom', 'Netherlands Special Operations Command', 'Ministerie van Defensie', 'https://www.defensie.nl/organisatie/bestuursstaf/cds/netherlands-special-operations-command', 'institutional'],
        ['defensie-nl', 'Defensie', 'Ministerie van Defensie', 'https://www.defensie.nl/', 'institutional'],
        ['forsvaret-no', 'Forsvaret', 'Forsvaret', 'https://www.forsvaret.no/', 'institutional'],
    ] as [$code, $name, $pub, $url, $type]) {
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

    // --- $U redéfini avec les tables rechargées ---
    $U = static function (...$args) use ($addUnit, $pdo, $countryId, $serviceId, $typeId, $domainId, $classId, $functionId, $specialtyId, $sourceId): int {
        return $addUnit($pdo, $countryId, $serviceId, $typeId, $domainId, $classId, $functionId, $specialtyId, $sourceId, ...$args);
    };

    // --- Helpers de correction (les liaisons/alias sont additifs : il faut supprimer explicitement) ---
    $setStatus = static function (string $code, string $status, ?string $dissolvedAt = null) use ($pdo, $ins): void {
        $ins($pdo, 'UPDATE military_units SET status = ?, active = ?, dissolved_at = ?, updated_at = NOW() WHERE code = ?',
            [$status, $status === 'active' ? 1 : 0, $dissolvedAt, $code]);
    };
    $dropAlias = static function (string $code, string $alias) use ($pdo, $ins): void {
        $ins($pdo, 'DELETE a FROM military_unit_aliases a JOIN military_units u ON u.id = a.unit_id WHERE u.code = ? AND a.alias = ?',
            [$code, $alias]);
    };
    $dropClass = static function (string $code, string $classCode) use ($pdo, $ins): void {
        $ins($pdo, 'DELETE x FROM military_unit_classifications x JOIN military_units u ON u.id = x.unit_id
            JOIN military_classifications c ON c.id = x.classification_id WHERE u.code = ? AND c.code = ?', [$code, $classCode]);
    };
    $dropSpec = static function (string $code, string $specCode) use ($pdo, $ins): void {
        $ins($pdo, 'DELETE x FROM military_unit_specialties x JOIN military_units u ON u.id = x.unit_id
            JOIN military_specialties s ON s.id = x.specialty_id WHERE u.code = ? AND s.code = ?', [$code, $specCode]);
    };

    // =====================================================================
    // CORRECTIONS — FRANCE
    // =====================================================================
    // CAST : ex-COM FST (2016) renommé CAST au 1er janvier 2024 ; autorité organique CFOT, emploi COS.
    $U('fr-bfst', 'FR', 'fr-terre', 'COMPONENT_COMMAND',
        'Commandement des actions spéciales Terre', 'CAST', 'CAST',
        'Army Special Actions Command', 'fr-cos', 1, 20,
        [['COM FST', 'FORMER_NAME', 'fr'], ['COMFST', 'ALTERNATIVE_SPELLING', 'fr'], ['FST', 'ACRONYM', 'fr'], ['Forces spéciales Terre', 'COMMON_NAME', 'fr']],
        ['LAND', 'INFORMATION'], ['SOF_COMMAND'], ['COMMAND'], [], 'defense-gouv-cast');

    // Centre Arès → Académie des forces spéciales
    $U('fr-cfor', 'FR', 'fr-terre', 'TRAINING_UNIT',
        'Académie des forces spéciales', 'Académie FS', 'Académie des forces spéciales (ex-centre Arès)',
        'Special Forces Academy', 'fr-bfst', 2, 60,
        [['Centre Arès', 'FORMER_NAME', 'fr'], ['Académie des forces spéciales', 'COMMON_NAME', 'fr']],
        ['LAND'], ['TRAINING', 'SOF_SUPPORT'], ['TRAINING'], [], 'defense-gouv-cast');

    $U('fr-ciae', 'FR', 'fr-terre', 'CENTER',
        'Centre interarmées des actions sur l’environnement', 'CIAE', 'CIAE',
        null, 'fr-bfst', 2, 58,
        [['Centre interarmées des actions sur l’environnement', 'COMMON_NAME', 'fr']],
        ['LAND', 'INFORMATION'], ['SOF_SUPPORT', 'SPECIALIZED'], ['INFORMATION_OPERATIONS'], ['INFORMATION_OPERATIONS', 'CIVIL_AFFAIRS'], 'defense-gouv-ciae');

    $U('fr-cpmo', 'FR', 'fr-terre', 'CENTER',
        'Centre terre pour le partenariat militaire opérationnel', 'CPMO', 'CPMO',
        null, 'fr-bfst', 2, 61,
        [['CPMO', 'ACRONYM', 'fr'], ['Partenariat militaire opérationnel', 'COMMON_NAME', 'fr']],
        ['LAND'], ['SOF_SUPPORT', 'SPECIALIZED'], ['TRAINING', 'SUPPORT'], ['MILITARY_ASSISTANCE', 'SECURITY_FORCE_ASSISTANCE'], 'defense-gouv-cast');

    // CPA 20 n'appartient PAS à la BFSA (BFSA = CPA 10, CPA 30, ET 3/61, EH 1/67) : rattaché à la BAFSI.
    $U('fr-bafsi', 'FR', 'fr-aae', 'BRIGADE',
        'Brigade aérienne des forces de sécurité et d’intervention', 'BAFSI', 'BAFSI',
        null, null, 0, 185,
        [['BAFSI', 'ACRONYM', 'fr']],
        ['AIR', 'LAND'], ['SPECIALIZED'], ['COMMAND', 'SUPPORT'], [], 'defense-gouv-air');
    $U('fr-cpa20', 'FR', 'fr-aae', 'COMMANDO',
        'Commando parachutiste de l’air n° 20', 'CPA 20', 'CPA 20',
        null, 'fr-bafsi', 1, 186,
        [['Commando parachutiste de l’air 20', 'COMMON_NAME', 'fr']],
        ['AIR', 'LAND'], ['SPECIALIZED'], ['COMBAT', 'SUPPORT'], ['AIRBORNE'], 'defense-gouv-air');
    $dropClass('fr-cpa20', 'SOF');
    $dropClass('fr-cpa20', 'SOF_SUPPORT');
    $dropSpec('fr-cpa20', 'PERSONNEL_RECOVERY');

    // CPA 30 : RESCO / appui aérien (a absorbé les groupes spécialisés du CPA 20 en 2018)
    $U('fr-cpa30', 'FR', 'fr-aae', 'COMMANDO',
        'Commando parachutiste de l’air n° 30', 'CPA 30', 'CPA 30',
        null, 'fr-bfsa', 2, 170,
        [['RESCO', 'COMMON_NAME', 'fr']],
        ['AIR', 'LAND'], ['SOF'], ['COMBAT', 'SUPPORT'], ['PERSONNEL_RECOVERY', 'CLOSE_AIR_SUPPORT'], 'defense-gouv-air');

    $U('fr-gign', 'FR', 'fr-gendarmerie', 'GROUP',
        'Groupe d’intervention de la Gendarmerie nationale', 'GIGN', 'GIGN',
        'National Gendarmerie Intervention Group', null, 0, 200,
        [['Groupe d’intervention de la Gendarmerie nationale', 'COMMON_NAME', 'fr']],
        ['LAND'], ['INTERVENTION'], ['COMBAT'], ['COUNTER_TERRORISM', 'HOSTAGE_RESCUE'], 'gendarmerie-gign');

    // =====================================================================
    // CORRECTIONS — USA
    // =====================================================================
    $U('us-delta', 'US', 'us-army', 'DETACHMENT',
        '1st Special Forces Operational Detachment–Delta (Airborne)', '1st SFOD-D', '1st SFOD-D (Delta Force)',
        '1st Special Forces Operational Detachment-Delta (Airborne)', 'us-jsoc', 3, 40,
        [['Delta', 'NICKNAME', 'en'], ['Army Compartmented Elements', 'CODE_NAME', 'en'], ['ACE', 'CODE_NAME', 'en'], ['Task Force Green', 'CODE_NAME', 'en'], ['The Unit', 'NICKNAME', 'en']],
        ['LAND'], ['SOF'], ['COMBAT'], ['DIRECT_ACTION', 'COUNTER_TERRORISM', 'HOSTAGE_RESCUE'], 'socom-mil');

    // 17th STS transféré du 720th STG au 724th STG le 1er octobre 2020
    $U('us-17sts', 'US', 'us-airforce', 'SQUADRON',
        '17th Special Tactics Squadron', '17th STS', '17th Special Tactics Squadron',
        '17th Special Tactics Squadron', 'us-724stg', 5, 236,
        [['17 STS', 'ALTERNATIVE_SPELLING', 'en']],
        ['AIR', 'LAND'], ['SOF'], ['COMBAT', 'SUPPORT'], ['SPECIAL_TACTICS', 'COMBAT_CONTROL', 'PARARESCUE', 'AIRBORNE'], 'afsoc-17sts');

    // 24th STS : rattachement organique 724th STG, emploi JSOC (on conserve le parent JSOC, alias ajoutés)
    $U('us-24sts', 'US', 'us-airforce', 'SQUADRON',
        '24th Special Tactics Squadron', '24th STS', '24th Special Tactics Squadron',
        '24th Special Tactics Squadron', 'us-jsoc', 3, 45,
        [['24th STS (724th STG)', 'COMMON_NAME', 'en']],
        ['AIR', 'LAND'], ['SOF'], ['COMBAT', 'SUPPORT'], ['SPECIAL_TACTICS', 'COMBAT_CONTROL', 'PARARESCUE', 'DIRECT_ACTION'], 'afsoc-af-mil');

    // 8th POG(A) inactivé le 29 septembre 2026 à Fort Bragg
    $U('us-8pog', 'US', 'us-army', 'GROUP',
        '8th Psychological Operations Group (Airborne)', '8th POG', '8th Psychological Operations Group',
        '8th Psychological Operations Group (Airborne)', 'us-1sfc', 4, 132,
        [['Phantom Knights', 'NICKNAME', 'en'], ['8th MISG', 'FORMER_NAME', 'en'], ['8th Military Information Support Group', 'FORMER_NAME', 'en']],
        ['LAND', 'INFORMATION'], ['SOF', 'SPECIALIZED'], ['PSYCHOLOGICAL_OPERATIONS'], ['PSYCHOLOGICAL_OPERATIONS'], 'army-mil-8pog');
    $setStatus('us-8pog', 'dissolved', '2026-09-29');

    // MARSOC : le groupe d'appui s'appelle Marine Raider Support Group (MRSG), pas "Marine Raider Group"
    $U('us-mrg', 'US', 'us-marines', 'GROUP',
        'Marine Raider Support Group', 'MRSG', 'Marine Raider Support Group',
        'Marine Raider Support Group', 'us-marsoc', 3, 255,
        [],
        ['LAND'], ['SOF_SUPPORT'], ['SUPPORT', 'LOGISTICS', 'INTELLIGENCE', 'COMMUNICATIONS'], [], 'marsoc-marines');
    $dropAlias('us-mrg', 'MRSG');
    $dropAlias('us-mrg', 'Marine Raider Support Group');
    $dropAlias('us-mrg', 'MRG');
    $U('us-mrg', 'US', 'us-marines', 'GROUP',
        'Marine Raider Support Group', 'MRSG', 'Marine Raider Support Group',
        'Marine Raider Support Group', 'us-marsoc', 3, 255,
        [['MRSG', 'ACRONYM', 'en'], ['Marine Raider Support Group', 'COMMON_NAME', 'en']],
        [], [], [], [], null);

    // SREC : aucune source publique trouvée (Fact Books 2023-2025, CRS, socom.mil) → désactivé en attente de preuve
    $setStatus('us-srec', 'inactive');

    // =====================================================================
    // CORRECTIONS — ALLEMAGNE
    // =====================================================================
    $U('de-dsk', 'DE', 'de-heer', 'DIVISION',
        'Division Schnelle Kräfte', 'DSK', 'Division Schnelle Kräfte (DSK)',
        'Rapid Forces Division', null, 0, 5,
        [['DSK', 'ACRONYM', 'de'], ['Division des forces rapides', 'FRENCH_NAME', 'fr'], ['Rapid Forces Division', 'ENGLISH_NAME', 'en'], ['Division Spezielle Operationen', 'FORMER_NAME', 'de'], ['DSO', 'FORMER_NAME', 'de']],
        ['LAND', 'AIR'], ['ELITE_CONVENTIONAL', 'SOF_COMMAND'], ['COMMAND'], ['AIRBORNE', 'AIR_ASSAULT'], 'bundeswehr-dsk');

    $U('de-kdo-sok', 'DE', 'de-heer', 'COMMAND',
        'Kommando Spezialkräfte', 'KSK', 'KSK',
        'Special Forces Command', 'de-dsk', 1, 10,
        [['Kommando Spezialkräfte', 'COMMON_NAME', 'de'], ['Commandement des forces spéciales (Allemagne)', 'FRENCH_NAME', 'fr'], ['Calw', 'COMMON_NAME', 'de']],
        ['LAND'], ['SOF'], ['COMBAT', 'COMMAND'], ['DIRECT_ACTION', 'SPECIAL_RECONNAISSANCE', 'HOSTAGE_RESCUE', 'COUNTER_TERRORISM'], 'bundeswehr-ksk');
    $dropAlias('de-kdo-sok', 'Kommando Spezialkräfte (KSK)');

    $U('de-ksk-hq', 'DE', 'de-heer', 'COMPONENT_COMMAND',
        'Stab Kommando Spezialkräfte', 'Stab KSK', 'KSK — Stab',
        'KSK Headquarters', 'de-kdo-sok', 2, 20,
        [['Stab KSK', 'SHORT_NAME', 'de']], ['LAND'], ['SOF_COMMAND'], ['COMMAND'], [], 'bundeswehr-ksk');

    $U('de-ksk-kompanien', 'DE', 'de-heer', 'COMPANY',
        'Kommandokräfte', 'Kommandokräfte', 'KSK — Kommandokräfte',
        'KSK commando forces', 'de-kdo-sok', 2, 30,
        [['Kommandokompanien', 'COMMON_NAME', 'de'], ['KSK Kommandokompanie', 'COMMON_NAME', 'de']],
        ['LAND'], ['SOF'], ['COMBAT'], ['DIRECT_ACTION', 'SPECIAL_RECONNAISSANCE'], 'bundeswehr-ksk');

    $U('de-ksk-unterstuetzung', 'DE', 'de-heer', 'SUPPORT_UNIT',
        'Unterstützungskräfte KSK', 'UstgKr KSK', 'KSK — Unterstützungskräfte',
        'KSK support forces', 'de-kdo-sok', 2, 35,
        [['Unterstützungskräfte', 'COMMON_NAME', 'de'], ['Sanitätseinsatzversorgungszentrum', 'COMMON_NAME', 'de']],
        ['LAND'], ['SOF_SUPPORT'], ['SUPPORT', 'COMMUNICATIONS', 'MEDICAL_SUPPORT', 'LOGISTICS'], [], 'bundeswehr-ksk');

    $U('de-llbrig1', 'DE', 'de-heer', 'BRIGADE',
        'Luftlandebrigade 1', 'LLBrig 1', 'Luftlandebrigade 1',
        '1st Airborne Brigade', 'de-dsk', 1, 50,
        [['LLBrig 1', 'SHORT_NAME', 'de'], ['Fallschirmjäger', 'NICKNAME', 'de'], ['Saarlandbrigade', 'NICKNAME', 'de']],
        ['LAND', 'AIR'], ['ELITE_CONVENTIONAL'], ['COMBAT', 'COMMAND'], ['AIRBORNE', 'AIR_ASSAULT'], 'bundeswehr-llbrig1');
    foreach ([
        ['de-fschjgrgt26', 'Fallschirmjägerregiment 26', 'FschJgRgt 26', 'Zweibrücken / Merzig', 51, [['Luftlandebrigade 26', 'FORMER_NAME', 'de']]],
        ['de-fschjgrgt31', 'Fallschirmjägerregiment 31', 'FschJgRgt 31', 'Seedorf', 52, [['Luftlandebrigade 31', 'FORMER_NAME', 'de']]],
    ] as [$code, $official, $short, $loc, $s, $extra]) {
        $U($code, 'DE', 'de-heer', 'REGIMENT',
            $official, $short, $official, null, 'de-llbrig1', 2, $s,
            array_merge([[$short, 'SHORT_NAME', 'de'], [$loc, 'COMMON_NAME', 'de']], $extra),
            ['LAND', 'AIR'], ['ELITE_CONVENTIONAL'], ['COMBAT'], ['AIRBORNE', 'AIR_ASSAULT'], 'bundeswehr-llbrig1');
    }

    // KSM : nom officiel = Kommando Spezialkräfte der Marine (Eckernförde, Einsatzflottille 1)
    $U('de-einsfltl1', 'DE', 'de-marine', 'COMMAND',
        'Einsatzflottille 1', 'EinsFltl 1', 'Einsatzflottille 1',
        '1st Flotilla', null, 0, 38,
        [['EinsFltl 1', 'SHORT_NAME', 'de']],
        ['MARITIME'], ['SPECIALIZED'], ['COMMAND'], [], 'bundeswehr-ksm');
    $U('de-ksm', 'DE', 'de-marine', 'COMMAND',
        'Kommando Spezialkräfte der Marine', 'KSM', 'KSM',
        'Naval Special Forces Command', 'de-einsfltl1', 1, 40,
        [['Kommando Spezialkräfte der Marine', 'COMMON_NAME', 'de'], ['Kampfschwimmer', 'NICKNAME', 'de'], ['Eckernförde', 'COMMON_NAME', 'de']],
        ['MARITIME', 'UNDERWATER'], ['SOF'], ['COMMAND', 'COMBAT'], ['COMBAT_DIVING', 'MARITIME_OPERATIONS', 'DIRECT_ACTION'], 'bundeswehr-ksm');
    $U('de-ksm-kompanien', 'DE', 'de-marine', 'COMPANY',
        'Kampfschwimmerkompanie', 'KSchwKp', 'KSM — Kampfschwimmerkompanie',
        'Combat Swimmer Company', 'de-ksm', 2, 50,
        [['Kampfschwimmerkompanie', 'COMMON_NAME', 'de'], ['Kampfschwimmer', 'NICKNAME', 'de']],
        ['MARITIME', 'UNDERWATER'], ['SOF'], ['COMBAT'], ['COMBAT_DIVING', 'UNDERWATER_OPERATIONS'], 'bundeswehr-ksm');
    $U('de-ksm-bootskp', 'DE', 'de-marine', 'COMPANY',
        'Spezialoperationen-Bootskompanie', 'SpezOp-BootsKp', 'KSM — Spezialoperationen-Bootskompanie',
        'Special Operations Boat Company', 'de-ksm', 2, 52,
        [], ['MARITIME'], ['SOF', 'SOF_SUPPORT'], ['SUPPORT', 'COMBAT'], ['MARITIME_OPERATIONS'], 'bundeswehr-ksm');
    $U('de-ksm-ustgkp', 'DE', 'de-marine', 'SUPPORT_UNIT',
        'Unterstützungskompanie KSM', 'UstgKp KSM', 'KSM — Unterstützungskompanie',
        'KSM Support Company', 'de-ksm', 2, 54,
        [], ['MARITIME'], ['SOF_SUPPORT'], ['SUPPORT', 'LOGISTICS'], [], 'bundeswehr-ksm');

    // =====================================================================
    // CORRECTIONS — BELGIQUE
    // =====================================================================
    $U('be-socom', 'BE', 'be-defence', 'JOINT_COMMAND',
        'Special Operations Command', 'SOCOM', 'Belgian SOCOM',
        'Belgian Special Operations Command', null, 0, 5,
        [['SOCOM', 'ACRONYM', 'en'], ['BELSOCOM', 'ACRONYM', 'en'], ['Commandement des opérations spéciales (Belgique)', 'FRENCH_NAME', 'fr']],
        ['LAND', 'AIR', 'MARITIME'], ['SOF_COMMAND'], ['COMMAND'], [], 'mil-be');
    $U('be-sor', 'BE', 'be-land', 'REGIMENT',
        'Special Operations Regiment', 'SOR', 'Régiment Opérations Spéciales (SOR)',
        'Special Operations Regiment', null, 0, 8,
        [['SOR', 'ACRONYM', 'en'], ['Régiment Opérations Spéciales', 'FRENCH_NAME', 'fr'], ['Regiment Speciale Operaties', 'ALTERNATIVE_SPELLING', 'nl'], ['Brigade Légère', 'FORMER_NAME', 'fr'], ['Light Brigade', 'FORMER_NAME', 'en'], ['Lichte Brigade', 'FORMER_NAME', 'nl'], ['Heverlee', 'COMMON_NAME', 'fr']],
        ['LAND'], ['SOF_COMMAND', 'ELITE_CONVENTIONAL'], ['COMMAND', 'COMBAT'], ['AIRBORNE'], 'mil-be');
    $U('be-sf-gp', 'BE', 'be-land', 'GROUP',
        'Special Forces Group', 'SF Gp', 'Special Forces Group (SF Gp)',
        'Special Forces Group', 'be-sor', 1, 10,
        [['SFG', 'ACRONYM', 'en']],
        ['LAND'], ['SOF'], ['COMBAT', 'COMMAND'], ['DIRECT_ACTION', 'SPECIAL_RECONNAISSANCE', 'MILITARY_ASSISTANCE'], 'mil-be');
    $U('be-sf-gp-hq', 'BE', 'be-land', 'COMPONENT_COMMAND',
        'SF Gp — État-major', 'SF Gp HQ', 'SF Gp — État-major',
        null, 'be-sf-gp', 2, 20, [], ['LAND'], ['SOF_COMMAND'], ['COMMAND'], [], 'mil-be');
    foreach ([['be-sf-gp-1st', '1re', 30], ['be-sf-gp-2nd', '2e', 40], ['be-sf-gp-3rd', '3e', 50]] as [$code, $ord, $s]) {
        $U($code, 'BE', 'be-land', 'COMPANY',
            'SF Gp — ' . $ord . ' compagnie opérationnelle', $ord . ' cie', 'SF Gp — ' . $ord . ' compagnie opérationnelle',
            null, 'be-sf-gp', 2, $s, [], ['LAND'], ['SOF'], ['COMBAT'], ['DIRECT_ACTION'], null);
    }
    // 2 Para n'existe plus : le bataillon est le 2e Bataillon Commando (Flawinne)
    $dropAlias('be-2para', '2 Para');
    $U('be-2para', 'BE', 'be-land', 'BATTALION',
        '2e Bataillon Commando', '2 Cdo', '2e Bataillon Commando',
        '2nd Commando Battalion', 'be-sor', 1, 70,
        [['2 Cdo', 'SHORT_NAME', 'fr'], ['2Cdo', 'ALTERNATIVE_SPELLING', 'fr'], ['2de Bataljon Commando', 'ALTERNATIVE_SPELLING', 'nl'], ['Flawinne', 'COMMON_NAME', 'fr'], ['Para-Commando', 'COMMON_NAME', 'fr']],
        ['LAND'], ['ELITE_CONVENTIONAL'], ['COMBAT'], ['AIRBORNE', 'SPECIAL_RECONNAISSANCE'], 'mil-be');
    $U('be-3para', 'BE', 'be-land', 'BATTALION',
        '3e Bataillon de Parachutistes', '3 Para', '3e Bataillon Parachutiste',
        '3rd Parachute Battalion', 'be-sor', 1, 80,
        [['3 Para', 'SHORT_NAME', 'fr'], ['3de Bataljon Parachutisten', 'ALTERNATIVE_SPELLING', 'nl'], ['Tielen', 'COMMON_NAME', 'fr'], ['Para-Commando', 'COMMON_NAME', 'fr']],
        ['LAND', 'AIR'], ['ELITE_CONVENTIONAL'], ['COMBAT'], ['AIRBORNE', 'MILITARY_FREE_FALL'], 'mil-be');
    $U('be-6gp-cis', 'BE', 'be-land', 'SUPPORT_UNIT',
        '6e Groupe CIS', '6 Gp CIS', '6e Groupe Communication & Information Systems',
        '6th CIS Group', 'be-sor', 1, 85,
        [['6 CIS', 'ALTERNATIVE_SPELLING', 'fr']],
        ['LAND'], ['SOF_SUPPORT'], ['COMMUNICATIONS'], ['COMMUNICATIONS'], 'mil-be');
    $U('be-ce-para', 'BE', 'be-land', 'TRAINING_UNIT',
        'Centre d’entraînement de parachutistes', 'CE Para', 'Centre d’entraînement de parachutistes (Schaffen)',
        'Parachute Training Centre', 'be-sor', 1, 90,
        [['Para School', 'COMMON_NAME', 'en'], ['Schaffen', 'COMMON_NAME', 'fr'], ['Trainingscentrum voor Parachutisten', 'ALTERNATIVE_SPELLING', 'nl']],
        ['LAND', 'AIR'], ['TRAINING'], ['TRAINING'], ['AIRBORNE', 'MILITARY_FREE_FALL'], 'mil-be');
    $U('be-ce-cdo', 'BE', 'be-land', 'TRAINING_UNIT',
        'Centre d’entraînement commando', 'CE Cdo', 'Centre d’entraînement commando (Marche-les-Dames)',
        'Commando Training Centre', 'be-sor', 1, 95,
        [['Marche-les-Dames', 'COMMON_NAME', 'fr'], ['Trainingscentrum Commando', 'ALTERNATIVE_SPELLING', 'nl']],
        ['LAND'], ['TRAINING'], ['TRAINING'], [], 'mil-be');
    // Ancienne Brigade Para-Commando (devenue Brigade légère en 2011 puis SOR en 2018)
    $U('be-para-cdo', 'BE', 'be-land', 'BRIGADE',
        'Brigade Para-Commando', 'Bde Para-Cdo', 'Brigade Para-Commando (historique)',
        'Para-Commando Brigade', null, 0, 990,
        [['Brigade Para-Commando', 'COMMON_NAME', 'fr']],
        ['LAND'], ['ELITE_CONVENTIONAL'], ['COMBAT'], ['AIRBORNE'], null);
    $setStatus('be-para-cdo', 'dissolved', null);

    // =====================================================================
    // CORRECTIONS — ESPAGNE
    // =====================================================================
    $U('es-mcoe', 'ES', 'es-emad', 'JOINT_COMMAND',
        'Mando Conjunto de Operaciones Especiales', 'MCOE', 'MCOE',
        'Joint Special Operations Command (Spain)', null, 0, 5,
        [['MCOE', 'ACRONYM', 'es'], ['NCOE', 'FORMER_NAME', 'es'], ['Commandement interarmées des opérations spéciales (Espagne)', 'FRENCH_NAME', 'fr'], ['Retamares', 'COMMON_NAME', 'es']],
        ['LAND', 'AIR', 'MARITIME'], ['SOF_COMMAND'], ['COMMAND'], [], 'emad-mcoe');

    $U('es-moe', 'ES', 'es-ejercito', 'COMMAND',
        'Mando de Operaciones Especiales', 'MOE', 'MOE',
        'Special Operations Command', null, 0, 10,
        [['Mando de Operaciones Especiales', 'COMMON_NAME', 'es'], ['Boinas Verdes', 'NICKNAME', 'es'], ['Rabasa', 'COMMON_NAME', 'es']],
        ['LAND'], ['SOF_COMMAND'], ['COMMAND'], [], 'ejercito-moe');

    $U('es-moe-gcg', 'ES', 'es-ejercito', 'SUPPORT_UNIT',
        'Grupo de Cuartel General del MOE', 'GCG MOE', 'MOE — Grupo de Cuartel General',
        null, 'es-moe', 1, 15,
        [['GCG', 'ACRONYM', 'es']],
        ['LAND'], ['SOF_SUPPORT'], ['COMMAND', 'SUPPORT', 'INTELLIGENCE'], ['INTELLIGENCE'], 'ejercito-moe');
    $U('es-goe-iii', 'ES', 'es-ejercito', 'GROUP',
        'Grupo de Operaciones Especiales «Valencia» III', 'GOE III', 'GOE «Valencia» III',
        'Special Operations Group III "Valencia"', 'es-moe', 1, 20,
        [['GOE III', 'SHORT_NAME', 'es'], ['GOE Valencia', 'COMMON_NAME', 'es'], ['Valencia III', 'COMMON_NAME', 'es']],
        ['LAND'], ['SOF'], ['COMBAT'], ['DIRECT_ACTION', 'SPECIAL_RECONNAISSANCE', 'MILITARY_ASSISTANCE'], 'ejercito-moe');
    $U('es-goe-iv', 'ES', 'es-ejercito', 'GROUP',
        'Grupo de Operaciones Especiales «Tercio del Ampurdán» IV', 'GOE IV', 'GOE «Tercio del Ampurdán» IV',
        'Special Operations Group IV "Tercio del Ampurdán"', 'es-moe', 1, 30,
        [['GOE IV', 'SHORT_NAME', 'es'], ['Tercio del Ampurdán', 'COMMON_NAME', 'es'], ['Tercio de Ampurdán', 'ALTERNATIVE_SPELLING', 'es']],
        ['LAND'], ['SOF'], ['COMBAT'], ['DIRECT_ACTION', 'SPECIAL_RECONNAISSANCE', 'MOUNTAIN_WARFARE'], 'ejercito-moe');
    $U('es-boel-xix', 'ES', 'es-ejercito', 'BATTALION',
        'Bandera de Operaciones Especiales «Caballero Legionario Maderal Oleaga» XIX', 'BOEL XIX', 'BOEL «Maderal Oleaga» XIX',
        'Legion Special Operations Battalion XIX', 'es-moe', 1, 40,
        [['BOEL', 'ACRONYM', 'es'], ['GOE XIX', 'FORMER_NAME', 'es'], ['Bandera de Operaciones Especiales de la Legión', 'COMMON_NAME', 'es'], ['Maderal Oleaga', 'COMMON_NAME', 'es']],
        ['LAND'], ['SOF'], ['COMBAT'], ['DIRECT_ACTION', 'SPECIAL_RECONNAISSANCE', 'AIRBORNE'], 'ejercito-moe');
    $U('es-moe-ulog', 'ES', 'es-ejercito', 'SUPPORT_UNIT',
        'Unidad Logística del MOE', 'ULOG MOE', 'MOE — Unidad Logística',
        null, 'es-moe', 1, 45,
        [], ['LAND'], ['SOF_SUPPORT'], ['LOGISTICS', 'SUPPORT'], [], 'ejercito-moe');
    $U('es-goe-ii', 'ES', 'es-ejercito', 'GROUP',
        'Grupo de Operaciones Especiales «Granada» II', 'GOE II', 'GOE «Granada» II (dissous)',
        null, 'es-moe', 1, 990,
        [['GOE II', 'SHORT_NAME', 'es'], ['GOE Granada', 'COMMON_NAME', 'es']],
        ['LAND'], ['SOF'], ['COMBAT'], ['DIRECT_ACTION'], 'ejercito-moe');
    $setStatus('es-goe-ii', 'dissolved', null);
    // Entrées erronées « BOE Órdenes I/II/III » : n'ont jamais existé → désactivées (supprimer les lignes du seed)
    foreach (['es-boe-i', 'es-boe-ii', 'es-boe-iii'] as $c) {
        $setStatus($c, 'inactive');
    }

    // Armada : UOE + UEBC fusionnées le 10 juin 2009 dans la FGNE
    $U('es-fgne', 'ES', 'es-armada', 'FORCE',
        'Fuerza de Guerra Naval Especial', 'FGNE', 'FGNE',
        'Spanish Naval Special Warfare Force', null, 0, 50,
        [['FGNE', 'ACRONYM', 'es'], ['Guerra Naval Especial', 'COMMON_NAME', 'es'], ['La Algameca', 'COMMON_NAME', 'es'], ['UOE', 'FORMER_NAME', 'es'], ['UEBC', 'FORMER_NAME', 'es']],
        ['MARITIME', 'UNDERWATER', 'LAND'], ['SOF'], ['COMBAT'], ['MARITIME_OPERATIONS', 'COMBAT_DIVING', 'DIRECT_ACTION', 'MARITIME_ASSAULT'], 'armada-es');
    $U('es-uoe', 'ES', 'es-armada', 'COMMAND',
        'Unidad de Operaciones Especiales', 'UOE', 'UOE — Armada (fusionnée dans la FGNE)',
        null, null, 0, 991,
        [], ['MARITIME'], ['SOF'], ['COMBAT'], ['MARITIME_OPERATIONS'], 'armada-es');
    $setStatus('es-uoe', 'dissolved', '2009-06-10');

    $U('es-ezapac', 'ES', 'es-aire', 'SQUADRON',
        'Escuadrón de Zapadores Paracaidistas', 'EZAPAC', 'EZAPAC',
        'Paratrooper Sapper Squadron', null, 0, 60,
        [['Escuadrón de Zapadores Paracaidistas', 'COMMON_NAME', 'es'], ['Alcantarilla', 'COMMON_NAME', 'es']],
        ['AIR', 'LAND'], ['SOF'], ['COMBAT', 'SUPPORT'], ['AIRBORNE', 'MILITARY_FREE_FALL', 'SPECIAL_RECONNAISSANCE', 'COMBAT_CONTROL', 'PERSONNEL_RECOVERY'], 'ejercitodelaire-es');

    // Guardia Civil : pas de "GOE" ; unités = UEI et GAR. Le GEO appartient à la Policía Nacional.
    $setStatus('es-grupo-oe', 'inactive');
    $U('es-gc-uei', 'ES', 'es-gc', 'GROUP',
        'Unidad Especial de Intervención', 'UEI', 'UEI — Guardia Civil',
        'Special Intervention Unit', null, 0, 70,
        [['UEI', 'ACRONYM', 'es'], ['Grupo de Intervención Inmediata', 'FORMER_NAME', 'es'], ['Valdemoro', 'COMMON_NAME', 'es']],
        ['LAND'], ['INTERVENTION'], ['COMBAT'], ['COUNTER_TERRORISM', 'HOSTAGE_RESCUE'], 'guardiacivil-es');
    $U('es-gc-uar', 'ES', 'es-gc', 'COMMAND',
        'Unidad de Acción Rural', 'UAR', 'UAR — Guardia Civil',
        'Rural Action Unit', null, 0, 72,
        [['UAR', 'ACRONYM', 'es']],
        ['LAND'], ['INTERVENTION'], ['COMMAND', 'TRAINING'], ['COUNTER_TERRORISM'], 'guardiacivil-es');
    $U('es-gc-gar', 'ES', 'es-gc', 'GROUP',
        'Grupo de Acción Rápida', 'GAR', 'GAR — Guardia Civil',
        'Rapid Action Group', 'es-gc-uar', 1, 73,
        [['GAR', 'ACRONYM', 'es'], ['Grupo Antiterrorista Rural', 'FORMER_NAME', 'es'], ['Logroño', 'COMMON_NAME', 'es']],
        ['LAND'], ['INTERVENTION'], ['COMBAT'], ['COUNTER_TERRORISM', 'MILITARY_ASSISTANCE'], 'guardiacivil-es');
    $U('es-pn-geo', 'ES', 'es-pn', 'GROUP',
        'Grupo Especial de Operaciones', 'GEO', 'GEO — Policía Nacional',
        'Special Operations Group (National Police)', null, 0, 80,
        [['GEO', 'ACRONYM', 'es'], ['GEOS', 'ALTERNATIVE_SPELLING', 'es']],
        ['LAND'], ['INTERVENTION'], ['COMBAT'], ['COUNTER_TERRORISM', 'HOSTAGE_RESCUE'], 'policia-es');

    $U('es-bripac', 'ES', 'es-ejercito', 'BRIGADE',
        'Brigada «Almogávares» VI de Paracaidistas', 'BRIPAC', 'BRIPAC «Almogávares» VI',
        'Paratrooper Brigade "Almogávares" VI', null, 0, 100,
        [['BRIPAC', 'ACRONYM', 'es'], ['Almogávares', 'NICKNAME', 'es'], ['Brigada Paracaidista', 'COMMON_NAME', 'es']],
        ['LAND', 'AIR'], ['ELITE_CONVENTIONAL'], ['COMBAT', 'COMMAND'], ['AIRBORNE'], 'ejercito-es');
    $U('es-brileg', 'ES', 'es-ejercito', 'BRIGADE',
        'Brigada «Rey Alfonso XIII» II de La Legión', 'BRILEG', 'BRILEG — La Legión',
        'Spanish Legion Brigade "Rey Alfonso XIII"', null, 0, 110,
        [['BRILEG', 'ACRONYM', 'es'], ['La Legión', 'COMMON_NAME', 'es'], ['Légion espagnole', 'FRENCH_NAME', 'fr'], ['Spanish Legion', 'ENGLISH_NAME', 'en']],
        ['LAND'], ['ELITE_CONVENTIONAL'], ['COMBAT', 'COMMAND'], [], 'ejercito-es');

    // =====================================================================
    // NOUVEAU — FRANCE conventionnel (élite milsim)
    // =====================================================================
    $U('fr-11bp', 'FR', 'fr-terre', 'BRIGADE',
        '11e brigade parachutiste', '11e BP', '11e brigade parachutiste',
        '11th Parachute Brigade', null, 0, 400,
        [['11e BP', 'SHORT_NAME', 'fr'], ['11 BP', 'ALTERNATIVE_SPELLING', 'fr'], ['Brigade parachutiste', 'COMMON_NAME', 'fr'], ['Paras', 'NICKNAME', 'fr'], ['11e DP', 'FORMER_NAME', 'fr'], ['11e division parachutiste', 'FORMER_NAME', 'fr']],
        ['LAND', 'AIR'], ['ELITE_CONVENTIONAL'], ['COMMAND', 'COMBAT'], ['AIRBORNE'], 'defense-gouv-11bp');
    $s = 401;
    foreach ([
        ['fr-1rcp', 'REGIMENT', '1er régiment de chasseurs parachutistes', '1er RCP', 'Pamiers', ['COMBAT'], ['AIRBORNE'], [['1RCP', 'ALTERNATIVE_SPELLING', 'fr']]],
        ['fr-2rep', 'REGIMENT', '2e régiment étranger de parachutistes', '2e REP', 'Calvi', ['COMBAT'], ['AIRBORNE', 'MILITARY_FREE_FALL', 'AMPHIBIOUS_OPERATIONS'], [['2REP', 'ALTERNATIVE_SPELLING', 'fr'], ['Légion étrangère', 'COMMON_NAME', 'fr']]],
        ['fr-3rpima', 'REGIMENT', '3e régiment de parachutistes d’infanterie de marine', '3e RPIMa', 'Carcassonne', ['COMBAT'], ['AIRBORNE', 'AMPHIBIOUS_OPERATIONS'], [['3RPIMA', 'ALTERNATIVE_SPELLING', 'fr']]],
        ['fr-8rpima', 'REGIMENT', '8e régiment de parachutistes d’infanterie de marine', '8e RPIMa', 'Castres', ['COMBAT'], ['AIRBORNE'], [['8RPIMA', 'ALTERNATIVE_SPELLING', 'fr']]],
        ['fr-1rhp', 'REGIMENT', '1er régiment de hussards parachutistes', '1er RHP', 'Tarbes', ['COMBAT', 'RECONNAISSANCE'], ['AIRBORNE'], [['1RHP', 'ALTERNATIVE_SPELLING', 'fr']]],
        ['fr-35rap', 'REGIMENT', '35e régiment d’artillerie parachutiste', '35e RAP', 'Tarbes', ['FIRE_SUPPORT'], ['AIRBORNE'], [['35RAP', 'ALTERNATIVE_SPELLING', 'fr']]],
        ['fr-17rgp', 'REGIMENT', '17e régiment du génie parachutiste', '17e RGP', 'Montauban', ['SUPPORT'], ['AIRBORNE', 'COMBAT_ENGINEERING'], [['17RGP', 'ALTERNATIVE_SPELLING', 'fr']]],
        ['fr-1rtp', 'REGIMENT', '1er régiment du train parachutiste', '1er RTP', 'Toulouse', ['LOGISTICS'], ['AIRBORNE'], [['1RTP', 'ALTERNATIVE_SPELLING', 'fr']]],
        ['fr-etap', 'TRAINING_UNIT', 'École des troupes aéroportées', 'ETAP', 'Pau', ['TRAINING'], ['AIRBORNE', 'MILITARY_FREE_FALL'], [['ETAP', 'ACRONYM', 'fr']]],
    ] as [$code, $etype, $official, $short, $loc, $fns, $specs, $extra]) {
        $U($code, 'FR', 'fr-terre', $etype,
            $official, $short, $official, null, 'fr-11bp', 1, $s++,
            array_merge([[$short, 'SHORT_NAME', 'fr'], [$loc, 'COMMON_NAME', 'fr']], $extra),
            ['LAND', 'AIR'], ['ELITE_CONVENTIONAL'], $fns, $specs, 'defense-gouv-11bp');
    }

    $U('fr-27bim', 'FR', 'fr-terre', 'BRIGADE',
        '27e brigade d’infanterie de montagne', '27e BIM', '27e brigade d’infanterie de montagne',
        '27th Mountain Infantry Brigade', null, 0, 420,
        [['27e BIM', 'SHORT_NAME', 'fr'], ['27 BIM', 'ALTERNATIVE_SPELLING', 'fr'], ['Troupes de montagne', 'COMMON_NAME', 'fr'], ['Chasseurs alpins', 'NICKNAME', 'fr']],
        ['LAND'], ['ELITE_CONVENTIONAL'], ['COMMAND', 'COMBAT'], ['MOUNTAIN_WARFARE', 'ARCTIC_WARFARE'], 'defense-gouv-terre');
    $s = 421;
    foreach ([
        ['fr-7bca', 'BATTALION', '7e bataillon de chasseurs alpins', '7e BCA', 'Varces', ['COMBAT'], ['MOUNTAIN_WARFARE'], [['7BCA', 'ALTERNATIVE_SPELLING', 'fr']]],
        ['fr-13bca', 'BATTALION', '13e bataillon de chasseurs alpins', '13e BCA', 'Barby (Chambéry)', ['COMBAT'], ['MOUNTAIN_WARFARE'], [['13BCA', 'ALTERNATIVE_SPELLING', 'fr']]],
        ['fr-27bca', 'BATTALION', '27e bataillon de chasseurs alpins', '27e BCA', 'Cran-Gevrier (Annecy)', ['COMBAT'], ['MOUNTAIN_WARFARE'], [['27BCA', 'ALTERNATIVE_SPELLING', 'fr']]],
        ['fr-4rch', 'REGIMENT', '4e régiment de chasseurs', '4e RCh', 'Gap', ['COMBAT', 'RECONNAISSANCE'], ['MOUNTAIN_WARFARE'], [['4RCH', 'ALTERNATIVE_SPELLING', 'fr']]],
        ['fr-93ram', 'REGIMENT', '93e régiment d’artillerie de montagne', '93e RAM', 'Varces', ['FIRE_SUPPORT'], ['MOUNTAIN_WARFARE'], [['93RAM', 'ALTERNATIVE_SPELLING', 'fr']]],
        ['fr-2reg', 'REGIMENT', '2e régiment étranger de génie', '2e REG', 'Saint-Christol', ['SUPPORT', 'COMBAT'], ['MOUNTAIN_WARFARE', 'COMBAT_ENGINEERING'], [['2REG', 'ALTERNATIVE_SPELLING', 'fr'], ['Légion étrangère', 'COMMON_NAME', 'fr']]],
    ] as [$code, $etype, $official, $short, $loc, $fns, $specs, $extra]) {
        $U($code, 'FR', 'fr-terre', $etype,
            $official, $short, $official, null, 'fr-27bim', 1, $s++,
            array_merge([[$short, 'SHORT_NAME', 'fr'], [$loc, 'COMMON_NAME', 'fr']], $extra),
            ['LAND'], ['ELITE_CONVENTIONAL'], $fns, $specs, 'defense-gouv-terre');
    }

    $U('fr-9bima', 'FR', 'fr-terre', 'BRIGADE',
        '9e brigade d’infanterie de marine', '9e BIMa', '9e brigade d’infanterie de marine',
        '9th Marine Infantry Brigade', null, 0, 440,
        [['9e BIMa', 'SHORT_NAME', 'fr'], ['9 BIMa', 'ALTERNATIVE_SPELLING', 'fr'], ['Troupes de marine', 'COMMON_NAME', 'fr'], ['La Coloniale', 'NICKNAME', 'fr'], ['9e DIMa', 'FORMER_NAME', 'fr']],
        ['LAND'], ['ELITE_CONVENTIONAL'], ['COMMAND', 'COMBAT'], ['AMPHIBIOUS_OPERATIONS'], 'defense-gouv-terre');
    $s = 441;
    foreach ([
        ['fr-2rima', '2e régiment d’infanterie de marine', '2e RIMa', 'Champagné (Le Mans)', ['COMBAT'], []],
        ['fr-3rima', '3e régiment d’infanterie de marine', '3e RIMa', 'Vannes', ['COMBAT'], ['AMPHIBIOUS_OPERATIONS']],
        ['fr-126ri', '126e régiment d’infanterie', '126e RI', 'Brive-la-Gaillarde', ['COMBAT'], []],
        ['fr-ricm', 'Régiment d’infanterie-chars de marine', 'RICM', 'Poitiers', ['COMBAT', 'RECONNAISSANCE'], []],
        ['fr-11rama', '11e régiment d’artillerie de marine', '11e RAMa', 'Saint-Aubin-du-Cormier', ['FIRE_SUPPORT'], []],
        ['fr-6rg', '6e régiment du génie', '6e RG', 'Angers', ['SUPPORT'], ['COMBAT_ENGINEERING']],
    ] as [$code, $official, $short, $loc, $fns, $specs]) {
        $U($code, 'FR', 'fr-terre', 'REGIMENT',
            $official, $short, $official, null, 'fr-9bima', 1, $s++,
            [[$short, 'SHORT_NAME', 'fr'], [str_replace(' ', '', $short), 'ALTERNATIVE_SPELLING', 'fr'], [$loc, 'COMMON_NAME', 'fr']],
            ['LAND'], ['ELITE_CONVENTIONAL'], $fns, $specs, 'defense-gouv-terre');
    }

    // Légion étrangère : 2e REP (11e BP) et 2e REG (27e BIM) sont rattachés ci-dessus à leur brigade.
    $U('fr-comle', 'FR', 'fr-terre', 'COMMAND',
        'Commandement de la Légion étrangère', 'COMLE', 'Commandement de la Légion étrangère',
        'French Foreign Legion Command', null, 0, 460,
        [['COMLE', 'ACRONYM', 'fr'], ['Légion étrangère', 'COMMON_NAME', 'fr'], ['French Foreign Legion', 'ENGLISH_NAME', 'en'], ['La Légion', 'NICKNAME', 'fr'], ['Képis blancs', 'NICKNAME', 'fr']],
        ['LAND'], ['ELITE_CONVENTIONAL'], ['COMMAND'], [], 'defense-gouv-terre');
    $U('fr-6blb', 'FR', 'fr-terre', 'BRIGADE',
        '6e brigade légère blindée', '6e BLB', '6e brigade légère blindée',
        '6th Light Armoured Brigade', null, 0, 470,
        [['6e BLB', 'SHORT_NAME', 'fr'], ['6 BLB', 'ALTERNATIVE_SPELLING', 'fr']],
        ['LAND'], ['ELITE_CONVENTIONAL'], ['COMMAND', 'COMBAT'], [], 'defense-gouv-terre');
    $s = 461;
    foreach ([
        ['fr-1re', 'fr-comle', 'REGIMENT', '1er régiment étranger', '1er RE', 'Aubagne', ['COMMAND', 'SUPPORT'], []],
        ['fr-4re', 'fr-comle', 'TRAINING_UNIT', '4e régiment étranger', '4e RE', 'Castelnaudary', ['TRAINING'], []],
        ['fr-3rei', 'fr-comle', 'REGIMENT', '3e régiment étranger d’infanterie', '3e REI', 'Kourou (Guyane)', ['COMBAT'], []],
        ['fr-dlem', 'fr-comle', 'DETACHMENT', 'Détachement de Légion étrangère de Mayotte', 'DLEM', 'Mayotte', ['COMBAT'], []],
        ['fr-2rei', 'fr-6blb', 'REGIMENT', '2e régiment étranger d’infanterie', '2e REI', 'Nîmes', ['COMBAT'], []],
        ['fr-1rec', 'fr-6blb', 'REGIMENT', '1er régiment étranger de cavalerie', '1er REC', 'Carpiagne', ['COMBAT', 'RECONNAISSANCE'], []],
        ['fr-1reg', 'fr-6blb', 'REGIMENT', '1er régiment étranger de génie', '1er REG', 'Laudun-l’Ardoise', ['SUPPORT', 'COMBAT'], ['COMBAT_ENGINEERING']],
        ['fr-13dble', 'fr-6blb', 'REGIMENT', '13e demi-brigade de Légion étrangère', '13e DBLE', 'La Cavalerie (Larzac)', ['COMBAT'], []],
    ] as [$code, $parent, $etype, $official, $short, $loc, $fns, $specs]) {
        $U($code, 'FR', 'fr-terre', $etype,
            $official, $short, $official, null, $parent, 1, $s++,
            [[$short, 'SHORT_NAME', 'fr'], [str_replace(' ', '', $short), 'ALTERNATIVE_SPELLING', 'fr'], [$loc, 'COMMON_NAME', 'fr'], ['Légion étrangère', 'COMMON_NAME', 'fr']],
            ['LAND'], ['ELITE_CONVENTIONAL'], $fns, $specs, 'defense-gouv-terre');
    }

    // =====================================================================
    // NOUVEAU — USA conventionnel
    // =====================================================================
    $usDiv = static function (string $code, string $official, string $short, string $display, int $sort, array $aliases, array $specs, array $children, string $svc = 'us-army', string $src = 'army-mil') use ($U): void {
        $U($code, 'US', $svc, 'DIVISION', $official, $short, $display, $official, null, 0, $sort,
            $aliases, ['LAND'], ['ELITE_CONVENTIONAL'], ['COMMAND', 'COMBAT'], $specs, $src);
        $i = 1;
        foreach ($children as [$ccode, $etype, $cofficial, $cshort, $caliases, $cspecs, $fns]) {
            $U($ccode, 'US', $svc, $etype, $cofficial, $cshort, $cofficial, $cofficial, $code, 1, $sort + $i++,
                array_merge([[$cshort, 'SHORT_NAME', 'en']], $caliases), ['LAND'], ['ELITE_CONVENTIONAL'], $fns, $cspecs, $src);
        }
    };

    $usDiv('us-82abn', '82nd Airborne Division', '82nd ABN', '82nd Airborne Division', 500,
        [['82nd Airborne', 'COMMON_NAME', 'en'], ['82nd ABN DIV', 'ALTERNATIVE_SPELLING', 'en'], ['All American', 'NICKNAME', 'en'], ['America’s Guard of Honor', 'NICKNAME', 'en'], ['82e division aéroportée', 'FRENCH_NAME', 'fr']],
        ['AIRBORNE'], [
            ['us-82abn-1bct', 'BRIGADE', '1st Brigade Combat Team, 82nd Airborne Division', '1/82 ABN', [['Devil Brigade', 'NICKNAME', 'en']], ['AIRBORNE'], ['COMBAT']],
            ['us-82abn-2bct', 'BRIGADE', '2nd Brigade Combat Team, 82nd Airborne Division', '2/82 ABN', [['Falcon Brigade', 'NICKNAME', 'en']], ['AIRBORNE'], ['COMBAT']],
            ['us-82abn-3bct', 'BRIGADE', '3rd Brigade Combat Team, 82nd Airborne Division', '3/82 ABN', [['Panther Brigade', 'NICKNAME', 'en']], ['AIRBORNE'], ['COMBAT']],
            ['us-82cab', 'BRIGADE', '82nd Combat Aviation Brigade', '82nd CAB', [['82nd CAB', 'ACRONYM', 'en']], ['HELICOPTER_OPERATIONS', 'AIR_ASSAULT'], ['AVIATION']],
        ]);
    $usDiv('us-101abn', '101st Airborne Division (Air Assault)', '101st ABN', '101st Airborne Division (Air Assault)', 510,
        [['101st Airborne', 'COMMON_NAME', 'en'], ['Screaming Eagles', 'NICKNAME', 'en'], ['101e division aéroportée', 'FRENCH_NAME', 'fr']],
        ['AIR_ASSAULT', 'AIRBORNE'], [
            ['us-101abn-1bct', 'BRIGADE', '1st Brigade Combat Team, 101st Airborne Division', '1/101 ABN', [['Bastogne', 'NICKNAME', 'en']], ['AIR_ASSAULT'], ['COMBAT']],
            ['us-101abn-2bct', 'BRIGADE', '2nd Brigade Combat Team, 101st Airborne Division', '2/101 ABN', [['Strike', 'NICKNAME', 'en'], ['2nd MBCT', 'COMMON_NAME', 'en']], ['AIR_ASSAULT'], ['COMBAT']],
            ['us-101abn-3bct', 'BRIGADE', '3rd Brigade Combat Team, 101st Airborne Division', '3/101 ABN', [['Rakkasans', 'NICKNAME', 'en']], ['AIR_ASSAULT'], ['COMBAT']],
            ['us-101cab', 'BRIGADE', '101st Combat Aviation Brigade', '101st CAB', [['Wings of Destiny', 'NICKNAME', 'en']], ['HELICOPTER_OPERATIONS', 'AIR_ASSAULT'], ['AVIATION']],
        ]);
    $usDiv('us-11abn', '11th Airborne Division', '11th ABN', '11th Airborne Division', 520,
        [['Arctic Angels', 'NICKNAME', 'en'], ['11e division aéroportée', 'FRENCH_NAME', 'fr']],
        ['AIRBORNE', 'ARCTIC_WARFARE'], [
            ['us-11abn-1bct', 'BRIGADE', '1st Infantry Brigade Combat Team, 11th Airborne Division', '1/11 ABN', [['Arctic Wolves', 'NICKNAME', 'en']], ['ARCTIC_WARFARE'], ['COMBAT']],
            ['us-11abn-2bct', 'BRIGADE', '2nd Infantry Brigade Combat Team (Airborne), 11th Airborne Division', '2/11 ABN', [['Spartan Brigade', 'NICKNAME', 'en'], ['4/25 ABN', 'FORMER_NAME', 'en']], ['AIRBORNE', 'ARCTIC_WARFARE'], ['COMBAT']],
        ]);
    $U('us-173abn', 'US', 'us-army', 'BRIGADE',
        '173rd Airborne Brigade', '173rd ABN', '173rd Airborne Brigade',
        '173rd Airborne Brigade', null, 0, 530,
        [['173rd ABN', 'SHORT_NAME', 'en'], ['Sky Soldiers', 'NICKNAME', 'en'], ['173rd IBCT (A)', 'ALTERNATIVE_SPELLING', 'en'], ['Vicenza', 'COMMON_NAME', 'en']],
        ['LAND', 'AIR'], ['ELITE_CONVENTIONAL'], ['COMBAT'], ['AIRBORNE'], 'army-mil');
    $usDiv('us-10mtn', '10th Mountain Division (Light Infantry)', '10th MTN', '10th Mountain Division', 540,
        [['10th Mountain', 'COMMON_NAME', 'en'], ['Climb to Glory', 'NICKNAME', 'en'], ['10e division de montagne', 'FRENCH_NAME', 'fr'], ['Fort Drum', 'COMMON_NAME', 'en']],
        ['MOUNTAIN_WARFARE', 'ARCTIC_WARFARE'], [
            ['us-10mtn-1bct', 'BRIGADE', '1st Brigade Combat Team, 10th Mountain Division', '1/10 MTN', [['Warrior Brigade', 'NICKNAME', 'en']], ['MOUNTAIN_WARFARE'], ['COMBAT']],
            ['us-10mtn-2bct', 'BRIGADE', '2nd Brigade Combat Team, 10th Mountain Division', '2/10 MTN', [['Commando Brigade', 'NICKNAME', 'en']], ['MOUNTAIN_WARFARE'], ['COMBAT']],
            ['us-10mtn-3bct', 'BRIGADE', '3rd Brigade Combat Team, 10th Mountain Division', '3/10 MTN', [['Patriots', 'NICKNAME', 'en'], ['Fort Johnson', 'COMMON_NAME', 'en']], ['MOUNTAIN_WARFARE'], ['COMBAT']],
            ['us-10cab', 'BRIGADE', '10th Combat Aviation Brigade', '10th CAB', [], ['HELICOPTER_OPERATIONS'], ['AVIATION']],
        ]);
    $usDiv('us-25id', '25th Infantry Division', '25th ID', '25th Infantry Division', 550,
        [['Tropic Lightning', 'NICKNAME', 'en'], ['25e division d’infanterie', 'FRENCH_NAME', 'fr'], ['Schofield Barracks', 'COMMON_NAME', 'en']],
        [], [
            ['us-25id-2bct', 'BRIGADE', '2nd Brigade Combat Team, 25th Infantry Division', '2/25 ID', [['Warrior Brigade (2/25)', 'NICKNAME', 'en']], [], ['COMBAT']],
            ['us-25id-3bct', 'BRIGADE', '3rd Brigade Combat Team, 25th Infantry Division', '3/25 ID', [['Broncos', 'NICKNAME', 'en']], [], ['COMBAT']],
            ['us-25cab', 'BRIGADE', '25th Combat Aviation Brigade', '25th CAB', [['Wings of Lightning', 'NICKNAME', 'en']], ['HELICOPTER_OPERATIONS'], ['AVIATION']],
        ]);
    $usDiv('us-1mardiv', '1st Marine Division', '1st MARDIV', '1st Marine Division', 560,
        [['1st MarDiv', 'ALTERNATIVE_SPELLING', 'en'], ['The Old Breed', 'NICKNAME', 'en'], ['Blue Diamond', 'NICKNAME', 'en'], ['Camp Pendleton', 'COMMON_NAME', 'en']],
        ['AMPHIBIOUS_OPERATIONS'], [
            ['us-1stmarines', 'REGIMENT', '1st Marine Regiment', '1st Marines', [], ['AMPHIBIOUS_OPERATIONS'], ['COMBAT']],
            ['us-5thmarines', 'REGIMENT', '5th Marine Regiment', '5th Marines', [], ['AMPHIBIOUS_OPERATIONS'], ['COMBAT']],
            ['us-7thmarines', 'REGIMENT', '7th Marine Regiment', '7th Marines', [], ['AMPHIBIOUS_OPERATIONS'], ['COMBAT']],
            ['us-11thmarines', 'REGIMENT', '11th Marine Regiment', '11th Marines', [['Cannon Cockers', 'NICKNAME', 'en']], [], ['FIRE_SUPPORT']],
            ['us-1recon', 'BATTALION', '1st Reconnaissance Battalion', '1st Recon Bn', [['Marine Recon', 'COMMON_NAME', 'en']], ['SPECIAL_RECONNAISSANCE', 'AMPHIBIOUS_OPERATIONS'], ['RECONNAISSANCE']],
        ], 'us-marines', 'marines-mil');
    $usDiv('us-2mardiv', '2nd Marine Division', '2nd MARDIV', '2nd Marine Division', 570,
        [['2nd MarDiv', 'ALTERNATIVE_SPELLING', 'en'], ['Follow Me', 'NICKNAME', 'en'], ['Camp Lejeune', 'COMMON_NAME', 'en']],
        ['AMPHIBIOUS_OPERATIONS'], [
            ['us-2ndmarines', 'REGIMENT', '2nd Marine Regiment', '2nd Marines', [], ['AMPHIBIOUS_OPERATIONS'], ['COMBAT']],
            ['us-6thmarines', 'REGIMENT', '6th Marine Regiment', '6th Marines', [], ['AMPHIBIOUS_OPERATIONS'], ['COMBAT']],
            ['us-10thmarines', 'REGIMENT', '10th Marine Regiment', '10th Marines', [], [], ['FIRE_SUPPORT']],
            ['us-2recon', 'BATTALION', '2nd Reconnaissance Battalion', '2nd Recon Bn', [['Marine Recon', 'COMMON_NAME', 'en']], ['SPECIAL_RECONNAISSANCE', 'AMPHIBIOUS_OPERATIONS'], ['RECONNAISSANCE']],
            ['us-8thmarines', 'REGIMENT', '8th Marine Regiment', '8th Marines', [], [], ['COMBAT']],
        ], 'us-marines', 'marines-mil');
    $setStatus('us-8thmarines', 'dissolved', '2021-01-28');

    // =====================================================================
    // NOUVEAU — ROYAUME-UNI (codes gb-)
    // =====================================================================
    $U('gb-uksf', 'GB', 'gb-defence', 'JOINT_COMMAND',
        'United Kingdom Special Forces', 'UKSF', 'UKSF',
        'United Kingdom Special Forces', null, 0, 10,
        [['UKSF', 'ACRONYM', 'en'], ['Director Special Forces', 'COMMON_NAME', 'en'], ['DSF', 'ACRONYM', 'en'], ['Forces spéciales britanniques', 'FRENCH_NAME', 'fr']],
        ['LAND', 'AIR', 'MARITIME'], ['SOF_COMMAND'], ['COMMAND'], [], 'gov-uk-mod');
    $U('gb-22sas', 'GB', 'gb-army', 'REGIMENT',
        '22 Special Air Service Regiment', '22 SAS', '22 SAS',
        '22 Special Air Service Regiment', 'gb-uksf', 1, 20,
        [['22 SAS', 'SHORT_NAME', 'en'], ['SAS', 'ACRONYM', 'en'], ['Special Air Service', 'COMMON_NAME', 'en'], ['The Regiment', 'NICKNAME', 'en'], ['Hereford', 'COMMON_NAME', 'en'], ['Who Dares Wins', 'NICKNAME', 'en']],
        ['LAND', 'AIR'], ['SOF'], ['COMBAT'], ['DIRECT_ACTION', 'COUNTER_TERRORISM', 'HOSTAGE_RESCUE', 'SPECIAL_RECONNAISSANCE', 'MILITARY_FREE_FALL'], 'gov-uk-mod');
    $U('gb-21sas', 'GB', 'gb-army', 'REGIMENT',
        '21 Special Air Service Regiment (Artists) (Reserve)', '21 SAS(R)', '21 SAS (Reserve)',
        '21 Special Air Service Regiment (Artists)', 'gb-uksf', 1, 21,
        [['21 SAS', 'SHORT_NAME', 'en'], ['Artists Rifles', 'NICKNAME', 'en']],
        ['LAND'], ['SOF'], ['COMBAT', 'RECONNAISSANCE'], ['SPECIAL_RECONNAISSANCE', 'MILITARY_ASSISTANCE'], 'gov-uk-mod');
    $U('gb-23sas', 'GB', 'gb-army', 'REGIMENT',
        '23 Special Air Service Regiment (Reserve)', '23 SAS(R)', '23 SAS (Reserve)',
        '23 Special Air Service Regiment', 'gb-uksf', 1, 22,
        [['23 SAS', 'SHORT_NAME', 'en']],
        ['LAND'], ['SOF'], ['COMBAT', 'RECONNAISSANCE'], ['SPECIAL_RECONNAISSANCE', 'MILITARY_ASSISTANCE'], 'gov-uk-mod');
    $U('gb-sbs', 'GB', 'gb-rn', 'SQUADRON',
        'Special Boat Service', 'SBS', 'SBS',
        'Special Boat Service', 'gb-uksf', 1, 23,
        [['SBS', 'ACRONYM', 'en'], ['Poole', 'COMMON_NAME', 'en'], ['By Strength and Guile', 'NICKNAME', 'en']],
        ['MARITIME', 'UNDERWATER', 'LAND'], ['SOF'], ['COMBAT'], ['MARITIME_ASSAULT', 'COMBAT_DIVING', 'COUNTER_TERRORISM', 'DIRECT_ACTION'], 'royalnavy-mod-uk');
    $U('gb-sbs-r', 'GB', 'gb-rn', 'SQUADRON',
        'Special Boat Service (Reserve)', 'SBS(R)', 'SBS (Reserve)',
        'Special Boat Service (Reserve)', 'gb-uksf', 1, 24,
        [['SBS(R)', 'ACRONYM', 'en']],
        ['MARITIME', 'LAND'], ['SOF'], ['COMBAT'], ['MARITIME_OPERATIONS'], 'royalnavy-mod-uk');
    $U('gb-srr', 'GB', 'gb-army', 'REGIMENT',
        'Special Reconnaissance Regiment', 'SRR', 'SRR',
        'Special Reconnaissance Regiment', 'gb-uksf', 1, 25,
        [['SRR', 'ACRONYM', 'en'], ['14 Intelligence Company', 'FORMER_NAME', 'en'], ['The Det', 'NICKNAME', 'en']],
        ['LAND'], ['SOF', 'RECONNAISSANCE'], ['RECONNAISSANCE', 'INTELLIGENCE'], ['SPECIAL_RECONNAISSANCE', 'INTELLIGENCE'], 'gov-uk-mod');
    $U('gb-18sigs', 'GB', 'gb-army', 'SUPPORT_UNIT',
        '18 (UKSF) Signal Regiment', '18 (UKSF) Sig Regt', '18 (UKSF) Signal Regiment',
        '18 (UKSF) Signal Regiment', 'gb-uksf', 1, 26,
        [['18 Signal Regiment', 'COMMON_NAME', 'en'], ['18 Sigs', 'NICKNAME', 'en']],
        ['LAND', 'CYBER'], ['SOF_SUPPORT'], ['COMMUNICATIONS'], ['COMMUNICATIONS', 'ELECTRONIC_WARFARE'], 'gov-uk-mod');
    $U('gb-sfsg', 'GB', 'gb-army', 'GROUP',
        'Special Forces Support Group', 'SFSG', 'SFSG',
        'Special Forces Support Group', 'gb-uksf', 1, 27,
        [['SFSG', 'ACRONYM', 'en'], ['1 PARA', 'COMMON_NAME', 'en'], ['1st Battalion, The Parachute Regiment', 'COMMON_NAME', 'en']],
        ['LAND', 'AIR'], ['SOF_SUPPORT', 'SOF'], ['COMBAT', 'SUPPORT'], ['DIRECT_ACTION', 'AIRBORNE', 'COUNTER_TERRORISM'], 'gov-uk-mod');
    $U('gb-jsfaw', 'GB', 'gb-defence', 'WING',
        'Joint Special Forces Aviation Wing', 'JSFAW', 'JSFAW',
        'Joint Special Forces Aviation Wing', 'gb-uksf', 1, 28,
        [['JSFAW', 'ACRONYM', 'en'], ['RAF Odiham', 'COMMON_NAME', 'en']],
        ['AIR'], ['SOF', 'AVIATION'], ['AVIATION'], ['SPECIAL_OPERATIONS_AVIATION', 'HELICOPTER_OPERATIONS'], 'raf-mod-uk');
    $U('gb-7sqn', 'GB', 'gb-raf', 'AIR_SQUADRON',
        'No. 7 Squadron RAF', '7 Sqn', 'No. 7 Squadron RAF',
        'No. 7 Squadron RAF', 'gb-jsfaw', 2, 29,
        [['7 Squadron', 'COMMON_NAME', 'en'], ['7 Sqn RAF', 'ALTERNATIVE_SPELLING', 'en']],
        ['AIR'], ['SOF', 'AVIATION'], ['AVIATION'], ['SPECIAL_OPERATIONS_AVIATION', 'HELICOPTER_OPERATIONS'], 'raf-mod-uk');
    $U('gb-658sqn', 'GB', 'gb-army', 'AIR_SQUADRON',
        '658 Squadron Army Air Corps', '658 Sqn AAC', '658 Squadron AAC',
        '658 Squadron Army Air Corps', 'gb-jsfaw', 2, 30,
        [['658 Sqn', 'SHORT_NAME', 'en'], ['8 Flight AAC', 'FORMER_NAME', 'en'], ['Credenhill', 'COMMON_NAME', 'en']],
        ['AIR'], ['SOF', 'AVIATION'], ['AVIATION'], ['SPECIAL_OPERATIONS_AVIATION', 'HELICOPTER_OPERATIONS', 'COUNTER_TERRORISM'], 'army-mod-uk');

    // Land Special Operations Force (depuis 2023) — a absorbé l'Army Special Operations Brigade (dissoute le 14/04/2026)
    $U('gb-lsof', 'GB', 'gb-army', 'COMMAND',
        'Land Special Operations Force', 'LSOF', 'Land Special Operations Force',
        'Land Special Operations Force', null, 0, 40,
        [['LSOF', 'ACRONYM', 'en']],
        ['LAND', 'INFORMATION'], ['SOF_COMMAND', 'SPECIALIZED'], ['COMMAND'], ['MILITARY_ASSISTANCE', 'INFORMATION_OPERATIONS'], 'army-mod-uk');
    $U('gb-rangers', 'GB', 'gb-army', 'REGIMENT',
        'The Ranger Regiment', 'RANGERS', 'Ranger Regiment',
        'The Ranger Regiment', 'gb-lsof', 1, 41,
        [['Ranger Regiment', 'COMMON_NAME', 'en'], ['Army Special Operations Ranger Regiment', 'COMMON_NAME', 'en'], ['Specialised Infantry', 'FORMER_NAME', 'en']],
        ['LAND'], ['SOF'], ['COMBAT'], ['MILITARY_ASSISTANCE', 'UNCONVENTIONAL_WARFARE', 'DIRECT_ACTION', 'SECURITY_FORCE_ASSISTANCE'], 'army-mod-uk');
    foreach ([['1', 'Belfast', 'ex-1 SCOTS'], ['2', 'Aldershot', 'ex-2 PWRR'], ['3', 'Pirbright', 'ex-2 LANCS'], ['4', 'Aldershot', 'ex-4 RIFLES']] as $i => [$n, $loc, $former]) {
        $U('gb-rangers-' . $n, 'GB', 'gb-army', 'BATTALION',
            $n . ($n === '1' ? 'st' : ($n === '2' ? 'nd' : ($n === '3' ? 'rd' : 'th'))) . ' Battalion, The Ranger Regiment', $n . ' RANGERS', $n . ' RANGERS',
            null, 'gb-rangers', 2, 42 + $i,
            [[$n . ' RANGERS', 'SHORT_NAME', 'en'], [$loc, 'COMMON_NAME', 'en'], [$former, 'FORMER_NAME', 'en']],
            ['LAND'], ['SOF'], ['COMBAT'], ['MILITARY_ASSISTANCE', 'SECURITY_FORCE_ASSISTANCE'], 'army-mod-uk');
    }
    $U('gb-asob', 'GB', 'gb-army', 'BRIGADE',
        'Army Special Operations Brigade', 'ASOB', 'Army Special Operations Brigade (dissoute)',
        'Army Special Operations Brigade', null, 0, 995,
        [['ASOB', 'ACRONYM', 'en'], ['Specialised Infantry Group', 'FORMER_NAME', 'en']],
        ['LAND'], ['SOF_COMMAND'], ['COMMAND'], [], 'army-mod-uk');
    $setStatus('gb-asob', 'dissolved', '2026-04-14');

    // 16 Air Assault Brigade Combat Team
    $U('gb-16aa', 'GB', 'gb-army', 'BRIGADE',
        '16 Air Assault Brigade Combat Team', '16 AA BCT', '16 Air Assault Brigade',
        '16 Air Assault Brigade Combat Team', null, 0, 60,
        [['16 Air Assault Brigade', 'COMMON_NAME', 'en'], ['16 AA Bde', 'ALTERNATIVE_SPELLING', 'en'], ['16X', 'NICKNAME', 'en'], ['Paras', 'NICKNAME', 'en'], ['Colchester', 'COMMON_NAME', 'en'], ['16e brigade d’assaut aérien', 'FRENCH_NAME', 'fr']],
        ['LAND', 'AIR'], ['ELITE_CONVENTIONAL'], ['COMMAND', 'COMBAT'], ['AIRBORNE', 'AIR_ASSAULT'], 'army-mod-uk');
    $s = 61;
    foreach ([
        ['gb-2para', 'BATTALION', '2nd Battalion, The Parachute Regiment', '2 PARA', ['COMBAT'], ['AIRBORNE', 'AIR_ASSAULT'], [['Parachute Regiment', 'COMMON_NAME', 'en']]],
        ['gb-3para', 'BATTALION', '3rd Battalion, The Parachute Regiment', '3 PARA', ['COMBAT'], ['AIRBORNE', 'AIR_ASSAULT'], [['Parachute Regiment', 'COMMON_NAME', 'en']]],
        ['gb-4para', 'BATTALION', '4th Battalion, The Parachute Regiment (Reserve)', '4 PARA', ['COMBAT'], ['AIRBORNE'], [['Parachute Regiment', 'COMMON_NAME', 'en']]],
        ['gb-pathfinders', 'DETACHMENT', 'Pathfinder Platoon', 'Pathfinders', ['RECONNAISSANCE'], ['SPECIAL_RECONNAISSANCE', 'AIRBORNE', 'MILITARY_FREE_FALL'], [['PF Pl', 'ALTERNATIVE_SPELLING', 'en']]],
        ['gb-7rha', 'REGIMENT', '7th Parachute Regiment Royal Horse Artillery', '7 PARA RHA', ['FIRE_SUPPORT'], ['AIRBORNE'], [['7 RHA', 'ALTERNATIVE_SPELLING', 'en']]],
        ['gb-23para-eng', 'REGIMENT', '23 Parachute Engineer Regiment', '23 Para Engr Regt', ['SUPPORT'], ['AIRBORNE', 'COMBAT_ENGINEERING'], [['23 Engr Regt', 'ALTERNATIVE_SPELLING', 'en']]],
        ['gb-13aasr', 'REGIMENT', '13 Air Assault Support Regiment, Royal Logistic Corps', '13 AASR', ['LOGISTICS'], ['AIR_ASSAULT'], [['13 AA Sp Regt', 'ALTERNATIVE_SPELLING', 'en']]],
        ['gb-16med', 'REGIMENT', '16 Medical Regiment', '16 Med Regt', ['MEDICAL_SUPPORT'], ['AIRBORNE'], [['16 Close Support Medical Regiment', 'FORMER_NAME', 'en']]],
        ['gb-216sigs', 'SQUADRON', '216 (Parachute) Signal Squadron', '216 Sig Sqn', ['COMMUNICATIONS'], ['AIRBORNE', 'COMMUNICATIONS'], []],
    ] as [$code, $etype, $official, $short, $fns, $specs, $extra]) {
        $U($code, 'GB', 'gb-army', $etype, $official, $short, $official, $official, 'gb-16aa', 1, $s++,
            array_merge([[$short, 'SHORT_NAME', 'en']], $extra),
            ['LAND', 'AIR'], [$code === 'gb-pathfinders' ? 'RECONNAISSANCE' : 'ELITE_CONVENTIONAL'], $fns, $specs, 'army-mod-uk');
    }

    // UK Commando Force (ex-3 Commando Brigade, renommée en juin 2026)
    $U('gb-ukcf', 'GB', 'gb-rm', 'BRIGADE',
        'United Kingdom Commando Force', 'UKCF', 'UK Commando Force',
        'United Kingdom Commando Force', null, 0, 80,
        [['UKCF', 'ACRONYM', 'en'], ['3 Commando Brigade', 'FORMER_NAME', 'en'], ['3 Cdo Bde', 'FORMER_NAME', 'en'], ['Royal Marines', 'COMMON_NAME', 'en'], ['Royal Marines Commandos', 'COMMON_NAME', 'en'], ['Bootnecks', 'NICKNAME', 'en'], ['Future Commando Force', 'COMMON_NAME', 'en']],
        ['MARITIME', 'LAND'], ['ELITE_CONVENTIONAL'], ['COMMAND', 'COMBAT'], ['AMPHIBIOUS_OPERATIONS', 'MARITIME_OPERATIONS', 'ARCTIC_WARFARE'], 'royalnavy-ukcf');
    $s = 81;
    foreach ([
        ['gb-40cdo', 'gb-rm', 'COMMANDO', '40 Commando Royal Marines', '40 Cdo', 'Taunton', ['COMBAT'], ['AMPHIBIOUS_OPERATIONS']],
        ['gb-42cdo', 'gb-rm', 'COMMANDO', '42 Commando Royal Marines', '42 Cdo', 'Bickleigh', ['COMBAT'], ['MARITIME_OPERATIONS', 'MARITIME_ASSAULT']],
        ['gb-43cdo', 'gb-rm', 'COMMANDO', '43 Commando Fleet Protection Group Royal Marines', '43 Cdo FP Gp', 'Faslane', ['COMBAT'], ['MARITIME_OPERATIONS', 'MARITIME_ASSAULT']],
        ['gb-45cdo', 'gb-rm', 'COMMANDO', '45 Commando Royal Marines', '45 Cdo', 'Arbroath', ['COMBAT'], ['AMPHIBIOUS_OPERATIONS', 'ARCTIC_WARFARE', 'MOUNTAIN_WARFARE']],
        ['gb-47cdo', 'gb-rm', 'COMMANDO', '47 Commando (Raiding Group) Royal Marines', '47 Cdo', 'Plymouth', ['COMBAT', 'SUPPORT'], ['MARITIME_OPERATIONS', 'AMPHIBIOUS_OPERATIONS']],
        ['gb-30cdo', 'gb-rm', 'COMMANDO', '30 Commando Information Exploitation Group Royal Marines', '30 Cdo IX Gp', 'Stonehouse', ['INTELLIGENCE', 'COMMUNICATIONS'], ['INTELLIGENCE', 'ELECTRONIC_WARFARE']],
        ['gb-29cdo-ra', 'gb-army', 'REGIMENT', '29 Commando Regiment Royal Artillery', '29 Cdo Regt RA', 'Plymouth', ['FIRE_SUPPORT'], ['AMPHIBIOUS_OPERATIONS']],
        ['gb-24cdo-re', 'gb-army', 'REGIMENT', '24 Commando Royal Engineers', '24 Cdo RE', 'Chivenor', ['SUPPORT'], ['COMBAT_ENGINEERING', 'AMPHIBIOUS_OPERATIONS']],
        ['gb-cdo-log', 'gb-rm', 'SUPPORT_UNIT', 'Commando Logistic Regiment Royal Marines', 'CLR', 'Chivenor', ['LOGISTICS', 'MEDICAL_SUPPORT'], []],
    ] as [$code, $svc, $etype, $official, $short, $loc, $fns, $specs]) {
        $U($code, 'GB', $svc, $etype, $official, $short, $official, $official, 'gb-ukcf', 1, $s++,
            [[$short, 'SHORT_NAME', 'en'], [$loc, 'COMMON_NAME', 'en']],
            ['MARITIME', 'LAND'], ['ELITE_CONVENTIONAL'], $fns, $specs, 'royalnavy-mod-uk');
    }

    // =====================================================================
    // NOUVEAU — CANADA
    // =====================================================================
    $U('ca-cansofcom', 'CA', 'ca-caf', 'JOINT_COMMAND',
        'Canadian Special Operations Forces Command', 'CANSOFCOM', 'CANSOFCOM / COMFOSCAN',
        'Canadian Special Operations Forces Command', null, 0, 10,
        [['CANSOFCOM', 'ACRONYM', 'en'], ['COMFOSCAN', 'ACRONYM', 'fr'], ['Commandement des Forces d’opérations spéciales du Canada', 'FRENCH_NAME', 'fr']],
        ['LAND', 'AIR', 'MARITIME'], ['SOF_COMMAND'], ['COMMAND'], [], 'canada-cansofcom');
    foreach ([
        ['ca-jtf2', 'GROUP', 'Joint Task Force 2', 'JTF 2', 'Deuxième Force opérationnelle interarmées', 'FOI 2', ['LAND', 'MARITIME'], ['SOF'], ['COMBAT'], ['COUNTER_TERRORISM', 'HOSTAGE_RESCUE', 'DIRECT_ACTION', 'SPECIAL_RECONNAISSANCE'], [['JTF2', 'ALTERNATIVE_SPELLING', 'en']]],
        ['ca-csor', 'REGIMENT', 'Canadian Special Operations Regiment', 'CSOR', 'Régiment d’opérations spéciales du Canada', 'ROSC', ['LAND'], ['SOF'], ['COMBAT'], ['DIRECT_ACTION', 'MILITARY_ASSISTANCE', 'SPECIAL_RECONNAISSANCE', 'AIRBORNE'], [['Petawawa', 'COMMON_NAME', 'en']]],
        ['ca-427soas', 'AIR_SQUADRON', '427 Special Operations Aviation Squadron', '427 SOAS', '427e Escadron d’opérations spéciales d’aviation', '427 EOSA', ['AIR'], ['SOF', 'AVIATION'], ['AVIATION'], ['SPECIAL_OPERATIONS_AVIATION', 'HELICOPTER_OPERATIONS'], [['427 Tactical Helicopter Squadron', 'FORMER_NAME', 'en'], ['427 Sqn', 'ALTERNATIVE_SPELLING', 'en']]],
        ['ca-cjiru', 'SUPPORT_UNIT', 'Canadian Joint Incident Response Unit — CBRN', 'CJIRU', 'Unité interarmées d’intervention du Canada — NRBC', 'UIIC', ['LAND'], ['SOF_SUPPORT', 'SPECIALIZED'], ['SUPPORT'], ['CBRN_DEFENSE'], [['CJIRU-CBRN', 'ALTERNATIVE_SPELLING', 'en'], ['JNBCD Coy', 'FORMER_NAME', 'en'], ['Trenton', 'COMMON_NAME', 'en']]],
        ['ca-csotc', 'TRAINING_UNIT', 'Canadian Special Operations Training Centre', 'CSOTC', 'Centre d’instruction des opérations spéciales du Canada', 'CIOSC', ['LAND'], ['TRAINING', 'SOF_SUPPORT'], ['TRAINING'], [], []],
    ] as $i => [$code, $etype, $official, $short, $fr, $frShort, $doms, $cls, $fns, $specs, $extra]) {
        $U($code, 'CA', 'ca-caf', $etype, $official, $short, $short, $official, 'ca-cansofcom', 1, 20 + $i * 10,
            array_merge([[$short, 'ACRONYM', 'en'], [$fr, 'FRENCH_NAME', 'fr'], [$frShort, 'ACRONYM', 'fr']], $extra),
            $doms, $cls, $fns, $specs, 'canada-cansofcom');
    }

    // =====================================================================
    // NOUVEAU — AUSTRALIE
    // =====================================================================
    $U('au-socomd', 'AU', 'au-army', 'COMMAND',
        'Special Operations Command', 'SOCOMD', 'SOCOMD',
        'Australian Special Operations Command', null, 0, 10,
        [['SOCOMD', 'ACRONYM', 'en'], ['SOCAUST', 'ACRONYM', 'en'], ['Commandement des opérations spéciales australien', 'FRENCH_NAME', 'fr']],
        ['LAND', 'MARITIME', 'AIR'], ['SOF_COMMAND'], ['COMMAND'], [], 'defence-gov-au-socomd');
    foreach ([
        ['au-sasr', 'REGIMENT', 'Special Air Service Regiment', 'SASR', ['SOF'], ['COMBAT'], ['DIRECT_ACTION', 'SPECIAL_RECONNAISSANCE', 'COUNTER_TERRORISM', 'HOSTAGE_RESCUE'], [['Australian SAS', 'COMMON_NAME', 'en'], ['TAG-West', 'CODE_NAME', 'en'], ['Perth', 'COMMON_NAME', 'en'], ['Campbell Barracks', 'COMMON_NAME', 'en']]],
        ['au-1cdo', 'REGIMENT', '1st Commando Regiment', '1 Cdo Regt', ['SOF'], ['COMBAT'], ['UNCONVENTIONAL_WARFARE', 'DIRECT_ACTION', 'AMPHIBIOUS_OPERATIONS'], [['1 CDO', 'ALTERNATIVE_SPELLING', 'en']]],
        ['au-2cdo', 'REGIMENT', '2nd Commando Regiment', '2 Cdo Regt', ['SOF'], ['COMBAT'], ['DIRECT_ACTION', 'COUNTER_TERRORISM', 'HOSTAGE_RESCUE', 'AIRBORNE'], [['2 CDO', 'ALTERNATIVE_SPELLING', 'en'], ['TAG-East', 'CODE_NAME', 'en'], ['4 RAR (Commando)', 'FORMER_NAME', 'en'], ['Holsworthy', 'COMMON_NAME', 'en']]],
        ['au-soer', 'REGIMENT', 'Special Operations Engineer Regiment', 'SOER', ['SOF_SUPPORT'], ['SUPPORT'], ['CBRN_DEFENSE', 'COMBAT_ENGINEERING'], [['Incident Response Regiment', 'FORMER_NAME', 'en'], ['IRR', 'FORMER_NAME', 'en']]],
        ['au-solsqn', 'SUPPORT_UNIT', 'Special Operations Logistic Squadron', 'SOLS', ['SOF_SUPPORT'], ['LOGISTICS'], [], []],
    ] as $i => [$code, $etype, $official, $short, $cls, $fns, $specs, $extra]) {
        $U($code, 'AU', 'au-army', $etype, $official, $short, $official, $official, 'au-socomd', 1, 20 + $i * 10,
            array_merge([[$short, 'ACRONYM', 'en']], $extra),
            ['LAND'], $cls, $fns, $specs, 'defence-gov-au-socomd');
    }

    // =====================================================================
    // NOUVEAU — POLOGNE
    // =====================================================================
    $U('pl-dkws', 'PL', 'pl-ws', 'COMPONENT_COMMAND',
        'Dowództwo Komponentu Wojsk Specjalnych', 'DKWS', 'DKWS',
        'Special Forces Component Command', null, 0, 10,
        [['DKWS', 'ACRONYM', 'pl'], ['Wojska Specjalne', 'COMMON_NAME', 'pl'], ['Centrum Operacji Specjalnych', 'FORMER_NAME', 'pl'], ['Commandement de la composante forces spéciales (Pologne)', 'FRENCH_NAME', 'fr']],
        ['LAND', 'MARITIME', 'AIR'], ['SOF_COMMAND'], ['COMMAND'], [], 'wojsko-polskie');
    foreach ([
        ['pl-grom', 'Jednostka Wojskowa GROM im. Cichociemnych Spadochroniarzy Armii Krajowej', 'JW GROM', 'JW 2305', 'Warszawa / Gdańsk', ['LAND', 'MARITIME'], ['SOF'], ['COMBAT'], ['COUNTER_TERRORISM', 'HOSTAGE_RESCUE', 'DIRECT_ACTION', 'MARITIME_ASSAULT'], [['GROM', 'ACRONYM', 'pl'], ['Grupa Reagowania Operacyjno-Manewrowego', 'COMMON_NAME', 'pl']]],
        ['pl-jwk', 'Jednostka Wojskowa Komandosów', 'JWK', 'JW 4101', 'Lubliniec', ['LAND'], ['SOF'], ['COMBAT'], ['DIRECT_ACTION', 'SPECIAL_RECONNAISSANCE', 'MILITARY_ASSISTANCE', 'AIRBORNE'], [['JW Komandosów', 'COMMON_NAME', 'pl'], ['1 Pułk Specjalny Komandosów', 'FORMER_NAME', 'pl']]],
        ['pl-formoza', 'Jednostka Wojskowa Formoza', 'JW Formoza', 'JW 4026', 'Gdynia', ['MARITIME', 'UNDERWATER'], ['SOF'], ['COMBAT'], ['MARITIME_ASSAULT', 'COMBAT_DIVING', 'MARITIME_OPERATIONS'], [['Formoza', 'COMMON_NAME', 'pl']]],
        ['pl-agat', 'Jednostka Wojskowa Agat', 'JW Agat', 'JW 3940', 'Gliwice', ['LAND'], ['SOF'], ['COMBAT'], ['DIRECT_ACTION', 'AIRBORNE'], [['Agat', 'COMMON_NAME', 'pl']]],
        ['pl-nil', 'Jednostka Wojskowa Nil', 'JW Nil', 'JW 4724', 'Kraków', ['LAND', 'INFORMATION'], ['SOF_SUPPORT'], ['SUPPORT', 'INTELLIGENCE', 'COMMUNICATIONS', 'LOGISTICS'], ['INTELLIGENCE', 'COMMUNICATIONS'], [['Nil', 'COMMON_NAME', 'pl']]],
    ] as $i => [$code, $official, $short, $num, $loc, $doms, $cls, $fns, $specs, $extra]) {
        $U($code, 'PL', 'pl-ws', 'GROUP', $official, $short, $short, null, 'pl-dkws', 1, 20 + $i * 10,
            array_merge([[$short, 'SHORT_NAME', 'pl'], [$num, 'CODE_NAME', 'pl'], [$loc, 'COMMON_NAME', 'pl']], $extra),
            $doms, $cls, $fns, $specs, 'wojsko-polskie');
    }
    $U('pl-7eds', 'PL', 'pl-sp', 'AIR_SQUADRON',
        '7 Eskadra Działań Specjalnych', '7 EDS', '7 EDS',
        '7th Special Operations Squadron', null, 0, 80,
        [['7 EDS', 'ACRONYM', 'pl'], ['7. Eskadra Działań Specjalnych', 'ALTERNATIVE_SPELLING', 'pl'], ['Powidz', 'COMMON_NAME', 'pl'], ['PJOS', 'COMMON_NAME', 'pl']],
        ['AIR'], ['SOF_SUPPORT', 'AVIATION'], ['AVIATION'], ['SPECIAL_OPERATIONS_AVIATION', 'HELICOPTER_OPERATIONS'], 'wojsko-polskie');

    // =====================================================================
    // NOUVEAU — ITALIE
    // =====================================================================
    $U('it-cofs', 'IT', 'it-difesa', 'JOINT_COMMAND',
        'Comando interforze per le Operazioni delle Forze Speciali', 'COFS', 'COFS',
        'Joint Special Forces Operations Command', null, 0, 10,
        [['COFS', 'ACRONYM', 'it'], ['Commandement interarmées des opérations spéciales (Italie)', 'FRENCH_NAME', 'fr'], ['Centocelle', 'COMMON_NAME', 'it']],
        ['LAND', 'AIR', 'MARITIME'], ['SOF_COMMAND'], ['COMMAND'], [], 'difesa-it');
    $U('it-comfose', 'IT', 'it-esercito', 'COMPONENT_COMMAND',
        'Comando delle Forze Speciali dell’Esercito', 'COMFOSE', 'COMFOSE',
        'Italian Army Special Forces Command', 'it-cofs', 1, 20,
        [['COMFOSE', 'ACRONYM', 'it'], ['Forze Speciali dell’Esercito', 'COMMON_NAME', 'it']],
        ['LAND'], ['SOF_COMMAND'], ['COMMAND'], [], 'esercito-it');
    foreach ([
        ['it-9colmoschin', 'REGIMENT', '9° Reggimento d’assalto paracadutisti «Col Moschin»', '9° Col Moschin', ['SOF'], ['COMBAT'], ['DIRECT_ACTION', 'COUNTER_TERRORISM', 'HOSTAGE_RESCUE', 'SPECIAL_RECONNAISSANCE', 'MILITARY_FREE_FALL'], [['Col Moschin', 'COMMON_NAME', 'it'], ['9° Reggimento', 'SHORT_NAME', 'it'], ['Incursori', 'NICKNAME', 'it'], ['Livorno', 'COMMON_NAME', 'it']]],
        ['it-4alpar', 'REGIMENT', '4° Reggimento alpini paracadutisti «Monte Cervino»', '4° Alpini Paracadutisti', ['SOF'], ['COMBAT'], ['DIRECT_ACTION', 'MOUNTAIN_WARFARE', 'AIRBORNE', 'SPECIAL_RECONNAISSANCE'], [['Ranger', 'NICKNAME', 'it'], ['Monte Cervino', 'COMMON_NAME', 'it'], ['4° Rgt Alpini Par', 'ALTERNATIVE_SPELLING', 'it']]],
        ['it-185rrao', 'REGIMENT', '185° Reggimento paracadutisti ricognizione acquisizione obiettivi «Folgore»', '185° RRAO', ['SOF', 'RECONNAISSANCE'], ['RECONNAISSANCE', 'FIRE_SUPPORT'], ['SPECIAL_RECONNAISSANCE', 'CLOSE_AIR_SUPPORT', 'AIRBORNE'], [['RRAO', 'ACRONYM', 'it'], ['Reggimento Ricognizione Acquisizione Obiettivi', 'COMMON_NAME', 'it']]],
        ['it-28pavia', 'REGIMENT', '28° Reggimento «Pavia»', '28° Pavia', ['SOF_SUPPORT', 'SPECIALIZED'], ['INFORMATION_OPERATIONS', 'PSYCHOLOGICAL_OPERATIONS'], ['PSYCHOLOGICAL_OPERATIONS', 'INFORMATION_OPERATIONS'], [['Reggimento comunicazioni operative', 'COMMON_NAME', 'it']]],
    ] as $i => [$code, $etype, $official, $short, $cls, $fns, $specs, $extra]) {
        $U($code, 'IT', 'it-esercito', $etype, $official, $short, $short, null, 'it-comfose', 2, 30 + $i,
            array_merge([[$short, 'SHORT_NAME', 'it']], $extra),
            ['LAND'], $cls, $fns, $specs, 'esercito-it');
    }
    $U('it-3reos', 'IT', 'it-esercito', 'REGIMENT',
        '3° Reggimento elicotteri per operazioni speciali «Aldebaran»', '3° REOS', '3° REOS «Aldebaran»',
        '3rd Special Operations Helicopter Regiment "Aldebaran"', null, 0, 40,
        [['3° REOS', 'SHORT_NAME', 'it'], ['REOS', 'ACRONYM', 'it'], ['Aldebaran', 'NICKNAME', 'it'], ['26° Gruppo Squadroni «Giove»', 'COMMON_NAME', 'it'], ['Viterbo', 'COMMON_NAME', 'it']],
        ['AIR'], ['SOF_SUPPORT', 'AVIATION'], ['AVIATION'], ['SPECIAL_OPERATIONS_AVIATION', 'HELICOPTER_OPERATIONS'], 'esercito-it');
    $U('it-comsubin', 'IT', 'it-marina', 'COMMAND',
        'Comando Raggruppamento Subacquei e Incursori «Teseo Tesei»', 'COMSUBIN', 'COMSUBIN',
        'Navy Divers and Raiders Group Command', null, 0, 50,
        [['COMSUBIN', 'ACRONYM', 'it'], ['Varignano', 'COMMON_NAME', 'it'], ['Teseo Tesei', 'COMMON_NAME', 'it']],
        ['MARITIME', 'UNDERWATER'], ['SOF_COMMAND'], ['COMMAND'], ['COMBAT_DIVING'], 'marina-it');
    $U('it-goi', 'IT', 'it-marina', 'GROUP',
        'Gruppo Operativo Incursori', 'GOI', 'GOI',
        'Raiders Operational Group', 'it-comsubin', 1, 51,
        [['GOI', 'ACRONYM', 'it'], ['Incursori di Marina', 'COMMON_NAME', 'it']],
        ['MARITIME', 'UNDERWATER', 'LAND'], ['SOF'], ['COMBAT'], ['COMBAT_DIVING', 'MARITIME_ASSAULT', 'DIRECT_ACTION', 'COUNTER_TERRORISM'], 'marina-it');
    $U('it-gos', 'IT', 'it-marina', 'GROUP',
        'Gruppo Operativo Subacquei', 'GOS', 'GOS',
        'Divers Operational Group', 'it-comsubin', 1, 52,
        [['GOS', 'ACRONYM', 'it'], ['Palombari', 'NICKNAME', 'it']],
        ['MARITIME', 'UNDERWATER'], ['SPECIALIZED'], ['SUPPORT'], ['UNDERWATER_OPERATIONS'], 'marina-it');
    $U('it-17stormo', 'IT', 'it-am', 'WING',
        '17° Stormo Incursori', '17° Stormo', '17° Stormo Incursori',
        '17th Raiders Wing', null, 0, 60,
        [['17° Stormo', 'SHORT_NAME', 'it'], ['Incursori dell’Aeronautica', 'COMMON_NAME', 'it'], ['Furbara', 'COMMON_NAME', 'it']],
        ['AIR', 'LAND'], ['SOF'], ['COMBAT'], ['DIRECT_ACTION', 'COMBAT_CONTROL', 'PERSONNEL_RECOVERY', 'SPECIAL_RECONNAISSANCE'], 'aeronautica-it');
    $U('it-cc-2brimob', 'IT', 'it-cc', 'BRIGADE',
        '2ª Brigata Mobile Carabinieri', '2ª Brigata Mobile', '2ª Brigata Mobile Carabinieri',
        '2nd Carabinieri Mobile Brigade', null, 0, 70,
        [['2ª BRIMOB', 'ALTERNATIVE_SPELLING', 'it']],
        ['LAND'], ['ELITE_CONVENTIONAL'], ['COMMAND'], [], 'carabinieri-it');
    $U('it-gis', 'IT', 'it-cc', 'GROUP',
        'Gruppo di Intervento Speciale', 'GIS', 'GIS — Carabinieri',
        'Special Intervention Group', 'it-cc-2brimob', 1, 71,
        [['GIS', 'ACRONYM', 'it']],
        ['LAND'], ['SOF', 'INTERVENTION'], ['COMBAT'], ['COUNTER_TERRORISM', 'HOSTAGE_RESCUE', 'DIRECT_ACTION'], 'carabinieri-it');
    $U('it-tuscania', 'IT', 'it-cc', 'REGIMENT',
        '1° Reggimento Carabinieri Paracadutisti «Tuscania»', 'Tuscania', '1° Rgt CC Par «Tuscania»',
        '1st Carabinieri Paratroopers Regiment "Tuscania"', 'it-cc-2brimob', 1, 72,
        [['Tuscania', 'COMMON_NAME', 'it'], ['1° Tuscania', 'SHORT_NAME', 'it']],
        ['LAND'], ['ELITE_CONVENTIONAL'], ['COMBAT'], ['AIRBORNE'], 'carabinieri-it');
    $U('it-folgore', 'IT', 'it-esercito', 'BRIGADE',
        'Brigata paracadutisti «Folgore»', 'Folgore', 'Brigata paracadutisti Folgore',
        'Paratroopers Brigade "Folgore"', null, 0, 80,
        [['Folgore', 'COMMON_NAME', 'it'], ['Brigade Folgore', 'FRENCH_NAME', 'fr']],
        ['LAND', 'AIR'], ['ELITE_CONVENTIONAL'], ['COMMAND', 'COMBAT'], ['AIRBORNE'], 'esercito-it');
    foreach ([
        ['it-183nembo', '183° Reggimento paracadutisti «Nembo»', '183° Nembo'],
        ['it-186par', '186° Reggimento paracadutisti «Folgore»', '186° Rgt Par'],
        ['it-187par', '187° Reggimento paracadutisti «Folgore»', '187° Rgt Par'],
    ] as $i => [$code, $official, $short]) {
        $U($code, 'IT', 'it-esercito', 'REGIMENT', $official, $short, $official, null, 'it-folgore', 1, 81 + $i,
            [[$short, 'SHORT_NAME', 'it']], ['LAND', 'AIR'], ['ELITE_CONVENTIONAL'], ['COMBAT'], ['AIRBORNE'], 'esercito-it');
    }

    // =====================================================================
    // NOUVEAU — PAYS-BAS
    // =====================================================================
    $U('nl-socom', 'NL', 'nl-defensie', 'JOINT_COMMAND',
        'Netherlands Special Operations Command', 'NLD SOCOM', 'NLD SOCOM',
        'Netherlands Special Operations Command', null, 0, 10,
        [['NLD SOCOM', 'ACRONYM', 'en'], ['NLDSOCOM', 'ALTERNATIVE_SPELLING', 'en'], ['Commandement des opérations spéciales néerlandais', 'FRENCH_NAME', 'fr']],
        ['LAND', 'MARITIME', 'AIR'], ['SOF_COMMAND'], ['COMMAND'], [], 'defensie-nlsocom');
    $U('nl-kct', 'NL', 'nl-kl', 'REGIMENT',
        'Korps Commandotroepen', 'KCT', 'KCT',
        'Netherlands Army Commando Corps', 'nl-socom', 1, 20,
        [['KCT', 'ACRONYM', 'nl'], ['Korps Commandotroepen', 'COMMON_NAME', 'nl'], ['Groene baretten', 'NICKNAME', 'nl'], ['Roosendaal', 'COMMON_NAME', 'nl']],
        ['LAND'], ['SOF'], ['COMBAT'], ['DIRECT_ACTION', 'SPECIAL_RECONNAISSANCE', 'MILITARY_ASSISTANCE'], 'defensie-nlsocom');
    $U('nl-nlmarsof', 'NL', 'nl-km', 'GROUP',
        'Netherlands Maritime Special Operations Forces', 'NLMARSOF', 'NLMARSOF',
        'Netherlands Maritime Special Operations Forces', 'nl-socom', 1, 30,
        [['NLMARSOF', 'ACRONYM', 'en'], ['MARSOF', 'ACRONYM', 'en'], ['Unit Interventie Mariniers', 'FORMER_NAME', 'nl'], ['UIM', 'FORMER_NAME', 'nl'], ['BBE', 'FORMER_NAME', 'nl'], ['MSO-compagnie', 'FORMER_NAME', 'nl']],
        ['MARITIME', 'UNDERWATER', 'LAND'], ['SOF'], ['COMBAT'], ['MARITIME_ASSAULT', 'COUNTER_TERRORISM', 'HOSTAGE_RESCUE', 'COMBAT_DIVING'], 'defensie-nlsocom');
    $U('nl-11amb', 'NL', 'nl-kl', 'BRIGADE',
        '11 Luchtmobiele Brigade', '11 Lumbl Bde', '11 Luchtmobiele Brigade',
        '11th Airmobile Brigade', null, 0, 50,
        [['11 Luchtmobiele Brigade', 'COMMON_NAME', 'nl'], ['11 AMB', 'ACRONYM', 'en'], ['Rode baretten', 'NICKNAME', 'nl'], ['11e brigade aéromobile', 'FRENCH_NAME', 'fr']],
        ['LAND', 'AIR'], ['ELITE_CONVENTIONAL'], ['COMMAND', 'COMBAT'], ['AIR_ASSAULT', 'AIRBORNE'], 'defensie-nl');
    foreach ([
        ['nl-11infbat', '11 Infanteriebataljon Luchtmobiel «Garderegiment Grenadiers en Jagers»', '11 Infbat Lumbl'],
        ['nl-12infbat', '12 Infanteriebataljon Luchtmobiel «Regiment Van Heutsz»', '12 Infbat Lumbl'],
        ['nl-13infbat', '13 Infanteriebataljon Luchtmobiel «Regiment Stoottroepen Prins Bernhard»', '13 Infbat Lumbl'],
    ] as $i => [$code, $official, $short]) {
        $U($code, 'NL', 'nl-kl', 'BATTALION', $official, $short, $official, null, 'nl-11amb', 1, 51 + $i,
            [[$short, 'SHORT_NAME', 'nl']], ['LAND', 'AIR'], ['ELITE_CONVENTIONAL'], ['COMBAT'], ['AIR_ASSAULT'], 'defensie-nl');
    }

    // =====================================================================
    // NOUVEAU — NORVÈGE
    // =====================================================================
    $U('no-fs', 'NO', 'no-spesialstyrker', 'JOINT_COMMAND',
        'Forsvarets spesialstyrker', 'FS', 'Forsvarets spesialstyrker (NORSOCOM)',
        'Norwegian Special Operations Command', null, 0, 10,
        [['NORSOCOM', 'ACRONYM', 'en'], ['FS', 'ACRONYM', 'no'], ['Forsvarets spesialstyrker', 'COMMON_NAME', 'no'], ['Forces spéciales norvégiennes', 'FRENCH_NAME', 'fr']],
        ['LAND', 'MARITIME', 'AIR'], ['SOF_COMMAND'], ['COMMAND'], [], 'forsvaret-no');
    $U('no-fsk', 'NO', 'no-spesialstyrker', 'COMMANDO',
        'Forsvarets spesialkommando', 'FSK', 'FSK',
        'Armed Forces Special Command', 'no-fs', 1, 20,
        [['FSK', 'ACRONYM', 'no'], ['HJK', 'FORMER_NAME', 'no'], ['Hærens jegerkommando', 'FORMER_NAME', 'no'], ['Rena', 'COMMON_NAME', 'no']],
        ['LAND'], ['SOF'], ['COMBAT'], ['DIRECT_ACTION', 'COUNTER_TERRORISM', 'HOSTAGE_RESCUE', 'SPECIAL_RECONNAISSANCE', 'ARCTIC_WARFARE'], 'forsvaret-no');
    $U('no-jegertroppen', 'NO', 'no-spesialstyrker', 'COMPANY',
        'Jegertroppen', 'Jegertroppen', 'Jegertroppen',
        'Hunter Troop', 'no-fsk', 2, 25,
        [['Hunter Troop', 'ENGLISH_NAME', 'en']],
        ['LAND'], ['SOF', 'TRAINING'], ['TRAINING', 'RECONNAISSANCE'], ['SPECIAL_RECONNAISSANCE', 'ARCTIC_WARFARE'], 'forsvaret-no');
    $U('no-mjk', 'NO', 'no-spesialstyrker', 'COMMANDO',
        'Marinejegerkommandoen', 'MJK', 'MJK',
        'Norwegian Naval Special Operations Commando', 'no-fs', 1, 30,
        [['MJK', 'ACRONYM', 'no'], ['Marinejegerne', 'NICKNAME', 'no'], ['Haakonsvern', 'COMMON_NAME', 'no']],
        ['MARITIME', 'UNDERWATER', 'LAND'], ['SOF'], ['COMBAT'], ['MARITIME_ASSAULT', 'COMBAT_DIVING', 'DIRECT_ACTION', 'COUNTER_TERRORISM'], 'forsvaret-no');
    $U('no-339soas', 'NO', 'no-luftforsvaret', 'AIR_SQUADRON',
        '339 skvadron', '339 SOAS', '339 Special Operations Aviation Squadron',
        '339 Special Operations Aviation Squadron', 'no-fs', 1, 40,
        [['339 SOAS', 'ACRONYM', 'en'], ['339 skvadron', 'COMMON_NAME', 'no']],
        ['AIR'], ['SOF_SUPPORT', 'AVIATION'], ['AVIATION'], ['SPECIAL_OPERATIONS_AVIATION', 'HELICOPTER_OPERATIONS'], 'forsvaret-no');

    $total = (int) $pdo->query('SELECT COUNT(*) FROM military_units')->fetchColumn();
    echo "  Seed synchronisé : {$total} entités militaires.\n";
}
