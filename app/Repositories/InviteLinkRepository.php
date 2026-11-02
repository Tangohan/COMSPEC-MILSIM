<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Core\Database;
use PDO;

/**
 * Liens d'invitation partageables (/i/<code>) : lignes de recruitment_invite_codes avec code_kind = 'link',
 * plus le journal recruitment_invite_link_events. Les codes prioritaires (code_kind = 'priority') ne sont jamais lus
 * ni modifiés ici. Toutes les lectures et écritures du back-office sont bornées à la communauté ; seule la résolution
 * publique du code (findByPublicCode) cherche sur tout le site, le code étant unique.
 */
class InviteLinkRepository
{
    public const KIND = 'link';

    public const ACTION_APPLY = 'apply';
    public const ACTION_JOIN = 'join';

    public const EVENT_CLICK = 'click';
    public const EVENT_SIGNUP = 'signup';
    public const EVENT_APPLICATION = 'application';
    public const EVENT_JOIN = 'join';
    public const EVENT_REQUEST = 'request';

    private const EVENTS = [self::EVENT_CLICK, self::EVENT_SIGNUP, self::EVENT_APPLICATION, self::EVENT_JOIN, self::EVENT_REQUEST];

    private ?bool $ready = null;

    public function __construct(private ?PDO $pdo = null)
    {
    }

    private function pdo(): PDO
    {
        return $this->pdo ??= Database::getPdo();
    }

    /** Colonnes et table des liens présentes (étape recruitment_invite_links passée). */
    public function tablesExist(): bool
    {
        if ($this->ready === null) {
            try {
                $this->pdo()->query('SELECT public_code, link_action, requires_approval, revoked_at FROM recruitment_invite_codes WHERE 1 = 0');
                $this->pdo()->query('SELECT id FROM recruitment_invite_link_events WHERE 1 = 0');
                $this->ready = true;
            } catch (\Throwable) {
                $this->ready = false;
            }
        }

        return $this->ready;
    }

    /**
     * @param array{public_code: string, label: string, link_action: string, requires_approval?: bool, max_uses?: ?int,
     *   expires_at?: ?string, opening_id?: ?int, metadata?: array<string, mixed>, created_by?: ?int} $data
     */
    public function create(int $tenantId, array $data): int
    {
        if (!$this->tablesExist() || $tenantId < 1) {
            return 0;
        }
        $public = strtolower(trim($data['public_code']));
        $stmt = $this->pdo()->prepare(
            'INSERT INTO recruitment_invite_codes
                (tenant_id, code, label, code_kind, public_code, link_action, requires_approval, max_uses, uses_count, expires_at,
                 auto_accept, assign_to_opening_id, metadata_json, created_by, created_at)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, 0, ?, 0, ?, ?, ?, ?)'
        );
        $stmt->execute([
            $tenantId,
            self::internalCode($public),
            mb_substr(trim($data['label']), 0, 180),
            self::KIND,
            $public,
            $data['link_action'] === self::ACTION_JOIN ? self::ACTION_JOIN : self::ACTION_APPLY,
            !empty($data['requires_approval']) ? 1 : 0,
            isset($data['max_uses']) && (int) $data['max_uses'] > 0 ? (int) $data['max_uses'] : null,
            $data['expires_at'] ?? null,
            isset($data['opening_id']) && (int) $data['opening_id'] > 0 ? (int) $data['opening_id'] : null,
            json_encode($data['metadata'] ?? [], JSON_UNESCAPED_UNICODE),
            isset($data['created_by']) && (int) $data['created_by'] > 0 ? (int) $data['created_by'] : null,
            self::now(),
        ]);

