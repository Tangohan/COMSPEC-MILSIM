<?php

declare(strict_types=1);

/**
 * Chaînon Formation → Qualification.
 *
 * `personnel_qualifications` existait mais n'était jamais alimentée : la table n'avait
 * aucun lien vers le LMS, et aucun chemin de code n'y écrivait. Une formation certifiante
 * produisait donc un certificat sans qualification exploitable au dossier.
 *
 * Cette migration ajoute :
 *   - tenant_id               : isolation et requêtes par communauté (jusqu'ici filtrage
 *                               indirect par user_id uniquement) ;
 *   - training_course_id      : la formation d'origine ;
 *   - training_certificate_id : le certificat qui a produit la qualification ;
 *   - source                  : distingue une saisie manuelle d'une émission automatique ;
 *   - index unique sur training_certificate_id : rend l'émission idempotente (un certificat
 *     ne peut produire qu'une seule qualification, même si issueCertificate est rejoué).
 *
 * Idempotent : rejouable sans effet de bord.
 *
 * @return callable(PDO): void
 */
return static function (PDO $pdo): void {
    require_once __DIR__ . '/schema_ensure_column.php';

    if (!schema_table_exists($pdo, 'personnel_qualifications')) {
        echo "  [ATTENTION] personnel_qualifications absente — skip lien formation\n";

        return;
    }

    schema_ensure_column(
        $pdo,
        'personnel_qualifications',
        'tenant_id',
        '`tenant_id` int(10) UNSIGNED DEFAULT NULL AFTER `id`'
    );
    schema_ensure_column(
        $pdo,
        'personnel_qualifications',
        'training_course_id',
        '`training_course_id` int(10) UNSIGNED DEFAULT NULL AFTER `qualification_name`'
    );
    schema_ensure_column(
        $pdo,
        'personnel_qualifications',
        'training_certificate_id',
        '`training_certificate_id` int(10) UNSIGNED DEFAULT NULL AFTER `training_course_id`'
    );
    schema_ensure_column(
        $pdo,
        'personnel_qualifications',
        'source',
        "`source` varchar(20) NOT NULL DEFAULT 'manual' AFTER `training_certificate_id`"
    );

    // Renseigner tenant_id pour les lignes historiques éventuelles (saisies manuelles).
    try {
        $pdo->exec(
            'UPDATE personnel_qualifications pq
             INNER JOIN users u ON u.id = pq.user_id
             SET pq.tenant_id = u.tenant_id
             WHERE pq.tenant_id IS NULL'
        );
    } catch (Throwable $e) {
        echo '  [ATTENTION] backfill tenant_id personnel_qualifications : ' . $e->getMessage() . "\n";
    }

    $hasIndex = static function (PDO $pdo, string $table, string $index): bool {
        $st = $pdo->prepare(
            'SELECT 1 FROM information_schema.STATISTICS
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND INDEX_NAME = ? LIMIT 1'
        );
        $st->execute([$table, $index]);

        return (bool) $st->fetchColumn();
    };

    // Un certificat ne produit qu'une qualification : garantit l'idempotence de l'émission.
    if (!$hasIndex($pdo, 'personnel_qualifications', 'uq_pq_training_certificate')) {
        try {
            $pdo->exec(
                'ALTER TABLE personnel_qualifications
                 ADD UNIQUE KEY uq_pq_training_certificate (training_certificate_id)'
            );
            echo "  [OK] personnel_qualifications.uq_pq_training_certificate\n";
        } catch (Throwable $e) {
            echo '  [ATTENTION] uq_pq_training_certificate : ' . $e->getMessage() . "\n";
        }
    }

    if (!$hasIndex($pdo, 'personnel_qualifications', 'idx_pq_tenant_user')) {
        try {
            $pdo->exec(
                'ALTER TABLE personnel_qualifications
                 ADD KEY idx_pq_tenant_user (tenant_id, user_id)'
            );
            echo "  [OK] personnel_qualifications.idx_pq_tenant_user\n";
        } catch (Throwable $e) {
            echo '  [ATTENTION] idx_pq_tenant_user : ' . $e->getMessage() . "\n";
        }
    }

    // Recherche des expirations à venir par communauté (tableau de bord des recyclages).
    if (!$hasIndex($pdo, 'personnel_qualifications', 'idx_pq_tenant_expires')) {
        try {
            $pdo->exec(
                'ALTER TABLE personnel_qualifications
                 ADD KEY idx_pq_tenant_expires (tenant_id, expires_at)'
            );
            echo "  [OK] personnel_qualifications.idx_pq_tenant_expires\n";
        } catch (Throwable $e) {
            echo '  [ATTENTION] idx_pq_tenant_expires : ' . $e->getMessage() . "\n";
        }
    }
};
