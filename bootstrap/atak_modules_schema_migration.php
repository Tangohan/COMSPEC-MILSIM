<?php

declare(strict_types=1);

/**
 * Schéma complet des modules ATAK « intelligence » (rapports, POI, zones, MEDEVAC, QRF, véhicules…).
 * Les fichiers migrations/2026_07_24_00*.sql n’étaient pas branchés dans run-migrations.php.
 * Idempotent — appelée depuis run-migrations.php.
 *
 * Durcissements prod :
 * - désactive les FK le temps du CREATE
 * - retire COMMENT / FOREIGN KEY / colonnes GENERATED (NOW() etc.) qui font échouer MariaDB/MySQL
 * - retire INDEX qui pointaient vers ces colonnes GENERATED
 * - retire triggers DELIMITER
 */
return static function (PDO &$pdo): void {
    $root = dirname(__DIR__);

    $tableExists = static function (PDO $pdo, string $table): bool {
        $st = $pdo->prepare(
            'SELECT 1 FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? LIMIT 1'
        );
        $st->execute([$table]);

        return (bool) $st->fetchColumn();
    };

    $hasColumn = static function (PDO $pdo, string $table, string $column): bool {
        $st = $pdo->prepare(
            'SELECT 1 FROM information_schema.COLUMNS
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ? LIMIT 1'
        );
        $st->execute([$table, $column]);

        return (bool) $st->fetchColumn();
    };

    /**
     * Retire COMMENT '…' / COMMENT='…' en respectant \' et ''.
     */
    $stripCommentsClauses = static function (string $sql): string {
        $out = '';
        $len = strlen($sql);
        $i = 0;
        while ($i < $len) {
            if (preg_match('/^\s+COMMENT\s*=?\s*\'/i', substr($sql, $i), $m)) {
                $i += strlen($m[0]);
                while ($i < $len) {
                    $ch = $sql[$i];
                    if ($ch === '\\' && $i + 1 < $len) {
                        $i += 2;
                        continue;
                    }
                    if ($ch === "'" && $i + 1 < $len && $sql[$i + 1] === "'") {
                        $i += 2;
                        continue;
                    }
                    if ($ch === "'") {
                        $i++;
                        break;
                    }
                    $i++;
                }
                continue;
            }
            $out .= $sql[$i];
            $i++;
        }

        return $out;
    };

    /**
     * Retire une définition de colonne GENERATED ALWAYS AS (…) STORED|VIRTUAL.
     * Gère INT UNSIGNED, BOOLEAN, multi-lignes, etc.
     *
     * Important : on consomme la virgule *précédente* (séparateur de liste),
     * pas la virgule *suivante* — sinon on mange les deux et la colonne d’avant
     * se colle à la suivante → erreur SQL près de ENGINE=InnoDB.
     */
    $stripGeneratedColumns = static function (string $sql): string {
        $pattern = '/,?\s*`?[a-zA-Z_][a-zA-Z0-9_]*`?\s+[a-zA-Z]+(?:\s+[a-zA-Z]+)*(?:\s*\([^)]*\))?\s+GENERATED\s+ALWAYS\s+AS\s*\(/i';
        $out = '';
        $len = strlen($sql);
        $i = 0;
        while ($i < $len) {
            if (preg_match($pattern, substr($sql, $i), $m)) {
                $i += strlen($m[0]);
                $depth = 1;
                while ($i < $len && $depth > 0) {
                    $ch = $sql[$i];
                    if ($ch === '(') {
                        $depth++;
                    } elseif ($ch === ')') {
                        $depth--;
                    }
                    $i++;
                }
                while ($i < $len && preg_match('/\s/', $sql[$i])) {
                    $i++;
                }
                if (preg_match('/^(STORED|VIRTUAL)\b/i', substr($sql, $i), $sm)) {
                    $i += strlen($sm[0]);
                }
                // Ne pas consommer la virgule suivante : elle sépare la colonne d’après.
                continue;
            }
            $out .= $sql[$i];
            $i++;
        }

        return $out;
    };

    $sanitizeSql = static function (string $sql) use ($stripCommentsClauses, $stripGeneratedColumns): string {
        // Triggers non portables
        $sql = preg_replace('/DELIMITER\s+\$\$[\s\S]*?DELIMITER\s*;/i', "\n", $sql) ?? $sql;
        // Vues d’origine (schéma username / first_name)
        $sql = preg_replace('/CREATE\s+OR\s+REPLACE\s+VIEW[\s\S]*?;/i', "\n", $sql) ?? $sql;
        // Commentaires de ligne
        $sql = preg_replace('/--[^\n]*/', '', $sql) ?? $sql;
        // COMMENT colonne / table (apostrophes FR + \')
        $sql = $stripCommentsClauses($sql);
        // FK : table(col) ET `table` (`col`) — l’ancien motif [^,)]+ cassait sur la 1re parenthèse
        $fk = '/,?\s*(?:CONSTRAINT\s+`?[a-zA-Z0-9_]+`?\s+)?'
            . 'FOREIGN\s+KEY\s*\([^)]*\)\s+REFERENCES\s+`?[a-zA-Z0-9_]+`?\s*(?:\([^)]*\))?'
            . '(?:\s+ON\s+DELETE\s+(?:CASCADE|SET\s+NULL|RESTRICT|NO\s+ACTION|SET\s+DEFAULT))?'
            . '(?:\s+ON\s+UPDATE\s+(?:CASCADE|SET\s+NULL|RESTRICT|NO\s+ACTION|SET\s+DEFAULT))?/i';
        $sql = preg_replace($fk, '', $sql) ?? $sql;
        // Colonnes GENERATED (NOW() non déterministe, INT UNSIGNED, etc.)
        $sql = $stripGeneratedColumns($sql);
        // INDEX orphelins sur colonnes GENERATED retirées
        $sql = preg_replace(
            '/,?\s*INDEX\s+`?[a-zA-Z0-9_]+`?\s*\(\s*`?(?:is_fuel_critical|is_ammo_critical|is_damaged|is_golden_hour_critical|total_patients)`?\s*\)/i',
            '',
            $sql
        ) ?? $sql;
        // Virgules orphelines (doubles, après (, avant ))
        $sql = preg_replace('/,\s*,/', ',', $sql) ?? $sql;
        $sql = preg_replace('/\(\s*,/', '(', $sql) ?? $sql;
        $sql = preg_replace('/,\s*\)/', ')', $sql) ?? $sql;

        return $sql;
    };

    $splitStatements = static function (string $sql): array {
        $statements = [];
        $buf = '';
        $len = strlen($sql);
        $inString = false;
        for ($i = 0; $i < $len; $i++) {
            $ch = $sql[$i];
            if ($ch === '\\' && $inString && $i + 1 < $len) {
                // conserver l’échappement dans la chaîne (ne pas basculer inString sur \')
                $buf .= $ch . $sql[$i + 1];
                $i++;
                continue;
            }
            if ($ch === "'" && !$inString) {
                $inString = true;
                $buf .= $ch;
                continue;
            }
            if ($ch === "'" && $inString) {
                // '' = apostrophe échappée SQL
                if ($i + 1 < $len && $sql[$i + 1] === "'") {
                    $buf .= "''";
                    $i++;
                    continue;
                }
                $inString = false;
                $buf .= $ch;
                continue;
            }
            if ($ch === ';' && !$inString) {
                $stmt = trim($buf);
                $buf = '';
                if ($stmt !== '') {
                    $statements[] = $stmt;
                }
                continue;
            }
            $buf .= $ch;
        }
        $tail = trim($buf);
        if ($tail !== '') {
            $statements[] = $tail;
        }

        return $statements;
    };

    $reconnectPdo = static function () use (&$pdo): void {
        $host = $_ENV['DB_HOST'] ?? getenv('DB_HOST') ?: '127.0.0.1';
        $name = $_ENV['DB_NAME'] ?? getenv('DB_NAME') ?: '';
        $user = $_ENV['DB_USER'] ?? getenv('DB_USER') ?: '';
        $pass = $_ENV['DB_PASSWORD'] ?? getenv('DB_PASSWORD') ?: '';
        $charset = $_ENV['DB_CHARSET'] ?? getenv('DB_CHARSET') ?: 'utf8mb4';
        if ($name === '' || $user === '') {
            throw new RuntimeException('Connexion MySQL perdue et identifiants indisponibles pour reconnecter.');
        }
        $dsn = "mysql:host={$host};dbname={$name};charset={$charset}";
        $pdo = new PDO($dsn, $user, $pass, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
        try {
            $pdo->exec('SET FOREIGN_KEY_CHECKS = 0');
        } catch (Throwable) {
        }
    };

    $runStatements = static function (PDO &$pdo, string $label, string $sql) use ($sanitizeSql, $splitStatements, $reconnectPdo): void {
        $sql = $sanitizeSql($sql);
        $statements = $splitStatements($sql);

        $execWithRetry = static function (string $trimmed) use (&$pdo, $reconnectPdo, $label): void {
            $attempts = 0;
            while (true) {
                $attempts++;
                try {
                    $pdo->exec($trimmed);
                    return;
                } catch (Throwable $e) {
                    $msg = $e->getMessage();
                    $gone = str_contains($msg, '2006')
                        || str_contains($msg, '2013')
                        || str_contains($msg, 'gone away')
                        || str_contains($msg, 'Lost connection');
                    if ($gone && $attempts < 2) {
                        echo "  [INFO] {$label} — reconnexion MySQL…\n";
                        $reconnectPdo();
                        continue;
                    }
                    throw $e;
                }
            }
        };

        $ok = 0;
        $skip = 0;
        $warn = 0;
        foreach ($statements as $stmt) {
            $trimmed = trim($stmt);
            if ($trimmed === '' || str_starts_with($trimmed, '/*')) {
                continue;
            }
            // Rebut : fragment orphelin après sanitize raté
            if (preg_match('/^\s*ENGINE\s*=/i', $trimmed)) {
                echo "  [SKIP] {$label} — fragment ENGINE orphelin ignoré\n";
                $skip++;
                continue;
            }
            try {
                $execWithRetry($trimmed);
                if (preg_match('/^\s*CREATE\s+TABLE\s+IF\s+NOT\s+EXISTS\s+`?(\w+)/i', $trimmed, $m)) {
                    echo "  [OK] {$m[1]}\n";
                    $ok++;
                } elseif (preg_match('/^\s*ALTER\s+TABLE\s+`?(\w+)/i', $trimmed, $m)) {
                    echo "  [OK] ALTER {$m[1]}\n";
                    $ok++;
                } else {
                    $ok++;
                }
            } catch (Throwable $e) {
                $msg = $e->getMessage();
                if (
                    str_contains($msg, 'already exists')
                    || str_contains($msg, 'Duplicate')
                    || str_contains($msg, '1060')
                    || str_contains($msg, '1061')
                    || str_contains($msg, '1050')
                ) {
                    $skip++;
                    continue;
                }
                $snippet = preg_replace('/\s+/', ' ', $trimmed) ?? $trimmed;
                if (function_exists('mb_substr')) {
                    $snippet = mb_substr($snippet, 0, 160);
                } else {
                    $snippet = substr($snippet, 0, 160);
                }
                echo '  [ATTENTION] ' . $label . ' : ' . $msg . "\n";
                echo '           SQL: ' . $snippet . (strlen($trimmed) > 160 ? '…' : '') . "\n";
                $warn++;
            }
        }
        if ($ok === 0 && $skip > 0 && $warn === 0) {
            echo "  [SKIP] {$label} — rien à créer (déjà en place)\n";
        }
    };

    try {
        $pdo->exec('SET FOREIGN_KEY_CHECKS = 0');
    } catch (Throwable) {
    }

    $files = [
        '2026_07_24_001_atak_tactical_reports.sql' => 'rapports tactiques',
        '2026_07_24_002_atak_poi_intelligence.sql' => 'POI',
        '2026_07_24_003_atak_tactical_zones.sql' => 'zones tactiques',
        '2026_07_24_004_atak_medevac_extended.sql' => 'MEDEVAC',
        '2026_07_24_005_atak_qrf_system.sql' => 'QRF',
        '2026_07_24_006_atak_vehicle_tracking.sql' => 'véhicules',
        '2026_07_24_007_atak_intelligence_enhancements.sql' => 'extensions intelligence',
        '2026_07_27_001_atak_waypoints.sql' => 'waypoints et itinéraires',
    ];

    foreach ($files as $file => $label) {
        $path = $root . '/migrations/' . $file;
        if (!is_file($path)) {
            echo "  [ATTENTION] Fichier absent : migrations/{$file}\n";
            continue;
        }
        echo "  → {$label} ({$file})\n";
        $sql = (string) file_get_contents($path);
        $runStatements($pdo, $label, $sql);
    }

    // Colonnes remplacées (ex-GENERATED) pour que l’appli puisse lire/écrire sans erreur
    $ensurePlainColumns = [
        'atak_medevac_requests' => [
            'total_patients' => 'INT UNSIGNED NOT NULL DEFAULT 0',
            'is_golden_hour_critical' => 'TINYINT(1) NOT NULL DEFAULT 0',
        ],
        'atak_vehicle_tracking' => [
            'is_fuel_critical' => 'TINYINT(1) NOT NULL DEFAULT 0',
            'is_ammo_critical' => 'TINYINT(1) NOT NULL DEFAULT 0',
            'is_damaged' => 'TINYINT(1) NOT NULL DEFAULT 0',
        ],
    ];
    foreach ($ensurePlainColumns as $table => $cols) {
        if (!$tableExists($pdo, $table)) {
            continue;
        }
        foreach ($cols as $col => $ddl) {
            if ($hasColumn($pdo, $table, $col)) {
                continue;
            }
            try {
                $pdo->exec("ALTER TABLE `{$table}` ADD COLUMN `{$col}` {$ddl}");
                echo "  [OK] ALTER {$table}.{$col}\n";
            } catch (Throwable $e) {
                echo '  [ATTENTION] ALTER ' . $table . '.' . $col . ' : ' . $e->getMessage() . "\n";
            }
        }
    }

    try {
        $pdo->exec('SET FOREIGN_KEY_CHECKS = 1');
    } catch (Throwable) {
    }

    // Vues Athena (display_name / callsign / email — pas username).
    $views = [
        'v_atak_tactical_reports' =>
            "CREATE OR REPLACE VIEW v_atak_tactical_reports AS
             SELECT r.*,
                    u.email AS submitter_username,
                    u.display_name AS submitter_first_name,
                    u.callsign AS submitter_last_name,
                    COALESCE(NULLIF(TRIM(r.submitter_callsign), ''), NULLIF(TRIM(u.callsign), ''), NULLIF(TRIM(u.display_name), ''), 'Compte inconnu') AS display_name,
                    ack_u.email AS acknowledged_by_username,
                    (SELECT COUNT(*) FROM atak_report_attachments WHERE report_id = r.id) AS actual_attachment_count
             FROM atak_tactical_reports r
             LEFT JOIN users u ON r.submitter_user_id = u.id
             LEFT JOIN users ack_u ON r.acknowledged_by_user_id = ack_u.id
             WHERE r.deleted_at IS NULL",
        'v_atak_poi' =>
            "CREATE OR REPLACE VIEW v_atak_poi AS
             SELECT p.*,
                    reporter_u.email AS reported_by_username,
                    COALESCE(NULLIF(TRIM(p.reported_by_callsign), ''), NULLIF(TRIM(reporter_u.callsign), ''), NULLIF(TRIM(reporter_u.display_name), ''), 'Compte inconnu') AS reporter_display_name,
                    creator_u.email AS created_by_username,
                    (SELECT COUNT(*) FROM atak_poi_photos WHERE poi_id = p.id) AS photo_count,
                    (SELECT COUNT(*) FROM atak_poi_observations WHERE poi_id = p.id) AS observation_count,
                    (SELECT MAX(observed_at) FROM atak_poi_observations WHERE poi_id = p.id) AS last_observation_timestamp
             FROM atak_poi p
             LEFT JOIN users reporter_u ON p.reported_by_user_id = reporter_u.id
             LEFT JOIN users creator_u ON p.created_by_user_id = creator_u.id
             WHERE p.deleted_at IS NULL",
        'v_atak_active_zones' =>
            "CREATE OR REPLACE VIEW v_atak_active_zones AS
             SELECT z.*,
                    creator_u.email AS created_by_username,
                    (SELECT COUNT(*) FROM atak_zone_alerts WHERE zone_id = z.id) AS alert_count,
                    (SELECT COUNT(*) FROM atak_zone_alerts WHERE zone_id = z.id AND acknowledged = 0) AS unacknowledged_alert_count,
                    1 AS is_currently_active
             FROM atak_tactical_zones z
             LEFT JOIN users creator_u ON z.created_by_user_id = creator_u.id
             WHERE z.deleted_at IS NULL AND z.status IN ('PLANNED', 'ACTIVE')",
        'v_atak_active_medevac' =>
            "CREATE OR REPLACE VIEW v_atak_active_medevac AS
             SELECT m.*,
                    requester_u.email AS requested_by_username,
                    pilot_u.email AS assigned_pilot_username,
                    (SELECT COUNT(*) FROM atak_medevac_patients WHERE medevac_request_id = m.id) AS actual_patient_count,
                    (SELECT COUNT(*) FROM atak_medevac_patients WHERE medevac_request_id = m.id AND is_stabilized = 1) AS stabilized_patient_count
             FROM atak_medevac_requests m
             LEFT JOIN users requester_u ON m.requested_by_user_id = requester_u.id
             LEFT JOIN users pilot_u ON m.assigned_pilot_user_id = pilot_u.id
             WHERE m.status NOT IN ('COMPLETED', 'CANCELLED')",
        'v_atak_active_qrf' =>
            "CREATE OR REPLACE VIEW v_atak_active_qrf AS
             SELECT q.*,
                    requester_u.email AS requesting_username,
                    qrf_leader_u.email AS qrf_leader_username,
                    (SELECT COUNT(*) FROM atak_qrf_sitrep_updates WHERE qrf_request_id = q.id) AS sitrep_update_count,
                    (SELECT COUNT(*) FROM atak_qrf_waypoints WHERE qrf_request_id = q.id AND reached = 1) AS waypoints_reached,
                    (SELECT COUNT(*) FROM atak_qrf_waypoints WHERE qrf_request_id = q.id) AS waypoints_total
             FROM atak_qrf_requests q
             LEFT JOIN users requester_u ON q.requesting_user_id = requester_u.id
             LEFT JOIN users qrf_leader_u ON q.assigned_qrf_leader_user_id = qrf_leader_u.id
             WHERE q.status IN ('REQUESTED', 'ACKNOWLEDGED', 'QRF_ASSIGNED', 'QRF_ENROUTE', 'QRF_ENGAGED')",
        'v_atak_active_vehicles' =>
            "CREATE OR REPLACE VIEW v_atak_active_vehicles AS
             SELECT v.*,
                    commander_u.email AS crew_commander_username,
                    (SELECT COUNT(*) FROM atak_vehicle_service_requests
                     WHERE vehicle_tracking_id = v.id
                       AND status IN ('REQUESTED', 'ACKNOWLEDGED', 'ENROUTE', 'IN_PROGRESS')) AS pending_service_requests
             FROM atak_vehicle_tracking v
             LEFT JOIN users commander_u ON v.crew_commander_user_id = commander_u.id
             WHERE v.status <> 'DESTROYED'",
    ];

    if ($tableExists($pdo, 'atak_zone_events')) {
        $views['v_atak_zones_threat_assessed'] =
            "CREATE OR REPLACE VIEW v_atak_zones_threat_assessed AS
             SELECT z.*,
                    (SELECT COUNT(*) FROM atak_zone_events WHERE zone_id = z.id) AS event_count
             FROM atak_tactical_zones z
             WHERE z.deleted_at IS NULL";
    }
    if ($tableExists($pdo, 'atak_medevac_requests') && $hasColumn($pdo, 'atak_medevac_requests', 'estimated_response_time_minutes')) {
        $views['v_atak_medevac_optimized'] =
            "CREATE OR REPLACE VIEW v_atak_medevac_optimized AS
             SELECT m.* FROM atak_medevac_requests m
             WHERE m.status NOT IN ('COMPLETED', 'CANCELLED')";
    }

    echo "  → vues Athena\n";
    foreach ($views as $viewName => $ddl) {
        $baseTable = match (true) {
            str_contains($viewName, 'poi') => 'atak_poi',
            str_contains($viewName, 'zones') => 'atak_tactical_zones',
            str_contains($viewName, 'medevac') => 'atak_medevac_requests',
            str_contains($viewName, 'qrf') => 'atak_qrf_requests',
            str_contains($viewName, 'vehicle') => 'atak_vehicle_tracking',
            str_contains($viewName, 'report') => 'atak_tactical_reports',
            default => '',
        };
        if ($baseTable !== '' && !$tableExists($pdo, $baseTable)) {
            echo "  [SKIP] {$viewName} (table {$baseTable} absente)\n";
            continue;
        }
        try {
            $pdo->exec($ddl);
            echo "  [OK] vue {$viewName}\n";
        } catch (Throwable $e) {
            echo '  [ATTENTION] vue ' . $viewName . ' : ' . $e->getMessage() . "\n";
        }
    }

    $expected = [
        'atak_tactical_reports',
        'atak_report_attachments',
        'atak_report_templates',
        'atak_poi',
        'atak_poi_observations',
        'atak_poi_photos',
        'atak_tactical_zones',
        'atak_zone_alerts',
        'atak_medevac_requests',
        'atak_medevac_patients',
        'atak_medevac_status_updates',
        'atak_qrf_requests',
        'atak_qrf_sitrep_updates',
        'atak_qrf_waypoints',
        'atak_vehicle_tracking',
        'atak_vehicle_position_history',
        'atak_vehicle_events',
        'atak_vehicle_service_requests',
        'atak_report_routing_rules',
        'atak_report_routing_history',
        'atak_zone_events',
        'atak_realtime_notifications',
        'atak_medical_assets',
        'atak_qrf_coordination',
        'atak_vehicle_maintenance_log',
        'atak_poi_correlations',
        'atak_intelligence_analysis',
        'atak_waypoint_routes',
        'atak_waypoints',
    ];
    $missing = [];
    foreach ($expected as $t) {
        if (!$tableExists($pdo, $t)) {
            $missing[] = $t;
        }
    }
    if ($missing === []) {
        echo "  [OK] toutes les tables modules ATAK sont présentes\n";
    } else {
        echo '  [ATTENTION] tables encore absentes : ' . implode(', ', $missing) . "\n";
    }
};
