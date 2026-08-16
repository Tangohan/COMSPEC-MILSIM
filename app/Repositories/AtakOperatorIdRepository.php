<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Support\LazyDatabaseConnection;

use PDO;

/**
 * Identifiants militaires stables (affichables) pour opérateurs / terminaux ATAK.
 * Format : MID-XXXX (ex. MID-7K2M).
 */
class AtakOperatorIdRepository
{
    use LazyDatabaseConnection;


    public function __construct(?PDO $pdo = null)
    {
        $this->pdo = $pdo;
    }

    public function tablesReady(): bool
    {
        try {
            $st = $this->pdo()->query(
                "SELECT 1 FROM information_schema.TABLES
                 WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'atak_operator_ids' LIMIT 1"
            );

            return $st !== false && (bool) $st->fetchColumn();
        } catch (\Throwable) {
            return false;
        }
    }

    public function unitsMilitaryIdColumnReady(): bool
    {
        try {
            $st = $this->pdo()->prepare(
                "SELECT 1 FROM information_schema.COLUMNS
                 WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'atak_units' AND COLUMN_NAME = 'military_id' LIMIT 1"
            );
            $st->execute();

            return (bool) $st->fetchColumn();
        } catch (\Throwable) {
            return false;
        }
    }

    /**
     * @return array{military_id: string, user_id: int|null, call_sign: string|null}|null
     */
    public function findByUserId(int $tenantId, int $userId): ?array
    {
        if (!$this->tablesReady() || $userId < 1) {
            return null;
        }
        $stmt = $this->pdo()->prepare(
            'SELECT military_id, user_id, call_sign FROM atak_operator_ids
             WHERE tenant_id = ? AND user_id = ? LIMIT 1'
        );
        $stmt->execute([$tenantId, $userId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return $row ?: null;
    }

    /**
     * @return array{military_id: string, user_id: int|null, call_sign: string|null}|null
     */
    public function findByCallSign(int $tenantId, string $callSign): ?array
    {
        $callSign = trim($callSign);
        if (!$this->tablesReady() || $callSign === '') {
            return null;
        }
        $stmt = $this->pdo()->prepare(
            'SELECT military_id, user_id, call_sign FROM atak_operator_ids
             WHERE tenant_id = ? AND UPPER(call_sign) = UPPER(?) LIMIT 1'
        );
        $stmt->execute([$tenantId, $callSign]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return $row ?: null;
    }

    /**
     * Garantit un ID militaire pour un utilisateur (et met à jour l’indicatif si fourni).
     */
    public function ensureForUser(int $tenantId, int $userId, ?string $callSign = null): string
    {
        if (!$this->tablesReady() || $tenantId < 1 || $userId < 1) {
            return $this->generateMilitaryId();
        }

        $existing = $this->findByUserId($tenantId, $userId);
        if ($existing) {
            $mid = (string) $existing['military_id'];
            $cs = trim((string) ($callSign ?? ''));
            if ($cs !== '' && strcasecmp((string) ($existing['call_sign'] ?? ''), $cs) !== 0) {
                $this->pdo()->prepare(
                    'UPDATE atak_operator_ids SET call_sign = ?, updated_at = NOW() WHERE tenant_id = ? AND user_id = ?'
                )->execute([$cs, $tenantId, $userId]);
            }

            return $mid;
        }

        $cs = trim((string) ($callSign ?? ''));
        if ($cs !== '') {
            $byCs = $this->findByCallSign($tenantId, $cs);
            if ($byCs && empty($byCs['user_id'])) {
                $this->pdo()->prepare(
                    'UPDATE atak_operator_ids SET user_id = ?, updated_at = NOW() WHERE tenant_id = ? AND military_id = ?'
                )->execute([$userId, $tenantId, $byCs['military_id']]);

                return (string) $byCs['military_id'];
            }
        }

        $mid = $this->allocateUnique($tenantId);
        try {
            $this->pdo()->prepare(
                'INSERT INTO atak_operator_ids (tenant_id, user_id, call_sign, military_id)
                 VALUES (?, ?, ?, ?)'
            )->execute([$tenantId, $userId, $cs !== '' ? $cs : null, $mid]);
        } catch (\Throwable) {
            $again = $this->findByUserId($tenantId, $userId);

            return $again ? (string) $again['military_id'] : $mid;
        }

        return $mid;
    }

    /**
     * Garantit un ID militaire pour un indicatif (terminal solo / non lié).
     */
    public function ensureForCallSign(int $tenantId, string $callSign): string
    {
        $callSign = trim($callSign);
        if (!$this->tablesReady() || $tenantId < 1 || $callSign === '') {
            return $this->generateMilitaryId();
        }

        $existing = $this->findByCallSign($tenantId, $callSign);
        if ($existing) {
            return (string) $existing['military_id'];
        }

        $mid = $this->allocateUnique($tenantId);
        try {
            $this->pdo()->prepare(
                'INSERT INTO atak_operator_ids (tenant_id, user_id, call_sign, military_id)
                 VALUES (?, NULL, ?, ?)'
            )->execute([$tenantId, $callSign, $mid]);
        } catch (\Throwable) {
            $again = $this->findByCallSign($tenantId, $callSign);

            return $again ? (string) $again['military_id'] : $mid;
        }

        return $mid;
    }

    /**
     * Assigne / synchronise military_id sur une ligne atak_units.
     */
    public function syncUnitMilitaryId(int $tenantId, int $unitRowId, string $callSign, ?int $userId = null): string
    {
        $mid = $userId && $userId > 0
            ? $this->ensureForUser($tenantId, $userId, $callSign)
            : $this->ensureForCallSign($tenantId, $callSign);

        if ($this->unitsMilitaryIdColumnReady() && $unitRowId > 0) {
            try {
                $this->pdo()->prepare(
                    'UPDATE atak_units SET military_id = ? WHERE id = ? AND tenant_id = ?'
                )->execute([$mid, $unitRowId, $tenantId]);
            } catch (\Throwable) {
                // index unique collision rare — ignorer, l’ID opérateur reste la source de vérité
            }
        }

        return $mid;
    }

    public function generateMilitaryId(): string
    {
        $alphabet = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';
        $suffix = '';
        for ($i = 0; $i < 4; $i++) {
            $suffix .= $alphabet[random_int(0, strlen($alphabet) - 1)];
        }

        return 'MID-' . $suffix;
    }

    private function allocateUnique(int $tenantId): string
    {
        for ($attempt = 0; $attempt < 16; $attempt++) {
            $mid = $this->generateMilitaryId();
            $stmt = $this->pdo()->prepare(
                'SELECT 1 FROM atak_operator_ids WHERE tenant_id = ? AND military_id = ? LIMIT 1'
            );
            $stmt->execute([$tenantId, $mid]);
            if (!$stmt->fetchColumn()) {
                return $mid;
            }
        }

        return 'MID-' . strtoupper(substr(bin2hex(random_bytes(3)), 0, 4));
    }
}
