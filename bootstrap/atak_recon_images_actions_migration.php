<?php

declare(strict_types=1);

return static function (PDO $pdo): void {
    $table = 'recon_images';
    $columns = [
        'operator_comment' => "ALTER TABLE recon_images ADD COLUMN operator_comment text DEFAULT NULL AFTER caption",
        'is_blurred' => "ALTER TABLE recon_images ADD COLUMN is_blurred tinyint(1) NOT NULL DEFAULT 0 AFTER operator_comment",
        'deleted_at' => "ALTER TABLE recon_images ADD COLUMN deleted_at datetime DEFAULT NULL AFTER is_blurred",
        'fx_profile' => "ALTER TABLE recon_images ADD COLUMN fx_profile varchar(64) DEFAULT NULL AFTER deleted_at",
        'fx_intensity' => "ALTER TABLE recon_images ADD COLUMN fx_intensity decimal(4,2) DEFAULT NULL AFTER fx_profile",
        'sse_case_id' => "ALTER TABLE recon_images ADD COLUMN sse_case_id int unsigned DEFAULT NULL AFTER deleted_at",
        'sse_evidence_id' => "ALTER TABLE recon_images ADD COLUMN sse_evidence_id int unsigned DEFAULT NULL AFTER sse_case_id",
        'sse_transferred_at' => "ALTER TABLE recon_images ADD COLUMN sse_transferred_at datetime DEFAULT NULL AFTER sse_evidence_id",
    ];

    foreach ($columns as $column => $sql) {
        $stmt = $pdo->query(
            "SELECT COLUMN_NAME FROM information_schema.COLUMNS
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = " . $pdo->quote($table) . "
               AND COLUMN_NAME = " . $pdo->quote($column)
        );
        if ($stmt && !$stmt->fetch()) {
            $pdo->exec($sql);
        }
    }
};
