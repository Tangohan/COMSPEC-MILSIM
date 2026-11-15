<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Core\Database;
use App\Support\ShortCache;
use PDO;

/**
 * Règles de droits d'accès du portail SSE (tables sse_access_rules et sse_access_rule_history, étape sse_access_rules).
 *
 * Tolère l'absence des tables (communautés dont l'étape n'est pas passée) : lectures vides, écritures sans effet, jamais
 * d'exception. Toute requête est bornée par communauté. Aucune méthode ne décide d'un droit : c'est SseAccessPolicy.
 *
 * Les règles non retirées d'une communauté sont gardées quelques secondes dans ShortCache (clé par communauté, génération
 * relevée à chaque écriture) ; les dates de début et de fin ne sont jamais filtrées ici, la décision le fait à l'heure
 * de la requête.
 */
class SseAccessRuleRepository
{
    public const CACHE_NS = 'sse_access_rules';
    private const CACHE_TTL = 20;

    private ?bool $rulesReady = null;
    private ?bool $historyReady = null;

    /** @var array<int, list<array<string, mixed>>> */
    private array $memo = [];

    public function __construct(private ?PDO $pdo = null)
    {
    }

    private function pdo(): PDO
    {
        return $this->pdo ??= Database::getPdo();
    }

    public function ready(): bool
    {
        if ($this->rulesReady === null) {
            try {
                $this->pdo()->query('SELECT 1 FROM sse_access_rules LIMIT 1');
                $this->rulesReady = true;
            } catch (\Throwable) {
                $this->rulesReady = false;
            }
        }

        return $this->rulesReady;
    }

    public function historyReady(): bool
    {
        if ($this->historyReady === null) {
            try {
                $this->pdo()->query('SELECT 1 FROM sse_access_rule_history LIMIT 1');
                $this->historyReady = true;
            } catch (\Throwable) {
                $this->historyReady = false;
            }
        }

        return $this->historyReady;
    }

    /** Efface la mémoire de la requête et rend périmées les copies partagées de la communauté. */
    public function forget(int $tenantId): void
    {
        unset($this->memo[$tenantId]);
        ShortCache::bump(self::CACHE_NS . ':' . $tenantId);
    }

    /**
     * Règles non retirées de la communauté (dates non filtrées), mémorisées pour la requête.
     *
     * @param bool $fresh lire la base sans passer par aucune copie (planification d'une écriture)
     * @return list<array<string, mixed>>
     */
    public function liveRules(int $tenantId, bool $fresh = false): array
    {
        if ($tenantId < 1 || !$this->ready()) {
            return [];
        }
        if ($fresh) {
            // Lecture directe de la base : ni la mémoire de la requête ni la copie partagée (planifier, c'est décider d'écrire).
            return $this->memo[$tenantId] = $this->loadLive($tenantId);
        }
        if (!isset($this->memo[$tenantId])) {
            $key = self::CACHE_NS . ':' . $tenantId . ':g' . ShortCache::gen(self::CACHE_NS . ':' . $tenantId);
            $this->memo[$tenantId] = ShortCache::remember($key, self::CACHE_TTL, fn (): array => $this->loadLive($tenantId));
        }

        return $this->memo[$tenantId];
    }

    /** @return list<array<string, mixed>> */
    private function loadLive(int $tenantId): array
    {
        try {
            $st = $this->pdo()->prepare(
                'SELECT id, tenant_id, section, level, subject_type, subject_id, effect, starts_at, ends_at, reason, source, created_by, created_at
                 FROM sse_access_rules WHERE tenant_id = ? AND revoked_at IS NULL ORDER BY id ASC LIMIT 5000'
            );
            $st->execute([$tenantId]);
        } catch (\Throwable) {
            return [];
        }
        $out = [];
        foreach ($st->fetchAll(PDO::FETCH_ASSOC) ?: [] as $r) {
            $out[] = self::normalize($r);
        }

        return $out;
    }

