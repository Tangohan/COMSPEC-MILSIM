<?php

declare(strict_types=1);

namespace App\Services\Auth;

use App\Support\LazyDatabaseConnection;
use PDO;

/**
 * « Se souvenir de cet appareil » : après un code de double vérification réussi, le navigateur reçoit
 * un jeton aléatoire (cookie HttpOnly) ; pendant TTL_DAYS le code n'est plus demandé sur ce navigateur.
 *
 * Garde-fous :
 * - seule l'empreinte du jeton est en base ;
 * - le jeton est lié au mot de passe : le changer (ou le réinitialiser) invalide tous les appareils ;
 * - le membre voit et retire ses appareils depuis « Double vérification » ;
 * - jamais pour les administrateurs de la plateforme (code demandé à chaque connexion).
 */
final class TrustedDeviceService
{
    use LazyDatabaseConnection;

    public const COOKIE = 'athena_trusted_device';

    public const TTL_DAYS = 30;

    public const MAX_DEVICES_PER_USER = 10;

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
                "SELECT 1 FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'user_trusted_devices' LIMIT 1"
            );
            $this->hasTable = $st !== false && (bool) $st->fetchColumn();
        } catch (\Throwable) {
            $this->hasTable = false;
        }

        return $this->hasTable;
    }

    public static function passwordFingerprint(string $passwordHash): string
    {
        return hash('sha256', 'athena-trusted-device|' . $passwordHash);
    }

    public static function tokenHash(string $token): string
    {
        return hash('sha256', $token);
    }

    /** Nom lisible du navigateur pour la liste « Appareils de confiance » (ex. « Firefox sur Windows »). */
    public static function labelFromUserAgent(string $ua): string
    {
        $browser = 'Navigateur';
        foreach ([
            'Edg/' => 'Edge',
            'OPR/' => 'Opera',
            'SamsungBrowser' => 'Samsung Internet',
            'Firefox/' => 'Firefox',
            'Chrome/' => 'Chrome',
            'Safari/' => 'Safari',
        ] as $needle => $name) {
            if (str_contains($ua, $needle)) {
                $browser = $name;
                break;
            }
        }
        $os = '';
        foreach ([
            'Android' => 'Android',
            'iPhone' => 'iPhone',
            'iPad' => 'iPad',
            'Windows' => 'Windows',
            'Mac OS X' => 'macOS',
            'Linux' => 'Linux',
        ] as $needle => $name) {
            if (str_contains($ua, $needle)) {
                $os = $name;
                break;
            }
        }

        return $os !== '' ? $browser . ' sur ' . $os : $browser;
    }

    /**
     * @param array<string, mixed> $user
     */
    public function isTrusted(array $user): bool
    {
        $userId = (int) ($user['id'] ?? 0);
        $token = (string) ($_COOKIE[self::COOKIE] ?? '');
        if ($userId < 1 || strlen($token) !== 64 || !ctype_xdigit($token) || !$this->isAvailable()) {
            return false;
        }
        $st = $this->pdo()->prepare(
            'SELECT id, password_fingerprint FROM user_trusted_devices
             WHERE token_hash = ? AND user_id = ? AND expires_at > NOW() LIMIT 1'
        );
        $st->execute([self::tokenHash($token), $userId]);
        $row = $st->fetch(PDO::FETCH_ASSOC);
        if (!is_array($row)) {
            return false;
        }
        $expected = self::passwordFingerprint((string) ($user['password_hash'] ?? ''));
        if (!hash_equals((string) $row['password_fingerprint'], $expected)) {
            return false;
        }
        $this->pdo()->prepare('UPDATE user_trusted_devices SET last_used_at = NOW() WHERE id = ?')->execute([(int) $row['id']]);

        return true;
    }

    /**
     * Enregistre ce navigateur comme appareil de confiance et pose le cookie.
     *
     * @param array<string, mixed> $user
     */
    public function remember(array $user, string $userAgent, string $ip): bool
    {
        $userId = (int) ($user['id'] ?? 0);
        if ($userId < 1 || !$this->isAvailable()) {
            return false;
        }
        $token = bin2hex(random_bytes(32));
        $this->pdo()->prepare(
            'INSERT INTO user_trusted_devices (user_id, token_hash, password_fingerprint, label, ip, created_at, last_used_at, expires_at)
             VALUES (?, ?, ?, ?, ?, NOW(), NOW(), DATE_ADD(NOW(), INTERVAL ' . self::TTL_DAYS . ' DAY))'
        )->execute([
            $userId,
            self::tokenHash($token),
            self::passwordFingerprint((string) ($user['password_hash'] ?? '')),
            mb_substr(self::labelFromUserAgent($userAgent), 0, 160),
            mb_substr(trim(explode(',', $ip)[0]), 0, 45) ?: null,
        ]);
        $this->pruneForUser($userId);
        $this->writeCookie($token, time() + self::TTL_DAYS * 86400);

        return true;
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function listForUser(int $userId): array
    {
        if ($userId < 1 || !$this->isAvailable()) {
            return [];
        }
        $st = $this->pdo()->prepare(
            'SELECT id, label, ip, created_at, last_used_at, expires_at, token_hash FROM user_trusted_devices
             WHERE user_id = ? AND expires_at > NOW() ORDER BY COALESCE(last_used_at, created_at) DESC'
        );
        $st->execute([$userId]);
        $current = (string) ($_COOKIE[self::COOKIE] ?? '');
        $currentHash = $current !== '' ? self::tokenHash($current) : '';
        $rows = [];
        foreach ($st->fetchAll(PDO::FETCH_ASSOC) ?: [] as $row) {
            $row['is_current'] = $currentHash !== '' && hash_equals((string) $row['token_hash'], $currentHash);
            unset($row['token_hash']);
            $rows[] = $row;
        }

        return $rows;
    }

    public function revoke(int $userId, int $deviceId): bool
    {
        if ($userId < 1 || $deviceId < 1 || !$this->isAvailable()) {
            return false;
        }
        $st = $this->pdo()->prepare('DELETE FROM user_trusted_devices WHERE id = ? AND user_id = ?');
        $st->execute([$deviceId, $userId]);

        return $st->rowCount() > 0;
    }

    public function revokeAll(int $userId): int
    {
        if ($userId < 1 || !$this->isAvailable()) {
            return 0;
        }
        $st = $this->pdo()->prepare('DELETE FROM user_trusted_devices WHERE user_id = ?');
        $st->execute([$userId]);

        return $st->rowCount();
    }

    private function pruneForUser(int $userId): void
    {
        $this->pdo()->prepare('DELETE FROM user_trusted_devices WHERE user_id = ? AND expires_at <= NOW()')->execute([$userId]);
        $st = $this->pdo()->prepare('SELECT id FROM user_trusted_devices WHERE user_id = ? ORDER BY id DESC');
        $st->execute([$userId]);
        $ids = array_map('intval', $st->fetchAll(PDO::FETCH_COLUMN) ?: []);
        foreach (array_slice($ids, self::MAX_DEVICES_PER_USER) as $id) {
            $this->pdo()->prepare('DELETE FROM user_trusted_devices WHERE id = ?')->execute([$id]);
        }
    }

    private function writeCookie(string $value, int $expires): void
    {
        if (headers_sent()) {
            return;
        }
        $secure = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
            || ((string) ($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https')
            || (bool) config('auth.session_secure_cookie', false);
        setcookie(self::COOKIE, $value, [
            'expires' => $expires,
            'path' => '/',
            'secure' => $secure,
            'httponly' => true,
            'samesite' => 'Lax',
        ]);
        $_COOKIE[self::COOKIE] = $value;
    }
}
