<?php

declare(strict_types=1);

namespace App\Support;

use App\Core\Gate;
use DateTimeImmutable;
use PDO;

final class MaintenanceService
{
    public function __construct(
        private PDO $pdo
    ) {}

    /**
     * Contexte optionnel pour bypass : ex. role_slug (slug du rôle tenant).
     *
     * @param array<string, mixed>|null $userContext
     */
    /**
     * Indique si la règle est activée et si l’horloge serveur se trouve dans la fenêtre
     * starts_at / ends_at (mêmes critères que la requête d’application réelle, hors périmètre URL).
     *
     * @param array<string, mixed> $row
     */
    public static function isWithinEnabledSchedule(array $row): bool
    {
        if ((int) ($row['is_enabled'] ?? 0) !== 1) {
            return false;
        }

        $nowStr = (new DateTimeImmutable())->format('Y-m-d H:i:s');
        $starts = isset($row['starts_at']) ? trim((string) $row['starts_at']) : '';
        if ($starts !== '' && $starts > $nowStr) {
            return false;
        }
        $ends = isset($row['ends_at']) ? trim((string) $row['ends_at']) : '';
        if ($ends !== '' && $ends < $nowStr) {
            return false;
        }

        return true;
    }

    public function getActiveMaintenance(string $requestPath, ?string $module = null): ?array
    {
        $now = (new DateTimeImmutable())->format('Y-m-d H:i:s');

        $sql = '
            SELECT *
            FROM app_maintenance
            WHERE is_enabled = 1
              AND (
                    starts_at IS NULL OR starts_at <= :now
              )
              AND (
                    ends_at IS NULL OR ends_at >= :now
              )
            ORDER BY priority DESC, id DESC
        ';

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute(['now' => $now]);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        foreach ($rows as $row) {
            if ($this->matchesScope((string) $row['scope'], $requestPath, $module)) {
                return $row;
            }
        }

        return null;
    }

    /**
     * @param array<string, mixed> $maintenance
     * @param array<string, mixed>|null $userContext
     */
    public function shouldBypass(array $maintenance, ?array $userContext, string $clientIp): bool
    {
        if ($this->isAllowedIp($maintenance['allowed_ips'] ?? null, $clientIp)) {
            return true;
        }

        if ((int) ($maintenance['allow_admin_bypass'] ?? 0) === 1 && $this->isAdminBypass()) {
            return true;
        }

        if ($this->hasAllowedRole($maintenance['allowed_roles'] ?? null, $userContext)) {
            return true;
        }

        if ($this->hasAllowedUser($maintenance['allowed_user_ids'] ?? null, $userContext)) {
            return true;
        }

        return false;
    }

    private function matchesScope(string $scope, string $requestPath, ?string $module): bool
    {
        if ($scope === 'global') {
            return true;
        }

        if (str_starts_with($scope, 'route:')) {
            $routePrefix = $this->normalizeRoutePrefix(substr($scope, 6));

            return $routePrefix !== '' && str_starts_with($requestPath, $routePrefix);
        }

        if (str_starts_with($scope, 'module:')) {
            $targetModule = substr($scope, 7);

            return $module !== null && $targetModule === $module;
        }

        return false;
    }

    /**
     * Accepte les préfixes saisis avec ou sans /public (ex. /public/forum → /forum).
     */
    private function normalizeRoutePrefix(string $raw): string
    {
        $p = '/' . trim($raw, '/');
        if (str_starts_with($p, '/public')) {
            $rest = substr($p, strlen('/public'));
            $p = ($rest === '' || $rest === false) ? '/' : '/' . ltrim($rest, '/');
        }
        if ($p !== '/') {
            $p = rtrim($p, '/') ?: '/';
        }

        return $p;
    }

    private function isAllowedIp(?string $allowedIps, string $clientIp): bool
    {
        if ($allowedIps === null || trim($allowedIps) === '') {
            return false;
        }

        $ips = array_filter(array_map('trim', explode(',', $allowedIps)));

        foreach ($ips as $ip) {
            if ($ip === $clientIp) {
                return true;
            }
        }

        return false;
    }

    private function isAdminBypass(): bool
    {
        $gate = Gate::getInstance();

        return $gate->allows('admin.system') || $gate->allows('admin.access');
    }

    /**
     * @param array<string, mixed>|null $userContext
     */
    private function hasAllowedRole(?string $allowedRoles, ?array $userContext): bool
    {
        if ($allowedRoles === null || trim($allowedRoles) === '' || $userContext === null) {
            return false;
        }

        $slug = isset($userContext['role_slug']) ? (string) $userContext['role_slug'] : '';
        if ($slug === '') {
            return false;
        }

        $allowed = array_filter(array_map('trim', explode(',', $allowedRoles)));

        foreach ($allowed as $role) {
            if ($role !== '' && strcasecmp($role, $slug) === 0) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param array<string, mixed>|null $userContext
     */
    private function hasAllowedUser(?string $allowedUsers, ?array $userContext): bool
    {
        if ($allowedUsers === null || trim($allowedUsers) === '' || $userContext === null) {
            return false;
        }

        $userId = (int) ($userContext['user_id'] ?? 0);
        if ($userId <= 0) {
            return false;
        }

        $ids = array_filter(array_map(static function (string $value): int {
            return (int) trim($value);
        }, explode(',', $allowedUsers)));

        return in_array($userId, $ids, true);
    }
}
