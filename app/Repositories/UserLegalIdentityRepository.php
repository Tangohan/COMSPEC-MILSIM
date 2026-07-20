<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Core\Database;
use PDO;

class UserLegalIdentityRepository
{
    private PDO $pdo;

    private static ?bool $tableExists = null;

    public function __construct()
    {
        $this->pdo = Database::getPdo();
    }

    public function tableExists(): bool
    {
        if (self::$tableExists === null) {
            $stmt = $this->pdo->query("SELECT 1 FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'user_legal_identities' LIMIT 1");
            self::$tableExists = $stmt && (bool) $stmt->fetchColumn();
        }

        return self::$tableExists;
    }

    public function getByUserId(int $userId): ?array
    {
        if (!$this->tableExists()) {
            return null;
        }
        $stmt = $this->pdo->prepare('SELECT * FROM user_legal_identities WHERE user_id = ? LIMIT 1');
        $stmt->execute([$userId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return $row ?: null;
    }

    /** RGPD : efface l’identité légale lors de l’anonymisation d’un compte. */
    public function deleteByUserId(int $userId): void
    {
        if (!$this->tableExists()) {
            return;
        }
        $stmt = $this->pdo->prepare('DELETE FROM user_legal_identities WHERE user_id = ?');
        $stmt->execute([$userId]);
    }

    public function upsert(int $userId, int $tenantId, array $data): void
    {
        if (!$this->tableExists()) {
            return;
        }
        $fields = [
            'first_name' => trim((string) ($data['first_name'] ?? '')),
            'last_name' => trim((string) ($data['last_name'] ?? '')),
            'phone' => trim((string) ($data['phone'] ?? '')),
            'birth_date' => trim((string) ($data['birth_date'] ?? '')),
            'nationality' => trim((string) ($data['nationality'] ?? '')),
        ];
        $exists = $this->getByUserId($userId);
        if ($exists) {
            $stmt = $this->pdo->prepare(
                'UPDATE user_legal_identities
                 SET first_name = ?, last_name = ?, phone = ?, birth_date = ?, nationality = ?, updated_at = NOW()
                 WHERE user_id = ?'
            );
            $stmt->execute([
                $fields['first_name'] !== '' ? $fields['first_name'] : null,
                $fields['last_name'] !== '' ? $fields['last_name'] : null,
                $fields['phone'] !== '' ? $fields['phone'] : null,
                $fields['birth_date'] !== '' ? $fields['birth_date'] : null,
                $fields['nationality'] !== '' ? $fields['nationality'] : null,
                $userId,
            ]);

            return;
        }

        $stmt = $this->pdo->prepare(
            'INSERT INTO user_legal_identities (tenant_id, user_id, first_name, last_name, phone, birth_date, nationality, created_at, updated_at)
             VALUES (?, ?, ?, ?, ?, ?, ?, NOW(), NOW())'
        );
        $stmt->execute([
            $tenantId,
            $userId,
            $fields['first_name'] !== '' ? $fields['first_name'] : null,
            $fields['last_name'] !== '' ? $fields['last_name'] : null,
            $fields['phone'] !== '' ? $fields['phone'] : null,
            $fields['birth_date'] !== '' ? $fields['birth_date'] : null,
            $fields['nationality'] !== '' ? $fields['nationality'] : null,
        ]);
    }
}
