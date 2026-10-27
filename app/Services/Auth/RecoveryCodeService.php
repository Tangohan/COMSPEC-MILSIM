<?php

declare(strict_types=1);

namespace App\Services\Auth;

use App\Support\LazyDatabaseConnection;
use PDO;

/**
 * Codes de secours de la double vérification : dix codes à usage unique, montrés une seule fois
 * à l'activation de l'application d'authentification (téléphone perdu, appli effacée…).
 * Seule une empreinte SHA-256 est gardée en base.
 */
final class RecoveryCodeService
{
    use LazyDatabaseConnection;

    public const CODE_COUNT = 10;

    /** Sans 0/O, 1/I/L : un code recopié à la main ne doit pas être ambigu. */
    private const ALPHABET = 'ABCDEFGHJKMNPQRSTUVWXYZ23456789';

    private ?bool $hasTable = null;

    public function __construct(?PDO $pdo = null)
    {
        $this->pdo = $pdo;
    }

    public function isAvailable(): bool
    {
        if ($this->hasTable !== null) {
            return $this->hasTable;
        }
        try {
            $st = $this->pdo()->query(
                "SELECT 1 FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'user_recovery_codes' LIMIT 1"
            );
            $this->hasTable = $st !== false && (bool) $st->fetchColumn();
        } catch (\Throwable) {
            $this->hasTable = false;
        }

        return $this->hasTable;
    }

    /** Code au format « ABCD-EFGH ». */
    public static function generateCode(): string
    {
        $max = strlen(self::ALPHABET) - 1;
        $raw = '';
        for ($i = 0; $i < 8; $i++) {
            $raw .= self::ALPHABET[random_int(0, $max)];
        }

        return substr($raw, 0, 4) . '-' . substr($raw, 4, 4);
    }

    /** Majuscules, sans espace ni tiret : « abcd efgh » et « ABCD-EFGH » donnent la même empreinte. */
    public static function normalize(string $input): string
    {
        return strtoupper((string) preg_replace('/[^A-Za-z0-9]/', '', $input));
    }

    public static function hashCode(string $input): string
    {
        return hash('sha256', 'athena-recovery|' . self::normalize($input));
    }

    /**
     * Remplace les codes du membre par une nouvelle série et la renvoie (en clair, à afficher une seule fois).
     *
     * @return list<string>
     */
    public function regenerate(int $userId): array
    {
        if ($userId < 1 || !$this->isAvailable()) {
            return [];
        }
        $codes = [];
        while (count($codes) < self::CODE_COUNT) {
            $code = self::generateCode();
            $codes[self::normalize($code)] = $code;
        }
        $codes = array_values($codes);
        $pdo = $this->pdo();
        $pdo->beginTransaction();
        try {
            $pdo->prepare('DELETE FROM user_recovery_codes WHERE user_id = ?')->execute([$userId]);
            $ins = $pdo->prepare('INSERT INTO user_recovery_codes (user_id, code_hash, created_at) VALUES (?, ?, NOW())');
            foreach ($codes as $code) {
                $ins->execute([$userId, self::hashCode($code)]);
            }
            $pdo->commit();
        } catch (\Throwable $e) {
            $pdo->rollBack();
            throw $e;
        }

        return $codes;
    }

    /** Consomme le code s'il est valide et pas encore utilisé (une seule fois, même en double envoi). */
    public function consume(int $userId, string $input): bool
    {
        if ($userId < 1 || strlen(self::normalize($input)) !== 8 || !$this->isAvailable()) {
            return false;
        }
        $st = $this->pdo()->prepare(
            'UPDATE user_recovery_codes SET used_at = NOW() WHERE user_id = ? AND code_hash = ? AND used_at IS NULL'
        );
        $st->execute([$userId, self::hashCode($input)]);

        return $st->rowCount() === 1;
    }

    public function remaining(int $userId): int
    {
        if ($userId < 1 || !$this->isAvailable()) {
            return 0;
        }
        $st = $this->pdo()->prepare('SELECT COUNT(*) FROM user_recovery_codes WHERE user_id = ? AND used_at IS NULL');
        $st->execute([$userId]);

        return (int) $st->fetchColumn();
    }

    public function deleteAll(int $userId): void
    {
        if ($userId < 1 || !$this->isAvailable()) {
            return;
        }
        $this->pdo()->prepare('DELETE FROM user_recovery_codes WHERE user_id = ?')->execute([$userId]);
    }
}
