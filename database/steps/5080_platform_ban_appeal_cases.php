<?php

declare(strict_types=1);

use App\Repositories\PlatformBanAppealRepository;
use App\Support\Migrations\SchemaInspector;

/**
 * Étape « Contestations de bannissement : dossiers, références et fil d'échange » (Schéma).
 *
 * Exécutée par run-migrations.php (file active) avec $pdo et $root. Idempotente : colonnes, index et tables ajoutés
 * seulement s'ils manquent. Rattrapage des contestations existantes : une référence CTS-AA-NNNN, un statut et le fil
 * (contestation puis réponse) ne sont posés que pour les dossiers qui n'en ont pas encore.
 * Retour arrière : migrations/rollback/platform_ban_appeal_cases_down.sql.
 */

$caseSchema = new SchemaInspector($pdo);
if (!$caseSchema->tableExists('platform_ban_appeals')) {
    echo "  [OK] platform_ban_appeals absente : étape sans objet.\n";

    return;
}

foreach ([
    'reference' => 'VARCHAR(20) DEFAULT NULL',
    'status' => "VARCHAR(16) NOT NULL DEFAULT 'received'",
    'assigned_to' => 'INT UNSIGNED DEFAULT NULL',
    'unread_staff' => 'TINYINT(1) NOT NULL DEFAULT 0',
    'updated_at' => 'DATETIME DEFAULT NULL',
] as $caseColumn => $caseDefinition) {
    if ($caseSchema->addColumnIfMissing('platform_ban_appeals', $caseColumn, $caseDefinition)) {
        echo '  [OK] platform_ban_appeals.' . $caseColumn . " ajoutée.\n";
    }
}
if (!$caseSchema->indexExists('platform_ban_appeals', 'uq_pba_reference')) {
    $pdo->exec('CREATE UNIQUE INDEX uq_pba_reference ON platform_ban_appeals (reference)');
    echo "  [OK] Référence unique indexée.\n";
}
$caseSchema->addIndexIfMissing('platform_ban_appeals', 'idx_pba_queue', ['status', 'unread_staff', 'created_at']);

if (!$caseSchema->tableExists('platform_ban_appeal_counters')) {
    $pdo->exec(
        "CREATE TABLE IF NOT EXISTS platform_ban_appeal_counters (
        period CHAR(2) NOT NULL,
        seq INT UNSIGNED NOT NULL DEFAULT 0,
        PRIMARY KEY (period)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
    );
}

if (!$caseSchema->tableExists('platform_ban_appeal_messages')) {
    $pdo->exec(
        "CREATE TABLE IF NOT EXISTS platform_ban_appeal_messages (
        id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
        ban_id INT UNSIGNED NOT NULL,
        author_type VARCHAR(8) NOT NULL,
        author_user_id INT UNSIGNED DEFAULT NULL,
        body TEXT NOT NULL,
        is_internal TINYINT(1) NOT NULL DEFAULT 0,
        created_at DATETIME NOT NULL,
        PRIMARY KEY (id),
        KEY idx_pbam_ban (ban_id, created_at),
        CONSTRAINT fk_pbam_ban FOREIGN KEY (ban_id) REFERENCES platform_ban_appeals(ban_id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
    );
}

// Rattrapage : un dossier sans référence reçoit la sienne, dans l'ordre de dépôt.
$caseRows = $pdo->query('SELECT * FROM platform_ban_appeals WHERE reference IS NULL ORDER BY created_at ASC, ban_id ASC')->fetchAll(PDO::FETCH_ASSOC);
$caseDone = 0;
foreach ($caseRows as $caseRow) {
    $caseYear = (int) substr((string) ($caseRow['created_at'] ?? gmdate('Y')), 0, 4) ?: (int) gmdate('Y');
    $caseReference = PlatformBanAppealRepository::allocateReference($pdo, $caseYear);
    $caseStatus = match ((string) ($caseRow['decision'] ?? '')) {
        'lift' => 'accepted',
        'uphold' => 'upheld',
        default => 'received',
    };
    $caseUnread = $caseStatus === 'received' ? 1 : 0;
    $pdo->prepare('UPDATE platform_ban_appeals SET reference = ?, status = ?, unread_staff = ' . $caseUnread . ', updated_at = ? WHERE ban_id = ? AND reference IS NULL')
        ->execute([$caseReference, $caseStatus, $caseRow['reviewed_at'] ?: $caseRow['created_at'], $caseRow['ban_id']]);
    $hasMessage = $pdo->prepare('SELECT COUNT(*) FROM platform_ban_appeal_messages WHERE ban_id = ?');
    $hasMessage->execute([$caseRow['ban_id']]);
    if ((int) $hasMessage->fetchColumn() === 0) {
        $insert = $pdo->prepare('INSERT INTO platform_ban_appeal_messages (ban_id, author_type, author_user_id, body, is_internal, created_at) VALUES (?, ?, ?, ?, 0, ?)');
        $insert->execute([$caseRow['ban_id'], 'user', null, (string) $caseRow['statement'], $caseRow['created_at']]);
        if (($caseRow['public_reply'] ?? null) !== null && (string) $caseRow['public_reply'] !== '') {
            $insert->execute([$caseRow['ban_id'], 'staff', $caseRow['reviewed_by'] ?: null, (string) $caseRow['public_reply'], $caseRow['reviewed_at'] ?: $caseRow['created_at']]);
        }
    }
    $caseDone++;
}
echo '  [OK] ' . $caseDone . " dossier(s) rattrapé(s).\n";
