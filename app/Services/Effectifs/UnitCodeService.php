<?php

declare(strict_types=1);

namespace App\Services\Effectifs;

use App\Repositories\UnitCodeRepository;
use App\Support\UnitCodeBuilder;
use Throwable;

/**
 * Codes unité d'une communauté : calcul depuis l'ORBAT, enregistrement et lecture.
 *
 * - Le code lisible (SOAR-B-A-1) suit l'arbre : il est recalculé à chaque changement de
 *   parent, de nom ou de sigle d'une unité, et quand le code de la communauté change.
 * - L'identifiant administratif (0042-0137) est attribué une fois pour toutes : numéro de la
 *   communauté + numéro de l'unité dans la communauté, jamais réattribué même après suppression.
 */
final class UnitCodeService
{
    /** @var array<int, array{community: array<string, mixed>|null, units: array<int, array{code: string, segment: string, admin_id: string, admin_number: int}>}> */
    private static array $memo = [];

    public function __construct(private ?UnitCodeRepository $repo = null)
    {
        $this->repo ??= new UnitCodeRepository();
    }

    public static function forget(?int $tenantId = null): void
    {
        if ($tenantId === null) {
            self::$memo = [];

            return;
        }
        unset(self::$memo[$tenantId]);
    }

    /**
     * Recalcule et enregistre les codes de la communauté, puis les renvoie.
     *
     * @return array{community: array{code: string, admin_number: int, admin_id: string, is_custom: bool}|null, units: array<int, array{code: string, segment: string, admin_id: string, admin_number: int}>}
     */
    public function sync(int $tenantId): array
    {
        if (isset(self::$memo[$tenantId])) {
            return self::$memo[$tenantId];
        }
        $empty = ['community' => null, 'units' => []];
        if ($tenantId < 1) {
            return $empty;
        }
        try {
            if (!$this->repo->ensureTables()) {
                return $empty;
            }
            $tenant = $this->repo->ensureTenant($tenantId);
            if ($tenant === null) {
                return $empty;
            }
            $result = $this->computeAndStore($tenantId, $tenant);
        } catch (Throwable $e) {
            error_log('[unit_codes] synchronisation impossible (communauté ' . $tenantId . ') : ' . $e->getMessage());

            return $empty;
        }

        return self::$memo[$tenantId] = $result;
    }

    /**
     * @param array{tenant_id: int, community_code: string, admin_number: int, next_unit_number: int, is_custom: bool} $tenant
     * @return array{community: array{code: string, admin_number: int, admin_id: string, is_custom: bool}, units: array<int, array{code: string, segment: string, admin_id: string, admin_number: int}>}
     */
    private function computeAndStore(int $tenantId, array $tenant): array
    {
        $units = $this->repo->unitsForTenant($tenantId);
        $built = UnitCodeBuilder::build($units, $tenant['community_code'], $this->tenantName($tenantId));
        $existing = $this->repo->codesForTenant($tenantId);

        $updates = [];
        $inserts = [];
        $next = $tenant['next_unit_number'];
        foreach ($existing as $row) {
            $next = max($next, $row['admin_number'] + 1);
        }
        // Les numéros suivent l'ordre de création des unités (ids croissants).
        ksort($built);
        foreach ($built as $unitId => $c) {
            if (isset($existing[$unitId])) {
                if ($existing[$unitId]['code_path'] !== $c['path'] || $existing[$unitId]['segment'] !== $c['segment']) {
                    $updates[$unitId] = ['segment' => $c['segment'], 'code_path' => $c['path']];
                }
                continue;
            }
            $inserts[$unitId] = ['segment' => $c['segment'], 'code_path' => $c['path'], 'admin_number' => $next++];
        }
        $deletes = array_values(array_map('intval', array_diff(array_keys($existing), array_keys($built))));
        if ($updates !== [] || $inserts !== [] || $deletes !== []) {
            $this->repo->applyChanges($tenantId, $updates, $inserts, $deletes);
        }
        if ($inserts !== []) {
            $this->repo->setNextUnitNumber($tenantId, $next);
        }

        $displayPrefix = $this->repo->displayPrefix($tenantId);
        $displayPrefix = $displayPrefix !== '' ? $displayPrefix : $tenant['community_code'];
        $out = [];
        foreach ($built as $unitId => $c) {
            $number = $existing[$unitId]['admin_number'] ?? $inserts[$unitId]['admin_number'];
            $out[$unitId] = [
                'code' => $displayPrefix . substr($c['path'], strlen($tenant['community_code'])),
                'administrative_code' => $c['path'],
                'segment' => $c['segment'],
                'admin_number' => $number,
                'admin_id' => UnitCodeBuilder::formatUnitAdminId($tenant['admin_number'], $number),
            ];
        }

        return [
            'community' => [
                'code' => $displayPrefix,
                'administrative_code' => $tenant['community_code'],
                'admin_number' => $tenant['admin_number'],
                'admin_id' => UnitCodeBuilder::formatTenantNumber($tenant['admin_number']),
                'is_custom' => $tenant['is_custom'],
            ],
            'units' => $out,
        ];
    }

