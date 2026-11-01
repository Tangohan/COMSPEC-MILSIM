<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Core\Database;
use App\Support\SecretCipher;
use PDO;

/**
 * Partage d'une formation du Studio, façon Google Docs : membres invités (lecteur / éditeur) et lien d'accès.
 * Le jeton du lien n'est stocké qu'haché (ouverture) et chiffré (pour « Copier le lien »).
 */
final class AcademyCourseShareRepository
{
    public const ROLES = ['viewer', 'editor'];
    public const ACCESS = ['community', 'public'];

    private PDO $pdo;
    private ?bool $ready = null;

    public function __construct(?PDO $pdo = null)
    {
        $this->pdo = $pdo ?? Database::getPdo();
    }

    /** Tables créées par l'étape academy_course_lifecycle_sharing (3270). */
    public function ready(): bool
    {
        if ($this->ready === null) {
            try {
                $this->pdo->query('SELECT 1 FROM academy_course_shares WHERE 1 = 0');
                $this->pdo->query('SELECT 1 FROM academy_course_links WHERE 1 = 0');
                $this->ready = true;
            } catch (\PDOException) {
                $this->ready = false;
            }
        }
        return $this->ready;
    }

    /** @return list<array{user_id:int, role:string, created_at:string}> */
    public function shares(int $tenantId, string $key): array
    {
        $stmt = $this->pdo->prepare('SELECT user_id, role, created_at FROM academy_course_shares WHERE tenant_id = ? AND course_key = ? ORDER BY created_at');
        $stmt->execute([$tenantId, $key]);
        return array_map(static fn (array $row): array => ['user_id' => (int) $row['user_id'], 'role' => (string) $row['role'], 'created_at' => (string) $row['created_at']], $stmt->fetchAll(PDO::FETCH_ASSOC));
    }

    public function roleFor(int $tenantId, string $key, int $userId): ?string
    {
        $stmt = $this->pdo->prepare('SELECT role FROM academy_course_shares WHERE tenant_id = ? AND course_key = ? AND user_id = ?');
        $stmt->execute([$tenantId, $key, $userId]);
        $role = $stmt->fetchColumn();
        return is_string($role) ? $role : null;
    }

    /** @return array<string,string> clé de formation => rôle */
    public function coursesFor(int $tenantId, int $userId): array
    {
        $stmt = $this->pdo->prepare('SELECT course_key, role FROM academy_course_shares WHERE tenant_id = ? AND user_id = ?');
        $stmt->execute([$tenantId, $userId]);
        return $stmt->fetchAll(PDO::FETCH_KEY_PAIR);
    }

    public function grant(int $tenantId, string $key, int $userId, string $role, int $by): void
    {
        if (!in_array($role, self::ROLES, true)) {
            throw new \InvalidArgumentException('invalid_content');
        }
        $this->revoke($tenantId, $key, $userId);
        $stmt = $this->pdo->prepare('INSERT INTO academy_course_shares (tenant_id, course_key, user_id, role, granted_by, created_at) VALUES (?, ?, ?, ?, ?, ?)');
        $stmt->execute([$tenantId, $key, $userId, $role, $by, date('Y-m-d H:i:s')]);
    }

    public function revoke(int $tenantId, string $key, int $userId): bool
    {
        $stmt = $this->pdo->prepare('DELETE FROM academy_course_shares WHERE tenant_id = ? AND course_key = ? AND user_id = ?');
        $stmt->execute([$tenantId, $key, $userId]);
        return $stmt->rowCount() > 0;
    }

    /** @return array{access:string, role:string, expires_at:?string, token:?string, token_hash:string}|null */
    public function link(int $tenantId, string $key): ?array
    {
        $stmt = $this->pdo->prepare('SELECT access, role, expires_at, token_cipher, token_hash FROM academy_course_links WHERE tenant_id = ? AND course_key = ?');
        $stmt->execute([$tenantId, $key]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$row) {
            return null;
        }
        return ['access' => (string) $row['access'], 'role' => (string) $row['role'], 'expires_at' => $row['expires_at'] !== null ? (string) $row['expires_at'] : null,
            'token' => is_string($row['token_cipher']) ? SecretCipher::decrypt($row['token_cipher']) : null, 'token_hash' => (string) $row['token_hash']];
    }

    /**
     * Active ou modifie le lien. Un nouveau jeton n'est tiré qu'à la création ou sur demande (l'ancien lien cesse alors de fonctionner).
     *
     * @return string|null jeton en clair quand il vient d'être créé
     */
    public function saveLink(int $tenantId, string $key, string $access, string $role, ?int $expiresAt, int $by, bool $regenerate): ?string
    {
        // Un lien public reste en lecture seule : personne hors communauté ne modifie une formation.
        if (!in_array($access, self::ACCESS, true) || !in_array($role, self::ROLES, true) || ($access === 'public' && $role !== 'viewer')) {
            throw new \InvalidArgumentException('invalid_content');
        }
        $expires = $expiresAt !== null ? date('Y-m-d H:i:s', $expiresAt) : null;
        $current = $this->link($tenantId, $key);
        if ($current !== null && !$regenerate) {
            $stmt = $this->pdo->prepare('UPDATE academy_course_links SET access = ?, role = ?, expires_at = ? WHERE tenant_id = ? AND course_key = ?');
            $stmt->execute([$access, $role, $expires, $tenantId, $key]);
            return null;
        }
        $token = rtrim(strtr(base64_encode(random_bytes(24)), '+/', '-_'), '=');
        $this->disableLink($tenantId, $key);
        $stmt = $this->pdo->prepare('INSERT INTO academy_course_links (tenant_id, course_key, token_hash, token_cipher, access, role, expires_at, created_by, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)');
        $stmt->execute([$tenantId, $key, hash('sha256', $token), SecretCipher::encrypt($token), $access, $role, $expires, $by, date('Y-m-d H:i:s')]);
        return $token;
    }

    public function disableLink(int $tenantId, string $key): bool
    {
        $stmt = $this->pdo->prepare('DELETE FROM academy_course_links WHERE tenant_id = ? AND course_key = ?');
        $stmt->execute([$tenantId, $key]);
        return $stmt->rowCount() > 0;
    }

    /** Lien valide (non expiré) correspondant au jeton, ou null. */
    public function resolve(string $token): ?array
    {
        if (!preg_match('/\A[A-Za-z0-9_-]{20,64}\z/', $token)) {
            return null;
        }
        $hash = hash('sha256', $token);
        $stmt = $this->pdo->prepare('SELECT tenant_id, course_key, access, role, expires_at, token_hash FROM academy_course_links WHERE token_hash = ?');
        $stmt->execute([$hash]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$row || !hash_equals((string) $row['token_hash'], $hash)
            || ($row['expires_at'] !== null && strtotime((string) $row['expires_at']) <= time())) {
            return null;
        }
        return ['tenant_id' => (int) $row['tenant_id'], 'course_key' => (string) $row['course_key'], 'access' => (string) $row['access'], 'role' => (string) $row['role'], 'token_hash' => $hash];
    }

    /** Une formation supprimée définitivement emporte ses partages. */
    public function forget(int $tenantId, string $key): void
    {
        foreach (['academy_course_shares', 'academy_course_links'] as $table) {
            $stmt = $this->pdo->prepare('DELETE FROM ' . $table . ' WHERE tenant_id = ? AND course_key = ?');
            $stmt->execute([$tenantId, $key]);
        }
    }
}
