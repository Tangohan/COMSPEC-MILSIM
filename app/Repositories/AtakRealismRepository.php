<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Core\Database;
use PDO;

final class AtakRealismRepository
{
    private PDO $pdo;
    private bool $ensured = false;

    public function __construct(
        ?PDO $pdo = null,
        private ?AtakOperatorIdRepository $operatorIds = null,
    ) {
        $this->pdo = $pdo ?? Database::getPdo();
        $this->operatorIds ??= new AtakOperatorIdRepository();
        $this->ensureSchema();
    }

    public function ensureSchema(): void
    {
        if ($this->ensured) {
            return;
        }
        $this->ensured = true;
        $migration = dirname(__DIR__, 2) . '/bootstrap/atak_realism_registry_migration.php';
        if (!is_file($migration)) {
            return;
        }
        try {
            $runner = require $migration;
            if (is_callable($runner)) {
                $runner($this->pdo);
            }
        } catch (\Throwable) {
        }
    }

    public function tablesReady(): bool
    {
        try {
            $st = $this->pdo->query("SELECT 1 FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'atak_terminals' LIMIT 1");
            return $st !== false && (bool) $st->fetchColumn();
        } catch (\Throwable) {
            return false;
        }
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function listTerminals(int $tenantId): array
    {
        if (!$this->tablesReady() || $tenantId < 1) {
            return [];
        }
        $sql = 'SELECT t.*, u.display_name, u.callsign
                FROM atak_terminals t
                LEFT JOIN users u ON u.id = t.user_id
                WHERE t.tenant_id = ?
                ORDER BY COALESCE(t.last_seen_at, t.updated_at, t.created_at) DESC, t.id DESC';
        $st = $this->pdo->prepare($sql);
        $st->execute([$tenantId]);

        return $st->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function listCertificates(int $tenantId): array
    {
        if (!$this->tablesReady() || $tenantId < 1) {
            return [];
        }
        $sql = 'SELECT c.*, t.terminal_label, t.terminal_uid, u.display_name, u.callsign
                FROM atak_certificates c
                LEFT JOIN atak_terminals t ON t.id = c.terminal_id
                LEFT JOIN users u ON u.id = c.user_id
                WHERE c.tenant_id = ?
                ORDER BY COALESCE(c.expires_at, c.updated_at, c.created_at) DESC, c.id DESC';
        $st = $this->pdo->prepare($sql);
        $st->execute([$tenantId]);

        return $st->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    /**
     * @param array<string, mixed> $payload
     * @return array<string, mixed>
     */
    public function upsertTerminal(int $tenantId, array $payload): array
    {
        $uid = $this->clip((string) ($payload['terminal_uid'] ?? ''), 64);
        if ($uid === '') {
            $uid = 'term-' . bin2hex(random_bytes(6));
        }

        $userId = (int) ($payload['user_id'] ?? 0);
        $callsign = $this->nullableString($payload['operator_callsign'] ?? null, 120);
        $mid = null;
        if ($tenantId > 0 && $userId > 0) {
            $mid = $this->operatorIds->tablesReady()
                ? $this->operatorIds->ensureForUser($tenantId, $userId, $callsign)
                : null;
        } elseif ($tenantId > 0 && $callsign !== null && $callsign !== '' && $this->operatorIds->tablesReady()) {
            $mid = $this->operatorIds->ensureForCallSign($tenantId, $callsign);
        }

        $row = $this->findTerminalByUid($tenantId, $uid);
        $fields = [
            'user_id' => $userId > 0 ? $userId : null,
            'terminal_label' => $this->clip((string) ($payload['terminal_label'] ?? 'Terminal ATAK'), 160),
            'terminal_type' => $this->allowed((string) ($payload['terminal_type'] ?? 'phone'), ['phone', 'tablet', 'radio', 'vehicle', 'desktop'], 'phone'),
            'platform_label' => $this->nullableString($payload['platform_label'] ?? null, 120),
            'operator_callsign' => $callsign,
            'operator_military_id' => $mid,
            'pairing_token' => $this->nullableString($payload['pairing_token'] ?? null, 32),
            'pairing_code' => $this->nullableString($payload['pairing_code'] ?? null, 8),
            'status' => $this->allowed((string) ($payload['status'] ?? 'pending'), ['pending', 'active', 'inactive', 'lost', 'revoked'], 'pending'),
            'notes' => $this->nullableText($payload['notes'] ?? null, 4000),
        ];

        if ($row) {
            $sql = 'UPDATE atak_terminals
                    SET user_id = ?, terminal_label = ?, terminal_type = ?, platform_label = ?, operator_callsign = ?,
                        operator_military_id = ?, pairing_token = ?, pairing_code = ?, status = ?, notes = ?,
                        linked_at = CASE WHEN ? IS NOT NULL THEN COALESCE(linked_at, UTC_TIMESTAMP()) ELSE linked_at END,
                        first_seen_at = COALESCE(first_seen_at, UTC_TIMESTAMP()),
                        last_seen_at = UTC_TIMESTAMP(),
                        updated_at = UTC_TIMESTAMP()
                    WHERE tenant_id = ? AND terminal_uid = ?';
            $this->pdo->prepare($sql)->execute([
                $fields['user_id'],
                $fields['terminal_label'],
                $fields['terminal_type'],
                $fields['platform_label'],
                $fields['operator_callsign'],
                $fields['operator_military_id'],
                $fields['pairing_token'],
                $fields['pairing_code'],
                $fields['status'],
                $fields['notes'],
                $fields['user_id'],
                $tenantId,
                $uid,
            ]);
        } else {
            $sql = 'INSERT INTO atak_terminals
                    (tenant_id, user_id, terminal_uid, terminal_label, terminal_type, platform_label,
                     operator_callsign, operator_military_id, pairing_token, pairing_code, status,
                     first_seen_at, last_seen_at, linked_at, notes, created_at, updated_at)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, UTC_TIMESTAMP(), UTC_TIMESTAMP(),
                            CASE WHEN ? IS NOT NULL THEN UTC_TIMESTAMP() ELSE NULL END, ?, UTC_TIMESTAMP(), UTC_TIMESTAMP())';
            $this->pdo->prepare($sql)->execute([
                $tenantId,
                $fields['user_id'],
                $uid,
                $fields['terminal_label'],
                $fields['terminal_type'],
                $fields['platform_label'],
                $fields['operator_callsign'],
                $fields['operator_military_id'],
                $fields['pairing_token'],
                $fields['pairing_code'],
                $fields['status'],
                $fields['user_id'],
                $fields['notes'],
            ]);
        }

        return $this->findTerminalByUid($tenantId, $uid) ?? [];
    }

    /**
     * @param array<string, mixed> $payload
     * @return array<string, mixed>
     */
    public function issueCertificate(int $tenantId, array $payload): array
    {
        $terminalId = (int) ($payload['terminal_id'] ?? 0);
        $userId = (int) ($payload['user_id'] ?? 0);
        $authority = $this->clip((string) ($payload['authority_label'] ?? 'Autorité ATAK locale'), 160);
        $type = $this->allowed((string) ($payload['certificate_type'] ?? 'device'), ['device', 'operator', 'gateway', 'test'], 'device');
        $status = $this->allowed((string) ($payload['status'] ?? 'issued'), ['issued', 'active', 'expired', 'revoked'], 'issued');
        $ref = $this->clip((string) ($payload['certificate_ref'] ?? ''), 64);
        if ($ref === '') {
            $ref = 'CERT-' . strtoupper(substr(bin2hex(random_bytes(8)), 0, 12));
        }
        $validFrom = $this->dateValue($payload['valid_from'] ?? null) ?? gmdate('Y-m-d H:i:s');
        $expiresAt = $this->dateValue($payload['expires_at'] ?? null);
        if ($expiresAt === null) {
            $days = max(1, min(1825, (int) ($payload['duration_days'] ?? 365)));
            $expiresAt = gmdate('Y-m-d H:i:s', time() + ($days * 86400));
        }
        $issuedAt = $this->dateValue($payload['issued_at'] ?? null) ?? gmdate('Y-m-d H:i:s');
        $revokedAt = $status === 'revoked' ? ($this->dateValue($payload['revoked_at'] ?? null) ?? gmdate('Y-m-d H:i:s')) : null;
        $meta = $payload['metadata'] ?? [];
        if (!is_array($meta)) {
            $meta = [];
        }
        $jsonMeta = json_encode($meta, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        $sql = 'INSERT INTO atak_certificates
                (tenant_id, terminal_id, user_id, certificate_ref, authority_label, certificate_type,
                 common_name, serial_number, fingerprint_sha256, status, issued_at, valid_from, expires_at,
                 revoked_at, revoked_reason, metadata_json, created_at, updated_at)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, UTC_TIMESTAMP(), UTC_TIMESTAMP())
                ON DUPLICATE KEY UPDATE
                    terminal_id = VALUES(terminal_id),
                    user_id = VALUES(user_id),
                    authority_label = VALUES(authority_label),
                    certificate_type = VALUES(certificate_type),
                    common_name = VALUES(common_name),
                    serial_number = VALUES(serial_number),
                    fingerprint_sha256 = VALUES(fingerprint_sha256),
                    status = VALUES(status),
                    issued_at = VALUES(issued_at),
                    valid_from = VALUES(valid_from),
                    expires_at = VALUES(expires_at),
                    revoked_at = VALUES(revoked_at),
                    revoked_reason = VALUES(revoked_reason),
                    metadata_json = VALUES(metadata_json),
                    updated_at = UTC_TIMESTAMP()';
        $this->pdo->prepare($sql)->execute([
            $tenantId,
            $terminalId > 0 ? $terminalId : null,
            $userId > 0 ? $userId : null,
            $ref,
            $authority,
            $type,
            $this->nullableString($payload['common_name'] ?? null, 255),
            $this->nullableString($payload['serial_number'] ?? null, 120),
            $this->nullableString($payload['fingerprint_sha256'] ?? null, 128),
            $status,
            $issuedAt,
            $validFrom,
            $expiresAt,
            $revokedAt,
            $this->nullableString($payload['revoked_reason'] ?? null, 255),
            $jsonMeta !== false ? $jsonMeta : null,
        ]);

        return $this->findCertificateByRef($tenantId, $ref) ?? [];
    }

    /**
     * @return array<string, mixed>|null
     */
    public function findTerminalByUid(int $tenantId, string $terminalUid): ?array
    {
        if (!$this->tablesReady() || $tenantId < 1 || trim($terminalUid) === '') {
            return null;
        }
        $st = $this->pdo->prepare('SELECT * FROM atak_terminals WHERE tenant_id = ? AND terminal_uid = ? LIMIT 1');
        $st->execute([$tenantId, trim($terminalUid)]);
        $row = $st->fetch(PDO::FETCH_ASSOC);

        return is_array($row) ? $row : null;
    }

    /**
     * @return array<string, mixed>|null
     */
    public function findLatestCertificateForTerminal(int $tenantId, int $terminalId): ?array
    {
        if (!$this->tablesReady() || $tenantId < 1 || $terminalId < 1) {
            return null;
        }
        $sql = 'SELECT * FROM atak_certificates
                WHERE tenant_id = ? AND terminal_id = ?
                ORDER BY COALESCE(expires_at, updated_at, created_at) DESC, id DESC
                LIMIT 1';
        $st = $this->pdo->prepare($sql);
        $st->execute([$tenantId, $terminalId]);
        $row = $st->fetch(PDO::FETCH_ASSOC);

        return is_array($row) ? $row : null;
    }

    /**
     * @return array<string, mixed>|null
     */
    public function findCertificateByRef(int $tenantId, string $reference): ?array
    {
        if (!$this->tablesReady() || $tenantId < 1 || trim($reference) === '') {
            return null;
        }
        $st = $this->pdo->prepare('SELECT * FROM atak_certificates WHERE tenant_id = ? AND certificate_ref = ? LIMIT 1');
        $st->execute([$tenantId, trim($reference)]);
        $row = $st->fetch(PDO::FETCH_ASSOC);

        return is_array($row) ? $row : null;
    }

    private function clip(string $value, int $max): string
    {
        $value = trim($value);
        if (mb_strlen($value) <= $max) {
            return $value;
        }

        return mb_substr($value, 0, $max);
    }

    private function nullableString(mixed $value, int $max): ?string
    {
        $value = trim((string) $value);

        return $value === '' ? null : $this->clip($value, $max);
    }

    private function nullableText(mixed $value, int $max): ?string
    {
        $value = trim((string) $value);
        if ($value === '') {
            return null;
        }
        if (mb_strlen($value) <= $max) {
            return $value;
        }

        return mb_substr($value, 0, $max);
    }

    private function allowed(string $value, array $allowed, string $fallback): string
    {
        $value = strtolower(trim($value));

        return in_array($value, $allowed, true) ? $value : $fallback;
    }

    private function dateValue(mixed $value): ?string
    {
        $value = trim((string) $value);
        if ($value === '') {
            return null;
        }
        $ts = strtotime($value);
        if ($ts === false) {
            return null;
        }

        return gmdate('Y-m-d H:i:s', $ts);
    }
}