    /** @return array<string, mixed>|null */
    public function find(int $tenantId, int $id): ?array
    {
        if ($tenantId < 1 || $id < 1 || !$this->ready()) {
            return null;
        }
        try {
            $st = $this->pdo()->prepare(
                'SELECT id, tenant_id, section, level, subject_type, subject_id, effect, starts_at, ends_at, reason, source, created_by, created_at, revoked_at, revoked_by
                 FROM sse_access_rules WHERE tenant_id = ? AND id = ? LIMIT 1'
            );
            $st->execute([$tenantId, $id]);
            $row = $st->fetch(PDO::FETCH_ASSOC);
        } catch (\Throwable) {
            return null;
        }

        return is_array($row) ? self::normalize($row) : null;
    }

    public function countLive(int $tenantId): int
    {
        return count($this->liveRules($tenantId));
    }

    /**
     * @param array{section: string, level: string, subject_type: string, subject_id: int, effect: string, starts_at: ?string, ends_at: ?string, reason: string, source: string} $rule
     * @return int identifiant créé, 0 si rien n'a pu être écrit
     */
    public function insert(int $tenantId, array $rule, int $createdBy, string $now): int
    {
        if ($tenantId < 1 || !$this->ready()) {
            return 0;
        }
        try {
            $this->pdo()->prepare(
                'INSERT INTO sse_access_rules (tenant_id, section, level, subject_type, subject_id, effect, starts_at, ends_at, reason, source, created_by, created_at)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
            )->execute([
                $tenantId, $rule['section'], $rule['level'], $rule['subject_type'], $rule['subject_id'], $rule['effect'],
                $rule['starts_at'], $rule['ends_at'], $rule['reason'], $rule['source'], $createdBy, $now,
            ]);
            $id = (int) $this->pdo()->lastInsertId();
        } catch (\Throwable) {
            return 0;
        }
        $this->forget($tenantId);

        return $id;
    }

    /** Retire une règle (elle reste en base) ; faux si elle n'existe pas, n'est pas à cette communauté ou est déjà retirée. */
    public function revoke(int $tenantId, int $id, int $by, string $now): bool
    {
        if ($tenantId < 1 || $id < 1 || !$this->ready()) {
            return false;
        }
        try {
            $st = $this->pdo()->prepare(
                'UPDATE sse_access_rules SET revoked_at = ?, revoked_by = ? WHERE tenant_id = ? AND id = ? AND revoked_at IS NULL'
            );
            $st->execute([$now, $by, $tenantId, $id]);
            $done = $st->rowCount() > 0;
        } catch (\Throwable) {
            return false;
        }
        if ($done) {
            $this->forget($tenantId);
        }

        return $done;
    }

