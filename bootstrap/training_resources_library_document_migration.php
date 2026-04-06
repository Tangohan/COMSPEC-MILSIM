<?php

declare(strict_types=1);

/**
 * training_resources : lien document (document_id), enum library_document — idempotent.
 * Gère les bases déjà pourvues de library_document_id (renommage) ou sans colonne (ajout).
 */
return function (PDO $pdo): void {
    $chk = $pdo->query("SELECT TABLE_NAME FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'training_resources' LIMIT 1");
    if (!$chk || !$chk->fetch()) {
        echo "[ATTENTION] training_resources document : table training_resources absente — ignoré.\n";

        return;
    }
    $docChk = $pdo->query("SELECT TABLE_NAME FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'documents' LIMIT 1");
    if (!$docChk || !$docChk->fetch()) {
        echo "[ATTENTION] training_resources document : table documents absente — ignoré.\n";

        return;
    }

    $enumCol = $pdo->query(
        'SELECT COLUMN_TYPE FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ' . $pdo->quote('training_resources') . " AND COLUMN_NAME = 'resource_type' LIMIT 1"
    );
    $enumType = $enumCol ? (string) ($enumCol->fetchColumn() ?: '') : '';
    if ($enumType !== '' && !str_contains($enumType, 'library_document')) {
        echo "training_resources.resource_type : ajout valeur library_document...\n";
        try {
            $pdo->exec(
                "ALTER TABLE training_resources MODIFY COLUMN resource_type ENUM('pdf','image','video','audio','zip','attachment','link','library_document') NOT NULL"
            );
        } catch (PDOException $e) {
            echo '  [ATTENTION] resource_type enum : ' . $e->getMessage() . "\n";
        }
    }

    $hasLib = (bool) $pdo->query(
        'SELECT COLUMN_NAME FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ' . $pdo->quote('training_resources') . ' AND COLUMN_NAME = ' . $pdo->quote('library_document_id') . ' LIMIT 1'
    )->fetch();
    $hasDoc = (bool) $pdo->query(
        'SELECT COLUMN_NAME FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ' . $pdo->quote('training_resources') . ' AND COLUMN_NAME = ' . $pdo->quote('document_id') . ' LIMIT 1'
    )->fetch();

    if ($hasLib && !$hasDoc) {
        echo "training_resources : migration library_document_id → document_id...\n";
        try {
            $pdo->exec('UPDATE training_resources SET resource_type = \'library_document\' WHERE library_document_id IS NOT NULL');
        } catch (PDOException $e) {
            echo '  [ATTENTION] update resource_type : ' . $e->getMessage() . "\n";
        }
        foreach (['fk_training_resources_library_document'] as $fkName) {
            try {
                $pdo->exec('ALTER TABLE training_resources DROP FOREIGN KEY ' . $fkName);
            } catch (PDOException) {
                /* déjà absent ou nom différent */
            }
        }
        try {
            $pdo->exec('ALTER TABLE training_resources DROP INDEX idx_training_resources_library_document');
        } catch (PDOException) {
            /* */
        }
        try {
            $pdo->exec(
                'ALTER TABLE training_resources CHANGE COLUMN library_document_id document_id BIGINT UNSIGNED NULL DEFAULT NULL'
            );
        } catch (PDOException $e) {
            echo '  [ATTENTION] rename document_id : ' . $e->getMessage() . "\n";

            return;
        }
        try {
            $pdo->exec('ALTER TABLE training_resources ADD KEY idx_training_resources_document (document_id)');
        } catch (PDOException) {
            /* */
        }
        try {
            $pdo->exec(
                'ALTER TABLE training_resources ADD CONSTRAINT fk_training_resources_document FOREIGN KEY (document_id) REFERENCES documents (id) ON DELETE SET NULL ON UPDATE CASCADE'
            );
        } catch (PDOException $e) {
            echo '  [ATTENTION] fk_training_resources_document : ' . $e->getMessage() . "\n";
        }

        return;
    }

    if ($hasDoc) {
        return;
    }

    echo "training_resources : ajout colonne document_id + clé étrangère...\n";
    try {
        $pdo->exec(
            'ALTER TABLE training_resources
             ADD COLUMN document_id BIGINT UNSIGNED NULL DEFAULT NULL AFTER file_size,
             ADD KEY idx_training_resources_document (document_id),
             ADD CONSTRAINT fk_training_resources_document
               FOREIGN KEY (document_id) REFERENCES documents (id) ON DELETE SET NULL ON UPDATE CASCADE'
        );
    } catch (PDOException $e) {
        echo '  [ATTENTION] training_resources document_id : ' . $e->getMessage() . "\n";
    }
};
