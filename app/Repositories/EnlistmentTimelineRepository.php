<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Core\Database;
use PDO;
use Throwable;

/**
 * Journal chronologique d’un dossier de candidature (événements automatiques + notes internes).
 */
final class EnlistmentTimelineRepository
{
    private PDO $pdo;

    private static ?bool $tableReady = null;

    public function __construct()
    {
        $this->pdo = Database::getPdo();
    }

    public function tableExists(): bool
    {
        if (self::$tableReady === null) {
            $stmt = $this->pdo->query("SELECT 1 FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'enlistment_timeline_entries' LIMIT 1");
            self::$tableReady = (bool) $stmt?->fetchColumn();
        }

        return self::$tableReady;
    }

    /**
     * @param array<string, mixed>|null $metadata
     */
    public function append(
        int $tenantId,
        int $enlistmentId,
        string $entryKind,
        string $stepCode,
        ?string $summary,
        ?string $body,
        ?int $actorUserId,
        ?array $metadata,
        ?string $createdAt = null
    ): void {
        if (!$this->tableExists() || $tenantId < 1 || $enlistmentId < 1) {
            return;
        }
        $kind = $entryKind === 'staff_note' ? 'staff_note' : 'system';
        $step = preg_match('/^[a-z0-9_]{1,40}$/', $stepCode) ? $stepCode : 'general';
        $metaJson = $metadata !== null && $metadata !== [] ? json_encode($metadata, JSON_UNESCAPED_UNICODE) : null;
        $createdAtSql = $createdAt !== null && $createdAt !== '' ? $createdAt : null;

        if ($createdAtSql !== null) {
            $stmt = $this->pdo->prepare(
                'INSERT INTO enlistment_timeline_entries
                 (tenant_id, enlistment_id, entry_kind, step_code, summary, body, actor_user_id, metadata, created_at)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)'
            );
            $stmt->execute([
                $tenantId,
                $enlistmentId,
                $kind,
                $step,
                $summary,
                $body,
                $actorUserId !== null && $actorUserId > 0 ? $actorUserId : null,
                $metaJson,
                $createdAtSql,
            ]);
        } else {
            $stmt = $this->pdo->prepare(
                'INSERT INTO enlistment_timeline_entries
                 (tenant_id, enlistment_id, entry_kind, step_code, summary, body, actor_user_id, metadata)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?)'
            );
            $stmt->execute([
                $tenantId,
                $enlistmentId,
                $kind,
                $step,
                $summary,
                $body,
                $actorUserId !== null && $actorUserId > 0 ? $actorUserId : null,
                $metaJson,
            ]);
        }
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function listForEnlistment(int $tenantId, int $enlistmentId): array
    {
        if (!$this->tableExists() || $tenantId < 1 || $enlistmentId < 1) {
            return [];
        }
        $stmt = $this->pdo->prepare(
            'SELECT id, tenant_id, enlistment_id, entry_kind, step_code, summary, body, actor_user_id, metadata, created_at
             FROM enlistment_timeline_entries
             WHERE tenant_id = ? AND enlistment_id = ?
             ORDER BY created_at ASC, id ASC'
        );
        $stmt->execute([$tenantId, $enlistmentId]);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

        foreach ($rows as &$row) {
            if (!empty($row['metadata']) && is_string($row['metadata'])) {
                $d = json_decode($row['metadata'], true);
                $row['metadata'] = is_array($d) ? $d : null;
            } elseif (!is_array($row['metadata'] ?? null)) {
                $row['metadata'] = null;
            }
        }
        unset($row);

        return $rows;
    }

    /**
     * Dossiers ayant déclenché la modération automatique du portail (événements les plus récents en premier).
     *
     * @return list<array<string, mixed>>
     */
    public function listRecentPortalAutomodForTenant(int $tenantId, int $limit = 50): array
    {
        if (!$this->tableExists() || $tenantId < 1) {
            return [];
        }
        $lim = max(1, min(120, $limit));
        $stmt = $this->pdo->prepare(
            "SELECT e.id AS enlistment_id, e.email, e.status AS enlistment_status, e.created_at AS enlistment_created_at,
                    t.id AS timeline_entry_id, t.created_at AS mod_at, t.body, t.metadata
             FROM enlistment_timeline_entries t
             INNER JOIN enlistments e ON e.id = t.enlistment_id AND e.tenant_id = t.tenant_id
             WHERE t.tenant_id = ? AND t.summary = 'Modération automatique du portail'
             ORDER BY t.created_at DESC, t.id DESC
             LIMIT {$lim}"
        );
        $stmt->execute([$tenantId]);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
        foreach ($rows as &$row) {
            if (!empty($row['metadata']) && is_string($row['metadata'])) {
                $d = json_decode($row['metadata'], true);
                $row['metadata'] = is_array($d) ? $d : null;
            } else {
                $row['metadata'] = null;
            }
        }
        unset($row);

        return $rows;
    }

    /**
     * Reconstitue un journal minimal pour les dossiers créés avant la table (une seule fois, sous verrou).
     *
     * @param array<string, mixed> $enlistmentRow
     */
    public function seedLegacyIfEmpty(int $tenantId, int $enlistmentId, array $enlistmentRow): void
    {
        if (!$this->tableExists() || $tenantId < 1 || $enlistmentId < 1) {
            return;
        }
        try {
            $this->pdo->beginTransaction();
            $chk = $this->pdo->prepare(
                'SELECT COUNT(*) FROM enlistment_timeline_entries WHERE tenant_id = ? AND enlistment_id = ? FOR UPDATE'
            );
            $chk->execute([$tenantId, $enlistmentId]);
            if ((int) $chk->fetchColumn() > 0) {
                $this->pdo->commit();

                return;
            }

            $created = trim((string) ($enlistmentRow['created_at'] ?? ''));
            $createdSql = $created !== '' ? date('Y-m-d H:i:s', strtotime($created) ?: time()) : date('Y-m-d H:i:s');

            $via = trim((string) ($enlistmentRow['submitted_via'] ?? ''));
            $viaLabel = match ($via) {
                'account' => 'depuis un compte connecté',
                'guest' => 'en invité (sans compte au dépôt)',
                'preset' => 'via un modèle de profil enregistré',
                '' => 'canal non renseigné',
                default => 'canal : ' . $via,
            };
            $this->append(
                $tenantId,
                $enlistmentId,
                'system',
                'reception',
                'Dossier reçu sur le portail',
                'Enregistrement initial de la candidature (' . $viaLabel . ').',
                null,
                ['legacy_seed' => true],
                $createdSql
            );

            $status = (string) ($enlistmentRow['status'] ?? '');
            $reviewedAt = trim((string) ($enlistmentRow['reviewed_at'] ?? ''));
            $reviewerId = (int) ($enlistmentRow['reviewed_by'] ?? 0);
            $comment = isset($enlistmentRow['reviewer_comment']) ? trim((string) $enlistmentRow['reviewer_comment']) : '';
            $comment = $comment !== '' ? $comment : null;

            if ($status !== 'submitted' && $reviewedAt !== '') {
                $ts = strtotime($reviewedAt);
                $decisionSql = $ts !== false && $ts > 0 ? date('Y-m-d H:i:s', $ts) : $createdSql;
                $summary = match ($status) {
                    'reviewed' => 'Candidature acceptée',
                    'rejected' => 'Candidature refusée',
                    'blocked' => 'Candidature classée sans suite (non admis)',
                    default => 'Statut mis à jour',
                };
                $this->append(
                    $tenantId,
                    $enlistmentId,
                    'system',
                    'decision',
                    $summary,
                    $comment,
                    $reviewerId > 0 ? $reviewerId : null,
                    ['legacy_seed' => true, 'to_status' => $status],
                    $decisionSql
                );
            }

            $this->pdo->commit();
        } catch (Throwable) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
        }
    }

    public function logIntakeFromSubmission(int $tenantId, int $enlistmentId, ?int $submitterUserId, string $submittedVia): void
    {
        $v = trim($submittedVia);
        $viaLabel = match ($v) {
            'account' => 'depuis un compte connecté',
            'guest' => 'en invité (sans compte au dépôt)',
            'preset' => 'via un modèle de profil enregistré',
            '' => 'invité',
            default => $v,
        };
        $this->append(
            $tenantId,
            $enlistmentId,
            'system',
            'reception',
            'Dossier reçu sur le portail',
            'La candidature est enregistrée et visible dans la file d’attente (' . $viaLabel . ').',
            $submitterUserId !== null && $submitterUserId > 0 ? $submitterUserId : null,
            ['submitted_via' => $submittedVia]
        );
    }

    public function logDecision(int $tenantId, int $enlistmentId, int $actorUserId, string $newStatus, ?string $reviewerComment): void
    {
        $summary = match ($newStatus) {
            'reviewed' => 'Candidature acceptée',
            'rejected' => 'Candidature refusée',
            'blocked' => 'Candidature classée sans suite (non admis)',
            default => 'Décision enregistrée',
        };
        $this->append(
            $tenantId,
            $enlistmentId,
            'system',
            'decision',
            $summary,
            $reviewerComment,
            $actorUserId,
            ['to_status' => $newStatus]
        );
    }

    public function logAdhesionStep(int $tenantId, int $enlistmentId, int $actorUserId, string $summary, ?string $detail): void
    {
        $this->append(
            $tenantId,
            $enlistmentId,
            'system',
            'adhesion',
            $summary,
            $detail,
            $actorUserId,
            null
        );
    }
}
