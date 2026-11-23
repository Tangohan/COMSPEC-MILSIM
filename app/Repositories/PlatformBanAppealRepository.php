<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Core\Database;
use App\Support\SqlText;
use PDO;

/**
 * Contestations de bannissement : une par décision, avec une référence de dossier (CTS-AA-NNNN), un statut,
 * une assignation et un fil d'échange utilisateur et modération (notes internes comprises).
 *
 * Les colonnes et tables du dossier viennent de l'étape 5080 ; sans elle, le dépôt reste celui d'avant.
 */
final class PlatformBanAppealRepository
{
    public const STATUS_RECEIVED = 'received';
    public const STATUS_IN_REVIEW = 'in_review';
    public const STATUS_INFO_REQUESTED = 'info_requested';
    public const STATUS_ACCEPTED = 'accepted';
    public const STATUS_UPHELD = 'upheld';
    public const STATUS_CLOSED = 'closed';

    /** Un dossier est ouvert tant que la modération n'a pas tranché ni clos. */
    public const OPEN_STATUSES = [self::STATUS_RECEIVED, self::STATUS_IN_REVIEW, self::STATUS_INFO_REQUESTED];

    public const STATUSES = [
        self::STATUS_RECEIVED, self::STATUS_IN_REVIEW, self::STATUS_INFO_REQUESTED,
        self::STATUS_ACCEPTED, self::STATUS_UPHELD, self::STATUS_CLOSED,
    ];

    private const UPDATABLE = ['status', 'assigned_to', 'unread_staff', 'updated_at', 'decision', 'public_reply', 'reviewed_at', 'reviewed_by'];

    private ?bool $ready = null;
    private ?bool $casesReady = null;

    public function __construct(private ?PDO $pdo = null)
    {
    }

    private function db(): PDO
    {
        return $this->pdo ??= Database::getPdo();
    }

    public function ready(): bool
    {
        if ($this->ready === null) {
            try {
                $this->ready = $this->db()->query('SELECT ban_id FROM platform_ban_appeals WHERE 1 = 0') !== false
                    && $this->db()->query('SELECT token_hash FROM platform_ban_appeal_links WHERE 1 = 0') !== false;
            } catch (\PDOException) {
                $this->ready = false;
            }
        }

        return $this->ready;
    }

    /** Vrai quand l'étape « dossiers de contestation » est passée (référence, statut, fil d'échange). */
    public function casesReady(): bool
    {
        if ($this->casesReady === null) {
            try {
                $this->casesReady = $this->ready()
                    && $this->db()->query('SELECT reference, status, assigned_to, unread_staff, updated_at FROM platform_ban_appeals WHERE 1 = 0') !== false
                    && $this->db()->query('SELECT id, ban_id, author_type, body, is_internal FROM platform_ban_appeal_messages WHERE 1 = 0') !== false
                    && $this->db()->query('SELECT period, seq FROM platform_ban_appeal_counters WHERE 1 = 0') !== false;
            } catch (\PDOException) {
                $this->casesReady = false;
            }
        }

        return $this->casesReady;
    }

    public static function formatReference(int $year, int $sequence): string
    {
        return sprintf('CTS-%02d-%04d', $year % 100, $sequence);
    }

    /**
     * Réserve la prochaine référence de l'année : le compteur est incrémenté par une seule instruction qui verrouille
     * sa ligne, deux dépôts simultanés ne reçoivent donc jamais le même numéro (la colonne est de plus unique).
     */
    public static function allocateReference(PDO $pdo, int $year): string
    {
        $period = sprintf('%02d', $year % 100);
        $own = !$pdo->inTransaction();
        if ($own) {
            $pdo->beginTransaction();
        }
        try {
            $bump = $pdo->prepare('UPDATE platform_ban_appeal_counters SET seq = seq + 1 WHERE period = ?');
            $bump->execute([$period]);
            if ($bump->rowCount() === 0) {
                try {
                    $pdo->prepare('INSERT INTO platform_ban_appeal_counters (period, seq) VALUES (?, 1)')->execute([$period]);
                } catch (\PDOException $e) {
                    // Création simultanée par un autre dépôt : on incrémente sa ligne.
                    if ((string) $e->getCode() !== '23000') {
                        throw $e;
                    }
                    $bump->execute([$period]);
                }
            }
            $read = $pdo->prepare('SELECT seq FROM platform_ban_appeal_counters WHERE period = ?');
            $read->execute([$period]);
            $sequence = (int) $read->fetchColumn();
            if ($own) {
                $pdo->commit();
            }

            return self::formatReference($year, $sequence);
        } catch (\Throwable $e) {
            if ($own && $pdo->inTransaction()) {
                $pdo->rollBack();
            }

            throw $e;
        }
    }

