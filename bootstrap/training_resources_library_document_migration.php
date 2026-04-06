<?php

declare(strict_types=1);

/**
 * training_resources.library_document_id — lien optionnel vers documents (bibliothèque), idempotent.
 */
return function (PDO $pdo): void {
    $chk = $pdo->query("SELECT TABLE_NAME FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'training_resources' LIMIT 1");
    if (!$chk || !$chk->fetch()) {
        echo "[ATTENTION] training_resources_library_document : table training_resources absente — ignoré.\n";

        return;
    }
    $docChk = $pdo->query("SELECT TABLE_NAME FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'documents' LIMIT 1");
    if (!$docChk || !$docChk->fetch()) {
        echo "[ATTENTION] training_resources_library_document : table documents absente — ignoré.\n";

        return;
    }

    $q = $pdo->query('SELECT COLUMN_NAME FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ' . $pdo->quote('training_resources') . ' AND COLUMN_NAME = ' . $pdo->quote('library_document_id') . ' LIMIT 1');
    if ($q && $q->fetch()) {
        return;
    }

    echo "training_resources : ajout colonne library_document_id + clé étrangère...\n";
    try {
        $pdo->exec(
            'ALTER TABLE training_resources
             ADD COLUMN library_document_id INT UNSIGNED NULL DEFAULT NULL AFTER file_size,
             ADD KEY idx_training_resources_library_document (library_document_id),
             ADD CONSTRAINT fk_training_resources_library_document
               FOREIGN KEY (library_document_id) REFERENCES documents (id) ON DELETE SET NULL ON UPDATE CASCADE'
        );
    } catch (PDOException $e) {
        echo '  [ATTENTION] training_resources library_document_id : ' . $e->getMessage() . "\n";
    }
};