    /**
     * @param array<string, mixed> $row
     */
    public function addHistory(int $tenantId, string $action, int $actorUserId, string $actorLabel, array $row, string $now): void
    {
        if ($tenantId < 1 || !$this->historyReady()) {
            return;
        }
        try {
            $this->pdo()->prepare(
                'INSERT INTO sse_access_rule_history (tenant_id, rule_id, action, actor_user_id, actor_label, section, level, subject_type, subject_id, subject_label, effect, starts_at, ends_at, reason, created_at)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
            )->execute([
                $tenantId,
                isset($row['rule_id']) && (int) $row['rule_id'] > 0 ? (int) $row['rule_id'] : null,
                mb_substr($action, 0, 16),
                $actorUserId,
                mb_substr($actorLabel, 0, 120),
                (string) ($row['section'] ?? ''),
                (string) ($row['level'] ?? ''),
                (string) ($row['subject_type'] ?? ''),
                (int) ($row['subject_id'] ?? 0),
                mb_substr((string) ($row['subject_label'] ?? ''), 0, 160),
                (string) ($row['effect'] ?? ''),
                isset($row['starts_at']) && $row['starts_at'] !== '' ? (string) $row['starts_at'] : null,
                isset($row['ends_at']) && $row['ends_at'] !== '' ? (string) $row['ends_at'] : null,
                mb_substr((string) ($row['reason'] ?? ''), 0, 500),
                $now,
            ]);
        } catch (\Throwable) {
            // L'historique ne doit jamais empêcher un changement déjà journalisé dans l'audit SSE.
        }
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function history(int $tenantId, int $limit = 100, ?int $ruleId = null): array
    {
        if ($tenantId < 1 || !$this->historyReady()) {
            return [];
        }
        $limit = max(1, min(300, $limit));
        try {
            if ($ruleId !== null && $ruleId > 0) {
                $st = $this->pdo()->prepare('SELECT * FROM sse_access_rule_history WHERE tenant_id = ? AND rule_id = ? ORDER BY id DESC LIMIT ' . $limit);
                $st->execute([$tenantId, $ruleId]);
            } else {
                $st = $this->pdo()->prepare('SELECT * FROM sse_access_rule_history WHERE tenant_id = ? ORDER BY id DESC LIMIT ' . $limit);
                $st->execute([$tenantId]);
            }
            $rows = $st->fetchAll(PDO::FETCH_ASSOC) ?: [];
        } catch (\Throwable) {
            return [];
        }
        $out = [];
        foreach ($rows as $r) {
            $out[] = [
                'id' => (int) ($r['id'] ?? 0),
                'rule_id' => isset($r['rule_id']) ? (int) $r['rule_id'] : null,
                'action' => (string) ($r['action'] ?? ''),
                'actor_user_id' => (int) ($r['actor_user_id'] ?? 0),
                'actor_label' => (string) ($r['actor_label'] ?? ''),
                'section' => (string) ($r['section'] ?? ''),
                'level' => (string) ($r['level'] ?? ''),
                'subject_type' => (string) ($r['subject_type'] ?? ''),
                'subject_id' => (int) ($r['subject_id'] ?? 0),
                'subject_label' => (string) ($r['subject_label'] ?? ''),
                'effect' => (string) ($r['effect'] ?? ''),
                'starts_at' => isset($r['starts_at']) && $r['starts_at'] !== null ? (string) $r['starts_at'] : null,
                'ends_at' => isset($r['ends_at']) && $r['ends_at'] !== null ? (string) $r['ends_at'] : null,
                'reason' => (string) ($r['reason'] ?? ''),
                'created_at' => (string) ($r['created_at'] ?? ''),
            ];
        }

        return $out;
    }

    /**
     * Historique des changements qui concernent un bénéficiaire (identité d'un membre, position, qualification…), du plus récent au plus ancien.
     *
     * @return list<array<string, mixed>>
     */
    public function historyForSubject(int $tenantId, string $type, int $id, int $limit = 30): array
    {
        if ($tenantId < 1 || $id < 1 || !$this->historyReady()) {
            return [];
        }
        // Lecture bornée à la communauté puis filtrée ici : l'historique d'une communauté reste de quelques centaines de lignes.
        $out = [];
        foreach ($this->history($tenantId, 300) as $row) {
            if ($row['subject_type'] === $type && $row['subject_id'] === $id) {
                $out[] = $row;
                if (count($out) >= max(1, min(100, $limit))) {
                    break;
                }
            }
        }

        return $out;
    }

    /**
     * @param array<string, mixed> $r
     * @return array<string, mixed>
     */
    private static function normalize(array $r): array
    {
        return [
            'id' => (int) ($r['id'] ?? 0),
            'tenant_id' => (int) ($r['tenant_id'] ?? 0),
            'section' => (string) ($r['section'] ?? ''),
            'level' => (string) ($r['level'] ?? ''),
            'subject_type' => (string) ($r['subject_type'] ?? ''),
            'subject_id' => (int) ($r['subject_id'] ?? 0),
            'effect' => (string) ($r['effect'] ?? ''),
            'starts_at' => isset($r['starts_at']) && $r['starts_at'] !== null && $r['starts_at'] !== '' ? (string) $r['starts_at'] : null,
            'ends_at' => isset($r['ends_at']) && $r['ends_at'] !== null && $r['ends_at'] !== '' ? (string) $r['ends_at'] : null,
            'reason' => (string) ($r['reason'] ?? ''),
            'source' => (string) ($r['source'] ?? ''),
            'created_by' => (int) ($r['created_by'] ?? 0),
            'created_at' => (string) ($r['created_at'] ?? ''),
            'revoked_at' => isset($r['revoked_at']) && $r['revoked_at'] !== null && $r['revoked_at'] !== '' ? (string) $r['revoked_at'] : null,
        ];
    }
}