    public function storeLink(int $banId, string $hash, int $expires): void
    {
        $this->db()->prepare('DELETE FROM platform_ban_appeal_links WHERE expires_at <= ?')->execute([gmdate('Y-m-d H:i:s')]);
        $this->db()->prepare('INSERT INTO platform_ban_appeal_links (token_hash, ban_id, expires_at) VALUES (?, ?, ?)')
            ->execute([$hash, $banId, gmdate('Y-m-d H:i:s', $expires)]);
    }

    /** @return array{ban_id: int, expires_at: string}|null */
    public function findValidLink(string $hash): ?array
    {
        $stmt = $this->db()->prepare('SELECT ban_id, expires_at FROM platform_ban_appeal_links WHERE token_hash = ? AND expires_at > ?');
        $stmt->execute([$hash, gmdate('Y-m-d H:i:s')]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return $row ?: null;
    }

    /** true : enregistré, false : déjà déposé, null : nouvelles contestations fermées. */
    public function submit(int $banId, string $statement): ?bool
    {
        $pdo = $this->db();
        $checkClosure = (new PlatformAccountBanRepository($pdo))->supportsAppealClosure();
        $cases = $this->casesReady();
        $transaction = $checkClosure || $cases;
        if ($transaction) {
            $pdo->beginTransaction();
        }
        try {
            if ($checkClosure) {
                // Le dépôt et la fermeture se sérialisent sur la même décision.
                $lock = SqlText::isMysqlFamily($pdo) ? ' FOR UPDATE' : '';
                $policy = $pdo->prepare('SELECT appeal_closed FROM platform_account_bans WHERE id = ?' . $lock);
                $policy->execute([$banId]);
                $closed = $policy->fetchColumn();
                if ($closed === false || (bool) $closed) {
                    return null;
                }
            }
            if ($transaction && $this->find($banId) !== null) {
                return false;
            }
            $now = gmdate('Y-m-d H:i:s');
            if ($cases) {
                $reference = self::allocateReference($pdo, (int) gmdate('Y'));
                $stmt = $pdo->prepare(
                    'INSERT INTO platform_ban_appeals (ban_id, statement, created_at, reference, status, unread_staff, updated_at) VALUES (?, ?, ?, ?, ?, 1, ?)'
                );
                $stmt->execute([$banId, $statement, $now, $reference, self::STATUS_RECEIVED, $now]);
                $this->addMessage($banId, 'user', null, $statement);
            } else {
                $stmt = $pdo->prepare('INSERT INTO platform_ban_appeals (ban_id, statement, created_at) VALUES (?, ?, ?)');
                $stmt->execute([$banId, $statement, $now]);
            }
            if ($transaction) {
                $pdo->commit();
            }

            return true;
        } catch (\PDOException $e) {
            // Un formulaire soumis deux fois garde la première contestation.
            if ($transaction && $pdo->inTransaction()) {
                $pdo->rollBack();
            }
            if ((string) $e->getCode() === '23000' && $this->find($banId) !== null) {
                return false;
            }

            throw $e;
        } finally {
            if ($transaction && $pdo->inTransaction()) {
                $pdo->rollBack();
            }
        }
    }

    /** @return array<string, mixed>|null */
    public function find(int $banId): ?array
    {
        $stmt = $this->db()->prepare('SELECT * FROM platform_ban_appeals WHERE ban_id = ?');
        $stmt->execute([$banId]);

        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    /** @return array<string, mixed>|null */
    public function findByReference(string $reference): ?array
    {
        $stmt = $this->db()->prepare('SELECT * FROM platform_ban_appeals WHERE reference = ?');
        $stmt->execute([strtoupper(trim($reference))]);

        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    /**
     * Dossier avec la sanction contestée (jamais montré tel quel à la personne : motif interne et noms inclus).
     *
     * @return array<string, mixed>|null
     */
    public function findCase(int $banId): ?array
    {
        $stmt = $this->db()->prepare(
            'SELECT a.*, b.email_norm, b.display_label, b.reason, b.rule_reference, b.public_reason, b.ends_at, b.lifted_at,
                    b.created_at AS ban_created_at, b.source_tenant_id, b.appeal_closed
             FROM platform_ban_appeals a JOIN platform_account_bans b ON b.id = a.ban_id WHERE a.ban_id = ?'
        );
        $stmt->execute([$banId]);

        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    public static function isOpen(?array $appeal): bool
    {
        return $appeal !== null && in_array((string) ($appeal['status'] ?? ''), self::OPEN_STATUSES, true);
    }

    /** @return list<array<string, mixed>> fil chronologique ; les notes internes seulement pour la modération */
    public function messages(int $banId, bool $withInternal = false): array
    {
        if (!$this->casesReady()) {
            return [];
        }
        $stmt = $this->db()->prepare(
            'SELECT id, ban_id, author_type, author_user_id, body, is_internal, created_at FROM platform_ban_appeal_messages
             WHERE ban_id = ?' . ($withInternal ? '' : ' AND is_internal = 0') . ' ORDER BY created_at ASC, id ASC'
        );
        $stmt->execute([$banId]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    public function addMessage(int $banId, string $authorType, ?int $authorUserId, string $body, bool $internal = false): void
    {
        $this->db()->prepare(
            'INSERT INTO platform_ban_appeal_messages (ban_id, author_type, author_user_id, body, is_internal, created_at) VALUES (?, ?, ?, ?, ?, ?)'
        )->execute([$banId, $authorType, $authorUserId, $body, $internal ? 1 : 0, gmdate('Y-m-d H:i:s')]);
    }

    /**
     * @param array<string, mixed> $fields colonnes autorisées seulement (statut, assignation, lu, décision)
     */
    public function update(int $banId, array $fields): bool
    {
        $fields = array_intersect_key($fields, array_flip(self::UPDATABLE));
        if ($fields === []) {
            return false;
        }
        $fields['updated_at'] ??= gmdate('Y-m-d H:i:s');
        $sets = implode(', ', array_map(static fn (string $c): string => $c . ' = ?', array_keys($fields)));
        $stmt = $this->db()->prepare('UPDATE platform_ban_appeals SET ' . $sets . ' WHERE ban_id = ?');
        $position = 1;
        foreach ($fields as $value) {
            // Entiers liés comme entiers : le marqueur « non lu » reste comparable à 1.
            $stmt->bindValue($position++, $value, is_int($value) ? PDO::PARAM_INT : ($value === null ? PDO::PARAM_NULL : PDO::PARAM_STR));
        }
        $stmt->bindValue($position, $banId, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->rowCount() === 1;
    }

    public function countPending(): int
    {
        if ($this->casesReady()) {
            return $this->countOpen();
        }

        return (int) $this->db()->query('SELECT COUNT(*) FROM platform_ban_appeals WHERE reviewed_at IS NULL')->fetchColumn();
    }

    /** Dossiers ouverts ; $tenantId limite à la communauté d'origine de la sanction (null : toute la plateforme). */
    public function countOpen(?int $tenantId = null): int
    {
        return $this->countWhere("a.status IN ('received', 'in_review', 'info_requested')", $tenantId);
    }

    /** Dossiers ouverts avec un message de la personne que personne n'a encore lu. */
    public function countUnread(?int $tenantId = null): int
    {
        return $this->countWhere("a.unread_staff = 1 AND a.status IN ('received', 'in_review', 'info_requested')", $tenantId);
    }

    private function countWhere(string $where, ?int $tenantId): int
    {
        $sql = 'SELECT COUNT(*) FROM platform_ban_appeals a JOIN platform_account_bans b ON b.id = a.ban_id WHERE ' . $where;
        $params = [];
        if ($tenantId !== null) {
            $sql .= ' AND b.source_tenant_id = ?';
            $params[] = $tenantId;
        }
        $stmt = $this->db()->prepare($sql);
        foreach ($params as $index => $value) {
            $stmt->bindValue($index + 1, $value, PDO::PARAM_INT);
        }
        $stmt->execute();

        return (int) $stmt->fetchColumn();
    }

    /**
     * File de traitement. $tenantId non nul : périmètre d'un administrateur de communauté (jamais d'autre communauté).
     *
     * @param array{status?: string, age_days?: int, tenant?: int, assigned?: string} $filters
     * @return list<array<string, mixed>>
     */
    public function listQueue(array $filters, ?int $tenantId, int $actorId, int $limit = 100): array
    {
        $where = ['1 = 1'];
        $params = [];
        $status = (string) ($filters['status'] ?? 'open');
        if ($status === 'open') {
            $where[] = "a.status IN ('received', 'in_review', 'info_requested')";
        } elseif ($status === 'unread') {
            $where[] = "a.unread_staff = 1 AND a.status IN ('received', 'in_review', 'info_requested')";
        } elseif (in_array($status, self::STATUSES, true)) {
            $where[] = 'a.status = ?';
            $params[] = $status;
        }
        $age = (int) ($filters['age_days'] ?? 0);
        if ($age > 0) {
            $where[] = 'a.created_at <= ?';
            $params[] = gmdate('Y-m-d H:i:s', time() - $age * 86400);
        }
        $scope = $tenantId ?? (int) ($filters['tenant'] ?? 0);
        if ($scope > 0) {
            $where[] = 'b.source_tenant_id = ?';
            $params[] = $scope;
        }
        $assigned = (string) ($filters['assigned'] ?? '');
        if ($assigned === 'me') {
            $where[] = 'a.assigned_to = ?';
            $params[] = $actorId;
        } elseif ($assigned === 'none') {
            $where[] = 'a.assigned_to IS NULL';
        }
        $limit = max(1, min(300, $limit));
        $stmt = $this->db()->prepare(
            'SELECT a.ban_id, a.reference, a.status, a.assigned_to, a.unread_staff, a.created_at, a.updated_at,
                    b.display_label, b.email_norm, b.source_tenant_id, b.ends_at, b.lifted_at
             FROM platform_ban_appeals a JOIN platform_account_bans b ON b.id = a.ban_id
             WHERE ' . implode(' AND ', $where) . '
             ORDER BY a.unread_staff DESC, a.created_at ASC, a.ban_id ASC LIMIT ' . $limit
        );
        foreach ($params as $index => $value) {
            $stmt->bindValue($index + 1, $value, is_int($value) ? PDO::PARAM_INT : PDO::PARAM_STR);
        }
        $stmt->execute();

        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    /** @return list<array<string, mixed>> */
    public function listForReview(bool $reviewed = false, int $limit = 50, int $focus = 0): array
    {
        $condition = $reviewed ? 'IS NOT NULL' : 'IS NULL';
        $order = $reviewed ? 'a.reviewed_at DESC, a.ban_id DESC' : 'a.created_at ASC, a.ban_id ASC';
        if ($focus > 0) {
            $order = '(a.ban_id = ' . $focus . ') DESC, ' . $order;
        }
        $limit = max(1, min(100, $limit));
        $stmt = $this->db()->query(
            "SELECT a.*, b.email_norm, b.display_label, b.reason, b.rule_reference, b.public_reason, b.ends_at, b.lifted_at
             FROM platform_ban_appeals a JOIN platform_account_bans b ON b.id = a.ban_id
             WHERE a.reviewed_at {$condition} ORDER BY {$order} LIMIT {$limit}"
        );

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function decide(int $banId, int $actor, string $decision, string $reply): bool
    {
        $stmt = $this->db()->prepare(
            'UPDATE platform_ban_appeals SET decision = ?, public_reply = ?, reviewed_at = ?, reviewed_by = ?
             WHERE ban_id = ? AND reviewed_at IS NULL'
        );
        $stmt->execute([$decision, $reply, gmdate('Y-m-d H:i:s'), $actor, $banId]);

        return $stmt->rowCount() === 1;
    }
}
