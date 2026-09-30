<?php

declare(strict_types=1);

/**
 * Extras catalogue équipement : description publique / galerie tenues,
 * covers fiches matériel et articles de dotation.
 * (Aussi appliqué via arsenal_wardrobe_migration pour les instances déjà migrées.)
 */
return static function (PDO $pdo): void {
    $columnExists = static function (string $table, string $column) use ($pdo): bool {
        $st = $pdo->prepare(
            'SELECT 1 FROM information_schema.COLUMNS
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ? LIMIT 1'
        );
        $st->execute([$table, $column]);

        return (bool) $st->fetchColumn();
    };
    $tableExists = static function (string $table) use ($pdo): bool {
        $st = $pdo->prepare(
            'SELECT 1 FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? LIMIT 1'
        );
        $st->execute([$table]);

        return (bool) $st->fetchColumn();
    };

    if ($tableExists('arsenal_wardrobes') && !$columnExists('arsenal_wardrobes', 'description')) {
        $pdo->exec('ALTER TABLE arsenal_wardrobes ADD COLUMN description VARCHAR(1000) DEFAULT NULL AFTER notes');
    }
    if ($tableExists('arsenal_wardrobes') && !$columnExists('arsenal_wardrobes', 'gallery_json')) {
        $after = $columnExists('arsenal_wardrobes', 'cover_image_path') ? 'cover_image_path' : 'notes';
        $pdo->exec('ALTER TABLE arsenal_wardrobes ADD COLUMN gallery_json JSON DEFAULT NULL AFTER ' . $after);
    }
    if ($tableExists('equipment_classes') && !$columnExists('equipment_classes', 'cover_image_path')) {
        $pdo->exec('ALTER TABLE equipment_classes ADD COLUMN cover_image_path VARCHAR(255) DEFAULT NULL AFTER description');
    }
    if ($tableExists('equipment_item_definitions') && !$columnExists('equipment_item_definitions', 'cover_image_path')) {
        $pdo->exec('ALTER TABLE equipment_item_definitions ADD COLUMN cover_image_path VARCHAR(255) DEFAULT NULL AFTER description');
    }
};