        return (int) $this->pdo()->lastInsertId();
    }

    /**
     * Réglages modifiables (le code public ne change jamais : un lien déjà partagé reste valable).
     *
     * @param array{label: string, link_action: string, requires_approval: bool, max_uses: ?int, expires_at: ?string,
     *   opening_id: ?int, metadata: array<string, mixed>} $data
     */
    public function update(int $tenantId, int $id, array $data): bool
    {
        if (!$this->tablesExist()) {
            return false;
        }
        $stmt = $this->pdo()->prepare(
            'UPDATE recruitment_invite_codes
             SET label = ?, link_action = ?, requires_approval = ?, max_uses = ?, expires_at = ?, assign_to_opening_id = ?,
                 metadata_json = ?, updated_at = ?
             WHERE tenant_id = ? AND id = ? AND code_kind = ?'
        );
        $stmt->execute([
            mb_substr(trim($data['label']), 0, 180),
            $data['link_action'] === self::ACTION_JOIN ? self::ACTION_JOIN : self::ACTION_APPLY,
            $data['requires_approval'] ? 1 : 0,
            $data['max_uses'] !== null && $data['max_uses'] > 0 ? $data['max_uses'] : null,
            $data['expires_at'],
            $data['opening_id'] !== null && $data['opening_id'] > 0 ? $data['opening_id'] : null,
            json_encode($data['metadata'], JSON_UNESCAPED_UNICODE),
            self::now(),
            $tenantId,
            $id,
            self::KIND,
        ]);

        return $stmt->rowCount() > 0;
    }

    public function revoke(int $tenantId, int $id): bool
    {
        return $this->setRevoked($tenantId, $id, self::now());
    }

    /** Réactivation : retire la révocation et pose une nouvelle échéance (null = jamais). */
    public function reactivate(int $tenantId, int $id, ?string $expiresAt): bool
    {
        if (!$this->tablesExist()) {
            return false;
        }
        $stmt = $this->pdo()->prepare(
            'UPDATE recruitment_invite_codes SET revoked_at = NULL, expires_at = ?, updated_at = ?
             WHERE tenant_id = ? AND id = ? AND code_kind = ?'
        );
        $stmt->execute([$expiresAt, self::now(), $tenantId, $id, self::KIND]);

        return $stmt->rowCount() > 0;
    }

    private function setRevoked(int $tenantId, int $id, ?string $at): bool
    {
        if (!$this->tablesExist()) {
            return false;
        }
        $stmt = $this->pdo()->prepare(
            'UPDATE recruitment_invite_codes SET revoked_at = ?, updated_at = ? WHERE tenant_id = ? AND id = ? AND code_kind = ?'
        );
        $stmt->execute([$at, self::now(), $tenantId, $id, self::KIND]);

        return $stmt->rowCount() > 0;
    }

    /** @return array<string, mixed>|null */
    public function findById(int $tenantId, int $id): ?array
    {
        if (!$this->tablesExist() || $tenantId < 1 || $id < 1) {
            return null;
        }
        $stmt = $this->pdo()->prepare('SELECT * FROM recruitment_invite_codes WHERE tenant_id = ? AND id = ? AND code_kind = ? LIMIT 1');
        $stmt->execute([$tenantId, $id, self::KIND]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return $row ? self::hydrate($row) : null;
    }

    /**
     * Résolution publique d'un lien : code unique sur tout le site, la communauté vient de la ligne.
     *
     * @return array<string, mixed>|null
     */
    public function findByPublicCode(string $code): ?array
    {
        $code = strtolower(trim($code));
        if (!$this->tablesExist() || $code === '' || strlen($code) > 64) {
            return null;
        }
        $stmt = $this->pdo()->prepare('SELECT * FROM recruitment_invite_codes WHERE public_code = ? AND code_kind = ? LIMIT 1');
        $stmt->execute([$code, self::KIND]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return $row ? self::hydrate($row) : null;
    }

    public function publicCodeTaken(string $code): bool
    {
        $code = strtolower(trim($code));
        if (!$this->tablesExist() || $code === '') {
            return false;
        }
        $stmt = $this->pdo()->prepare('SELECT 1 FROM recruitment_invite_codes WHERE public_code = ? LIMIT 1');
        $stmt->execute([$code]);

        return (bool) $stmt->fetchColumn();
    }

    /** @return list<array<string, mixed>> */
    public function listForTenant(int $tenantId): array
    {
        if (!$this->tablesExist() || $tenantId < 1) {
            return [];
        }
        $stmt = $this->pdo()->prepare('SELECT * FROM recruitment_invite_codes WHERE tenant_id = ? AND code_kind = ? ORDER BY created_at DESC, id DESC');
        $stmt->execute([$tenantId, self::KIND]);

        return array_map(self::hydrate(...), $stmt->fetchAll(PDO::FETCH_ASSOC) ?: []);
    }

    /** Liens utilisables à l'instant (non révoqués, non expirés, non épuisés). */
    public function countActiveForTenant(int $tenantId): int
    {
        if (!$this->tablesExist() || $tenantId < 1) {
            return 0;
        }
        $stmt = $this->pdo()->prepare(
            'SELECT COUNT(*) FROM recruitment_invite_codes
             WHERE tenant_id = ? AND code_kind = ? AND revoked_at IS NULL
               AND (expires_at IS NULL OR expires_at > ?)
               AND (max_uses IS NULL OR uses_count < max_uses)'
        );
        $stmt->execute([$tenantId, self::KIND, self::now()]);

        return (int) $stmt->fetchColumn();
    }

    /**
     * Consomme une utilisation de façon atomique : refusé si le lien est révoqué, expiré ou déjà épuisé, même quand
     * deux personnes l'utilisent au même instant.
     */
    public function consumeUse(int $tenantId, int $id): bool
    {
        if (!$this->tablesExist()) {
            return false;
        }
        $now = self::now();
        $stmt = $this->pdo()->prepare(
            'UPDATE recruitment_invite_codes SET uses_count = uses_count + 1, updated_at = ?
             WHERE tenant_id = ? AND id = ? AND code_kind = ? AND revoked_at IS NULL
               AND (expires_at IS NULL OR expires_at > ?)
               AND (max_uses IS NULL OR uses_count < max_uses)'
        );
        $stmt->execute([$now, $tenantId, $id, self::KIND, $now]);

        return $stmt->rowCount() > 0;
    }

    /** Rend une utilisation consommée quand l'adhésion ou la demande n'a finalement pas pu aboutir. */
    public function releaseUse(int $tenantId, int $id): void
    {
        if (!$this->tablesExist()) {
            return;
        }
        $stmt = $this->pdo()->prepare(
            'UPDATE recruitment_invite_codes SET uses_count = uses_count - 1 WHERE tenant_id = ? AND id = ? AND code_kind = ? AND uses_count > 0'
        );
        $stmt->execute([$tenantId, $id, self::KIND]);
    }

    public function recordEvent(int $tenantId, int $id, string $event, ?int $userId = null, ?int $enlistmentId = null, ?string $ip = null): void
    {
        if (!$this->tablesExist() || !in_array($event, self::EVENTS, true)) {
            return;
        }
        try {
            $stmt = $this->pdo()->prepare(
                'INSERT INTO recruitment_invite_link_events (tenant_id, invite_code_id, event, user_id, enlistment_id, ip_hash, created_at)
                 VALUES (?, ?, ?, ?, ?, ?, ?)'
            );
            $stmt->execute([
                $tenantId,
                $id,
                $event,
                $userId !== null && $userId > 0 ? $userId : null,
                $enlistmentId !== null && $enlistmentId > 0 ? $enlistmentId : null,
                $ip !== null && $ip !== '' ? hash('sha256', 'invite-link|' . $ip) : null,
                self::now(),
            ]);
        } catch (\Throwable $e) {
            error_log('[invite_link.event] ' . $e->getMessage());
        }
    }

    /**
     * Compteurs par lien pour toute la communauté (une requête).
     *
     * @return array<int, array{click: int, signup: int, application: int, join: int, request: int}>
     */
    public function statsByLinkForTenant(int $tenantId): array
    {
        if (!$this->tablesExist() || $tenantId < 1) {
            return [];
        }
        $stmt = $this->pdo()->prepare(
            'SELECT invite_code_id, event, COUNT(*) AS n FROM recruitment_invite_link_events WHERE tenant_id = ? GROUP BY invite_code_id, event'
        );
        $stmt->execute([$tenantId]);
        $out = [];
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) ?: [] as $row) {
            $id = (int) $row['invite_code_id'];
            $out[$id] ??= self::emptyStats();
            $event = (string) $row['event'];
            if (array_key_exists($event, $out[$id])) {
                $out[$id][$event] = (int) $row['n'];
            }
        }

        return $out;
    }

    /** @return array{click: int, signup: int, application: int, join: int, request: int} */
    public static function emptyStats(): array
    {
        return ['click' => 0, 'signup' => 0, 'application' => 0, 'join' => 0, 'request' => 0];
    }

    /**
     * Derniers usages d'un lien (hors clics), bornés à la communauté.
     *
     * @return list<array<string, mixed>>
     */
    public function recentUsesForLink(int $tenantId, int $id, int $limit = 30): array
    {
        if (!$this->tablesExist()) {
            return [];
        }
        $limit = max(1, min(100, $limit));
        $stmt = $this->pdo()->prepare(
            "SELECT e.event, e.user_id, e.enlistment_id, e.created_at, u.display_name, u.email
             FROM recruitment_invite_link_events e
             LEFT JOIN users u ON u.id = e.user_id
             WHERE e.tenant_id = ? AND e.invite_code_id = ? AND e.event <> 'click'
             ORDER BY e.created_at DESC, e.id DESC
             LIMIT {$limit}"
        );
        $stmt->execute([$tenantId, $id]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    /** Valeur de la colonne code (unique par communauté avec les codes prioritaires, jamais saisie par un candidat). */
    public static function internalCode(string $publicCode): string
    {
        return substr('LIEN-' . strtoupper($publicCode), 0, 64);
    }

    /**
     * @param array<string, mixed> $row
     * @return array<string, mixed>
     */
    private static function hydrate(array $row): array
    {
        $meta = json_decode((string) ($row['metadata_json'] ?? ''), true);
        $row['metadata'] = is_array($meta) ? $meta : [];
        $row['id'] = (int) $row['id'];
        $row['tenant_id'] = (int) $row['tenant_id'];
        $row['uses_count'] = (int) ($row['uses_count'] ?? 0);
        $row['max_uses'] = isset($row['max_uses']) && $row['max_uses'] !== null ? (int) $row['max_uses'] : null;
        $row['requires_approval'] = (int) ($row['requires_approval'] ?? 0) === 1;

        return $row;
    }

    private static function now(): string
    {
        return date('Y-m-d H:i:s');
    }
}
