<?php

declare(strict_types=1);

namespace Tests\Support;

use PDO;

/**
 * Base sqlite des essais du panneau Rapports du poste TOC web : mêmes colonnes que atak_tactical_reports, atak_nine_line,
 * atak_toc_report_reads et recon_images (sous MySQL), et un jeu de 40 rapports réalistes comme en produisent les téléphones
 * ATAK : 12 BDA de résultats variés, 15 comptes rendus (contact, SALUTE, observation, patrouille, blessé, fin d'opération)
 * de longueurs variées, 6 SITREP, 4 9-line (3 CAS et 1 5-LINE) et 3 MEDEVAC. Textes accentués, noms et lieux longs, un
 * rapport classifié, un rapport caviardé.
 */
final class TocReportsSqlite
{
    public const TENANT = 1;
    public const MAP = 1;

    public static function open(bool $withReads = true): PDO
    {
        $pdo = new PDO('sqlite::memory:', null, null, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
        $pdo->exec('CREATE TABLE atak_tactical_reports (
            id INTEGER PRIMARY KEY AUTOINCREMENT, tenant_id INTEGER NOT NULL, context_id INTEGER NOT NULL, report_type TEXT NOT NULL DEFAULT \'OTHER\',
            report_number TEXT NULL, priority TEXT NOT NULL DEFAULT \'ROUTINE\', classification TEXT NOT NULL DEFAULT \'UNCLASSIFIED\',
            submitter_user_id INTEGER NULL, submitter_callsign TEXT NULL, submitter_unit TEXT NULL, submitter_steam_id TEXT NULL,
            pos_x REAL NULL, pos_y REAL NULL, grid_reference TEXT NULL, location_description TEXT NULL, dtg TEXT NULL,
            report_timestamp TEXT NOT NULL, event_timestamp TEXT NULL, structured_data TEXT NULL, summary TEXT NULL, details TEXT NULL,
            remarks TEXT NULL, has_attachments INTEGER NOT NULL DEFAULT 0, status TEXT NOT NULL DEFAULT \'SUBMITTED\',
            acknowledged_by_user_id INTEGER NULL, acknowledged_at TEXT NULL, action_taken TEXT NULL, visibility TEXT NOT NULL DEFAULT \'ALL\',
            distributed_to TEXT NULL, created_at TEXT NULL, updated_at TEXT NULL, deleted_at TEXT NULL, mission_key TEXT NULL)');
        $pdo->exec('CREATE TABLE atak_nine_line (
            id INTEGER PRIMARY KEY AUTOINCREMENT, tenant_id INTEGER NOT NULL, map_id INTEGER NOT NULL DEFAULT 1, mission_kind TEXT NOT NULL DEFAULT \'cas\',
            mission_id TEXT NULL, author TEXT NULL, assigned_aircraft TEXT NULL,
            line1 TEXT NULL, line2 TEXT NULL, line3 TEXT NULL, line4 TEXT NULL, line5 TEXT NULL, line6 TEXT NULL, line7 TEXT NULL, line8 TEXT NULL, line9 TEXT NULL,
            lines_checked TEXT NULL, status TEXT NOT NULL DEFAULT \'SUBMITTED\', remarks TEXT NULL, geo_json TEXT NULL, author_user_id INTEGER NULL,
            created_at TEXT NULL, updated_at TEXT NULL)');
        $pdo->exec('CREATE TABLE recon_images (id INTEGER PRIMARY KEY AUTOINCREMENT, tenant_id INTEGER NOT NULL, image_path TEXT NOT NULL DEFAULT \'x.jpg\', caption TEXT NULL, captured_at TEXT NULL, is_confidential INTEGER NOT NULL DEFAULT 0)');
        if ($withReads) {
            self::createReads($pdo);
        }
        $migrate = require dirname(__DIR__, 2) . '/bootstrap/atak_toc_plans_migration.php';
        $migrate($pdo);

        return $pdo;
    }

    public static function createReads(PDO $pdo): void
    {
        $pdo->exec('CREATE TABLE atak_toc_report_reads (tenant_id INTEGER NOT NULL, user_id INTEGER NOT NULL, report_kind TEXT NOT NULL, report_id INTEGER NOT NULL, read_at TEXT NOT NULL, PRIMARY KEY (tenant_id, user_id, report_kind, report_id))');
    }

    /**
     * Un compte rendu tactique.
     *
     * @param array<string, mixed> $sd
     * @param array<string, mixed> $over
     */
    public static function tactical(PDO $pdo, int $tenant, int $minutesAgo, string $type, string $who, string $unit, string $summary, array $sd = [], array $over = []): int
    {
        $now = (int) ($over['now'] ?? time());
        unset($over['now']);
        $stamp = gmdate('Y-m-d H:i:s', $now - $minutesAgo * 60);
        $row = array_merge([
            'tenant_id' => $tenant, 'context_id' => self::MAP, 'report_type' => $type, 'report_number' => null, 'priority' => 'ROUTINE', 'classification' => 'UNCLASSIFIED',
            'submitter_callsign' => $who, 'submitter_unit' => $unit, 'pos_x' => 15200 + ($minutesAgo * 7) % 900, 'pos_y' => 17300 + ($minutesAgo * 11) % 800,
            'grid_reference' => null, 'report_timestamp' => $stamp, 'structured_data' => $sd === [] ? null : json_encode($sd, JSON_UNESCAPED_UNICODE),
            'summary' => $summary, 'details' => null, 'remarks' => null, 'status' => 'SUBMITTED', 'visibility' => 'ALL', 'created_at' => $stamp, 'updated_at' => $stamp,
        ], $over);
        if ($row['grid_reference'] === null && $row['pos_x'] !== null) {
            $row['grid_reference'] = sprintf('%03d %03d', intdiv((int) $row['pos_x'], 100), intdiv((int) $row['pos_y'], 100));
        }
        $cols = array_keys($row);
        $st = $pdo->prepare('INSERT INTO atak_tactical_reports (' . implode(',', $cols) . ') VALUES (' . implode(',', array_fill(0, count($cols), '?')) . ')');
        $st->execute(array_values($row));

        return (int) $pdo->lastInsertId();
    }

    /**
     * Une demande 9-line (CAS, 5-LINE ou MEDEVAC).
     *
     * @param list<string> $lines
     * @param array<string, mixed> $over
     */
    public static function nine(PDO $pdo, int $tenant, int $minutesAgo, string $kind, string $author, array $lines, array $over = []): int
    {
        $now = (int) ($over['now'] ?? time());
        unset($over['now']);
        $stamp = gmdate('Y-m-d H:i:s', $now - $minutesAgo * 60);
        $row = array_merge([
            'tenant_id' => $tenant, 'map_id' => self::MAP, 'mission_kind' => $kind, 'author' => $author, 'assigned_aircraft' => null,
            'status' => $kind === 'medevac' ? 'REQUESTED' : 'SUBMITTED', 'created_at' => $stamp, 'updated_at' => $stamp,
        ], $over);
        foreach ($lines as $i => $v) {
            $row['line' . ($i + 1)] = $v;
        }
        $cols = array_keys($row);
        $st = $pdo->prepare('INSERT INTO atak_nine_line (' . implode(',', $cols) . ') VALUES (' . implode(',', array_fill(0, count($cols), '?')) . ')');
        $st->execute(array_values($row));

        return (int) $pdo->lastInsertId();
    }

    /**
     * Les 40 rapports du poste : retourne le nombre de rapports par famille.
     *
     * @return array{bda: int, cr: int, sitrep: int, nineline: int}
     */
    public static function seedRealistic(PDO $pdo, int $now): array
    {
        $o = ['now' => $now];
        $bdaApp = static fn (string $grid, string $cible, string $res, string $ekia, string $equip, string $munition, string $reprise, string $rem = ''): array => [
            'kind' => 'bda', 'grid' => $grid, 'type' => $cible, 'desc' => $res, 'ekia' => $ekia, 'equip' => $equip, 'ordnance' => $munition, 'reattack' => $reprise, 'remarks' => $rem,
        ];
        $bdaRep = static fn (string $grid, string $cible, string $res, string $ekia, string $equip, string $munition, string $collat, string $reprise, string $rem = ''): array => [
            'kind' => 'bda', 'source' => 'COMSPEC ATAK · Comptes rendus', 'grid' => $grid, 'type' => $cible, 'result' => $res, 'ekia' => $ekia, 'equip' => $equip,
            'ordnance' => $munition, 'collateral' => $collat, 'reattack' => $reprise, 'remarks' => $rem,
        ];
        // 12 BDA : détruit ×4, endommagé ×3, neutralisé ×2, sans effet ×2, résultat non lisible ×1.
        $bda = [
            [8, 'ALPHA 1-1', 'ALPHA 1', $bdaRep('0412 1873', 'VÉHICULE', 'DÉTRUIT', '3', '1 BMP-2, 2 PKM', '2 x 81 mm HE, tirs réglés par le JTAC', 'AUCUN', 'PAS DE REPRISE', 'Le BMP brûle encore, deux survivants se replient vers le sud-est.'), 'ROUTINE'],
            [22, 'Capitaine Jean-Baptiste de La Rochefoucauld-Montmirail', 'COMPAGNIE DE FUSILIERS DE MONTAGNE · 2e SECTION', $bdaRep('1538 1745', 'BÂTIMENT', 'ENDOMMAGÉ', '', 'Toiture et mur nord', 'Javelin (1)', 'CIVILS', 'REPRISE REQUISE', 'Des civils sont signalés au rez-de-chaussée : ne pas reprendre sans confirmation.'), 'PRIORITY'],
            [41, 'BRAVO 2-1', 'BRAVO 2', $bdaApp('1602 1821', 'Personnel', 'neutralisé', '4', '', '12,7 mm', 'Pas de reprise', 'Équipe de mortier ennemie.'), 'ROUTINE'],
            [55, 'YA1', 'YA1', $bdaRep('0975 1690', 'POSITION', 'SANS EFFET', '0', '', 'Mortier de 60 mm (6 coups)', 'INCONNU', 'REPRISE REQUISE', 'Tirs trop courts de 150 m : corriger et retirer.'), 'IMMEDIATE'],
            [78, 'ALPHA 1-3', 'ALPHA 1', $bdaApp('1011 1755', 'Véhicule', 'détruit', '2', '1 technical DShK', 'AT4', 'Pas de reprise', ''), 'ROUTINE'],
            [96, 'VIKING 6', 'ESCADRON VIKING', $bdaRep('1120 1904', 'VÉHICULE', 'ENDOMMAGÉ', '1', '1 camion de ravitaillement', 'Canon de 30 mm, 12 obus', 'AUCUN', 'PAS DE REPRISE', 'Immobilisé, moteur en feu ; l’équipage a quitté le véhicule.'), 'ROUTINE'],
            [130, 'CHARLIE 3', 'CHARLIE 3', $bdaApp('0888 1802', 'Bâtiment', 'sans effet', '', '', 'RPG-7', 'Reprise requise', 'Le RPG a explosé sur le mur d’enceinte.'), 'PRIORITY'],
            [175, 'FOX 2-1', 'FOX 2', $bdaRep('1304 1633', 'PERSONNEL', 'NEUTRALISÉ', '2', '', 'Tir de précision 7,62 mm (2)', 'AUCUN', 'PAS DE REPRISE', ''), 'ROUTINE'],
            [214, 'ALPHA 1-2', 'ALPHA 1', $bdaRep('1449 1710', 'VÉHICULE', 'DÉTRUIT', '5', '2 Ural, 1 BTR-60', 'Bombe GBU-12 guidée laser', 'AUCUN', 'PAS DE REPRISE', 'Frappe CAS réussie, convoi arrêté sur le pont.'), 'IMMEDIATE'],
            [262, 'BRAVO 2-3', 'BRAVO 2', $bdaApp('1522 1690', 'Position', 'endommagé', '', 'Poste de tir fixe', 'C4 (2 charges)', 'Pas de reprise', 'Charges posées par le génie.'), 'ROUTINE'],
            [305, 'DELTA 4-2', 'DELTA 4', $bdaRep('0722 1544', 'BÂTIMENT', 'DÉTRUIT', '1', 'Dépôt de munitions', 'Obus de 155 mm (3)', 'AUCUN', 'PAS DE REPRISE', 'Explosions secondaires pendant quatre minutes.'), 'ROUTINE'],
            [340, 'ECHO 1', 'ECHO 1', ['kind' => 'bda', 'grid' => '1260 1802', 'type' => 'Véhicule', 'desc' => 'à confirmer (fumée dense)', 'ekia' => '?', 'remarks' => 'Observation interrompue par la fumée.'], 'ROUTINE'],
        ];
        foreach ($bda as [$min, $who, $unit, $sd, $prio]) {
            $res = \App\Services\AtakToc\TocReportCatalog::bdaResult($sd);
            self::tactical($pdo, self::TENANT, $min, 'BDA', $who, $unit, 'Bilan des dégâts (BDA) · ' . $sd['grid'] . ' · ' . $sd['type'] . ' ' . $res['label'], $sd, ['priority' => $prio, 'now' => $now, 'has_attachments' => $min === 22 ? 1 : 0]);
        }
        // 15 comptes rendus.
        $cr = [
            [3, 'TIC', 'ALPHA 1-1', 'ALPHA 1', 'Contact (TIC) · 0419 1883 · TIRS DIRECTS', ['kind' => 'tic', 'nature' => 'TIRS DIRECTS', 'grid' => '0419 1883', 'dir' => 'NE', 'dist' => '300-600 M', 'size' => 'Section (10 à 30)', 'weapons' => 'Mitrailleuses', 'status' => 'EN COURS', 'friendly' => 'BLESSÉS', 'need' => 'APPUI FEU', 'desc' => 'Tirs de PKM depuis la crête, au moins deux positions. Un blessé léger côté droit.'], 'FLASH'],
            [9, 'TIC', 'BRAVO 2-2', 'BRAVO 2', 'Contact (TIC) · 1608 1830 · OBSERVÉ', ['kind' => 'tic', 'nature' => 'OBSERVÉ', 'grid' => '1608 1830', 'dir' => 'E', 'dist' => '600 M-1 KM', 'size' => '1 à 2 personnes', 'status' => 'FIXÉ', 'friendly' => 'AUCUNE', 'need' => 'AUCUN'], 'IMMEDIATE'],
            [27, 'SALUTE', 'YA1', 'YA1', 'SALUTE · 1553 1720 · Patrouille', ['kind' => 'salute', 'size' => '6 hommes, 1 pick-up armé', 'activity' => 'Patrouille', 'movement' => 'N', 'location' => '1553 1720', 'unit' => 'Treillis sable, brassards rouges', 'time' => '1424', 'equipment' => 'AK-74, 1 PKM, technical DShK', 'confidence' => 'PROBABLE', 'remarks' => 'Se dirigent vers le village d’Agios Dionysios.'], 'PRIORITY'],
            [37, 'SPOTREP', 'FOX 2-1', 'FOX 2', 'Observation (SPOTREP) · 1304 1633 · Véhicule blindé', ['kind' => 'spotrep', 'what' => 'Véhicule blindé', 'count' => '2', 'grid' => '1304 1633', 'movement' => 'SE', 'time' => '1411', 'action' => 'SURVEILLANCE', 'remarks' => 'Deux BTR-60 à l’arrêt, tourelles vers l’ouest, équipages hors des véhicules.'], 'ROUTINE'],
            [58, 'PATROLREP', 'Lieutenant Anne-Sophie Delacroix-Villeneuve', 'PELOTON DE RECONNAISSANCE · 3e SECTION', 'Fin de patrouille (PATROLREP) · retour 1355', ['kind' => 'patrolrep', 'start' => '1020', 'end' => '1355', 'route' => 'Kavala › col de Sofia › crête 214 › Pyrgos', 'result' => 'PARTIELLE', 'wia' => '1', 'kia' => '0', 'observations' => "Terrain sec et dégagé jusqu’à la crête 214 ; le pont de Pyrgos est intact mais piégé (deux fils visibles côté est).\nLa population est absente de tous les hameaux traversés : volets fermés, aucun véhicule.\nTraces de chenilles fraîches sur la piste ouest, environ six à huit heures.", 'contacts' => 'Contact bref à 1240 : deux tireurs isolés, repliés après notre riposte. Un blessé léger (éclat au bras), soigné sur place.', 'recommendations' => 'Faire passer le génie sur le pont avant tout mouvement motorisé. Prévoir une reconnaissance aérienne de la piste ouest.'], 'PRIORITY'],
            [74, 'EAGLE_DOWN', 'BRAVO 2-4', 'BRAVO 2', 'Opérateur à terre (EAGLE_DOWN) · 1580 1804 · CRITIQUE', ['kind' => 'eagle_down', 'casualty' => 'Caporal Mélissa Okonkwo-Lefèvre', 'status' => 'CRITIQUE', 'grid' => '1580 1804', 'mechanism' => 'Éclats / explosion', 'injuries' => 'Plaie thoracique gauche, hémorragie jambe droite', 'signs' => 'CONFUS', 'treatment' => 'Garrot cuisse, pansement compressif', 'situation' => 'CONTACT EN COURS', 'medevac' => 'URGENTE', 'lz' => 'NON SÛRE', 'remarks' => 'Demande de MEDEVAC envoyée.'], 'FLASH'],
            [101, 'SALUTE', 'ZULU 1', 'ZULU 1', 'SALUTE · 0780 1655 · Rassemblement', ['kind' => 'salute', 'size' => 'Environ 20', 'activity' => 'Rassemblement', 'location' => '0780 1655', 'time' => '1301', 'equipment' => 'Armes légères', 'confidence' => 'DOUTEUX'], 'ROUTINE'],
            [118, 'TIC', 'CHARLIE 3', 'CHARLIE 3', 'Contact (TIC) · 0891 1795 · IED', ['kind' => 'tic', 'nature' => 'IED', 'grid' => '0891 1795', 'dist' => '< 100 M', 'status' => 'TERMINÉ', 'friendly' => 'AUCUNE', 'need' => 'AUCUN', 'desc' => 'IED sur le bas-côté, détecté avant le passage. Zone bouclée.'], 'PRIORITY'],
            [150, 'PATROLREP', 'ALPHA 1-1', 'ALPHA 1', 'Fin de patrouille (PATROLREP) · retour 1105', ['kind' => 'patrolrep', 'start' => '0830', 'end' => '1105', 'route' => 'Point d’appui ALPHA › moulin', 'result' => 'REMPLIE', 'wia' => '0', 'kia' => '0', 'observations' => 'Rien à signaler. Moulin vide.'], 'ROUTINE'],
            [186, 'SPOTREP', 'YA1', 'YA1', 'Observation (SPOTREP) · 0975 1690 · Drone', ['kind' => 'spotrep', 'what' => 'Drone', 'count' => '1', 'grid' => '0975 1690', 'time' => '1254', 'action' => 'SIGNALÉ', 'remarks' => 'Drone de reconnaissance quadricoptère, altitude estimée 80 m, cap sud.'], 'PRIORITY'],
            [230, 'DEBRIEF', 'DELTA 4-1', 'DELTA 4', 'Fin d’opération · Opération ÉPERVIER DU NORD', ['kind' => 'debrief', 'debrief_event' => 'submitted', 'deroule' => 'Infiltration à 0630, objectif atteint à 0920, exfiltration à 1210 sans perte.', 'points_forts' => 'Coordination avec le JTAC, discipline radio.', 'points_a_travailler' => 'Gestion des munitions, délais du MEDEVAC.'], 'ROUTINE'],
            [255, 'TIC', 'ECHO 1', 'ECHO 1', 'Contact (TIC) · 1255 1790 · TIRS INDIRECTS', ['kind' => 'tic', 'nature' => 'TIRS INDIRECTS', 'grid' => '1255 1790', 'dir' => 'O', 'dist' => '> 1 KM', 'size' => 'Inconnu', 'weapons' => 'Mortiers / artillerie', 'status' => 'EN COURS', 'friendly' => 'INCONNU', 'need' => 'RENFORTS'], 'IMMEDIATE'],
            [290, 'SALUTE', 'BRAVO 2-1', 'BRAVO 2', 'SALUTE · 1471 1702 · Embuscade', ['kind' => 'salute', 'size' => '4 hommes', 'activity' => 'Embuscade', 'location' => '1471 1702', 'time' => '1218', 'unit' => 'Sans uniforme, foulards verts', 'equipment' => 'RPG-7, AK', 'confidence' => 'CONFIRMÉ'], 'PRIORITY'],
            [320, 'EAGLE_DOWN', 'ALPHA 1-4', 'ALPHA 1', 'Opérateur à terre (EAGLE_DOWN) · 1118 1760 · STABLE', ['kind' => 'eagle_down', 'casualty' => 'ALPHA 1-4', 'status' => 'STABLE', 'grid' => '1118 1760', 'mechanism' => 'Chute', 'injuries' => 'Entorse cheville', 'signs' => 'CONSCIENT', 'situation' => 'ZONE SÛRE', 'medevac' => 'ROUTINE', 'lz' => 'SÛRE'], 'ROUTINE'],
            [360, 'OTHER', 'TA1', 'TOC', 'Compte rendu libre · Point radio du soir', ['kind' => 'other', 'title' => 'Point radio du soir', 'text' => 'Liaison rétablie avec la section de Pyrgos à 1330. Batteries à surveiller sur les deux relais nord.', 'grid' => '1520 1700'], 'ROUTINE'],
        ];
        foreach ($cr as [$min, $type, $who, $unit, $summary, $sd, $prio]) {
            $extra = ['now' => $now, 'priority' => $prio];
            if ($min === 186) {
                // Rapport caviardé : le bureau Remontées a masqué le lieu et un passage du détail.
                $extra['details'] = 'Drone repéré au-dessus de ' . str_repeat("\u{2588}", 6) . ' ; cap sud, altitude 80 m.';
            }
            self::tactical($pdo, self::TENANT, $min, $type, $who, $unit, $summary, $sd, $extra);
        }
        // 6 SITREP, dont un classifié (CONFIDENTIAL : réservé à l'encadrement).
        $sit = static fn (string $time, string $grid, string $activity, string $enemy, string $friend, string $fit, string $wia, string $kia, string $ammo, string $fuel, string $cap, string $next): array => [
            'kind' => 'sitrep', 'time' => $time, 'grid' => $grid, 'activity' => $activity, 'enemy' => $enemy, 'friendly' => $friend, 'fit' => $fit, 'wia' => $wia, 'kia' => $kia,
            'ammo' => $ammo, 'fuel' => $fuel, 'capability' => $cap, 'next' => $next,
        ];
        $sitreps = [
            [15, 'ALPHA 1-1', 'ALPHA 1', $sit('1432', '0419 1883', 'AU CONTACT', 'Section ennemie retranchée sur la crête, deux PKM, un RPG.', 'Alpha 1 en position défensive derrière le muret ; Bravo 2 en appui à 400 m.', '9', '1', '0', 'ORANGE', 'VERT', '75 %', 'Tenir la position jusqu’à l’arrivée de l’appui feu, puis débordement par le sud.'), 'IMMEDIATE', []],
            [66, 'BRAVO 2-1', 'BRAVO 2', $sit('1330', '1602 1821', 'EN POSITION', 'Aucun contact depuis 1245.', 'Bravo 2 en surveillance de la route nationale.', '11', '0', '0', 'VERT', 'VERT', '100 %', 'Relève à 1500 puis retour au point d’appui.'), 'ROUTINE', []],
            [140, 'CHARLIE 3', 'CHARLIE 3', $sit('1215', '0891 1795', 'EN ATTENTE', 'IED neutralisé, zone sécurisée.', 'Charlie 3 attend le génie pour l’ouverture d’itinéraire.', '7', '0', '0', 'VERT', 'ORANGE', '100 %', 'Reprendre l’itinéraire VERT dès l’ouverture.'), 'ROUTINE', []],
            [210, 'VIKING 6', 'ESCADRON VIKING', $sit('1100', '1120 1904', 'EN MOUVEMENT', 'Pas de contact. Trace de véhicules sur la piste ouest.', 'Escadron en colonne, formation en V.', '24', '0', '0', 'VERT', 'ORANGE', '100 %', 'Bond suivant vers le col, puis halte de dix minutes.'), 'ROUTINE', []],
            [275, 'DELTA 4-2', 'DELTA 4', $sit('0940', '0722 1544', 'AU CONTACT', 'Ennemi retranché dans le dépôt, appui de feux indirects demandé.', 'Delta 4 fixé à 200 m du dépôt, deux blessés évacués par le MEDEVAC.', '14', '2', '0', 'ROUGE', 'ORANGE', '50 %', 'Attendre le tir d’artillerie puis assaut.'), 'FLASH', []],
            [330, 'TA1', 'POSTE TOC', $sit('0830', '1520 1700', 'EN POSITION', 'Menace aérienne légère (drone observé hier).', 'Dispositif complet, tous les groupes en liaison.', '63', '1', '0', 'VERT', 'VERT', '100 %', 'Pas de changement avant la nuit.'), 'ROUTINE', ['classification' => 'CONFIDENTIAL', 'visibility' => 'COMMAND_ONLY']],
        ];
        foreach ($sitreps as [$min, $who, $unit, $sd, $prio, $over]) {
            self::tactical($pdo, self::TENANT, $min, 'SITREP', $who, $unit, 'Situation (SITREP) · ' . $sd['grid'] . ' · ' . $sd['activity'], $sd, ['now' => $now, 'priority' => $prio] + $over);
        }
        // 3 CAS 9-line + 1 5-LINE.
        self::nine($pdo, self::TENANT, 12, 'cas', 'JTAC-ALPHA', ['IP DELTA', '245° M, déviation droite', '4,2 NM', '450 ft MSL', '2 BTR à l’arrêt, infanterie retranchée dans le bâtiment est', '0419 1883', 'Laser 1688', 'Sud 600 m', 'Sortie nord-est vers IP DELTA'], $o + ['assigned_aircraft' => 'RAVEN 2-1', 'status' => 'INBOUND', 'remarks' => 'FAH 270 ± 20, menace SA-7 au sud, DANGER CLOSE.', 'geo_json' => json_encode(['source' => 'jtac', 'target' => [14190, 18830], 'fah' => '270', 'dangerClose' => true, 'laser' => true, 'laserCode' => 1688, 'aircraft' => 'RAVEN 2-1', 'ordnance' => 'GBU-12'])]);
        self::nine($pdo, self::TENANT, 95, 'cas', 'JTAC-BRAVO', ['IP ECHO', '090° M', '6 NM', '620 ft MSL', 'Convoi de trois camions sur la route nationale', '1449 1710', 'Fumigène rouge', 'Nord 900 m', 'Sortie plein sud'], $o + ['assigned_aircraft' => 'VIPER 1', 'status' => 'COMPLETE']);
        self::nine($pdo, self::TENANT, 205, 'cas', 'JTAC-ALPHA', ['IP FOXTROT', '180° M', '2,8 NM', '300 ft MSL', 'Mortier de 82 mm en batterie', '1011 1755', 'IR pointer', 'Ouest 400 m', 'Dégagement est'], $o + ['status' => 'ABORTED']);
        self::nine($pdo, self::TENANT, 150, 'fiveline', 'TA1-CCA', ['Observateur ALPHA 1 / tir immédiat', 'Sud 300 m, fumigène vert', '0975 1690', 'Mitrailleuse dans le bâtiment est, fumigène violet', 'Tir d’est en ouest, danger close'], $o + ['status' => 'ACKNOWLEDGED']);
        // 3 MEDEVAC / CASEVAC.
        self::nine($pdo, self::TENANT, 74, 'medevac', 'BRAVO 2-4', ['1580 1804', '41.500 · BRAVO 2', 'A2 · B1', 'Treuil', 'L2 · A1', 'E · ennemi possible', 'Fumigène violet', 'A · militaire allié', 'Pente nord, câbles électriques'], $o + ['assigned_aircraft' => 'DUSTOFF 1', 'status' => 'ON_SCENE']);
        self::nine($pdo, self::TENANT, 160, 'medevac', 'Sergent-chef Étienne Montgolfier-Duplessis-Castelnau', ['1118 1760', '40.250 · ALPHA 1', 'C1', 'Aucun', 'A1', 'N · pas d’ennemi', 'Panneau orange', 'A · militaire allié', ''], $o + ['status' => 'COMPLETE']);
        self::nine($pdo, self::TENANT, 245, 'medevac', 'DELTA 4-1', ['0722 1544', '42.000 · DELTA 4', 'A1 · B2', 'Ventilateur', 'L3', 'P · ennemi possible', 'Fumigène jaune', 'D · civil', 'Fils électriques à l’ouest'], $o + ['status' => 'LAUNCHED']);

        return ['bda' => 12, 'cr' => 15, 'sitrep' => 6, 'nineline' => 7];
    }
}
