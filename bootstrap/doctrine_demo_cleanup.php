<?php

declare(strict_types=1);

use App\Support\Doctrine\DoctrineDemoCatalog;

/**
 * Archive les doctrines de démonstration historiques (idempotent).
 *
 * Cible uniquement les paires référence + titre/slug du catalogue
 * bootstrap/doctrine_demo_seed.php. Ne touche pas SIC/ATAK/2026-001,
 * ni les médias pédagogiques (ex. bibliothèque JTAC), ni un document
 * dont la référence ou le titre ne correspond pas au seed.
 *
 * @return callable(PDO): int nombre de documents archivés
 */
return static function (PDO $pdo): int {
    $classFile = dirname(__DIR__) . '/app/Support/Doctrine/DoctrineDemoCatalog.php';
    if (!class_exists(DoctrineDemoCatalog::class) && is_file($classFile)) {
        require_once $classFile;
    }
    if (!class_exists(DoctrineDemoCatalog::class)) {
        echo "  [ATTENTION] doctrine_demo_cleanup : catalogue introuvable.\n";

        return 0;
    }

    $tableExists = static function (PDO $pdo, string $table): bool {
        try {
            $st = $pdo->prepare(
                'SELECT 1 FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? LIMIT 1'
            );
            $st->execute([$table]);

            return (bool) $st->fetchColumn();
        } catch (Throwable) {
            return false;
        }
    };

    if (!$tableExists($pdo, 'document_doctrines') || !$tableExists($pdo, 'documents')) {
        return 0;
    }

    $targets = DoctrineDemoCatalog::removeTargets();
    if ($targets === []) {
        return 0;
    }

    echo "Doctrine référentiel : retrait des documents de démonstration…\n";

    $select = $pdo->prepare(
        'SELECT dd.id AS doctrine_id, dd.document_id, dd.reference_code, dd.doctrine_status,
                d.title, d.slug, d.status AS document_status
         FROM document_doctrines dd
         INNER JOIN documents d ON d.id = dd.document_id
         WHERE dd.reference_code = ?'
    );
    $archiveDoc = $pdo->prepare(
        "UPDATE documents SET status = 'archived', updated_at = NOW()
         WHERE id = ? AND status <> 'archived'"
    );
    $archiveDoctrine = $pdo->prepare(
        "UPDATE document_doctrines SET doctrine_status = 'archived', updated_at = NOW()
         WHERE id = ? AND doctrine_status <> 'archived'"
    );

    $archived = 0;
    foreach ($targets as $target) {
        $select->execute([$target['reference']]);
        $rows = $select->fetchAll(PDO::FETCH_ASSOC) ?: [];
        foreach ($rows as $row) {
            $ref = (string) ($row['reference_code'] ?? '');
            $title = (string) ($row['title'] ?? '');
            $slug = (string) ($row['slug'] ?? '');
            if (!DoctrineDemoCatalog::isRemoveTarget($ref, $title, $slug)) {
                continue;
            }
            $documentId = (int) ($row['document_id'] ?? 0);
            $doctrineId = (int) ($row['doctrine_id'] ?? 0);
            if ($documentId < 1 || $doctrineId < 1) {
                continue;
            }
            $changed = false;
            if ((string) ($row['document_status'] ?? '') !== 'archived') {
                $archiveDoc->execute([$documentId]);
                $changed = $changed || $archiveDoc->rowCount() > 0;
            }
            if ((string) ($row['doctrine_status'] ?? '') !== 'archived') {
                $archiveDoctrine->execute([$doctrineId]);
                $changed = $changed || $archiveDoctrine->rowCount() > 0;
            }
            if ($changed) {
                $archived++;
            }
        }
    }

    if ($archived > 0) {
        echo '  [OK] doctrine_demo_cleanup : ' . $archived . " document(s) de démonstration archivé(s).\n";
    }

    return $archived;
};
