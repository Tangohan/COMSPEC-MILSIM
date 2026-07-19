<?php

declare(strict_types=1);

/**
 * Enrichissement des créneaux : image, conditions, déroulement, étiquettes.
 *
 * @return callable(PDO): void
 */
return static function (PDO $pdo): void {
    $hasTable = static function (PDO $pdo, string $table): bool {
        $st = $pdo->prepare(
            "SELECT 1 FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? LIMIT 1"
        );
        $st->execute([$table]);

        return (bool) $st->fetchColumn();
    };
    $hasCol = static function (PDO $pdo, string $table, string $col): bool {
        $st = $pdo->prepare(
            "SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ? LIMIT 1"
        );
        $st->execute([$table, $col]);

        return (bool) $st->fetchColumn();
    };

    if (!$hasTable($pdo, 'community_events')) {
        return;
    }

    $alters = [
        'cover_image_path' => "ALTER TABLE community_events ADD COLUMN cover_image_path VARCHAR(512) DEFAULT NULL AFTER description",
        'conditions_general' => "ALTER TABLE community_events ADD COLUMN conditions_general TEXT DEFAULT NULL AFTER cover_image_path",
        'conditions_special' => "ALTER TABLE community_events ADD COLUMN conditions_special TEXT DEFAULT NULL AFTER conditions_general",
        'schedule_json' => "ALTER TABLE community_events ADD COLUMN schedule_json TEXT DEFAULT NULL AFTER conditions_special",
        'tags_json' => "ALTER TABLE community_events ADD COLUMN tags_json TEXT DEFAULT NULL AFTER schedule_json",
    ];

    foreach ($alters as $col => $sql) {
        if ($hasCol($pdo, 'community_events', $col)) {
            continue;
        }
        try {
            $pdo->exec($sql);
            echo "  [OK] community_events.{$col}\n";
        } catch (Throwable $e) {
            echo '  [ATTENTION] community_events.' . $col . ' : ' . $e->getMessage() . "\n";
        }
    }
};
