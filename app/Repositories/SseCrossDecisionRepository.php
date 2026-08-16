<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Core\Database;
use App\Support\SilentSchemaMigration;

/**
 * Décisions d'opérateur sur les rapprochements proposés.
 *
 * Une proposition automatique reste une hypothèse : c'est la décision consignée ici,
 * avec son auteur et sa justification, qui fait foi dans le dossier d'intérêt.
 */
final class SseCrossDecisionRepository
{
    public const CONFIRMED = 'confirme';
    public const SEPARATE = 'separe';
    public const FURTHER = 'complement';

    /** @var array<string, string> */
    public const DECISION_LABELS = [
        self::CONFIRMED => 'Rapprochement confirmé',
        self::SEPARATE => 'Maintenu séparé',
        self::FURTHER => 'Analyse complémentaire demandée',
    ];

    public function __construct(private ?Database $db = null)
    {
        $this->db ??= Database::getInstance();
        SilentSchemaMigration::run(base_path('bootstrap/atak_sse_cross_decisions_migration.php'));
    }

    public static function isDecision(string $key): bool
    {
        return isset(self::DECISION_LABELS[$key]);
    }

    public static function decisionLabel(string $key): string
    {
        return self::DECISION_LABELS[$key] ?? 'Décision inconnue';
    }

    /**
     * Décisions du dossier, indexées « identité:entrée » pour un rapprochement direct
     * avec les propositions recalculées à chaque ouverture.
     *
     * @return array<string, array<string, mixed>>
     */
    public function mapForCase(int $tenantId, int $interestCaseId): array
    {
        try {
            $rows = $this->db->fetchAll(
                'SELECT * FROM sse_cross_decisions WHERE tenant_id = :t AND interest_case_id = :c ORDER BY id ASC',
                ['t' => $tenantId, 'c' => $interestCaseId]
            );
        } catch (\Throwable) {
            return [];
        }

        $out = [];
        foreach ($rows as $row) {
            $row['decision_label'] = self::decisionLabel((string) ($row['decision'] ?? ''));
            $out[(int) $row['person_id'] . ':' . (int) $row['entry_id']] = $row;
        }

        return $out;
    }

    /**
     * @param array<string, mixed> $data
     */
    public function record(int $tenantId, int $interestCaseId, array $data): bool
    {
        $decision = (string) ($data['decision'] ?? '');
        $personId = (int) ($data['person_id'] ?? 0);
        $entryId = (int) ($data['entry_id'] ?? 0);
        if (!self::isDecision($decision) || $personId < 1 || $entryId < 1) {
            return false;
        }

        try {
            $this->db->execute(
                'INSERT INTO sse_cross_decisions
                    (tenant_id, interest_case_id, person_id, entry_id, decision, score, reason, note, author_label, decided_by)
                 VALUES (:t, :c, :p, :e, :d, :s, :r, :n, :a, :u)
                 ON DUPLICATE KEY UPDATE
                    decision = VALUES(decision), score = VALUES(score), reason = VALUES(reason),
                    note = VALUES(note), author_label = VALUES(author_label), decided_by = VALUES(decided_by)',
                [
                    't' => $tenantId,
                    'c' => $interestCaseId,
                    'p' => $personId,
                    'e' => $entryId,
                    'd' => $decision,
                    's' => max(0, min(100, (int) ($data['score'] ?? 0))),
                    'r' => ($data['reason'] ?? null) ?: null,
                    'n' => ($data['note'] ?? null) ?: null,
                    'a' => ($data['author_label'] ?? null) ?: null,
                    'u' => ((int) ($data['decided_by'] ?? 0)) ?: null,
                ]
            );

            return true;
        } catch (\Throwable) {
            return false;
        }
    }

    /** Rouvre une proposition : la décision est retirée, la proposition redevient à traiter. */
    public function clear(int $tenantId, int $interestCaseId, int $personId, int $entryId): bool
    {
        try {
            return $this->db->execute(
                'DELETE FROM sse_cross_decisions
                 WHERE tenant_id = :t AND interest_case_id = :c AND person_id = :p AND entry_id = :e',
                ['t' => $tenantId, 'c' => $interestCaseId, 'p' => $personId, 'e' => $entryId]
            ) > 0;
        } catch (\Throwable) {
            return false;
        }
    }
}