    /** Code d'une unité, ou null si l'unité n'existe pas (ou plus). */
    public function forUnit(int $tenantId, int $unitId): ?array
    {
        if ($unitId < 1) {
            return null;
        }

        return $this->sync($tenantId)['units'][$unitId] ?? null;
    }

    /**
     * Change le code de la communauté. Renvoie un message d'erreur lisible, ou null si c'est fait.
     */
    public function changeCommunityCode(int $tenantId, string $raw): ?string
    {
        $code = UnitCodeBuilder::normalizeInput($raw, UnitCodeBuilder::COMMUNITY_MIN, UnitCodeBuilder::COMMUNITY_MAX);
        if ($code === null || $code === '') {
            return 'Le code de la communauté doit compter de 2 à 10 lettres ou chiffres, sans espace ni accent.';
        }
        if (!$this->repo->ensureTables() || $this->repo->ensureTenant($tenantId) === null) {
            return 'Les codes unité ne sont pas encore disponibles. Réessayez dans un instant.';
        }
        if ($this->repo->communityCodeTaken($code, $tenantId)) {
            return 'Ce code est déjà utilisé par une autre communauté.';
        }
        try {
            $this->repo->updateCommunityCode($tenantId, $code);
        } catch (Throwable) {
            return 'Ce code est déjà utilisé par une autre communauté.';
        }
        self::forget($tenantId);
        $this->sync($tenantId);

        return null;
    }

    /** Personnalise uniquement le préfixe visible dans cette organisation. Vide = défaut. */
    public function changeDisplayPrefix(int $tenantId, string $raw): ?string
    {
        $prefix = UnitCodeBuilder::normalizeInput($raw, 2, 10);
        if ($prefix === null) {
            return 'Le préfixe doit compter de 2 à 10 lettres ou chiffres, sans espace ni accent.';
        }
        if ($tenantId < 1 || !$this->repo->ensureTables() || $this->repo->ensureTenant($tenantId) === null) {
            return 'Les codes unité ne sont pas encore disponibles.';
        }
        try {
            $this->repo->saveDisplayPrefix($tenantId, $prefix);
        } catch (Throwable) {
            return 'Le préfixe n’a pas pu être enregistré.';
        }
        self::forget($tenantId);
        return null;
    }

    /**
     * Vérifie un segment saisi pour une unité : null si accepté, sinon un message lisible.
     * Deux unités sœurs ne peuvent pas porter le même segment saisi.
     *
     * @param list<array<string, mixed>> $units lignes de l'ORBAT (id, parent_id, code)
     */
    public static function validateSegment(array $units, int $unitId, string $raw, ?string &$normalized = null): ?string
    {
        $seg = UnitCodeBuilder::normalizeInput($raw, 1, UnitCodeBuilder::SEGMENT_MAX);
        if ($seg === null) {
            return 'Le code de l’unité doit compter de 1 à 8 lettres ou chiffres, sans espace ni accent.';
        }
        $normalized = $seg;
        if ($seg === '') {
            return null;
        }
        $parent = null;
        foreach ($units as $u) {
            if ((int) ($u['id'] ?? 0) === $unitId) {
                $parent = (int) ($u['parent_id'] ?? 0);
            }
        }
        foreach ($units as $u) {
            $id = (int) ($u['id'] ?? 0);
            if ($id === $unitId || (int) ($u['parent_id'] ?? 0) !== $parent) {
                continue;
            }
            if (UnitCodeBuilder::sanitize((string) ($u['code'] ?? '')) === $seg) {
                return 'Une unité du même échelon porte déjà ce code : « ' . (string) ($u['name'] ?? '') . ' ».';
            }
        }

        return null;
    }

    public function repository(): UnitCodeRepository
    {
        return $this->repo;
    }

    private function tenantName(int $tenantId): string
    {
        try {
            return $this->repo->tenantName($tenantId);
        } catch (Throwable) {
            return '';
        }
    }
}
