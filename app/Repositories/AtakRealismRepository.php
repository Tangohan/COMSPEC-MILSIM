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
        if ($this->cryptoDomainsReady()) {
            $sql = 'SELECT c.*, t.terminal_label, t.terminal_uid, u.display_name, u.callsign,
                           d.label AS crypto_domain_label, d.domain_ref AS crypto_domain_ref
                    FROM atak_certificates c
                    LEFT JOIN atak_terminals t ON t.id = c.terminal_id
                    LEFT JOIN users u ON u.id = c.user_id
                    LEFT JOIN atak_crypto_domains d ON d.id = c.crypto_domain_id
                    WHERE c.tenant_id = ?
                    ORDER BY COALESCE(c.expires_at, c.updated_at, c.created_at) DESC, c.id DESC';
        } else {
            $sql = 'SELECT c.*, t.terminal_label, t.terminal_uid, u.display_name, u.callsign
                    FROM atak_certificates c
                    LEFT JOIN atak_terminals t ON t.id = c.terminal_id
                    LEFT JOIN users u ON u.id = c.user_id
                    WHERE c.tenant_id = ?
                    ORDER BY COALESCE(c.expires_at, c.updated_at, c.created_at) DESC, c.id DESC';
        }
        $st = $this->pdo->prepare($sql);
        $st->execute([$tenantId]);

        return $st->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    /**
     * Session navigateur / TOC — pas un terminal terrain (jeu, téléphone appairé, radio).
     *
     * @param array<string, mixed> $row
     */
    public static function isWebSessionTerminal(array $row): bool
    {
        $type = strtolower(trim((string) ($row['terminal_type'] ?? '')));
        if ($type === 'web') {
            return true;
        }
        $uid = strtoupper(trim((string) ($row['terminal_uid'] ?? '')));
        if (str_starts_with($uid, 'WEB-') || str_starts_with($uid, 'SESSION-')) {
            return true;
        }
        $platform = strtolower(trim((string) ($row['platform_label'] ?? '')));
        if ($platform !== '' && preg_match('/web|navigateur|browser|session web|poste de commandement|\btoc\b/u', $platform) === 1) {
            return true;
        }
        if ($type === 'desktop' && ($platform === '' || preg_match('/web|navigateur|browser|athena/u', $platform) === 1)) {
            return true;
        }

        return false;
    }

    /**
     * @param list<array<string, mixed>> $rows
     * @return array{physical: list<array<string, mixed>>, web: list<array<string, mixed>>}
     */
    public static function partitionTerminals(array $rows): array
    {
        $physical = [];
        $web = [];
        foreach ($rows as $row) {
            if (!is_array($row)) {
                continue;
            }
            if (self::isWebSessionTerminal($row)) {
                $web[] = $row;
            } else {
                $physical[] = $row;
            }
        }

        return ['physical' => $physical, 'web' => $web];
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function listPhysicalTerminals(int $tenantId): array
    {
        return self::partitionTerminals($this->listTerminals($tenantId))['physical'];
    }

    /**
     * @return array<string, mixed>|null
     */
    public function findWebSessionForUser(int $tenantId, int $userId): ?array
    {
        if ($tenantId < 1 || $userId < 1) {
            return null;
        }
        foreach ($this->listTerminals($tenantId) as $row) {
            if ((int) ($row['user_id'] ?? 0) !== $userId) {
                continue;
            }
            if (self::isWebSessionTerminal($row)) {
                return $row;
            }
        }

        return null;
    }

    /**
     * @return array<string, mixed>|null
     */
    public function findTerminalById(int $tenantId, int $terminalId): ?array
    {
        if (!$this->tablesReady() || $tenantId < 1 || $terminalId < 1) {
            return null;
        }
        $st = $this->pdo->prepare('SELECT * FROM atak_terminals WHERE tenant_id = ? AND id = ? LIMIT 1');
        $st->execute([$tenantId, $terminalId]);
        $row = $st->fetch(PDO::FETCH_ASSOC);

        return is_array($row) ? $row : null;
    }

    public function deleteTerminal(int $tenantId, int $terminalId): bool
    {
        return $this->deleteTerminals($tenantId, [$terminalId]) > 0;
    }

    /**
     * @param list<int|string> $terminalIds
     */
    public function deleteTerminals(int $tenantId, array $terminalIds): int
    {
        $ids = [];
        foreach ($terminalIds as $raw) {
            $id = (int) $raw;
            if ($id > 0) {
                $ids[$id] = $id;
            }
        }
        $ids = array_values($ids);
        if (!$this->tablesReady() || $tenantId < 1 || $ids === []) {
            return 0;
        }
        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        $params = array_merge([$tenantId], $ids);
        try {
            $this->pdo->beginTransaction();
            try {
                $this->pdo->prepare(
                    "UPDATE atak_certificates SET terminal_id = NULL WHERE tenant_id = ? AND terminal_id IN ({$placeholders})"
                )->execute($params);
            } catch (\Throwable) {
                // Table absente ou schéma partiel — on tente quand même le retrait.
            }
            $st = $this->pdo->prepare(
                "DELETE FROM atak_terminals WHERE tenant_id = ? AND id IN ({$placeholders})"
            );
            $st->execute($params);
            $deleted = $st->rowCount();
            $this->pdo->commit();

            return $deleted;
        } catch (\Throwable) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }

            return 0;
        }
    }

    /**
     * @param array<string, mixed> $payload
     * @return array<string, mixed>
     */
    public function upsertTerminal(int $tenantId, array $payload): array
    {
        $uid = $this->sanitizeTerminalUid((string) ($payload['terminal_uid'] ?? ''));
        if ($uid === '') {
            $uid = 'OW-' . strtoupper(bin2hex(random_bytes(4))) . '-' . random_int(100000, 999999);
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
        // Récupère une fiche corrompue (<null>) du même compte / indicatif pour la réparer
        if ($row === null) {
            $corrupt = $this->findCorruptTerminal($tenantId, $userId, $callsign);
            if ($corrupt !== null) {
                $this->repairTerminalIdentity($tenantId, (int) $corrupt['id'], $uid);
                $row = $this->findTerminalByUid($tenantId, $uid);
            }
        }
        $fields = [
            'user_id' => $userId > 0 ? $userId : null,
            'terminal_label' => $this->clip((string) ($payload['terminal_label'] ?? 'Terminal ATAK'), 160),
            'terminal_type' => $this->allowed((string) ($payload['terminal_type'] ?? 'phone'), ['phone', 'tablet', 'radio', 'vehicle', 'desktop', 'web'], 'phone'),
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
        $ref = $this->sanitizeCertificateRef((string) ($payload['certificate_ref'] ?? ''), $terminalId);
        if ($ref === '') {
            $ref = 'OW-CERT-' . strtoupper(substr(bin2hex(random_bytes(8)), 0, 12));
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
                (tenant_id, terminal_id, user_id, crypto_domain_id, certificate_ref, authority_label, certificate_type,
                 common_name, serial_number, fingerprint_sha256, status, issued_at, valid_from, expires_at,
                 revoked_at, revoked_reason, metadata_json, created_at, updated_at)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, UTC_TIMESTAMP(), UTC_TIMESTAMP())
                ON DUPLICATE KEY UPDATE
                    terminal_id = VALUES(terminal_id),
                    user_id = VALUES(user_id),
                    crypto_domain_id = VALUES(crypto_domain_id),
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
        $domainId = (int) ($payload['crypto_domain_id'] ?? 0);
        $this->pdo->prepare($sql)->execute([
            $tenantId,
            $terminalId > 0 ? $terminalId : null,
            $userId > 0 ? $userId : null,
            $domainId > 0 ? $domainId : null,
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
     * @return list<array<string, mixed>>
     */
    public function listCryptoDomains(int $tenantId): array
    {
        if (!$this->tablesReady() || !$this->cryptoDomainsReady() || $tenantId < 1) {
            return [];
        }
        $st = $this->pdo->prepare(
            'SELECT * FROM atak_crypto_domains WHERE tenant_id = ? ORDER BY label ASC, id ASC'
        );
        $st->execute([$tenantId]);

        return $st->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    /**
     * @param array<string, mixed> $payload
     * @return array<string, mixed>
     */
    public function upsertCryptoDomain(int $tenantId, array $payload): array
    {
        if (!$this->tablesReady() || !$this->cryptoDomainsReady() || $tenantId < 1) {
            return [];
        }
        $ref = $this->clip((string) ($payload['domain_ref'] ?? ''), 64);
        if ($ref === '' || $this->isCorruptIdentity($ref)) {
            $ref = 'NET-' . strtoupper(substr(bin2hex(random_bytes(6)), 0, 10));
        }
        $label = $this->clip((string) ($payload['label'] ?? 'Réseau ami'), 160);
        $faction = $this->nullableString($payload['faction_key'] ?? null, 64);
        $status = $this->allowed((string) ($payload['status'] ?? 'active'), ['active', 'inactive'], 'active');
        $mapId = (int) ($payload['map_id'] ?? 0);
        $sql = 'INSERT INTO atak_crypto_domains
                (tenant_id, map_id, domain_ref, label, faction_key, status, created_at, updated_at)
                VALUES (?, ?, ?, ?, ?, ?, UTC_TIMESTAMP(), UTC_TIMESTAMP())
                ON DUPLICATE KEY UPDATE
                    label = VALUES(label),
                    faction_key = VALUES(faction_key),
                    status = VALUES(status),
                    map_id = VALUES(map_id),
                    updated_at = UTC_TIMESTAMP()';
        $this->pdo->prepare($sql)->execute([
            $tenantId,
            $mapId > 0 ? $mapId : null,
            $ref,
            $label,
            $faction,
            $status,
        ]);

        return $this->findCryptoDomainByRef($tenantId, $ref) ?? [];
    }

    /**
     * @return array<string, mixed>|null
     */
    public function findCryptoDomainByRef(int $tenantId, string $ref): ?array
    {
        if (!$this->cryptoDomainsReady() || $tenantId < 1 || trim($ref) === '') {
            return null;
        }
        $st = $this->pdo->prepare(
            'SELECT * FROM atak_crypto_domains WHERE tenant_id = ? AND domain_ref = ? LIMIT 1'
        );
        $st->execute([$tenantId, trim($ref)]);
        $row = $st->fetch(PDO::FETCH_ASSOC);

        return is_array($row) ? $row : null;
    }

    /**
     * @return array<string, mixed>|null
     */
    public function findCryptoDomainById(int $tenantId, int $domainId): ?array
    {
        if (!$this->cryptoDomainsReady() || $tenantId < 1 || $domainId < 1) {
            return null;
        }
        $st = $this->pdo->prepare(
            'SELECT * FROM atak_crypto_domains WHERE tenant_id = ? AND id = ? LIMIT 1'
        );
        $st->execute([$tenantId, $domainId]);
        $row = $st->fetch(PDO::FETCH_ASSOC);

        return is_array($row) ? $row : null;
    }

    /**
     * Assure un domaine « Réseau ami » pour un tenant (nouveaux tenants / seed).
     *
     * @return array<string, mixed>
     */
    public function ensureDefaultCryptoDomain(int $tenantId): array
    {
        if ($tenantId < 1 || !$this->cryptoDomainsReady()) {
            return [];
        }
        $existing = $this->listCryptoDomains($tenantId);
        foreach ($existing as $row) {
            if (($row['status'] ?? '') === 'active') {
                return $row;
            }
        }
        if ($existing !== []) {
            return $existing[0];
        }

        return $this->upsertCryptoDomain($tenantId, [
            'domain_ref' => 'FRIENDLY-NET',
            'label' => 'Réseau ami',
            'faction_key' => 'friendly',
            'status' => 'active',
        ]);
    }

    /**
     * @return array<string, mixed>|null
     */
    public function setTerminalCompromise(
        int $tenantId,
        string $terminalUid,
        string $state,
        ?string $reason = null
    ): ?array {
        if (!$this->tablesReady() || $tenantId < 1 || trim($terminalUid) === '') {
            return null;
        }
        $state = $this->allowed($state, ['none', 'captured', 'compromised'], 'none');
        $terminal = $this->findTerminalByUid($tenantId, $terminalUid);
        if ($terminal === null) {
            return null;
        }
        if (!$this->hasCompromiseColumns()) {
            return $terminal;
        }
        if ($state === 'none') {
            $this->pdo->prepare(
                "UPDATE atak_terminals
                 SET compromise_state = 'none', compromised_at = NULL, compromise_reason = NULL,
                     updated_at = UTC_TIMESTAMP()
                 WHERE tenant_id = ? AND terminal_uid = ?"
            )->execute([$tenantId, trim($terminalUid)]);
        } else {
            $this->pdo->prepare(
                'UPDATE atak_terminals
                 SET compromise_state = ?, compromised_at = UTC_TIMESTAMP(), compromise_reason = ?,
                     updated_at = UTC_TIMESTAMP()
                 WHERE tenant_id = ? AND terminal_uid = ?'
            )->execute([
                $state,
                $this->nullableString($reason, 255),
                $tenantId,
                trim($terminalUid),
            ]);
        }

        return $this->findTerminalByUid($tenantId, $terminalUid);
    }

    public function certificateIsValid(array $certificate): bool
    {
        $status = strtolower(trim((string) ($certificate['status'] ?? '')));
        if (!in_array($status, ['active', 'issued'], true)) {
            return false;
        }
        $expires = trim((string) ($certificate['expires_at'] ?? ''));
        if ($expires !== '') {
            $ts = strtotime($expires . ' UTC');
            if ($ts !== false && $ts < time()) {
                return false;
            }
        }
        $revoked = trim((string) ($certificate['revoked_at'] ?? ''));
        if ($revoked !== '' && $status === 'revoked') {
            return false;
        }

        return true;
    }

    public function cryptoDomainsReady(): bool
    {
        try {
            $st = $this->pdo->query(
                "SELECT 1 FROM information_schema.TABLES
                 WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'atak_crypto_domains' LIMIT 1"
            );

            return $st !== false && (bool) $st->fetchColumn();
        } catch (\Throwable) {
            return false;
        }
    }

    public function hasCompromiseColumns(): bool
    {
        try {
            $st = $this->pdo->query(
                "SELECT 1 FROM information_schema.COLUMNS
                 WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'atak_terminals'
                   AND COLUMN_NAME = 'compromise_state' LIMIT 1"
            );

            return $st !== false && (bool) $st->fetchColumn();
        } catch (\Throwable) {
            return false;
        }
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

    /**
     * @return array<string, mixed>|null
     */
    public function findCertificateById(int $tenantId, int $certificateId): ?array
    {
        if (!$this->tablesReady() || $tenantId < 1 || $certificateId < 1) {
            return null;
        }
        $st = $this->pdo->prepare('SELECT * FROM atak_certificates WHERE tenant_id = ? AND id = ? LIMIT 1');
        $st->execute([$tenantId, $certificateId]);
        $row = $st->fetch(PDO::FETCH_ASSOC);

        return is_array($row) ? $row : null;
    }

    /**
     * @return array<string, mixed>|null
     */
    public function revokeCertificate(int $tenantId, int $certificateId, ?string $reason = null): ?array
    {
        if (!$this->tablesReady() || $tenantId < 1 || $certificateId < 1) {
            return null;
        }
        $existing = $this->findCertificateById($tenantId, $certificateId);
        if ($existing === null) {
            return null;
        }
        $reasonClip = $this->nullableString($reason, 255);
        $sql = "UPDATE atak_certificates
                SET status = 'revoked',
                    revoked_at = UTC_TIMESTAMP(),
                    revoked_reason = COALESCE(?, revoked_reason),
                    updated_at = UTC_TIMESTAMP()
                WHERE tenant_id = ? AND id = ?";
        $this->pdo->prepare($sql)->execute([$reasonClip, $tenantId, $certificateId]);

        return $this->findCertificateById($tenantId, $certificateId);
    }

    public function deleteCertificate(int $tenantId, int $certificateId): bool
    {
        if (!$this->tablesReady() || $tenantId < 1 || $certificateId < 1) {
            return false;
        }
        $st = $this->pdo->prepare('DELETE FROM atak_certificates WHERE tenant_id = ? AND id = ?');
        $st->execute([$tenantId, $certificateId]);

        return $st->rowCount() > 0;
    }

    /**
     * Terminaux ATAK vus par jour (last_seen_at) — proxy « sessions » pour le graphique.
     *
     * @return list<array{day:string, count:int}>
     */
    public function dailyTerminalSeenCountsForTenant(int $tenantId, int $days = 14): array
    {
        $days = max(1, min(60, $days));
        if ($tenantId < 1) {
            return [];
        }
        try {
            $end = (new \DateTimeImmutable('today'))->setTime(0, 0, 0);
            $start = $end->modify('-' . ($days - 1) . ' days');
        } catch (\Throwable) {
            return [];
        }
        $since = $start->format('Y-m-d') . ' 00:00:00';
        $map = [];
        try {
            $stmt = $this->pdo->prepare(
                'SELECT DATE(last_seen_at) AS d, COUNT(*) AS cnt
                 FROM atak_terminals
                 WHERE tenant_id = ? AND last_seen_at IS NOT NULL AND last_seen_at >= ?
                 GROUP BY DATE(last_seen_at)'
            );
            $stmt->execute([$tenantId, $since]);
            while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                $day = (string) ($row['d'] ?? '');
                if ($day !== '') {
                    $map[$day] = (int) ($row['cnt'] ?? 0);
                }
            }
        } catch (\Throwable) {
            $map = [];
        }
        $out = [];
        for ($d = $start; $d <= $end; $d = $d->modify('+1 day')) {
            $key = $d->format('Y-m-d');
            $out[] = ['day' => $key, 'count' => (int) ($map[$key] ?? 0)];
        }

        return $out;
    }

    private function clip(string $value, int $max): string
    {
        $value = trim($value);
        if (mb_strlen($value) <= $max) {
            return $value;
        }

        return mb_substr($value, 0, $max);
    }

    /**
     * Rejette les sentinelles Arma/SQF (« &lt;null&gt; », nil…) qui ont pollué terminal_uid.
     */
    private function sanitizeTerminalUid(string $uid): string
    {
        $uid = $this->clip($uid, 64);
        if ($this->isCorruptIdentity($uid)) {
            return '';
        }

        return $uid;
    }

    private function sanitizeCertificateRef(string $ref, int $terminalId = 0): string
    {
        $ref = $this->clip($ref, 64);
        if ($this->isCorruptIdentity($ref) || str_contains(strtolower($ref), '<null') || str_contains(strtolower($ref), '-null')) {
            if ($terminalId > 0) {
                $st = $this->pdo->prepare('SELECT terminal_uid FROM atak_terminals WHERE id = ? LIMIT 1');
                $st->execute([$terminalId]);
                $uid = $this->sanitizeTerminalUid((string) ($st->fetchColumn() ?: ''));
                if ($uid !== '') {
                    return $this->clip('OW-CERT-' . $uid, 64);
                }
            }

            return '';
        }

        return $ref;
    }

    private function isCorruptIdentity(string $value): bool
    {
        $lower = strtolower(trim($value));
        if ($lower === '' || in_array($lower, ['null', '<null>', '<nul>', 'nil', 'undefined', 'any'], true)) {
            return true;
        }
        if (str_starts_with($lower, '<null') || str_contains($lower, '<null') || str_contains($lower, '<nul>')) {
            return true;
        }

        return false;
    }

    /**
     * @return array<string, mixed>|null
     */
    private function findCorruptTerminal(int $tenantId, int $userId, ?string $callsign): ?array
    {
        if (!$this->tablesReady() || $tenantId < 1) {
            return null;
        }
        $sql = 'SELECT * FROM atak_terminals
                WHERE tenant_id = ?
                  AND (
                    terminal_uid IS NULL
                    OR terminal_uid = \'\'
                    OR LOWER(terminal_uid) IN (\'null\', \'<null>\', \'<nul>\', \'nil\')
                    OR terminal_uid LIKE \'%<null%\'
                    OR terminal_uid LIKE \'%<nul>%\'
                  )';
        $params = [$tenantId];
        if ($userId > 0) {
            $sql .= ' AND user_id = ?';
            $params[] = $userId;
        } elseif ($callsign !== null && $callsign !== '') {
            $sql .= ' AND UPPER(TRIM(operator_callsign)) = UPPER(?)';
            $params[] = $callsign;
        } else {
            return null;
        }
        $sql .= ' ORDER BY updated_at DESC, id DESC LIMIT 1';
        $st = $this->pdo->prepare($sql);
        $st->execute($params);
        $row = $st->fetch(PDO::FETCH_ASSOC);

        return is_array($row) ? $row : null;
    }

    private function repairTerminalIdentity(int $tenantId, int $terminalId, string $newUid): void
    {
        if ($terminalId < 1 || $newUid === '') {
            return;
        }
        $this->pdo->prepare(
            'UPDATE atak_terminals SET terminal_uid = ?, updated_at = UTC_TIMESTAMP() WHERE tenant_id = ? AND id = ?'
        )->execute([$newUid, $tenantId, $terminalId]);

        $newRef = $this->clip('OW-CERT-' . $newUid, 64);
        $st = $this->pdo->prepare(
            'SELECT id, certificate_ref FROM atak_certificates WHERE tenant_id = ? AND terminal_id = ?'
        );
        $st->execute([$tenantId, $terminalId]);
        $upd = $this->pdo->prepare(
            'UPDATE atak_certificates SET certificate_ref = ?, updated_at = UTC_TIMESTAMP() WHERE id = ?'
        );
        while ($cert = $st->fetch(PDO::FETCH_ASSOC)) {
            $ref = (string) ($cert['certificate_ref'] ?? '');
            if ($this->isCorruptIdentity($ref) || str_contains(strtolower($ref), '<null') || str_contains(strtolower($ref), '-null')) {
                $upd->execute([$newRef, (int) $cert['id']]);
            }
        }
    }

    /**
     * Répare toutes les identités terminal / certificat corrompues d’un tenant.
     * @return int Nombre de terminaux corrigés
     */
    public function repairCorruptIdentitiesForTenant(int $tenantId): int
    {
        if (!$this->tablesReady() || $tenantId < 1) {
            return 0;
        }
        $st = $this->pdo->query(
            'SELECT id, terminal_uid FROM atak_terminals WHERE tenant_id = ' . (int) $tenantId
        );
        if ($st === false) {
            return 0;
        }
        $fixed = 0;
        while ($row = $st->fetch(PDO::FETCH_ASSOC)) {
            $uid = (string) ($row['terminal_uid'] ?? '');
            $id = (int) ($row['id'] ?? 0);
            if ($id < 1) {
                continue;
            }
            $needsUid = $this->isCorruptIdentity($uid);
            $needsCert = false;
            $cst = $this->pdo->prepare(
                'SELECT certificate_ref FROM atak_certificates WHERE tenant_id = ? AND terminal_id = ?'
            );
            $cst->execute([$tenantId, $id]);
            while ($c = $cst->fetch(PDO::FETCH_ASSOC)) {
                $ref = (string) ($c['certificate_ref'] ?? '');
                if ($this->isCorruptIdentity($ref) || str_contains(strtolower($ref), '<null') || str_contains(strtolower($ref), '-null')) {
                    $needsCert = true;
                    break;
                }
            }
            if (!$needsUid && !$needsCert) {
                continue;
            }
            $newUid = $needsUid
                ? ('OW-FIX-' . $id . '-' . strtoupper(bin2hex(random_bytes(3))))
                : $this->sanitizeTerminalUid($uid);
            if ($newUid === '') {
                $newUid = 'OW-FIX-' . $id . '-' . strtoupper(bin2hex(random_bytes(3)));
            }
            $this->repairTerminalIdentity($tenantId, $id, $newUid);
            $fixed++;
        }

        return $fixed;
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
