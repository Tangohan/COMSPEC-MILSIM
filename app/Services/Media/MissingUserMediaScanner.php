<?php

declare(strict_types=1);

namespace App\Services\Media;

use App\Core\Database;
use PDO;

/**
 * Détecte les chemins média locaux (avatar / portrait / bannière) encore en base
 * alors que le fichier a disparu du disque — typique après une migration sans uploads.
 */
final class MissingUserMediaScanner
{
    private const LOCAL_PREFIXES = [
        'uploads/avatars/',
        'uploads/portraits/',
        'uploads/banners/',
    ];

    /**
     * @return array{
     *   total_users: int,
     *   broken_avatars: int,
     *   broken_portraits: int,
     *   broken_banners: int,
     *   users: list<array{id: int, display_name: string, email: string, missing: list<string>}>
     * }
     */
    public function scanTenant(int $tenantId, int $limitUsers = 200): array
    {
        $out = [
            'total_users' => 0,
            'broken_avatars' => 0,
            'broken_portraits' => 0,
            'broken_banners' => 0,
            'users' => [],
        ];
        if ($tenantId < 1) {
            return $out;
        }

        $pdo = Database::getPdo();
        $sql = 'SELECT u.id, u.email, u.display_name, u.avatar_url, u.profile_banner_url,
                       pp.character_portrait_path, pp.character_banner_path
                FROM users u
                LEFT JOIN personnel_profiles pp ON pp.user_id = u.id
                WHERE u.tenant_id = ? AND u.status = \'active\'
                ORDER BY u.id ASC
                LIMIT ' . max(1, min(500, $limitUsers));
        try {
            $stmt = $pdo->prepare($sql);
            $stmt->execute([$tenantId]);
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
        } catch (\Throwable) {
            return $out;
        }

        foreach ($rows as $row) {
            $missing = [];
            if ($this->isBrokenLocalPath((string) ($row['avatar_url'] ?? ''))) {
                $missing[] = 'avatar';
                $out['broken_avatars']++;
            }
            $portrait = trim((string) ($row['character_portrait_path'] ?? ''));
            if ($portrait !== '' && $this->isBrokenLocalPath($portrait)) {
                $missing[] = 'portrait';
                $out['broken_portraits']++;
            }
            $bannerUser = trim((string) ($row['profile_banner_url'] ?? ''));
            $bannerChar = trim((string) ($row['character_banner_path'] ?? ''));
            $bannerBroken = false;
            if ($bannerUser !== '' && $this->isBrokenLocalPath($bannerUser)) {
                $bannerBroken = true;
            }
            if ($bannerChar !== '' && $this->isBrokenLocalPath($bannerChar)) {
                $bannerBroken = true;
            }
            if ($bannerBroken) {
                $missing[] = 'banner';
                $out['broken_banners']++;
            }
            if ($missing === []) {
                continue;
            }
            $out['total_users']++;
            $out['users'][] = [
                'id' => (int) ($row['id'] ?? 0),
                'display_name' => trim((string) ($row['display_name'] ?? '')) ?: ('#' . (int) ($row['id'] ?? 0)),
                'email' => (string) ($row['email'] ?? ''),
                'missing' => $missing,
            ];
        }

        return $out;
    }

    /**
     * @param array<string, mixed> $user
     * @param array<string, mixed>|null $personnelProfile
     * @return list<string> kinds: avatar|portrait|banner
     */
    public function missingKindsForUser(array $user, ?array $personnelProfile): array
    {
        $missing = [];
        if ($this->isBrokenLocalPath((string) ($user['avatar_url'] ?? ''))) {
            $missing[] = 'avatar';
        }
        $pp = is_array($personnelProfile) ? $personnelProfile : [];
        if ($this->isBrokenLocalPath((string) ($pp['character_portrait_path'] ?? ''))) {
            $missing[] = 'portrait';
        }
        $bannerUser = (string) ($user['profile_banner_url'] ?? '');
        $bannerChar = (string) ($pp['character_banner_path'] ?? '');
        if (
            ($bannerUser !== '' && $this->isBrokenLocalPath($bannerUser))
            || ($bannerChar !== '' && $this->isBrokenLocalPath($bannerChar))
        ) {
            $missing[] = 'banner';
        }

        return $missing;
    }

    public function isBrokenLocalPath(string $raw): bool
    {
        $path = trim($raw);
        if ($path === '') {
            return false;
        }
        if (preg_match('#^(https?:)?//#i', $path) === 1) {
            return false;
        }
        $norm = ltrim(str_replace('\\', '/', $path), '/');
        if (str_starts_with($norm, 'public/')) {
            $norm = substr($norm, 7);
        }
        $isLocalUpload = false;
        foreach (self::LOCAL_PREFIXES as $prefix) {
            if (str_starts_with($norm, $prefix)) {
                $isLocalUpload = true;
                break;
            }
        }
        if (!$isLocalUpload) {
            return false;
        }
        $absolute = base_path('public/' . $norm);

        return !is_file($absolute);
    }
}
